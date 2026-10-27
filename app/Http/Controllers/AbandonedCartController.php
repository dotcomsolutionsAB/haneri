<?php

namespace App\Http\Controllers;

use App\Services\AbandonedCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbandonedCartController extends Controller
{
    public function index(Request $request, AbandonedCartService $carts): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->input('limit', 15)));
        $offset = max(0, (int) $request->input('offset', 0));
        $search = trim((string) $request->input('search', ''));

        return response()->json([
            'success' => true,
            'message' => 'Abandoned carts fetched successfully.',
            'data' => $carts->paginate($search, $limit, $offset),
        ]);
    }
}
