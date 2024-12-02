<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BrandModel;

class BrandController extends Controller
{
    //
    // Store
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'logo' => 'nullable|string', // Path to logo image
            'custom_sort' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $brand = BrandModel::create([
            'name' => $request->input('name'),
            'logo' => $request->input('logo', null),
            'custom_sort' => $request->input('custom_sort', 0),
            'description' => $request->input('description', null),
        ]);

        unset($brand['id'], $brand['created_at'], $brand['updated_at']);

        return response()->json(['message' => 'Brand created successfully!', 'data' => $brand], 201);
    }

    // View All
    public function index()
    {
        $brands = BrandModel::select('name', 'logo', 'custom_sort', 'description')
            ->get()
            ->makeHidden(['id', 'created_at', 'updated_at']);

        return $brands->isNotEmpty()
            ? response()->json(['message' => 'Brands fetched successfully!', 'data' => $brands, 'count' => count($brands)], 200)
            : response()->json(['message' => 'No brands available.'], 400);
    }

    // View Single
    public function show($id)
    {
        $brand = BrandModel::find($id);

        if ($brand) {
            return response()->json(['message' => 'Brand fetched successfully!', 'data' => $brand->makeHidden(['id', 'created_at', 'updated_at'])], 200);
        } else {
            return response()->json(['message' => 'Brand not found.'], 404);
        }
    }

    // Update
    public function update(Request $request, $id)
    {
        $brand = BrandModel::find($id);
        if (!$brand) {
            return response()->json(['message' => 'Brand not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'logo' => 'nullable|string',  // Path to logo image
            'custom_sort' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $brand->update([
            'name' => $request->input('name', $brand->name),
            'logo' => $request->input('logo', $brand->logo),
            'custom_sort' => $request->input('custom_sort', $brand->custom_sort),
            'description' => $request->input('description', $brand->description),
        ]);

        unset($brand['id'], $brand['created_at'], $brand['updated_at']);

        return response()->json(['message' => 'Brand updated successfully!', 'data' => $brand], 200);
    }

    // Delete
    public function destroy($id)
    {
        $brand = BrandModel::find($id);
        if (!$brand) {
            return response()->json(['message' => 'Brand not found.'], 404);
        }

        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully!'], 200);
    }
}
