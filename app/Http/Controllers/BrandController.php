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

    // Validate only if present (form-data)
    $validated = $request->validate([
        'name'         => 'sometimes|string|max:255',
        'logo'         => 'sometimes|file|image|mimes:jpg,jpeg,png,webp,gif,svg|max:5120',
        'custom_sort'  => 'sometimes|integer',
        'description'  => 'sometimes|nullable|string',
    ]);

    DB::beginTransaction();
    try {
        // If a key was sent at all (exists), update it — even if it's "0" or "".
        if ($request->exists('name')) {
            $brand->name = (string)($validated['name'] ?? $request->input('name', ''));
        }

        if ($request->exists('custom_sort')) {
            // integer rule accepts "2" from form-data; keep raw input fallback
            $brand->custom_sort = $validated['custom_sort'] ?? (int)$request->input('custom_sort');
        }

        if ($request->exists('description')) {
            // Treat empty string as null; remove `?: null` if you want to store ""
            $desc = $request->input('description');
            $brand->description = ($desc === '' ? null : $desc);
        }

        // IMPORTANT: works only when you POST with _method=PUT
        if ($request->hasFile('logo')) {
            $this->deleteOldBrandLogo($brand->logo);
            $newPath = $request->file('logo')->store('upload/brands', 'public'); // upload/brands/xyz.jpg
            $brand->logo = $newPath; // store relative path in DB
        }

        $brand->save();
        DB::commit();

        return response()->json([
            'message' => 'Brand updated successfully!',
            'data' => [
                'id'          => $brand->id,
                'name'        => $brand->name,
                'logo'        => $brand->logo ? asset('storage/' . ltrim($brand->logo, '/')) : null,
                'custom_sort' => $brand->custom_sort,
                'description' => $brand->description,
            ],
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Update failed.',
            'error'   => config('app.debug') ? $e->getMessage() : 'Unexpected error',
        ], 500);
    }
}

private function deleteOldBrandLogo(?string $logoPath): bool
{
    try {
        if (!$logoPath) return true;

        // Normalize URL/relative into storage-relative path
        $path = $logoPath;
        if (Str::startsWith($path, ['http://', 'https://'])) {
            $urlPath = parse_url($path, PHP_URL_PATH) ?? '';
            $urlPath = ltrim($urlPath, '/');                 // storage/upload/brands/abc.jpg
            $path    = Str::startsWith($urlPath, 'storage/')
                      ? Str::after($urlPath, 'storage/')     // upload/brands/abc.jpg
                      : $urlPath;
        }

        $path = ltrim($path, '/');
        $filename = basename($path);
        if (!$filename || $filename === '.' || $filename === '..') return true;

        $dir = 'upload/brands';
        $direct = $dir . '/' . $filename;

        if (Storage::disk('public')->exists($direct)) {
            Storage::disk('public')->delete($direct);
            return true;
        }

        foreach (Storage::disk('public')->files($dir) as $file) {
            if (basename($file) === $filename) {
                Storage::disk('public')->delete($file);
                return true;
            }
        }
        return true;
    } catch (\Throwable $e) {
        return false;
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
