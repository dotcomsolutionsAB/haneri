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

    // Validate (allow partial updates)
    $validated = $request->validate([
        'name'         => 'sometimes|string|max:255',
        'logo'         => 'sometimes|file|image|mimes:jpg,jpeg,png,webp,gif,svg|max:5120',
        'custom_sort'  => 'sometimes|integer',
        'description'  => 'sometimes|nullable|string',
    ]);

    DB::beginTransaction();
    try {
        // Update scalar fields if present
        if ($request->has('name')) {
            $brand->name = $validated['name'];
        }
        if ($request->has('custom_sort')) {
            $brand->custom_sort = $validated['custom_sort'];
        }
        if ($request->has('description')) {
            $brand->description = $validated['description'];
        }

        // If a new logo is uploaded: delete old file by filename and then store new
        if ($request->hasFile('logo')) {
            // Delete old by scanning directory for the filename
            $this->deleteOldBrandLogo($brand->logo);

            // Store new file (relative path only)
            $newPath = $request->file('logo')->store('upload/brands', 'public'); // e.g. upload/brands/xyz.jpg
            $brand->logo = $newPath;
        }

        $brand->save();
        DB::commit();

        // Build response with full URL for logo
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

/**
 * Delete old brand logo by extracting filename and scanning the brands directory.
 * Accepts either a relative path (upload/brands/abc.jpg) or a full URL.
 */
private function deleteOldBrandLogo(?string $logoPath): bool
{
    try {
        if (empty($logoPath)) {
            return true; // nothing to delete
        }

        // Normalize to just the filename (abc.jpg)
        $filename = $logoPath;

        // If full URL, get the path part first
        if (Str::startsWith($filename, ['http://', 'https://'])) {
            $path = parse_url($filename, PHP_URL_PATH) ?? '';
            $filename = ltrim($path, '/'); // e.g. storage/upload/brands/abc.jpg
            // Strip optional "storage/" and/or "public/"
            $filename = ltrim(Str::after($filename, 'storage/'), '/'); // -> upload/brands/abc.jpg
        }

        // Now reduce to pure filename
        $filename = basename($filename); // -> abc.jpg

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return true; // invalid filename; treat as deleted
        }

        // Target directory
        $dir = 'upload/brands';

        // 1) Quick path: try exact path "upload/brands/filename"
        $directPath = $dir . '/' . $filename;
        if (Storage::disk('public')->exists($directPath)) {
            Storage::disk('public')->delete($directPath);
            return true;
        }

        // 2) Scan directory and match by filename (in case of subfolders or odd paths)
        $files = Storage::disk('public')->files($dir);
        foreach ($files as $file) {
            if (basename($file) === $filename) {
                Storage::disk('public')->delete($file);
                return true;
            }
        }

        // Not found is also "ok" per your spec
        return true;
    } catch (\Throwable $e) {
        // You can log this if needed
        // \Log::warning('deleteOldBrandLogo failed', ['error' => $e->getMessage(), 'logoPath' => $logoPath]);
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
