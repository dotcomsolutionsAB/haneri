<?php

namespace App\Http\Controllers;

use App\services\ShipmentTrackingService;
use Illuminate\Http\Request;

class ShipmentWebhookController extends Controller
{
    public function delhivery(Request $request, ShipmentTrackingService $tracking)
    {
        // Delhivery's Tracking PUSH API posts one scan and expects 200.
        // Their setup asks for an authorization header, which we give them as "Token <secret>".
        if (! $this->tokenOk((string) config('services.delhivery.webhook_token'), $this->presentedToken($request))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $body = $request->json()->all();
        if ($body === []) {
            $body = $request->all();
        }

        $result = $tracking->applyScans($tracking->scansFromDelhivery($body));

        return response()->json([
            'success' => true,
            'matched' => $result['matched'],
            'emailed' => $result['emailed'],
        ]);
    }

    public function shiprocket(Request $request, ShipmentTrackingService $tracking)
    {
        $given = (string) $request->header('x-api-key', '');
        if (! $this->tokenOk((string) config('services.shiprocket.webhook_token'), $given)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $scan = $tracking->scanFromShiprocketWebhook($request->all());
        if (! $scan) {
            return response()->json([
                'success' => true,
                'matched' => false,
            ]);
        }

        $result = $tracking->applyAwb($scan);

        return response()->json([
            'success' => true,
            'matched' => $result['matched'],
            'emailed' => $result['emailed'],
        ]);
    }

    private function presentedToken(Request $request): string
    {
        $authorization = trim((string) $request->header('Authorization', ''));
        if ($authorization !== '') {
            if (preg_match('/^(?:Bearer|Token)\s+(\S+)/i', $authorization, $match)) {
                return $match[1];
            }

            return $authorization;
        }

        $header = trim((string) $request->header('X-Webhook-Token', ''));
        if ($header !== '') {
            return $header;
        }

        return trim((string) ($request->query('token', $request->input('token', ''))));
    }

    private function tokenOk(string $configured, string $given): bool
    {
        return $configured !== '' && $given !== '' && hash_equals($configured, $given);
    }
}
