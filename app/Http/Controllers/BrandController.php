<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // <-- add this at top
use Illuminate\Support\Str;
use App\Models\BrandModel;

class BrandController extends Controller
{
    //
    // Store
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'logo'         => 'nullable|file|image|mimes:jpg,jpeg,png,webp,gif,svg|max:5120',
            'custom_sort'  => 'nullable|integer',
            'description'  => 'nullable|string',
        ]);

        $logoPath = null;

        if ($request->hasFile('logo')) {
            // Store file and get relative path (e.g., upload/brands/filename.jpg)
            $path = $request->file('logo')->store('upload/brands', 'public');
            $logoPath = $path; // save only relative path in DB
        }

        $brand = BrandModel::create([
            'name'        => $validated['name'],
            'logo'        => $logoPath,
            'custom_sort' => $validated['custom_sort'] ?? 0,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Brand created successfully!',
            'data' => [
                'id'          => $brand->id,
                'name'        => $brand->name,
                'logo'        => $brand->logo 
                    ? asset('storage/' . $brand->logo) 
                    : null, // full URL for response
                'custom_sort' => $brand->custom_sort,
                'description' => $brand->description,
            ],
        ], 201);
    }

    // View All
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $query = BrandModel::select('id', 'name', 'logo', 'custom_sort', 'description');

        if ($request->has('name')) {
            $query->where('name', 'LIKE', '%' . $request->input('name') . '%');
        }

       // Get total record count before applying limit
        $totalRecords = $query->count();
        
        // Apply pagination
        $brands = $query->offset($offset)->limit($limit)->get();

        return $brands->isNotEmpty()
            ? response()->json(['message' => 'Brands fetched successfully!', 'data' => $brands, 'count' => count($brands), 'records' => $totalRecords], 200)
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
    // public function update(Request $request, $id)
    // {
    //     $brand = BrandModel::find($id);
    //     if (!$brand) {
    //         return response()->json(['message' => 'Brand not found.'], 404);
    //     }

    //     $request->validate([
    //         'name' => 'sometimes|string',
    //         'logo' => 'nullable|string',  // Path to logo image
    //         'custom_sort' => 'nullable|integer',
    //         'description' => 'nullable|string',
    //     ]);

    //     $brand->update([
    //         'name' => $request->input('name', $brand->name),
    //         'logo' => $request->input('logo', $brand->logo),
    //         'custom_sort' => $request->input('custom_sort', $brand->custom_sort),
    //         'description' => $request->input('description', $brand->description),
    //     ]);

    //     unset($brand['id'], $brand['created_at'], $brand['updated_at']);

    //     return response()->json(['message' => 'Brand updated successfully!', 'data' => $brand], 200);
    // }


public function update(Request $request, $id)
{
    $brand = BrandModel::find($id);
    if (!$brand) {
        return response()->json(['message' => 'Brand not found.'], 404);
    }

    // IMPORTANT: the client must send multipart/form-data when including a file
    $validated = $request->validate([
        'name'         => 'sometimes|string|max:255',
        'logo'         => 'sometimes|file|image|mimes:jpg,jpeg,png,webp,gif,svg|max:5120',
        'custom_sort'  => 'sometimes|integer',
        'description'  => 'sometimes|nullable|string',
    ]);

    DB::beginTransaction();
    try {
        // Scalar fields
        if ($request->has('name')) {
            $brand->name = $validated['name'];
        }
        if ($request->has('custom_sort')) {
            $brand->custom_sort = $validated['custom_sort'];
        }
        if ($request->has('description')) {
            $brand->description = $validated['description'];
        }

        // Logo replacement
        if ($request->hasFile('logo')) {
            // Delete old file if present (handles full URL or relative path)
            if (!empty($brand->logo)) {
                $relative = $brand->logo;

                // If DB stored a full URL, convert to relative by stripping domain + optional "storage/"
                if (Str::startsWith($relative, ['http://', 'https://'])) {
                    $path = parse_url($relative, PHP_URL_PATH) ?? '';
                    $relative = ltrim($path, '/');                 // e.g. storage/upload/brands/abc.jpg
                    $relative = ltrim(Str::after($relative, 'storage/'), '/'); // e.g. upload/brands/abc.jpg
                }

                // If DB mistakenly has "public/" prefix, drop it
                $relative = ltrim(Str::after($relative, 'public/'), '/');

                // Only delete files under our managed folder
                if ($relative && Str::startsWith($relative, 'upload/') && Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                }
            }

            // Store new file; DB keeps relative path like "upload/brands/xyz.jpg"
            $newPath = $request->file('logo')->store('upload/brands', 'public');
            $brand->logo = $newPath;
        }

        $brand->saveOrFail();
        DB::commit();

        // Reload from DB to avoid stale in-memory values
        $brand->refresh();

        return response()->json([
            'message' => 'Brand updated successfully!',
            'data' => [
                'id'          => $brand->id,
                'name'        => $brand->name,
                'logo'        => $brand->logo ? asset('storage/' . $brand->logo) : null, // full URL in response
                'custom_sort' => $brand->custom_sort,
                'description' => $brand->description,
            ],
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Update failed.',
            'error'   => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Unexpected error',
        ], 500);
    }
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
