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

    public function getShippingCost($originPin, $destinationPin, $codAmount, $weight, $paymentType = 'Pre-paid')
    {
        $endpoint = $this->getBaseUrl() . '/api/kinko/v1/invoice/charges/.json';

        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'query' => [
                    'md'  => 'E',
                    'ss'  => 'Delivered',
                    'd_pin' => $destinationPin,
                    'o_pin' => $originPin,
                    'cgm'  => $weight,
                    'pt'   => $paymentType,
                    'cod'  => $paymentType === 'COD' ? $codAmount : 0,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (shipping cost): ' . json_encode($responseBody));
            return ['error' => 'API Error: ' . ($responseBody['detail'] ?? $e->getMessage())];
        } catch (\Exception $e) {
            Log::error("Shipping cost calculation failed: " . $e->getMessage());
            return ['error' => 'Shipping cost calculation failed: ' . $e->getMessage()];
        }
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
                    'shipment_width' => $orderData['shipment_width'] ?? null,
                    'shipment_height'=> $orderData['shipment_height'] ?? null,
                    'shipping_mode'  => $orderData['shipping_mode'] ?? 'Surface',
                    'address_type'   => $orderData['address_type'] ?? 'home',
                    // optional return address (else defaults to pickup)
                    'return_pin'     => $orderData['return_pin']     ?? $orderData['pickup_pin'],
                    'return_city'    => $orderData['return_city']    ?? $orderData['pickup_city'],
                    'return_phone'   => $orderData['return_phone']   ?? $orderData['pickup_phone'],
                    'return_add'     => $orderData['return_address'] ?? $orderData['pickup_address'],
                    'return_state'   => $orderData['return_state']   ?? $orderData['pickup_state'],
                    'return_country' => $orderData['return_country'] ?? 'India',
                ],
            ];

            // 2) Pickup location (from your warehouse / sender)
            $finalPayload = [
                'pickup_name'  => $orderData['pickup_name'],
                'pickup_add'   => $orderData['pickup_address'],
                'pickup_pin'   => $orderData['pickup_pin'],
                'pickup_city'  => $orderData['pickup_city'],
                'pickup_state' => $orderData['pickup_state'],
                'pickup_phone' => $orderData['pickup_phone'],
                'shipments'    => $shipments,
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