<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // <-- add this at top
use App\Models\BrandModel;

class BrandController extends Controller
{
    //
    // Store
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string',
    //         'logo' => 'nullable|integer', // Path to logo image
    //         'custom_sort' => 'nullable|integer',
    //         'description' => 'nullable|string',
    //     ]);

    //     $brand = BrandModel::create([
    //         'name' => $request->input('name'),
    //         'logo' => $request->input('logo', null),
    //         'custom_sort' => $request->input('custom_sort', 0),
    //         'description' => $request->input('description', null),
    //     ]);

    //     unset($brand['id'], $brand['created_at'], $brand['updated_at']);

    //     return response()->json(['message' => 'Brand created successfully!', 'data' => $brand], 201);
    // }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'logo'         => 'nullable|file|image|mimes:jpg,jpeg,png,webp,gif,svg|max:5120',
            'custom_sort'  => 'nullable|integer',
            'description'  => 'nullable|string',
        ]);

        $logoUrl = null;

        if ($request->hasFile('logo')) {
            // Upload into storage/app/public/upload/brands/
            $path = $request->file('logo')->store('upload/brands', 'public');

            // Get public URL (requires `php artisan storage:link`)
            $logoUrl = asset(Storage::url($path));  // full URL, e.g. https://yourdomain.com/storage/upload/brands/xyz.jpg
        }

        $brand = BrandModel::create([
            'name'        => $validated['name'],
            'logo'        => $logoUrl,
            'custom_sort' => $validated['custom_sort'] ?? 0,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Brand created successfully!',
            'data' => [
                'id'          => $brand->id,
                'name'        => $brand->name,
                'logo'        => $brand->logo, // now full URL
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

    // Accept both multipart (file upload) and JSON (string URL)
    $rules = [
        'name'         => 'sometimes|string|max:255',
        'custom_sort'  => 'nullable|integer',
        'description'  => 'nullable|string',
    ];
    if ($request->hasFile('logo')) {
        $rules['logo'] = 'file|image|mimes:jpg,jpeg,png,webp,gif,svg|max:5120';
    } elseif ($request->filled('logo')) {
        $rules['logo'] = 'string|max:2048';
    }
    $validated = $request->validate($rules);

    // Prepare payload
    $data = [
        'name'        => $validated['name']        ?? $brand->name,
        'custom_sort' => $validated['custom_sort'] ?? $brand->custom_sort,
        'description' => $validated['description'] ?? $brand->description,
        'logo'        => $brand->logo, // will be replaced below if needed
    ];

    // If a new logo FILE was uploaded: delete old file from /public disk, then store new file
    if ($request->hasFile('logo')) {
        if (!empty($brand->logo)) {
            if ($relative = $this->extractPublicRelativePath($brand->logo)) {
                if (Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                }
            }
        }

        // Save new file to storage/app/public/upload/brands
        $path = $request->file('logo')->store('upload/brands', 'public');
        $data['logo'] = asset(Storage::url($path)); // full URL
    }
    // Else if a string/URL is provided for logo, just set it (cannot safely delete remote/external)
    elseif ($request->filled('logo')) {
        $data['logo'] = $validated['logo'];
    }

    $brand->update($data);

    return response()->json([
        'message' => 'Brand updated successfully!',
        'data' => [
            'id'          => $brand->id,
            'name'        => $brand->name,
            'logo'        => $brand->logo,
            'custom_sort' => (int) $brand->custom_sort,
            'description' => $brand->description,
        ],
    ], 200);
}

/**
 * Convert a saved logo (full URL or path) into a disk-relative path
 * suitable for Storage::disk('public')->delete().
 *
 * Examples:
 *  - http://domain.com/storage/upload/brands/foo.jpg -> upload/brands/foo.jpg
 *  - /storage/upload/brands/foo.jpg                  -> upload/brands/foo.jpg
 *  - storage/upload/brands/foo.jpg                   -> upload/brands/foo.jpg
 *  - upload/brands/foo.jpg                           -> upload/brands/foo.jpg
 *  - /upload/brands/foo.jpg                          -> upload/brands/foo.jpg
 */
private function extractPublicRelativePath(string $logo): ?string
{
    // If it's a full URL, parse its path
    $path = parse_url($logo, PHP_URL_PATH) ?: $logo;

    // Normalize backslashes (just in case)
    $path = str_replace('\\', '/', $path);

    // If it starts with /storage/..., strip that prefix
    if (str_starts_with($path, '/storage/')) {
        $path = substr($path, strlen('/storage/')); // now like 'upload/brands/foo.jpg'
    }

    // Remove any leading slash
    $path = ltrim($path, '/');

    // We only delete files under the public disk root; guard common cases
    if (
        str_starts_with($path, 'upload/brands/') ||
        str_starts_with($path, 'brands/') ||           // legacy fallback
        str_starts_with($path, 'upload/')              // broader fallback
    ) {
        return $path;
    }

    // If none of the known prefixes matched, we can't safely map to public disk
    return null;
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
