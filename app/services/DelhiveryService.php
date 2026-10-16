<?php

namespace App\Services;

use App\Http\Controllers\SettingController;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DelhiveryService
{
    protected $client;
    protected $apiKey;
    protected $apiUrl;
    protected $isTestEnvironment;
    protected $lastWarehouseError = '';

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('DELHIVERY_API_KEY');
        // Live Delhivery unless the flag is explicitly turned on.
        $this->isTestEnvironment = filter_var(env('DELHIVERY_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function getBaseUrl()
    {
        try {
            $cached = Cache::get('delhivery.api_host');
            if (is_string($cached) && str_starts_with($cached, 'https://')) {
                return rtrim($cached, '/');
            }
        } catch (\Throwable $e) {
            // Cache is unavailable outside the application.
        }

        return $this->isTestEnvironment 
            ? 'https://staging-express.delhivery.com' 
            : 'https://track.delhivery.com';
    }

    public function lastWarehouseError(): string
    {
        return $this->lastWarehouseError;
    }

    public function checkPincodeServiceability(string $pincode): array
    {
        $endpoint = $this->getBaseUrl() . '/c/api/pin-codes/json/';

        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept'        => 'application/json',
                ],
                'query' => [
                    'filter_codes' => $pincode,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (pincode check): ' . json_encode($responseBody));

            return [
                'error' => 'API Error: ' . ($responseBody['rmk'] ?? $e->getMessage()),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check pincode serviceability: ' . $e->getMessage());

            return [
                'error' => 'Pincode serviceability check failed: ' . $e->getMessage(),
            ];
        }
    }

    // public function getShippingCost($originPin, $destinationPin, $codAmount, $weight, $paymentType = 'Pre-paid')
    // {
    //     $endpoint = $this->getBaseUrl() . '/api/kinko/v1/invoice/charges/.json';

    //     try {
    //         $response = $this->client->get($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //                 'Content-Type'  => 'application/json',
    //                 'Accept'        => 'application/json',
    //             ],
    //             'query' => [
    //                 'md'  => 'E',
    //                 'ss'  => 'Delivered',
    //                 'd_pin' => $destinationPin,
    //                 'o_pin' => $originPin,
    //                 'cgm'  => $weight,
    //                 'pt'   => $paymentType,
    //                 'cod'  => $paymentType === 'COD' ? $codAmount : 0,
    //             ],
    //         ]);

    //         return json_decode($response->getBody()->getContents(), true);

    //     } catch (ClientException $e) {
    //         $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
    //         Log::error('Delhivery API Client Error (shipping cost): ' . json_encode($responseBody));
    //         return ['error' => 'API Error: ' . ($responseBody['detail'] ?? $e->getMessage())];
    //     } catch (\Exception $e) {
    //         Log::error("Shipping cost calculation failed: " . $e->getMessage());
    //         return ['error' => 'Shipping cost calculation failed: ' . $e->getMessage()];
    //     }
    // }
    
    /**
     * One “block” in your response: (air/surface) x (normal/express)
     *
     * NOTE:
     * - Delhivery invoice charge API officially supports md=E/S and ss=Delivered/RTO/DTO.
     * - “normal vs express” is not officially documented as a separate query param for same md.
     * - So both tiers call same md unless you later add a real param in applyTierOverrides().
     */
    public function getShippingCostBlock(array $baseQuery, string $md, string $tier, bool $includeDebug = false): array
    {
        $query = array_merge($baseQuery, ['md' => $md]);
        $query = $this->applyTierOverrides($query, $tier);

        $endpoint = $this->getBaseUrl() . '/api/kinko/v1/invoice/charges/.json';

        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept'        => 'application/json',
                ],
                'query' => $query,
                'timeout' => 20,
            ]);

            $raw = json_decode($response->getBody()->getContents(), true) ?? [];

            $result = [
                'ok'      => true,
                'summary' => $this->summarizeInvoiceResponse($raw),
            ];

            if ($includeDebug) {
                $result['request'] = $query;
                $result['raw']     = $raw;
            }

            return $result;

        } catch (\Throwable $e) {

            $result = [
                'ok'      => false,
                'summary' => [],
                'error'   => $e->getMessage(),
            ];

            if ($includeDebug) {
                $result['request'] = $query;
            }

            return $result;
        }
    }
    // full response
    // public function getShippingCostBlock(array $baseQuery, string $md, string $tier): array
    // {
    //     $query = array_merge($baseQuery, ['md' => $md]);

    //     // Optional: if later you get a real “express” parameter from Delhivery,
    //     // put it here (only place you need to change).
    //     $query = $this->applyTierOverrides($query, $tier);

    //     $endpoint = $this->getBaseUrl() . '/api/kinko/v1/invoice/charges/.json';

    //     try {
    //         $response = $this->client->get($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //                 'Accept'        => 'application/json',
    //             ],
    //             'query' => $query,
    //             'timeout' => 20,
    //         ]);

    //         $raw = json_decode($response->getBody()->getContents(), true) ?? [];

    //         return [
    //             'ok'      => true,
    //             'request' => $query,
    //             'summary' => $this->summarizeInvoiceResponse($raw),
    //             'raw'     => $raw,
    //         ];
    //     } catch (ClientException $e) {
    //         $rawErr = [];
    //         try {
    //             $rawErr = json_decode($e->getResponse()->getBody()->getContents(), true) ?? [];
    //         } catch (\Throwable $t) {}

    //         Log::error('Delhivery API Client Error (invoice charges): ' . json_encode($rawErr));

    //         return [
    //             'ok'      => false,
    //             'request' => $query,
    //             'summary' => [],
    //             'raw'     => $rawErr,
    //             'error'   => $rawErr['detail'] ?? $e->getMessage(),
    //         ];
    //     } catch (\Throwable $e) {
    //         Log::error("Delhivery invoice charges failed: " . $e->getMessage());

    //         return [
    //             'ok'      => false,
    //             'request' => $query,
    //             'summary' => [],
    //             'raw'     => [],
    //             'error'   => $e->getMessage(),
    //         ];
    //     }
    // }
    private function applyTierOverrides(array $query, string $tier): array
    {
        // ✅ Right now: no official “express vs normal” param is documented for invoice/charges.
        // If Delhivery gives you one, add it here, e.g.:
        // if ($tier === 'express') $query['service'] = 'EXPRESS';

        return $query;
    }
    private function summarizeInvoiceResponse(array $raw): array
    {
        // Delhivery returns an array of charge objects
        $row = isset($raw[0]) && is_array($raw[0]) ? $raw[0] : $raw;

        $total = $row['total_amount'] ?? null;
        $gross = $row['gross_amount'] ?? null;

        $cgst = $row['tax_data']['CGST'] ?? null;
        $sgst = $row['tax_data']['SGST'] ?? null;
        $igst = $row['tax_data']['IGST'] ?? null;

        return [
            'total_amount'   => is_numeric($total) ? round((float)$total, 2) : null,
            'gross_amount'   => is_numeric($gross) ? round((float)$gross, 2) : null,
            'cgst'           => is_numeric($cgst) ? round((float)$cgst, 2) : null,
            'sgst'           => is_numeric($sgst) ? round((float)$sgst, 2) : null,
            'igst'           => is_numeric($igst) ? round((float)$igst, 2) : null,
            'charged_weight' => $row['charged_weight'] ?? null,
            'zone'           => $row['zone'] ?? null,
            'eta_days'       => null, // not coming from this API response
        ];
    }

    public function placeOrder(array $orderData): array
    {
        $endpoint = $this->getBaseUrl() . '/api/cmu/create.json';

        try {
            $packages = [];
            if (!empty($orderData['packages']) && is_array($orderData['packages'])) {
                foreach ($orderData['packages'] as $package) {
                    if (!is_array($package)) {
                        continue;
                    }
                    $packages[] = $package;
                }
            }

            if ($packages === []) {
                $packages[] = [
                    'products_description' => $orderData['products_description'] ?? '',
                    'quantity'             => $orderData['quantity'] ?? 1,
                    'weight'               => $orderData['weight'] ?? 1,
                    'shipment_length'      => $orderData['shipment_length'] ?? 10,
                    'shipment_width'       => $orderData['shipment_width'] ?? 10,
                    'shipment_height'      => $orderData['shipment_height'] ?? 10,
                    'total_amount'         => $orderData['total_amount'] ?? 0,
                    'cod_amount'           => $orderData['cod_amount'] ?? 0,
                ];
            }

            $baseOrderNo = (string) ($orderData['order_no'] ?? '');
            $multi = count($packages) > 1;
            $orderTotal = round((float) ($orderData['total_amount'] ?? 0), 2);
            $orderCod = round((float) ($orderData['cod_amount'] ?? 0), 2);
            $money = self::allocatePackageAmounts($packages, $orderTotal, $orderCod);
            $sellerInvoice = (string) ($orderData['seller_invoice'] ?? ('INV-' . $baseOrderNo));
            $seller = SettingController::sellerDetails();
            $sellerName = $seller['name'] !== ''
                ? $seller['name']
                : (string) ($orderData['seller_name'] ?? '');
            $sellerAddress = $seller['address'] !== ''
                ? $seller['address']
                : (string) ($orderData['seller_address'] ?? '');

            // Multi-box Cargo / MPS: one client order + one master AWB, N linked packages.
            // Prefetched waybills are mandatory for MPS.
            $masterWaybill = null;
            $waybills = [];
            if ($multi) {
                $fetched = $this->fetchWaybills(count($packages));
                if (empty($fetched['success'])) {
                    return [
                        'error' => $fetched['error'] ?? 'Unable to prefetch Delhivery waybills for multi-box Cargo.',
                        'raw'   => $fetched['raw'] ?? null,
                    ];
                }
                $waybills = $fetched['waybills'];
                $masterWaybill = $waybills[0];
            }

            $shipments = [];
            foreach ($packages as $index => $package) {
                $weightKg = (float) ($package['weight'] ?? ($orderData['weight'] ?? 1));
                $boxNo = $index + 1;
                // Per-box invoice value (each fan). Never send 0 — Delhivery labels show ₹0.1.
                $boxAmount = round((float) ($money[$index]['total_amount'] ?? 0), 2);
                $boxCod = round((float) ($money[$index]['cod_amount'] ?? 0), 2);
                if ($multi && $boxAmount < 1 && $orderTotal >= count($packages)) {
                    $boxAmount = round($orderTotal / count($packages), 2);
                }

                // MPS: unique order/invoice per box so each label keeps its own price,
                // while master_id links them as one Cargo / multi-box shipment.
                $boxOrderNo = $multi ? ($baseOrderNo . '-B' . $boxNo) : $baseOrderNo;
                $boxInvoice = $multi ? ($sellerInvoice . '-B' . $boxNo) : $sellerInvoice;

                $row = [
                    'name'            => $orderData['customer_name'],
                    'add'             => $orderData['customer_address'],
                    'pin'             => $orderData['pin'],
                    'city'            => $orderData['city'],
                    'state'           => $orderData['state'],
                    'country'         => 'India',
                    'phone'           => $orderData['phone'],
                    'order'           => $boxOrderNo,
                    'payment_mode'    => $orderData['payment_mode'],
                    'products_desc'   => $package['products_description']
                        ?? $package['products_desc']
                        ?? ($orderData['products_description'] ?? ''),
                    // Delhivery sample payloads use strings for money fields.
                    'cod_amount'      => (string) $boxCod,
                    'total_amount'    => (string) $boxAmount,
                    'order_date'      => $orderData['order_date'],
                    'seller_add'      => $sellerAddress,
                    'seller_name'     => $sellerName,
                    'seller_inv'      => $boxInvoice,
                    'quantity'        => max(1, (int) ($package['quantity'] ?? 1)),
                    'weight'          => self::kgToDelhiveryGrams($weightKg),
                    'shipping_mode'   => $orderData['shipping_mode'] ?? 'Surface',
                    'service_level'   => $orderData['service_level'] ?? 'normal',
                    'address_type'    => $orderData['address_type'] ?? 'home',
                    'return_pin'      => $orderData['return_pin']     ?? $orderData['pickup_pin'],
                    'return_city'     => $orderData['return_city']    ?? $orderData['pickup_city'],
                    'return_phone'    => $orderData['return_phone']   ?? $orderData['pickup_phone'],
                    'return_add'      => $orderData['return_address'] ?? $orderData['pickup_address'],
                    'return_state'    => $orderData['return_state']   ?? $orderData['pickup_state'],
                    'return_country'  => $orderData['return_country'] ?? 'India',
                    'shipment_width'  => !empty($package['shipment_width'])
                        ? $package['shipment_width']
                        : (!empty($orderData['shipment_width']) ? $orderData['shipment_width'] : 10),
                    'shipment_height' => !empty($package['shipment_height'])
                        ? $package['shipment_height']
                        : (!empty($orderData['shipment_height']) ? $orderData['shipment_height'] : 10),
                    'shipment_length' => !empty($package['shipment_length'])
                        ? $package['shipment_length']
                        : (!empty($orderData['shipment_length']) ? $orderData['shipment_length'] : 10),
                ];

                if ($multi && $masterWaybill !== null) {
                    $row['shipment_type'] = 'MPS';
                    $row['master_id'] = $masterWaybill;
                    $row['mps_children'] = (string) count($packages);
                    $row['mps_amount'] = (string) (int) round($orderCod);
                    $row['waybill'] = $waybills[$index];
                }

                $shipments[] = $row;
            }

            $finalPayload = [
                'pickup_location' => [
                    // THIS "name" must match Delhivery-registered warehouse name (case sensitive)
                    'name'    => $orderData['pickup_name'],
                    'add'     => $orderData['pickup_address'],
                    'pin'     => (string) $orderData['pickup_pin'],
                    'city'    => $orderData['pickup_city'],
                    'state'   => $orderData['pickup_state'],
                    'country' => 'India',
                    'phone'   => (string) $orderData['pickup_phone'],
                ],
                'shipments' => $shipments,
            ];

            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept'        => 'application/json',
                ],
                'form_params' => [
                    'format' => 'json',
                    'data'   => json_encode($finalPayload),
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            if (!is_array($body)) {
                return [
                    'error' => 'Unexpected Delhivery create response.',
                    'raw'   => $body,
                ];
            }

            if ($multi && $masterWaybill !== null) {
                $body['master_waybill'] = $masterWaybill;
                $body['shipment_type'] = 'MPS';
                $body['package_count'] = count($packages);
            }

            return $body;

        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (placeOrder): ' . json_encode($responseBody));

            return [
                'error' => 'API Error: ' . ($responseBody['rmk'] ?? $e->getMessage()),
                'raw'   => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to place Delhivery order: " . $e->getMessage());

            return [
                'error' => 'Order creation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Prefetch N waybills for MPS / multi-box Cargo manifestation.
     * GET /waybill/api/bulk/json/?cl={client}&token={key}&count={n}
     *
     * @return array{success: bool, waybills?: array<int, string>, error?: string, raw?: mixed}
     */
    public function fetchWaybills(int $count): array
    {
        $count = max(1, min(100, $count));
        $clientName = trim((string) config('shipping.delhivery_client', env('DELHIVERY_CLIENT_NAME', '')));
        if ($clientName === '') {
            return [
                'success' => false,
                'error'   => 'Set DELHIVERY_CLIENT_NAME to your registered Delhivery client name (required to prefetch Cargo/MPS waybills).',
            ];
        }

        $endpoint = $this->getBaseUrl() . '/waybill/api/bulk/json/';

        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept'        => 'application/json',
                ],
                'query' => [
                    'cl'    => $clientName,
                    'token' => $this->apiKey,
                    'count' => $count,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $waybills = self::normalizeFetchedWaybills($body);
            if (count($waybills) < $count) {
                return [
                    'success' => false,
                    'error'   => 'Delhivery returned fewer waybills than requested for multi-box Cargo.',
                    'raw'     => $body,
                ];
            }

            return [
                'success'  => true,
                'waybills' => array_slice($waybills, 0, $count),
                'raw'      => $body,
            ];
        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (fetchWaybills): ' . json_encode($responseBody));

            return [
                'success' => false,
                'error'   => 'Waybill prefetch failed: ' . ($responseBody['rmk'] ?? $responseBody['error'] ?? $e->getMessage()),
                'raw'     => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch Delhivery waybills: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => 'Waybill prefetch failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeFetchedWaybills(mixed $raw): array
    {
        if (is_string($raw)) {
            $parts = preg_split('/[\s,;]+/', $raw) ?: [];
            return array_values(array_filter(array_map('trim', $parts)));
        }
        if (!is_array($raw)) {
            return [];
        }

        $candidates = [];
        foreach (['waybills', 'Waybills', 'wbns', 'packages', 'data', 'result'] as $key) {
            if (isset($raw[$key]) && is_array($raw[$key])) {
                $candidates = $raw[$key];
                break;
            }
            if (isset($raw[$key]) && is_string($raw[$key])) {
                return self::normalizeFetchedWaybills($raw[$key]);
            }
        }
        if ($candidates === [] && array_is_list($raw)) {
            $candidates = $raw;
        }

        $out = [];
        foreach ($candidates as $row) {
            if (is_string($row) || is_numeric($row)) {
                $value = trim((string) $row);
                if ($value !== '') {
                    $out[] = $value;
                }
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            foreach (['waybill', 'wbn', 'AWB', 'awb'] as $key) {
                if (!empty($row[$key])) {
                    $out[] = trim((string) $row[$key]);
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

    public function trackShipments(array $waybillNumbers)
    {
        $endpoint = $this->getBaseUrl() . '/api/v1/packages/json/';

        $waybillString = implode(',', $waybillNumbers);

        $response = $this->client->get($endpoint, [
            'headers' => [
                'Authorization' => 'Token ' . $this->apiKey,
                'Accept'        => 'application/json',
            ],
            'query' => [
                'waybill' => $waybillString,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Cancel a manifested / ready-to-ship package by waybill.
     * POST /api/p/edit with { waybill, cancellation: "true" }
     */
    public function cancelWaybill(string $waybill): array
    {
        $waybill = trim($waybill);
        if ($waybill === '') {
            return [
                'success' => false,
                'error'   => 'Waybill is required.',
            ];
        }

        $endpoint = $this->getBaseUrl() . '/api/p/edit';

        try {
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'waybill'      => $waybill,
                    'cancellation' => 'true',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            if (!is_array($body)) {
                return [
                    'success' => false,
                    'error'   => 'Unexpected Delhivery cancel response.',
                    'raw'     => $body,
                ];
            }

            $status = strtolower((string) ($body['status'] ?? $body['Status'] ?? ''));
            $ok = ($body['success'] ?? null) === true
                || in_array($status, ['success', 'true', 'ok', 'cancelled', 'canceled'], true)
                || (!isset($body['error']) && !isset($body['rmk']) && ($body['order_id'] ?? $body['waybill'] ?? null));

            // Some Delhivery responses only return remark + status without success=true.
            if (!$ok && isset($body['error'])) {
                $ok = false;
            } elseif (!$ok && empty($body['error']) && empty($body['rmk'])) {
                $ok = true;
            }

            if (!$ok) {
                return [
                    'success' => false,
                    'error'   => (string) ($body['error'] ?? $body['rmk'] ?? $body['remark'] ?? 'Delhivery refused cancellation.'),
                    'raw'     => $body,
                ];
            }

            return [
                'success' => true,
                'data'    => $body,
            ];
        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (cancelWaybill): ' . json_encode($responseBody));

            return [
                'success' => false,
                'error'   => 'API Error: ' . ($responseBody['error'] ?? $responseBody['rmk'] ?? $e->getMessage()),
                'raw'     => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to cancel Delhivery waybill: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => 'Cancellation failed: ' . $e->getMessage(),
            ];
        }
    }
    
    public function getTat(
            string $originPin,
            string $destinationPin,
            string $mot = 'S',                  // 'S' = Surface, 'E' = Express
            ?string $pdt = null,                // 'B2B', 'B2C', or null
            ?string $expectedPickupDate = null  // e.g. '2025-11-16T15:30:00'
        ): array 
    {
        // According to docs:
        // /api/dc/expected_tat?origin_pin=...&destination_pin=...&mot=...
        $endpoint = $this->getBaseUrl() . '/api/dc/expected_tat';

        try {
            $query = [
                'origin_pin'      => $originPin,
                'destination_pin' => $destinationPin,
                'mot'             => $mot,
            ];

            if (!empty($pdt)) {
                $query['pdt'] = $pdt; // B2B / B2C
            }

            if (!empty($expectedPickupDate)) {
                // Delhivery expects "expected_pd" in 'Y-m-d H:i' format
                $query['expected_pd'] = $expectedPickupDate;
            }

            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'query' => $query,
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (TAT): ' . json_encode($responseBody));

            return [
                'error' => 'API Error: ' . ($responseBody['detail'] ?? $e->getMessage()),
                'raw'   => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch TAT: ' . $e->getMessage());

            return [
                'error' => 'TAT fetch failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Warehouses registered on this Delhivery client. The create-shipment
     * API rejects any pickup name that is not an exact match.
     *
     * Delhivery does not publish /clientwarehouse/all/ (it returns the login
     * page). The live routes are fetch, search, and status-by-name.
     */
    public function listClientWarehouses(): array
    {
        $hosts = array_values(array_unique([
            $this->getBaseUrl(),
            'https://track.delhivery.com',
        ]));

        $reads = [
            ['GET', '/api/backend/clientwarehouse/search/', null],
            ['GET', '/api/backend/clientwarehouse/search/?page_size=100', null],
            ['GET', '/api/backend/clientwarehouse/fetch/', null],
            ['GET', '/api/backend/clientwarehouse/fetch/?page_size=100', null],
        ];

        $lastError = 'No Delhivery warehouses found.';
        foreach ($hosts as $host) {
            foreach ($reads as [$method, $path, $json]) {
                $fetched = $this->requestWarehouse($method, rtrim($host, '/') . $path, $json);
                if (self::normalizeClientWarehouses($fetched['body']) !== []) {
                    $this->rememberWarehouseHost($host);
                    return $fetched['body'];
                }
                if ($fetched['error'] !== '') {
                    $lastError = $fetched['error'];
                }
            }

            $confirmed = $this->confirmKnownWarehouses($host);
            if ($confirmed !== []) {
                $this->rememberWarehouseHost($host);
                return ['data' => $confirmed];
            }
        }

        $this->lastWarehouseError = $lastError;
        Log::warning('Delhivery warehouse list empty: ' . $lastError);

        return [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function confirmKnownWarehouses(string $host): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn ($name) => trim((string) $name),
            [
                config('shipping.pickup.name'),
                'HANERI HO',
            ]
        ))));

        $found = [];
        foreach ($names as $name) {
            $fetched = $this->requestWarehouse(
                'POST',
                rtrim($host, '/') . '/api/backend/clientwarehouse/status/',
                ['name' => $name]
            );
            $blob = strtolower(json_encode($fetched['body']) ?: '');
            $missing = $fetched['error'] !== ''
                || $fetched['body'] === []
                || str_contains($blob, 'does not exist')
                || str_contains($blob, 'not found')
                || (isset($fetched['body']['success']) && $fetched['body']['success'] === false);
            if ($missing) {
                continue;
            }

            $rows = self::normalizeClientWarehouses($fetched['body']);
            if ($rows === []) {
                $rows = [[
                    'name' => $name,
                    'pin' => '',
                    'address' => '',
                    'city' => '',
                    'state' => '',
                    'phone' => '',
                    'return_address' => '',
                    'return_pin' => '',
                    'return_city' => '',
                    'return_state' => '',
                    'return_phone' => '',
                ]];
            }
            foreach ($rows as $warehouse) {
                if (($warehouse['name'] ?? '') === '') {
                    $warehouse['name'] = $name;
                }
                $found[$warehouse['name']] = $warehouse;
            }
        }

        return array_values($found);
    }

    private function rememberWarehouseHost(string $host): void
    {
        $this->lastWarehouseError = '';
        try {
            Cache::put('delhivery.api_host', $host, 3600);
        } catch (\Throwable $e) {
            // Ignore when the cache store is not booted.
        }
    }

    /**
     * @return array{body: array, error: string}
     */
    private function requestWarehouse(string $method, string $url, ?array $json = null): array
    {
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept'        => 'application/json',
                ],
                'timeout' => 20,
            ];
            if ($json !== null) {
                $options['headers']['Content-Type'] = 'application/json';
                $options['json'] = $json;
            }

            $response = $this->client->request($method, $url, $options);
            $raw = json_decode($response->getBody()->getContents(), true);

            return [
                'body' => is_array($raw) ? $raw : [],
                'error' => is_array($raw) ? '' : 'Delhivery returned an unreadable warehouse list.',
            ];
        } catch (\Throwable $e) {
            return [
                'body' => [],
                'error' => $this->shortWarehouseError($e),
            ];
        }
    }

    private function shortWarehouseError(\Throwable $e): string
    {
        $message = $e->getMessage();
        if ($e instanceof ClientException && $e->getResponse()) {
            $decoded = json_decode((string) $e->getResponse()->getBody(), true);
            if (is_array($decoded)) {
                $message = (string) ($decoded['detail'] ?? $decoded['error'] ?? $decoded['rmk'] ?? $decoded['message'] ?? $message);
            }
        }

        if (stripos($message, '<script') !== false || stripos($message, '<!---->') !== false || strlen($message) > 240) {
            $status = $e instanceof ClientException && $e->getResponse()
                ? $e->getResponse()->getStatusCode()
                : 0;

            return $status > 0
                ? "Delhivery warehouse lookup failed ({$status})."
                : 'Delhivery warehouse lookup failed.';
        }

        return $message;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function normalizeClientWarehouses(array $raw): array
    {
        $found = [];
        self::collectWarehouses($raw, $found, 0);

        return array_values($found);
    }

    private static function collectWarehouses(array $node, array &$found, int $depth): void
    {
        if ($depth > 6) {
            return;
        }

        $name = '';
        foreach (['name', 'warehouse_name', 'pickup_location_name', 'registered_name'] as $key) {
            if (isset($node[$key]) && is_scalar($node[$key]) && trim((string) $node[$key]) !== '') {
                $name = trim((string) $node[$key]);
                break;
            }
        }
        $pin = '';
        foreach (['pin', 'pincode', 'pin_code'] as $key) {
            if (isset($node[$key]) && is_scalar($node[$key])) {
                $pin = preg_replace('/\D/', '', (string) $node[$key]) ?? '';
                break;
            }
        }
        $city = isset($node['city']) && is_scalar($node['city']) ? trim((string) $node['city']) : '';
        $address = '';
        foreach (['address', 'add'] as $key) {
            if (isset($node[$key]) && is_scalar($node[$key])) {
                $address = trim((string) $node[$key]);
                break;
            }
        }

        $marked = array_key_exists('active', $node) || array_key_exists('status', $node);
        if ($name !== '' && ($pin !== '' || $city !== '' || $address !== '' || $marked)) {
            $found[$name] = [
                'name' => $name,
                'pin' => $pin,
                'address' => $address,
                'city' => $city,
                'state' => trim((string) ($node['state'] ?? '')),
                'phone' => trim((string) ($node['phone'] ?? $node['phone_number'] ?? '')),
                'return_address' => trim((string) ($node['return_address'] ?? $node['return_add'] ?? '')),
                'return_pin' => preg_replace('/\D/', '', (string) ($node['return_pin'] ?? '')),
                'return_city' => trim((string) ($node['return_city'] ?? '')),
                'return_state' => trim((string) ($node['return_state'] ?? '')),
                'return_phone' => trim((string) ($node['return_phone'] ?? '')),
            ];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                self::collectWarehouses($value, $found, $depth + 1);
            }
        }
    }

    /**
     * Pick the registered warehouse for a shipment.
     * Name match wins, then a unique pincode match, then the only warehouse.
     *
     * @return array{match: ?array, names: array<int, string>}
     */
    public static function matchClientWarehouse(array $raw, string $requestedName, ?string $requestedPin): array
    {
        $warehouses = self::normalizeClientWarehouses($raw);
        $names = array_column($warehouses, 'name');
        if ($warehouses === []) {
            return ['match' => null, 'names' => []];
        }

        $wanted = trim($requestedName);
        foreach ($warehouses as $warehouse) {
            if ($wanted !== '' && strcasecmp($warehouse['name'], $wanted) === 0) {
                return ['match' => $warehouse, 'names' => $names];
            }
        }

        $pin = preg_replace('/\D/', '', (string) $requestedPin);
        if ($pin !== '') {
            $byPin = array_values(array_filter(
                $warehouses,
                fn (array $warehouse) => $warehouse['pin'] !== '' && $warehouse['pin'] === $pin
            ));
            if (count($byPin) === 1) {
                return ['match' => $byPin[0], 'names' => $names];
            }
        }

        if (count($warehouses) === 1) {
            return ['match' => $warehouses[0], 'names' => $names];
        }

        return ['match' => null, 'names' => $names];
    }

    /**
     * Haneri stores parcel weight in kilograms. Delhivery CMU treats `weight` as grams
     * (One UI labels it "gm"). Values already > 50 are left as grams.
     */
    public static function kgToDelhiveryGrams(float $kg): int
    {
        if ($kg <= 0) {
            return 1000;
        }
        if ($kg > 50) {
            return (int) round($kg);
        }

        return (int) round($kg * 1000);
    }

    /**
     * Split invoice / COD across packages. Prefers per-package totals when they look
     * like real line amounts; otherwise divides the order total (avoids each box
     * getting the full invoice value).
     *
     * @param  array<int, array>  $packages
     * @return array<int, array{total_amount: float, cod_amount: float}>
     */
    public static function allocatePackageAmounts(
        array $packages,
        float $orderTotal,
        float $orderCod
    ): array {
        $n = count($packages);
        if ($n === 0) {
            return [];
        }

        $orderTotal = max(0, round($orderTotal, 2));
        $orderCod = max(0, round($orderCod, 2));

        $explicit = [];
        foreach ($packages as $i => $package) {
            if (!is_array($package) || !array_key_exists('total_amount', $package)) {
                continue;
            }
            if ($package['total_amount'] === null || $package['total_amount'] === '') {
                continue;
            }
            $explicit[$i] = round((float) $package['total_amount'], 2);
        }

        $useExplicit = false;
        if (count($explicit) === $n) {
            $sum = round(array_sum($explicit), 2);
            $allFullOrder = $n > 1
                && $orderTotal > 0
                && count(array_filter(
                    $explicit,
                    static fn ($amount) => abs($amount - $orderTotal) < 0.02
                )) === $n;
            // Reject near-zero child amounts (Delhivery prints ₹0.1 on labels).
            $hasTiny = count(array_filter(
                $explicit,
                static fn ($amount) => $amount < 1.0
            )) > 0;
            $useExplicit = !$allFullOrder && !$hasTiny && $sum > 0;
        }

        if ($useExplicit) {
            $totals = $explicit;
        } else {
            // Equal split of the order invoice across boxes (each fan gets its share).
            $splitFrom = $orderTotal > 0
                ? $orderTotal
                : max(0, round(array_sum($explicit), 2));
            if ($splitFrom <= 0) {
                $splitFrom = (float) max(1, $n);
            }
            $base = floor(($splitFrom / $n) * 100) / 100;
            if ($base < 1 && $splitFrom >= $n) {
                $base = floor(($splitFrom / $n) * 100) / 100;
            }
            $allocated = 0.0;
            $totals = [];
            for ($i = 0; $i < $n; $i++) {
                if ($i === $n - 1) {
                    $totals[$i] = round(max(0.01, $splitFrom - $allocated), 2);
                } else {
                    $totals[$i] = $base > 0 ? $base : round($splitFrom / $n, 2);
                    $allocated = round($allocated + $totals[$i], 2);
                }
            }
        }

        $sumTotals = array_sum($totals) ?: 1.0;
        $out = [];
        $codAllocated = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $total = round((float) ($totals[$i] ?? 0), 2);

            if ($useExplicit
                && isset($packages[$i]['cod_amount'])
                && $packages[$i]['cod_amount'] !== null
                && $packages[$i]['cod_amount'] !== ''
            ) {
                $cod = round((float) $packages[$i]['cod_amount'], 2);
            } elseif ($i === $n - 1) {
                $cod = round($orderCod - $codAllocated, 2);
            } else {
                $cod = $orderCod > 0
                    ? round($orderCod * ($total / $sumTotals), 2)
                    : 0.0;
                $codAllocated = round($codAllocated + $cod, 2);
            }

            $out[$i] = [
                'total_amount' => $total,
                'cod_amount'   => max(0, $cod),
            ];
        }

        return $out;
    }

}