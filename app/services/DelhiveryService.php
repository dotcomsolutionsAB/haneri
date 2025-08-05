<?php

namespace App\Services;

use GuzzleHttp\Client;

class DelhiveryService
{
    protected $client;
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('DELHIVERY_API_KEY');
        $this->apiUrl = env('DELHIVERY_API_URL');
    }

    public function placeOrder($orderData)
    {
        $response = $this->client->post($this->apiUrl, [
            'json' => $orderData,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
