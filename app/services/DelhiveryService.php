<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class DelhiveryService
{
    protected $client;
    protected $apiKey;
    protected $apiUrl;
    protected $isTestEnvironment;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('DELHIVERY_API_KEY');
        $this->isTestEnvironment = env('DELHIVERY_TEST_MODE', true);
    }

    private function getBaseUrl()
    {
        return $this->isTestEnvironment 
            ? 'https://staging-express.delhivery.com' 
            : 'https://track.delhivery.com';
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
            // 1) Build shipments array (1 shipment per order)
            $shipments = [
                [
                    'name'           => $orderData['customer_name'],          // Consignee name
                    'add'            => $orderData['customer_address'],
                    'pin'            => $orderData['pin'],
                    'city'           => $orderData['city'],
                    'state'          => $orderData['state'],
                    'country'        => 'India',
                    'phone'          => $orderData['phone'],
                    'order'          => $orderData['order_no'],              // Your order no
                    'payment_mode'   => $orderData['payment_mode'],          // 'Prepaid' or 'COD'
                    'products_desc'  => $orderData['products_description'],  // Short desc
                    'cod_amount'     => $orderData['cod_amount'] ?? 0,       // If COD, else 0
                    'total_amount'   => $orderData['total_amount'],          // Invoice total
                    'order_date'     => $orderData['order_date'],            // YYYY-MM-DD
                    'seller_add'     => $orderData['seller_address'],
                    'seller_name'    => $orderData['seller_name'],
                    'seller_inv'     => $orderData['seller_invoice'],        // Invoice no
                    'quantity'       => $orderData['quantity'],              // Total items
                    'weight'         => $orderData['weight'],                // In kg
                    'shipping_mode'  => $orderData['shipping_mode'] ?? 'Surface',
                    'service_level'  => $orderData['service_level'] ?? 'normal',
                    'address_type'   => $orderData['address_type'] ?? 'home',
                    // optional return address (else defaults to pickup)
                    'return_pin'     => $orderData['return_pin']     ?? $orderData['pickup_pin'],
                    'return_city'    => $orderData['return_city']    ?? $orderData['pickup_city'],
                    'return_phone'   => $orderData['return_phone']   ?? $orderData['pickup_phone'],
                    'return_add'     => $orderData['return_address'] ?? $orderData['pickup_address'],
                    'return_state'   => $orderData['return_state']   ?? $orderData['pickup_state'],
                    'return_country' => $orderData['return_country'] ?? 'India',
                    'shipment_width'  => !empty($orderData['shipment_width'])  ? $orderData['shipment_width']  : 10,
                    'shipment_height' => !empty($orderData['shipment_height']) ? $orderData['shipment_height'] : 10,
                    'shipment_length' => !empty($orderData['shipment_length']) ? $orderData['shipment_length'] : 10,

                ],
            ];

            // 2) Pickup location (sender)
            $finalPayload = [
                'pickup_location' => [
                    // THIS "name" must match Delhivery-registered warehouse name (case sensitive)
                    'name'    => $orderData['pickup_name'],
                    'add'     => $orderData['pickup_address'],
                    'pin'     => (string)$orderData['pickup_pin'],
                    'city'    => $orderData['pickup_city'],
                    'state'   => $orderData['pickup_state'],
                    'country' => 'India',
                    'phone'   => (string)$orderData['pickup_phone'],
                ],
                'shipments' => $shipments,
            ];


            // 3) Call Delhivery CMU API
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

            return json_decode($response->getBody()->getContents(), true);

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

}