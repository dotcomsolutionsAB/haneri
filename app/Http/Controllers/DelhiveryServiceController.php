<?php

namespace App\Http\Controllers;
use App\Services\DelhiveryService;
use Illuminate\Http\Request;

class DelhiveryServiceController extends Controller
{
    //
    protected $delhiveryService;

    public function __construct(DelhiveryService $delhiveryService)
    {
        $this->delhiveryService = $delhiveryService;
    }

    public function createOrder(Request $request)
    {
        $orderData = $request->all();
        $response = $this->delhiveryService->placeOrder($orderData);

        return response()->json($response);
    }

    public function trackMultipleShipments(array $waybillNumbers)
    {
        // $response = $this->client->post($this->apiUrl, [
        //     'json' => [
        //         'waybill' => $waybillNumbers  // Pass the array of waybill numbers
        //     ],
        //     'headers' => [
        //         'Authorization' => 'Bearer ' . $this->apiKey,
        //     ],
        // ]);

        // // Parse and return the response
        // return json_decode($response->getBody()->getContents(), true);

        // Validate the waybill numbers array
        $request->validate([
            'waybill' => 'required|array',
            'waybill.*' => 'string',
        ]);

        $waybillNumbers = $request->input('waybill');
        $response = $this->delhiveryService->trackMultipleShipments($waybillNumbers);

        return response()->json($response);
    }

}
