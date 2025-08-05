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
        $this->apiUrl = env('DELHIVERY_API_URL', 'https://api.delhivery.com');
    }

    // public function placeOrder($orderData)
    // {
    //     // The correct endpoint for order creation on Delhivery One
    //     $endpoint = 'https://api.delhivery.com/v1/orders/upload';

    //     try {
    //         // Note: The API expects the data as 'form_params' with a 'data' key,
    //         // which is a JSON encoded string.
    //         $response = $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //             ],
    //             'form_params' => [
    //                 'format' => 'json',
    //                 'data' => json_encode($orderData)
    //             ],
    //         ]);

    //         return json_decode($response->getBody()->getContents(), true);

    //     } catch (ClientException $e) {
    //         // This is a Guzzle exception for 4xx errors
    //         $responseBody = $e->getResponse()->getBody()->getContents();
    //         Log::error('Delhivery API Client Error (placeOrder): ' . $e->getMessage(), ['response' => $responseBody]);
    //         return ['error' => 'API Client Error: ' . $responseBody];
    //     } catch (RequestException $e) {
    //          // Catch all other request exceptions (e.g., network issues)
    //         Log::error('Delhivery API Request Error (placeOrder): ' . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    // public function placeOrder($orderData)
    // {
    //     // The correct endpoint for order creation on the Delhivery One platform
    //     $endpoint = 'https://api.delhivery.com/api/pms/packages';

    //     try {
    //         $response = $this->client->post($endpoint, [
    //             'json' => $orderData,
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //                 'Content-Type' => 'application/json',
    //                 'Accept' => 'application/json', // Good practice to include this
    //             ],
    //         ]);

    //         return json_decode($response->getBody()->getContents(), true);

    //     } catch (ClientException $e) {
    //         $responseBody = $e->getResponse()->getBody()->getContents();
    //         // Log the error for debugging.
    //         // Log::error('Delhivery API Client Error (placeOrder): ' . $e->getMessage(), ['response' => $responseBody]);
    //         return ['error' => 'API Client Error: ' . $responseBody];
    //     } catch (\Exception $e) {
    //         // Catch all other exceptions.
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    // public function placeOrder($orderData)
    // {
    //     // The correct endpoint for order creation on the Delhivery One platform
    //     $endpoint = 'https://api.delhivery.com/v1/orders/upload';

    //     try {
    //         // This endpoint often expects the data as 'form_params'
    //         // with a key of 'data', which is a JSON encoded string.
    //         $response = $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //                 'Accept' => 'application/json', // Accept JSON response
    //             ],
    //             'form_params' => [
    //                 'format' => 'json', // Specify the format
    //                 'data' => json_encode($orderData) // Pass your data as a JSON string
    //             ],
    //         ]);

    //         return json_decode($response->getBody()->getContents(), true);

    //     } catch (ClientException $e) {
    //         $responseBody = $e->getResponse()->getBody()->getContents();
    //         return ['error' => 'API Client Error: ' . $responseBody];
    //     } catch (\Exception $e) {
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    // public function debugPlaceOrder($orderData)
    // {
    //     // List of known possible endpoints
    //     $endpoints = [
    //         'https://api.delhivery.com/api/pms/packages',
    //         'https://api.delhivery.com/v1/orders/upload'
    //     ];

    //     foreach ($endpoints as $endpoint) {
    //         try {
    //             // Log which endpoint we are trying
    //             \Illuminate\Support\Facades\Log::info("Trying Delhivery endpoint: $endpoint");

    //             // Attempt to send a POST request with the order data
    //             $response = $this->client->post($endpoint, [
    //                 'headers' => [
    //                     'Authorization' => 'Token ' . $this->apiKey,
    //                     'Accept' => 'application/json',
    //                 ],
    //                 'form_params' => [
    //                     'format' => 'json',
    //                     'data' => json_encode($orderData)
    //                 ],
    //             ]);

    //             // If the request succeeds, return the decoded JSON
    //             return json_decode($response->getBody()->getContents(), true);

    //         } catch (\Exception $e) {
    //             // Log the error and move on to the next endpoint if this one fails.
    //             \Illuminate\Support\Facades\Log::error("Failed to connect to $endpoint: " . $e->getMessage());
    //             // We'll let the loop continue
    //         }
    //     }

    //     // If all endpoints fail, return a generic error.
    //     return ['error' => 'All attempted Delhivery API endpoints failed. Please check your API key and URL.'];
    // }

    // public function debugPlaceOrder($orderData)
    // {
    //     $endpoint = 'https://track.delhivery.com/api/cmu/create.json';
    //     try {
    //         $response = $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //                 'Content-Type' => 'application/json',
    //             ],
    //             'json' => $orderData,
    //         ]);
    //         return json_decode($response->getBody()->getContents(), true);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to connect to Delhivery API: " . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    // public function placeOrder($orderData)
    // {
    //     $endpoint = 'https://track.delhivery.com/api/cmu/create.json';
    //     try {
    //         $response = $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //             ],
    //             'form_params' => [
    //                 'format' => 'json',
    //                 'data' => json_encode($orderData),
    //             ],
    //         ]);
    //         return json_decode($response->getBody()->getContents(), true);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to connect to Delhivery API: " . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    // public function PlaceOrder($orderData)
    // {
    //     $endpoint = 'https://track.delhivery.com/api/cmu/create.json';
    //     try {
    //         $shipments = [
    //             [
    //                 'name' => $orderData['customer_name'],
    //                 'add' => $orderData['customer_address'],
    //                 'pin' => $orderData['pin'],
    //                 'city' => $orderData['city'],
    //                 'state' => $orderData['state'],
    //                 'country' => 'India',
    //                 'phone' => $orderData['phone'],
    //                 'order' => $orderData['order'],
    //                 'payment_mode' => 'Prepaid',
    //                 // Add other required fields here...
    //             ],
    //         ];

    //         $data = [
    //             'shipments' => $shipments,
    //             'pickup_location' => [
    //                 'name' => 'warehouse_name',
    //             ],
    //         ];

    //         $response = $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //                 'Content-Type' => 'application/x-www-form-urlencoded',
    //             ],
    //             'form_params' => [
    //                 'format' => 'json',
    //                 'data' => json_encode($data),
    //             ],
    //         ]);

    //         return json_decode($response->getBody()->getContents(), true);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to connect to Delhivery API: " . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    // public function placeOrder($orderData)
    // {
    //     $endpoint = 'https://track.delhivery.com/api/cmu/create.json';
    //     try {
    //         $shipments = [
    //             [
    //                 'name' => $orderData['customer_name'],
    //                 'add' => $orderData['customer_address'],
    //                 'pin' => $orderData['pin'],
    //                 'city' => $orderData['city'],
    //                 'state' => $orderData['state'],
    //                 'country' => 'India',
    //                 'phone' => $orderData['phone'],
    //                 'order' => $orderData['order'],
    //                 'payment_mode' => 'Prepaid',
    //                 // Add other required fields here...
    //                 'shipment_width' => '100',
    //                 'shipment_height' => '100',
    //                 'shipping_mode' => 'Surface',
    //             ],
    //         ];

    //         $data = [
    //             'shipments' => $shipments,
    //             'pickup_location' => [
    //                 'name' => 'warehouse_name', // Replace with your warehouse name
    //             ],
    //         ];

    //         $response = $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //             ],
    //             'form_params' => [
    //                 'format' => 'json',
    //                 'data' => json_encode($data),
    //             ],
    //         ]);

    //         return json_decode($response->getBody()->getContents(), true);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to connect to Delhivery API: " . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    //  public function placeOrder($orderData)
    // {
    //     $endpoint = 'https://track.delhivery.com/api/cmu/create.json';
    //     try {
    //         $shipments = [
    //             [
    //                 'name' => $orderData['customer_name'],
    //                 'add' => $orderData['customer_address'],
    //                 'pin' => $orderData['pin'],
    //                 'city' => $orderData['city'],
    //                 'state' => $orderData['state'],
    //                 'country' => 'India',
    //                 'phone' => $orderData['phone'],
    //                 'order' => $orderData['order'],
    //                 'payment_mode' => 'Prepaid',
    //                 'return_pin' => $orderData['return_pin'],
    //                 'return_city' => $orderData['return_city'],
    //                 'return_phone' => $orderData['return_phone'],
    //                 'return_add' => $orderData['return_address'],
    //                 'return_state' => $orderData['return_state'],
    //                 'return_country' => $orderData['return_country'],
    //                 'products_desc' => $orderData['products_description'],
    //                 'hsn_code' => $orderData['hsn_code'],
    //                 'cod_amount' => $orderData['cod_amount'],
    //                 'order_date' => $orderData['order_date'],
    //                 'total_amount' => $orderData['total_amount'],
    //                 'seller_add' => $orderData['seller_address'],
    //                 'seller_name' => $orderData['seller_name'],
    //                 'seller_inv' => $orderData['seller_invoice'],
    //                 'quantity' => $orderData['quantity'],
    //                 'waybill' => $orderData['waybill'],
    //                 'shipment_width' => $orderData['shipment_width'],
    //                 'shipment_height' => $orderData['shipment_height'],
    //                 'weight' => $orderData['weight'],
    //                 'shipping_mode' => $orderData['shipping_mode'],
    //                 'address_type' => $orderData['address_type'],
    //             ],
    //         ];

    //         $data = [
    //             'shipments' => $shipments,
    //             'pickup_location' => [
    //                 'name' => 'warehouse_name', // Replace with your warehouse name
    //             ],
    //         ];

    //         $response = $this->client->post($endpoint, [
    //             'headers' => [
    //                 'Authorization' => 'Token ' . $this->apiKey,
    //             ],
    //             'form_params' => [
    //                 'format' => 'json',
    //                 'data' => json_encode($data),
    //             ],
    //         ]);

    //         return json_decode($response->getBody()->getContents(), true);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to connect to Delhivery API: " . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

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
                ],
            ];

            // The critical fix:
            // The pickup_location object must be fully populated with correct details,
            // not just the name. The API needs to verify the pincode, city, etc.
            $pickupLocationData = [
                'name' => 'Burhanuddin',
                'add' => '26, Netaji Subhas Rd, opp. Goopta Mansion, China Bazar',
                'pin' => '700001',
                'city' => 'Kolkata',
                'state' => 'West Bengal',
                'phone' => '8597348785',
            ];

            // Combine the pickup location and shipment data into the final API payload
            $finalPayload = [
                'shipments' => $shipments,
                'pickup_location' => $pickupLocationData,
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

    // public function trackMultipleShipments(array $waybillNumbers)
    // {
    //     // The correct endpoint for tracking. This is often a different base URL.
    //     $endpoint = 'https://track.delhivery.com/api/packages/json/';
        
    //     try {
    //         // Tracking API requires waybills as a GET query parameter
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

    //     } catch (ClientException $e) {
    //         $responseBody = $e->getResponse()->getBody()->getContents();
    //         Log::error('Delhivery API Client Error (trackMultipleShipments): ' . $e->getMessage(), ['response' => $responseBody]);
    //         return ['error' => 'API Client Error: ' . $responseBody];
    //     } catch (RequestException $e) {
    //         Log::error('Delhivery API Request Error (trackMultipleShipments): ' . $e->getMessage());
    //         return ['error' => 'API Request Error: ' . $e->getMessage()];
    //     }
    // }

    public function trackMultipleShipments(array $waybillNumbers)
    {
        $endpoint = 'https://track.delhivery.com/api/v1/packages/json/';
        try {
            $waybillString = implode(',', $waybillNumbers);
            $response = $this->client->get($endpoint, [
                'query' => [
                    'waybill' => $waybillString,
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("Failed to track shipments: " . $e->getMessage());
            return ['error' => 'API Request Error: ' . $e->getMessage()];
        }
    }
}