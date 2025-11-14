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

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('DELHIVERY_API_KEY');
        // Set the base URL correctly. For Delhivery One, it's often https://api.delhivery.com/
        // but tracking can be on a different subdomain.
        //$this->apiUrl = env('DELHIVERY_API_URL', 'https://api.delhivery.com');
        $this->isTestEnvironment = env('DELHIVERY_TEST_MODE', true);
    }

    private function getBaseUrl()
    {
        // Use a single method to get the correct base URL
        return $this->isTestEnvironment 
            ? 'https://staging-express.delhivery.com' 
            : 'https://track.delhivery.com';
    }

    public function placeOrder($orderData)
    {
        // Make sure to use the correct endpoint for your environment
        $endpoint = $this->getBaseUrl() . '/api/cmu/create.json';

        try {
            $shipments = [
                [
                    'name' => $orderData['customer_name'],
                    'add' => $orderData['customer_address'],
                    'pin' => $orderData['pin'],
                    'city' => $orderData['city'],
                    'state' => $orderData['state'],
                    'country' => 'India',
                    'phone' => $orderData['phone'],
                    'order' => $orderData['order'],
                    'payment_mode' => 'Prepaid',
                    'return_pin' => $orderData['return_pin'],
                    'return_city' => $orderData['return_city'],
                    'return_phone' => $orderData['return_phone'],
                    'return_add' => $orderData['return_address'], // Note: 'return_add' maps to 'return_address' from request
                    'return_state' => $orderData['return_state'],
                    'return_country' => $orderData['return_country'],
                    'products_desc' => $orderData['products_description'],
                    'hsn_code' => $orderData['hsn_code'],
                    'cod_amount' => $orderData['cod_amount'],
                    'order_date' => $orderData['order_date'],
                    'total_amount' => $orderData['total_amount'],
                    'seller_add' => $orderData['seller_address'],
                    'seller_name' => $orderData['seller_name'],
                    'seller_inv' => $orderData['seller_invoice'],
                    'quantity' => $orderData['quantity'],
                    'waybill' => $orderData['waybill'],
                    'shipment_width' => $orderData['shipment_width'],
                    'shipment_height' => $orderData['shipment_height'],
                    'weight' => $orderData['weight'],
                    'shipping_mode' => $orderData['shipping_mode'],
                    'address_type' => $orderData['address_type'],
                    //'end_date' => $orderData['end_date'],
                ],
            ];

            // // The critical fix:
            // // The pickup_location object must be fully populated with correct details,
            // // not just the name. The API needs to verify the pincode, city, etc.
            // $pickupLocationData = [
            //     'name' => 'Burhanuddin',
            //     'add' => '26, Netaji Subhas Rd, opp. Goopta Mansion, China Bazar',
            //     'pin' => '700001',
            //     'city' => 'Kolkata',
            //     'state' => 'West Bengal',
            //     'phone' => '8597348785',
            // ];

            // // Combine the pickup location and shipment data into the final API payload
            // $finalPayload = [
            //     'shipments' => $shipments,
            //     'pickup_location' => $pickupLocationData,
            // ];

            // This is the updated part.
            // We are now flattening the pickup location details into the main array.
            $finalPayload = [
                'shipments' => $shipments,
                'pickup_name' => 'Burhanuddin',
                'pickup_add' => '26, Netaji Subhas Rd, opp. Goopta Mansion, China Bazar',
                'pickup_pin' => '700001',
                'pickup_city' => 'Kolkata',
                'pickup_state' => 'West Bengal',
                'pickup_phone' => '8597348785',
            ];
            
            // This endpoint expects the data as 'form_params', with a 'data' key
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                ],
                'form_params' => [
                    'format' => 'json',
                    'data' => json_encode($finalPayload),
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
            
        } catch (\Exception $e) {
            Log::error("Failed to connect to Delhivery API: " . $e->getMessage());
            return ['error' => 'API Request Error: ' . $e->getMessage()];
        }
    }

    public function createOrder(Request $request)
    {
        // ... your validation code here

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $orderData = $request->all();

        // Call the new debug method
        $result = $this->delhiveryService->debugPlaceOrder($orderData);

        // Check if the result is an error and return it
        if (isset($result['error'])) {
            return response()->json($result, 500);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
    /**
     * Tracks one or more shipments by waybill number.
     * This consolidated method replaces both trackMultipleShipments and the previous trackShipments.
     *
     * @param array $waybillNumbers An array of waybill numbers.
     * @return array The API response or an error array.
     */
    public function trackShipments(array $waybillNumbers)
    {
        $endpoint = $this->getBaseUrl() . '/api/v1/packages/json/';
        
        try {
            $waybillString = implode(',', $waybillNumbers);
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'waybill' => $waybillString,
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (trackShipments): ' . json_encode($responseBody));
            return ['error' => 'API Error: ' . ($responseBody['rmk'] ?? $e->getMessage())];
        } catch (\Exception $e) {
            Log::error("Failed to track shipments: " . $e->getMessage());
            return ['error' => 'API Request Error: ' . $e->getMessage()];
        }
    }

    /**
     * Checks if a pincode is serviceable by Delhivery.
     *
     * @param string $pincode The pincode to check.
     * @return array The API response or an error array.
     */
    public function checkPincodeServiceability($pincode)
    {
        $endpoint = $this->getBaseUrl() . '/c/api/pin-codes/json/';
    
        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'filter_codes' => $pincode,
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (pincode check): ' . json_encode($responseBody));
            return ['error' => 'API Error: ' . ($responseBody['rmk'] ?? $e->getMessage())];
        } catch (\Exception $e) {
            Log::error("Failed to check pincode serviceability: " . $e->getMessage());
            return ['error' => 'Pincode serviceability check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Calculates the estimated shipping cost for a shipment.
     *
     * @param string $originPin The origin pincode.
     * @param string $destinationPin The destination pincode.
     * @param float $codAmount The Cash on Delivery amount.
     * @param float $weight The chargeable weight in kg.
     * @param string $paymentType 'Pre-paid' or 'COD'.
     * @return array The API response or an error array.
     */
    public function getShippingCost($originPin, $destinationPin, $codAmount, $weight, $paymentType = 'Pre-paid')
    {
        $endpoint = $this->getBaseUrl() . '/api/kinko/v1/invoice/charges/.json';
        
        try {
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'md' => 'E',
                    'ss' => 'Pre-paid',
                    'd_pin' => $destinationPin,
                    'o_pin' => $originPin,
                    'cgm' => $weight,
                    'pt' => $paymentType,
                    'cod' => ($paymentType === 'COD') ? $codAmount : 0,
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Delhivery API Client Error (shipping cost): ' . json_encode($responseBody));
            return ['error' => 'API Error: ' . ($responseBody['rmk'] ?? $e->getMessage())];
        } catch (\Exception $e) {
            Log::error("Failed to calculate shipping cost: " . $e->getMessage());
            return ['error' => 'Shipping cost calculation failed: ' . $e->getMessage()];
        }
    }


        // public function trackMultipleShipments(array $waybillNumbers)
    // {
    //     $endpoint = 'https://track.delhivery.com/api/v1/packages/json/';
    //     try {
    //         $waybillString = implode(',', $waybillNumbers);
    //         $response = $this->client->get($endpoint, [
    //             'query' => [
    //                 'waybill' => $waybillString,
    //             ],
    //         ]);
    //         return json_decode($response->getBody()->getContents(), true);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to track shipments: " . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    // public function checkPincodeServiceability($pincode)
    // {
    //     // Use the correct endpoint for B2C Pincode Serviceability
    //     $endpoint = $this->getBaseUrl() . '/c/api/pin-codes/json/';
    
    //     try {
    //         $response = $this->client->get($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //             ],
    //             'query' => [
    //                 'filter_codes' => $pincode,
    //             ],
    //         ]);
    
    //         return json_decode($response->getBody()->getContents(), true);
    
    //     } catch (\Exception $e) {
    //         return ['error' => 'Pincode serviceability check failed: ' . $e->getMessage()];
    //     }
    // }

    // public function trackShipments(array $waybillNumbers)
    // {
    //     // Use the correct endpoint for Shipment Tracking
    //     $endpoint = $this->getBaseUrl() . '/api/v1/packages/json/';
        
    //     try {
    //         $waybillString = implode(',', $waybillNumbers);
    
    //         $response = $this->client->get($endpoint, [
    //             'query' => [
    //                 'waybill' => $waybillString,
    //             ],
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //             ],
    //         ]);
            
    //         return json_decode($response->getBody()->getContents(), true);
    
    //     } catch (\Exception $e) {
    //         return ['error' => 'Shipment tracking failed: ' . $e->getMessage()];
    //     }
    // }

    // public function getShippingCost($originPin, $destinationPin, $codAmount, $weight, $paymentType = 'Pre-paid')
    // {
    //     $endpoint = $this->getBaseUrl() . '/api/kinko/v1/invoice/charges/.json';
        
    //     try {
    //         $response = $this->client->get($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //             ],
    //             'query' => [
    //                 'md' => 'E', // This is likely for "Express"
    //                 'ss' => 'Delivered', // Service Status
    //                 'd_pin' => $destinationPin,
    //                 'o_pin' => $originPin,
    //                 'cgm' => $weight, // Chargeable weight
    //                 'pt' => $paymentType, // Pre-paid or COD
    //                 'cod' => ($paymentType === 'COD') ? $codAmount : 0, // COD Amount
    //             ],
    //         ]);
            
    //         return json_decode($response->getBody()->getContents(), true);
    
    //     } catch (\Exception $e) {
    //         return ['error' => 'Shipping cost calculation failed: ' . $e->getMessage()];
    //     }
    // }

}