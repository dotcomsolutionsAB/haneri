<?php

namespace App\services;

use App\Mail\ShipmentMilestoneMail;
use App\Models\EmailLog;
use App\Models\OrderModel;
use App\Models\OrderShipment;
use App\Services\DelhiveryService;
use App\Services\ShiprocketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ShipmentTrackingService
{
    private const RANK = [
        'dispatched' => 1,
        'out_for_delivery' => 2,
        'delivered' => 3,
    ];

    /**
     * Pull every open booking and apply the carrier's current status.
     */
    public function syncOpenShipments(): int
    {
        $shipments = OrderShipment::query()
            ->whereIn('status', ['booked', 'in_transit'])
            ->whereNotNull('awb_no')
            ->where('awb_no', '!=', '')
            ->get();

        $processed = 0;
        $delhiveryAwbs = [];

        foreach ($shipments as $shipment) {
            if ($this->isShiprocket($shipment)) {
                $awb = $this->waybills($shipment)[0] ?? '';
                if ($awb === '') {
                    continue;
                }
                try {
                    $scan = $this->scanFromShiprocketTrack(
                        (new ShiprocketService())->trackByAwb($awb),
                        $awb
                    );
                    if ($scan) {
                        $this->apply($shipment, $scan);
                        $processed++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Shiprocket tracking sync failed', [
                        'shipment_id' => $shipment->id,
                        'awb' => $awb,
                        'error' => $e->getMessage(),
                    ]);
                }
                continue;
            }

            foreach ($this->waybills($shipment) as $awb) {
                $delhiveryAwbs[$awb] = true;
            }
        }

        if ($delhiveryAwbs !== []) {
            try {
                $payload = (new DelhiveryService())->trackShipments(array_keys($delhiveryAwbs));
                foreach ($this->scansFromDelhivery(is_array($payload) ? $payload : []) as $scan) {
                    $this->applyAwb($scan);
                    $processed++;
                }
            } catch (\Throwable $e) {
                Log::error('Delhivery tracking sync failed: '.$e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans
     * @return array{matched:int,emailed:array<int, string>}
     */
    public function applyScans(array $scans): array
    {
        $matched = 0;
        $emailed = [];
        foreach ($scans as $scan) {
            $result = $this->applyAwb($scan);
            if ($result['matched']) {
                $matched++;
            }
            if ($result['emailed']) {
                $emailed[] = $result['emailed'];
            }
        }

        return ['matched' => $matched, 'emailed' => $emailed];
    }

    /**
     * @param  array<string, mixed>  $scan
     * @return array{matched:bool,emailed:?string,milestone:?string}
     */
    public function applyAwb(array $scan): array
    {
        $awb = trim((string) ($scan['awb'] ?? ''));
        $shipment = $awb !== '' ? $this->findByAwb($awb) : null;
        if (! $shipment) {
            return ['matched' => false, 'emailed' => null, 'milestone' => null];
        }

        return $this->apply($shipment, $scan);
    }

    /**
     * @param  array<string, mixed>  $scan
     * @return array{matched:bool,emailed:?string,milestone:?string}
     */
    public function apply(OrderShipment $shipment, array $scan): array
    {
        $status = trim((string) ($scan['status'] ?? ''));
        $milestone = $this->milestone($status, $scan['status_type'] ?? null);
        $when = $this->when($scan['occurred_at'] ?? null);
        $location = trim((string) ($scan['location'] ?? ''));
        $emailed = null;

        DB::transaction(function () use ($shipment, $status, $milestone, $when, $location, &$emailed) {
            $locked = OrderShipment::query()->lockForUpdate()->find($shipment->id);
            if (! $locked || $status === '') {
                return;
            }

            $sent = $locked->milestones_sent;
            $first = $sent === null;
            $sent = is_array($sent) ? array_values($sent) : [];

            $locked->carrier_status = $status;
            $locked->carrier_status_at = $when ?? now();
            if ($location !== '') {
                $locked->status_location = $location;
            }

            $order = OrderModel::query()->lockForUpdate()->find($locked->order_id);
            $closed = in_array((string) $locked->status, ['cancelled', 'failed'], true)
                || ($order && in_array((string) $order->status, ['cancelled', 'refunded'], true));

            if ($milestone && ! $closed) {
                $this->moveShipment($locked, $milestone, $when);
                $this->moveOrder($order, $milestone);
            }

            $shouldEmail = $milestone && ! $closed && $this->shouldEmail($locked, $milestone, $sent, $first);
            $silent = $milestone && ! $closed && ! $shouldEmail && $first
                && in_array($milestone, ['delivered', 'returned'], true)
                && ! in_array($milestone, $sent, true);

            if ($first) {
                $locked->milestones_sent = $sent;
            }
            $locked->save();
            if ($order) {
                $order->save();
            }

            if ($silent) {
                $sent[] = $milestone;
                $locked->milestones_sent = array_values(array_unique($sent));
                $locked->save();
                return;
            }

            if (! $shouldEmail) {
                return;
            }

            $user = $order?->user;
            $email = $user?->email ?: $locked->customer_email;
            if (! $user || ! $email) {
                Log::warning('Shipment milestone has no customer email', [
                    'shipment_id' => $locked->id,
                    'milestone' => $milestone,
                ]);
                return;
            }

            $mail = new ShipmentMilestoneMail(
                $user,
                $order,
                $locked,
                $milestone,
                $status,
                $location !== '' ? $location : null,
                $when?->timezone(config('app.timezone'))->format('d M Y, h:i A'),
                $this->trackingLinks($locked)
            );

            try {
                Mail::to($email)->send($mail);
                EmailLog::record($email, ShipmentMilestoneMail::class, 'sent', [
                    'recipient_user_id' => $user->id,
                    'subject' => $mail->subjectLine(),
                    'metadata' => ['order_id' => $order->id, 'milestone' => $milestone],
                ]);
            } catch (\Throwable $e) {
                EmailLog::record($email, ShipmentMilestoneMail::class, 'failed', [
                    'recipient_user_id' => $user->id,
                    'subject' => $mail->subjectLine(),
                    'error_message' => $e->getMessage(),
                    'metadata' => ['order_id' => $order->id, 'milestone' => $milestone],
                ]);
                throw $e;
            }

            $sent[] = $milestone;
            $locked->milestones_sent = array_values(array_unique($sent));
            $locked->save();
            $emailed = $milestone;
        });

        return ['matched' => true, 'emailed' => $emailed, 'milestone' => $milestone];
    }

    public function findByAwb(string $awb): ?OrderShipment
    {
        $awb = trim($awb);
        if ($awb === '') {
            return null;
        }

        $candidates = OrderShipment::query()
            ->where('awb_no', 'like', '%'.$awb.'%')
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $shipment) {
            if (in_array($awb, $this->waybills($shipment), true)) {
                return $shipment;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function waybills(OrderShipment $shipment): array
    {
        $parts = preg_split('/\s*,\s*/', (string) $shipment->awb_no) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($awb) => $awb !== ''));
    }

    public function milestone(?string $status, ?string $statusType = null): ?string
    {
        $text = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', (string) $status)));
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        $type = strtoupper(trim((string) $statusType));

        if ($text === '' && $type === '') {
            return null;
        }

        if ($type === 'RT' || $type === 'RTO' || $this->contains($text, ['rto', 'return to origin', 'returned to origin', 'returned to seller'])) {
            return 'returned';
        }

        if ($type === 'CN' || $text === 'cancelled' || $text === 'canceled') {
            return null;
        }

        if ($this->contains($text, ['out for delivery']) || $text === 'dispatched' || $text === 'ofd') {
            return 'out_for_delivery';
        }

        if ($this->contains($text, [
            'undelivered',
            'not delivered',
            'delivery failed',
            'attempt failed',
            'consignee unavailable',
            'consignee not available',
            'customer not available',
            'customer unavailable',
        ])) {
            return 'undelivered';
        }

        if ($type === 'DL' || $this->contains($text, ['delivered'])) {
            return 'delivered';
        }

        if ($type === 'PU' || $this->contains($text, ['picked up', 'in transit', 'shipped'])) {
            return 'dispatched';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function scansFromDelhivery(array $payload): array
    {
        $rows = [];
        if (isset($payload['Shipment']) && is_array($payload['Shipment'])) {
            $rows[] = $payload['Shipment'];
        }
        foreach ($payload['ShipmentData'] ?? [] as $row) {
            if (is_array($row) && isset($row['Shipment']) && is_array($row['Shipment'])) {
                $rows[] = $row['Shipment'];
            }
        }
        if (array_is_list($payload)) {
            foreach ($payload as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (isset($row['Shipment']) && is_array($row['Shipment'])) {
                    $rows[] = $row['Shipment'];
                } elseif (isset($row['AWB']) || isset($row['awb'])) {
                    $rows[] = $row;
                }
            }
        }

        $scans = [];
        foreach ($rows as $row) {
            $status = $row['Status'] ?? [];
            $statusText = is_array($status) ? ($status['Status'] ?? '') : $status;
            $awb = trim((string) ($row['AWB'] ?? $row['awb'] ?? ''));
            if ($awb === '' || trim((string) $statusText) === '') {
                continue;
            }
            $scans[] = [
                'awb' => $awb,
                'status' => (string) $statusText,
                'status_type' => is_array($status) ? ($status['StatusType'] ?? null) : null,
                'location' => is_array($status) ? ($status['StatusLocation'] ?? null) : null,
                'occurred_at' => is_array($status) ? ($status['StatusDateTime'] ?? null) : null,
            ];
        }

        return $scans;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function scanFromShiprocketWebhook(array $payload): ?array
    {
        $awb = trim((string) ($payload['awb'] ?? ''));
        $status = trim((string) ($payload['current_status'] ?? $payload['shipment_status'] ?? ''));
        if ($awb === '' || $status === '') {
            return null;
        }

        $scans = is_array($payload['scans'] ?? null) ? $payload['scans'] : [];
        $latest = is_array($scans[0] ?? null) ? $scans[0] : [];

        return [
            'awb' => $awb,
            'status' => $status,
            'status_type' => null,
            'location' => $latest['location'] ?? null,
            'occurred_at' => $payload['current_timestamp'] ?? ($latest['date'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function scanFromShiprocketTrack(array $payload, string $awb): ?array
    {
        $track = $payload['tracking_data']['shipment_track'][0] ?? null;
        $activities = $payload['tracking_data']['shipment_track_activities'] ?? [];
        $latest = is_array($activities) && isset($activities[0]) && is_array($activities[0]) ? $activities[0] : [];
        $status = '';
        if (is_array($track)) {
            $status = trim((string) ($track['current_status'] ?? ''));
        }
        if ($status === '') {
            $status = trim((string) ($latest['sr-status'] ?? $latest['status'] ?? ''));
        }
        if ($status === '') {
            return null;
        }

        return [
            'awb' => $awb,
            'status' => $status,
            'status_type' => null,
            'location' => $latest['location'] ?? null,
            'occurred_at' => $latest['date'] ?? (is_array($track) ? ($track['updated_time_stamp'] ?? null) : null),
        ];
    }

    /**
     * @return array<int, array{awb:string,url:string}>
     */
    public function trackingLinks(OrderShipment $shipment): array
    {
        $links = [];
        foreach ($this->waybills($shipment) as $awb) {
            $links[] = [
                'awb' => $awb,
                'url' => $this->isShiprocket($shipment)
                    ? 'https://shiprocket.co/tracking/'.$awb
                    : 'https://www.delhivery.com/track-v2/package/'.$awb,
            ];
        }

        return $links;
    }

    private function moveShipment(OrderShipment $shipment, string $milestone, ?Carbon $when): void
    {
        if ($milestone === 'delivered') {
            $shipment->status = 'delivered';
            $shipment->delivered_at = $when ?? now();
            return;
        }

        if (in_array($milestone, ['dispatched', 'out_for_delivery', 'undelivered', 'returned'], true)
            && ! in_array((string) $shipment->status, ['delivered', 'cancelled'], true)) {
            $shipment->status = 'in_transit';
        }
    }

    private function moveOrder(?OrderModel $order, string $milestone): void
    {
        if (! $order || in_array((string) $order->status, ['cancelled', 'refunded'], true)) {
            return;
        }

        if ($milestone === 'delivered') {
            $order->status = 'completed';
            $order->delivery_status = 'completed';
            return;
        }

        if (in_array($milestone, ['dispatched', 'out_for_delivery'], true)
            && in_array((string) $order->status, ['pending', 'processing'], true)) {
            $order->status = 'dispatched';
        }
    }

    /**
     * @param  array<int, string>  $sent
     */
    private function shouldEmail(OrderShipment $shipment, string $milestone, array $sent, bool $first): bool
    {
        if (in_array($milestone, $sent, true)) {
            return false;
        }

        if (in_array('delivered', $sent, true)) {
            return false;
        }

        $rank = self::RANK[$milestone] ?? null;
        if ($rank !== null) {
            foreach ($sent as $previous) {
                if ((self::RANK[$previous] ?? 0) > $rank) {
                    return false;
                }
            }
        }

        if ($first && in_array($milestone, ['delivered', 'returned'], true)) {
            $bookedAt = $shipment->booked_at ?? $shipment->created_at;
            if (! $bookedAt || $bookedAt->lt(now()->subHours(48))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function contains(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function when(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isShiprocket(OrderShipment $shipment): bool
    {
        return str_contains(strtolower((string) $shipment->courier), 'shiprocket');
    }
}
