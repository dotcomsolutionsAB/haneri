<?php

namespace App\Http\Controllers;
use League\Csv\Reader;
use League\Csv\Statement;
use Illuminate\Http\Request;
use App\Models\ProductModel;
use App\Models\ProductFeatureModel;
use App\Models\ProductVariantModel;
use App\Models\BrandModel;
use App\Models\CategoryModel;
use App\Models\UploadModel;
use App\Models\UsersDiscountModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Auth;

class ProductController extends Controller
{
    //
    // Create
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'brand_id' => 'required|integer|exists:t_brands,id',
            'category_id' => 'required|integer|exists:t_categories,id',
            'slug' => 'required|string|unique:t_products,slug',
            'description' => 'required|string',
            'is_active' => 'required|boolean',
            // Validate product features
            'features' => 'nullable|array',
            'features.*.feature_name' => 'required_with:features|string',
            'features.*.is_filterable' => 'nullable|boolean',
            // Validate product variants
            'variants' => 'nullable|array',
            'variants.*.photo_id' => 'nullable|integer|exists:t_uploads,id',  // Optional photo
            'variants.*.min_qty' => 'nullable|integer|min:1',
            'variants.*.is_cod' => 'nullable|boolean',  // Optional COD flag
            'variants.*.weight' => 'nullable|numeric', // Optional weight
            'variants.*.description' => 'nullable|string', // Optional description
            'variants.*.variant_type' => 'required_with:variants|string',
            'variants.*.variant_value' => 'required_with:variants|string',
            'variants.*.regular_price' => 'required_with:variants|numeric',
            // 'variants.*.selling_price' => 'required_with:variants|numeric',
            // 'variants.*.sales_price_vendor' => 'required_with:variants|numeric',
            'variants.*.customer_discount' => 'nullable|numeric',
            'variants.*.dealer_discount' => 'nullable|numeric',
            'variants.*.architect_discount' => 'nullable|numeric',
            'variants.*.hsn' => 'required_with:variants|string',
            'variants.*.regular_tax' => 'required_with:variants|numeric',
            'variants.*.selling_tax' => 'required_with:variants|numeric',
            'variants.*.video_url' => 'nullable|string',
            'variants.*.product_pdf' => 'nullable|string',
        ]);

        $product = ProductModel::create([
            'name' => $request->input('name'),
            'brand_id' => $request->input('brand_id'),
            'category_id' => $request->input('category_id'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
            'is_active' => $request->input('is_active'),
        ]);

        // Add product features
        if ($request->has('features') && is_array($request->input('features'))) {
            foreach ($request->input('features') as $feature) {
                ProductFeatureModel::create([
                    'product_id' => $product->id,
                    'feature_name' => $feature['feature_name'],
                    'feature_value' => $feature['feature_value'],
                    'is_filterable' => $feature['is_filterable'] ?? false,
                ]);
            }
        }

        // Add product variants
        if ($request->has('variants') && is_array($request->input('variants'))) {
            foreach ($request->input('variants') as $variant) {
                ProductVariantModel::create([
                    'product_id' => $product->id,
                    'photo_id' => $variant['photo_id'] ?? null,
                    'min_qty' => $variant['min_qty'] ?? 1,
                    'is_cod' => $variant['is_cod'] ?? true,  // Default true if not provided
                    'weight' => $variant['weight'] ?? null,
                    'description' => $variant['description'] ?? null,
                    'variant_type' => $variant['variant_type'],
                    'variant_value' => $variant['variant_value'],
                    'regular_price' => $variant['regular_price'],
                    'customer_discount' => $variant['customer_discount'],
                    'dealer_discount' => $variant['dealer_discount'],
                    'architect_discount' => $variant['architect_discount'],
                    'hsn' => $variant['hsn'],
                    'regular_tax' => $variant['regular_tax'],
                    'selling_tax' => $variant['selling_tax'],
                    'video_url' => $variant['video_url'],
                    'product_pdf' => $variant['product_pdf'],
                ]);
            }
        }

        unset($product['id'], $product['created_at'], $product['updated_at']);

        return response()->json(['message' => 'Product created successfully!', 'data' => $product], 201);
    }

    // View All
    public function index(Request $request, $id = null)
    {
        try {

            $token = $request->bearerToken();
            $user = null;

            if ($token) {
                $user = Auth::guard('sanctum')->user();
            }

            if ($user) {
                $userId = $user->id;
                $userRole = $user->role;
            } else {
                $userId = 0;
                $userRole = "customer";
            }

            /* ----------  SINGLE PRODUCT  ---------- */
            if ($id) {
                $product = ProductModel::with([
                    'brand:id,name',
                    'category:id,name',
                    'features:id,product_id,feature_name,feature_value,is_filterable',
                    'variants:id,product_id,photo_id,variant_type,min_qty,is_cod,weight,description,variant_value,discount_price,regular_price,hsn,regular_tax,selling_tax,video_url,product_pdf,customer_discount,dealer_discount,architect_discount'
                ])->findOrFail($id);

                // $user = auth()->user();

                /* --- main images --- */
                $uploadIds = $product->image ? explode(',', $product->image) : [];
                $uploads   = UploadModel::whereIn('id', $uploadIds)->pluck('file_path', 'id');
                $product->image = array_map(fn($uid) => $uploads[$uid] ?? null, $uploadIds);

                /* --- variants: compute selling_price & file_urls --- */
                $product->variants = $product->variants->map(function ($variant) use ($userId, $userRole) {

                    /* 1. discount */
                    $discount = 0;
                    // if ($user) {
                        $userDiscount = UsersDiscountModel::where('user_id', $userId)
                            ->where('product_variant_id', $variant->id)
                            ->first();
                        $discount = $userDiscount?->discount
                            ?? match ($userRole) {
                                'customer'  => $variant->customer_discount,
                                'dealer'    => $variant->dealer_discount,
                                'architect' => $variant->architect_discount,
                                default     => 0,
                            };
                    // }

                    /* 2. selling price */
                    $regularPrice = $variant->regular_price;
                    $data = $variant->toArray();
                    $data['selling_price'] = round($regularPrice - ($regularPrice * ($discount / 100)), 0);

                    /* 3. images */
                    $fileUrls = [];
                    if (!empty($data['photo_id'])) {
                        $ids = array_filter(explode(',', $data['photo_id']));
                        $rows = UploadModel::whereIn('id', $ids)->get();
                        $fileUrls = $rows
                            ->map(fn($u) => Storage::disk('public')->url($u->file_path))
                            ->filter()
                            ->values()
                            ->all();
                    }
                    unset($data['photo_id'], $data['customer_discount'], $data['dealer_discount'], $data['architect_discount']);
                    $data['file_urls'] = $fileUrls;

                    return $data;
                });

                $response = [
                    'brand'    => $product->brand?->name,
                    'category' => $product->category?->name,
                    'features' => $product->features,
                    'variants' => $product->variants,
                ] + $product->toArray();

                return response()->json([
                    'success' => true,
                    'message' => 'Product details fetched successfully!',
                    'data'    => collect($response)
                        ->except(['id', 'brand_id', 'category_id', 'created_at', 'updated_at']),
                ], 200);
            }

            /* ---------- MULTIPLE PRODUCTS ---------- */
            $searchProduct  = $request->input('search_product');
            $searchBrand    = $request->input('search_brand');
            $searchCategory = $request->input('search_category');
            $isActive       = $request->input('is_active');
            $limit          = $request->input('limit', 10);
            $offset         = $request->input('offset', 0);
            $variantType    = $request->input('variant_type');

            $query = ProductModel::with([
                'brand:id,name',
                'category:id,name',
                'features:id,product_id,feature_name,feature_value,is_filterable',
                'variants:id,product_id,photo_id,variant_type,min_qty,is_cod,weight,description,variant_value,discount_price,regular_price,hsn,regular_tax,selling_tax,video_url,product_pdf,customer_discount,dealer_discount,architect_discount'
            ]);

            /* --- filters --- */
            if ($searchProduct) {
                $names = explode(',', $searchProduct);
                $query->where(fn($q) => collect($names)->each(fn($n) => $q->orWhere('name', 'LIKE', '%' . trim($n) . '%')));
            }
            if ($searchBrand) {
                $brands = explode(',', $searchBrand);
                $query->whereHas('brand', fn($q) => collect($brands)->each(fn($b) => $q->orWhere('name', 'LIKE', '%' . trim($b) . '%')));
            }
            if ($searchCategory) {
                $cats = array_filter(array_map('trim', explode(',', $searchCategory)));
                $query->whereHas('category', fn($q) => $q->where(function ($q2) use ($cats) {
                    foreach ($cats as $c) $q2->orWhere('name', 'LIKE', "%{$c}%");
                }));
            }
            if (!is_null($isActive)) $query->where('is_active', $isActive);
            if ($variantType) $query->whereHas('variants', fn($q) => $q->where('variant_type', $variantType));

            $totalRecords = $query->count();
            $products     = $query->offset($offset)->limit($limit)->get();

            /* --- image helper --- */
            $allImageIds = $products->flatMap(fn($p) => explode(',', $p->image ?? ''))->unique()->filter();
            $uploads     = UploadModel::whereIn('id', $allImageIds)->pluck('file_path', 'id');

            /* --- transform products --- */
            $products = $products->map(function ($prod) use ($uploads, $userId, $userRole) {
                $image = array_map(fn($uid) => $uploads[$uid] ?? null, explode(',', $prod->image ?? ''));
                // $variants = $prod->variants->map(function ($variant) {
                $variants = $prod->variants->map(function ($variant) use ($userId, $userRole) {
                    //$user = auth()->user();
                    $discount = 0;
                    // if ($user) {
                        $discount = UsersDiscountModel::where('user_id', $userId)
                            ->where('product_variant_id', $variant->id)
                            ->value('discount')
                            ?? match ($userRole) {
                                'customer' => $variant->customer_discount,
                                'dealer' => $variant->dealer_discount,
                                'architect' => $variant->architect_discount,
                                default => 0,
                            };
                    // }
                    // Calculate selling price
                    $regularPrice = $variant->regular_price;
                    $data = $variant->toArray();
                    $data['selling_price'] = number_format($regularPrice - ($regularPrice * ($discount / 100)), 0);
                    // Process images
                    $fileUrls = [];
                    if (!empty($data['photo_id'])) {
                        $ids = array_filter(explode(',', $data['photo_id']));
                        $rows = UploadModel::whereIn('id', $ids)->get();
                        $fileUrls = $rows
                            ->map(fn($u) => Storage::disk('public')->url($u->file_path))
                            ->filter()
                            ->values()
                            ->all();
                    }
                    unset($data['photo_id'], $data['customer_discount'], $data['dealer_discount'], $data['architect_discount']);
                    $data['file_urls'] = $fileUrls;
                    return $data;
                });
                $brand = $prod->brand?->name;
                $category = $prod->category?->name;
                $features = $prod->features instanceof \Illuminate\Support\Collection
                    ? $prod->features->toArray()
                    : $prod->features;

                return [
                    'id' => $prod->id,
                    'slug' => $prod->slug,
                    'name' => $prod->name,
                    'description' => $prod->description,
                    'type' => $prod->type,
                    'is_active' => $prod->is_active,
                    'image' => $image,
                    'variants' => $variants,
                    'brand' => $brand,
                    'category' => $category,
                    'features' => $features,
                ];
            });

            return response()->json([
                'success'       => true,
                'message'       => 'Products fetched successfully!',
                'data'          => $products,
                'total_records' => $totalRecords,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    
    // View Single
    public function show($slug)
    {
        $product = ProductModel::with(['photo', 'variants', 'features', 'brand', 'category'])
        ->select('id', 'name', 'brand_id', 'category_id', 'slug', 'description', 'is_active')
        ->where('slug', $slug)
        ->first();

        if ($product) 
        {
            $response = [
                'name' => $product->name,
                'brand' => $product->brand ? $product->brand->makeHidden(['id', 'created_at', 'updated_at']) : null,
                'category' => $product->category ? $product->category->makeHidden(['id', 'created_at', 'updated_at']) : null,
                'photo' => $product->photo ? $product->photo->makeHidden(['id', 'created_at', 'updated_at']) : null,
                'price' => $product->price,
                'discount_price' => $product->discount_price,
                'slug' => $product->slug,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'variants' => $product->variants ? $product->variants->makeHidden(['id', 'created_at', 'updated_at']) : null,
                'features' => $product->features ? $product->features->makeHidden(['id', 'created_at', 'updated_at']) : null,
            ];

            return response()->json(['message' => 'Product fetched successfully!', 'data' => $response], 200);
        } else {
                return response()->json(['message' => 'Product not found.'], 200);
            }
    }

    // Update
    public function update(Request $request, $id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'brand_id' => 'sometimes|integer|exists:t_brands,id',
            'category_id' => 'sometimes|integer|exists:t_categories,id',
            'slug' => 'sometimes|string|unique:t_products,slug,' . $id,
            'description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            // Validate product features
            'features' => 'nullable|array',
            'features.*.feature_name' => 'required_with:features|string',
            'features.*.feature_value' => 'required_with:features|string',
            'features.*.is_filterable' => 'nullable|boolean',
            // Validate product variants
            'variants' => 'nullable|array',
            'variants.*.photo_id' => 'nullable|integer|exists:t_uploads,id',
            'variants.*.min_qty' => 'sometimes|integer|min:1',
            'variants.*.is_cod' => 'sometimes|boolean',
            'variants.*.weight' => 'nullable|numeric',
            'variants.*.description' => 'sometimes|string',
            'variants.*.variant_type' => 'required_with:variants|string',
            'variants.*.variant_value' => 'required_with:variants|string',
            'variants.*.discount_price' => 'nullable|numeric',
            'variants.*.regular_price' => 'sometimes|numeric',
            // 'variants.*.selling_price' => 'sometimes|numeric',
            // 'variants.*.sales_price_vendor' => 'required_with:variants|numeric',
            'variants.*.customer_discount' => 'nullable|numeric',
            'variants.*.dealer_discount' => 'nullable|numeric',
            'variants.*.architect_discount' => 'nullable|numeric',
            'variants.*.hsn' => 'sometimes|string',
            'variants.*.regular_tax' => 'sometimes|numeric',
            'variants.*.selling_price' => 'required_with:variants|numeric',
            'variants.*.video_url' => 'sometimes|string',
            'variants.*.product_pdf' => 'sometimes|string',
        ]);

        // Update the product
        $product->update([
            'name' => $request->input('name', $product->name),
            'brand_id' => $request->input('brand_id', $product->brand_id),
            'category_id' => $request->input('category_id', $product->category_id),
            'photo_id' => $request->input('photo_id', $product->photo_id),
            'price' => $request->input('price', $product->price),
            'discount_price' => $request->input('discount_price', $product->discount_price),
            'hsn' => $request->input('hsn', $product->hsn),
            'tax' => $request->input('tax', $product->tax),
            'min_qty' => $request->input('min_qty', $product->min_qty),
            'is_cod' => $request->input('is_cod', $product->is_cod),
            'weight' => $request->input('weight', $product->weight),
            'slug' => $request->input('slug', $product->slug),
            'description' => $request->input('description', $product->description),
            'is_active' => $request->input('is_active', $product->is_active),
        ]);

        // Update product features
        if ($request->has('data.features') && is_array($request->input('data.features'))) {
            // Remove existing features if needed (optional)
            ProductFeatureModel::where('product_id', $product->id)->delete();

            // Add new features
            foreach ($request->input('data.features') as $feature) {
                ProductFeatureModel::create([
                    'product_id' => $product->id,
                    'feature_name' => $feature['feature_name'],
                    'is_filterable' => $feature['is_filterable'] ?? false,
                ]);
            }
        }

        // Update product variants
        if ($request->has('data.variants') && is_array($request->input('data.variants'))) {
            // Remove existing variants if needed (optional)
            ProductVariantModel::where('product_id', $product->id)->delete();

            // Add new variants
            foreach ($request->input('data.variants') as $variant) {
                ProductVariantModel::create([
                    'product_id' => $product->id,
                    'photo_id' => $variant['photo_id'] ?? null,
                    'min_qty' => $variant['min_qty'] ?? 1,
                    'is_cod' => $variant['is_cod'] ?? true,  // Default true if not provided
                    'weight' => $variant['weight'] ?? null,
                    'description' => $variant['description'] ?? null,
                    'variant_type' => $variant['variant_type'],
                    'variant_value' => $variant['variant_value'],
                    'regular_price' => $variant['regular_price'],
                    'customer_discount' => $variant['customer_discount'],
                    'dealer_discount' => $variant['dealer_discount'],
                    'architect_discount' => $variant['architect_discount'],
                    'hsn' => $variant['hsn'],
                    'regular_tax' => $variant['regular_tax'],
                    'selling_tax' => $variant['selling_tax'],
                    'video_url' => $variant['video_url'] ?? null,
                    'product_pdf' => $variant['product_pdf'] ?? null,
                ]);
            }
        }
        
        unset($product['id'], $product['created_at'], $product['updated_at']);

        return response()->json(['message' => 'Product updated successfully!', 'data' => $product], 200);
    }


    // Delete
    public function destroy($id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully!'], 200);
    }

    // import csv
    public function importProductsFromCsv()
    {
        try {
            // URL of the CSV file from Google Sheets
            $get_product_csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSCT6XqtlJtx8Wcktt3fUH_0GUyvIy1o_zaIahnW56-V-Pbc_ms5CPQcFu8ToL7n6PfNTa3CGZ-IhLC/pub?gid=0&single=true&output=csv';

            // Clear old data before import
            ProductModel::truncate();
            BrandModel::truncate();
            CategoryModel::truncate();
            ProductFeatureModel::truncate();
            ProductVariantModel::truncate();

            // Fetch the CSV content using file_get_contents
            $csvContent_product = file_get_contents($get_product_csv_url);

            // Parse the CSV content using League\Csv\Reader
            $csv_product = Reader::createFromString($csvContent_product);
            $csv_product->setHeaderOffset(0); // Set the header offset

            // Extract records from the CSV
            $records_csv = (new Statement())->process($csv_product);

            // Get the headers from the CSV
            $headers = $csv_product->getHeader();

            // Dynamically detect variant columns (prefixed with "v_")
            $variantColumns = array_filter($headers, fn($header) => str_starts_with($header, 'v_'));

            // Dynamically detect feature columns (prefixed with "Feature")
            $featureColumns = array_filter($headers, fn($header) => str_starts_with($header, 'Feature'));

            // Initialize record count and error array
            $recordCount = 0;
            $skippedCount = 0;
            $errors = [];

            // Loop through each product row in the CSV
            foreach ($records_csv as $row) {
                try {
                    // Check if the product already exists
                    $existingProduct = ProductModel::where('slug', Str::slug($row['Product Name']))->first();

                    if ($existingProduct) {
                        $skippedCount++;
                        Log::info("Skipped duplicate product: {$row['Product Name']}");
                        continue; // Skip this product if it already exists
                    }

                    // Check if Brand exists, otherwise create a new Brand
                    $brand = BrandModel::firstOrCreate([
                        'name' => $row['Brand'], // Assuming 'Brand' column in the CSV has the brand name
                    ]);

                    // Check if Category exists, otherwise create a new Category
                    $category = CategoryModel::firstOrCreate([
                        'name' => $row['Category'], // Assuming 'Category' column in the CSV has the category name
                    ]);

                    // Insert the product
                    $product = ProductModel::create([
                        'name' => $row['Product Name'], // Product Name
                        'description' => $row['Description'], // Product Description
                        'brand_id' => $brand->id, // Link the product to the brand
                        'category_id' => $category->id, // Link the product to the category
                        'slug' => Str::slug($row['Product Name']) . '-' . time(), // Generate SEO-friendly slug
                        'is_active' => true, // Set product status (this can be changed based on CSV data)
                    ]);

                    // Handle features dynamically
                    foreach ($featureColumns as $featureColumn) {
                        // Make sure we have a value for this feature in the row
                        if (!empty($row[$featureColumn])) {
                            ProductFeatureModel::create([
                                'product_id' => $product->id,
                                'feature_name' => $featureColumn, // Use the column name as feature name
                                'feature_value' => $row[$featureColumn], // Feature value
                                'is_filterable' => false, // Set based on your logic
                            ]);
                        }
                    }

                    // Handle variants dynamically
                    foreach ($variantColumns as $variantColumn) {
                        if (!empty($row[$variantColumn])) {
                            // Extract variant name (without "v_" prefix)
                            $variantType = str_replace('v_', '', $variantColumn);

                            $sellingPrice = !empty($row['Sale Price']) ? $row['Sale Price'] : 0000;

                            ProductVariantModel::create([
                                'product_id' => $product->id,
                                'variant_type' => $variantType, // Variant type e.g., 'Speed', 'Power'
                                'variant_value' => $row[$variantColumn], // Value from CSV
                                'regular_price' => $row['Regular Price'], // Map prices as required
                                'hsn' => '1234', // Set your HSN code if available
                                'regular_tax' => $row['Tax Rate'], // Use tax rate from the row
                                'selling_tax' => $row['Tax Rate'],
                            ]);
                        }
                    }

                    // Increment record count
                    $recordCount++;
                } catch (\Exception $e) {
                    // Log and track errors for each product import
                    $errors[] = "Error importing product '{$row['Product Name']}': " . $e->getMessage();
                }
            }

            // Log total records processed, skipped, and any errors
            Log::info("Total products imported: {$recordCount}, skipped: {$skippedCount}");
            if (!empty($errors)) {
                Log::error("Errors encountered during import: " . implode(', ', $errors));
            }

            return response()->json([
                'message' => 'Products and related data imported successfully.',
                'total_records' => $recordCount,
                'skipped_records' => $skippedCount,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            // General error handling for the entire process
            Log::error('Import failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Product import failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // unique variant type
    public function unique_type()
    {
        try {
            // Fetch unique variant types using distinct() and pluck() for a clean array.
            $uniqueTypes = ProductVariantModel::distinct()->pluck('variant_type');
            
            return response()->json([
                'success' => true,
                'data' => $uniqueTypes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // map product variant image
    public function mapVariantImagesToPhotoId()
    {
        try {
            $variants = ProductVariantModel::all();
            $baseFolder = 'upload/products';
            $summary = [];

            foreach ($variants as $variant) {
                try {
                    $folder = $baseFolder . '/' . $variant->variant_value;
                    // dd(Storage::disk('public')->path($folder));  // This will show the correct absolute path

                    if (!Storage::disk('public')->exists($folder)) {
                        $summary[] = [
                            'variant_id' => $variant->id,
                            'variant_value' => $variant->variant_value,
                            'status' => 'folder_not_found',
                            'photo_id' => null,
                            'files' => [],
                        ];
                        continue;
                    }

                    $files = Storage::disk('public')->files($folder);
                    $uploadIds = [];
                    foreach ($files as $filePath) {
                        try {
                            $fileName = basename($filePath);

                            // Check for existing upload (matching file_path)
                            $existingUpload = UploadModel::where('file_path', $filePath)->first();

                            if ($existingUpload) {
                                $uploadIds[] = $existingUpload->id;
                            } else {
                                $upload = UploadModel::create([
                                    'file_path' => $filePath,
                                    'type' => pathinfo($fileName, PATHINFO_EXTENSION),
                                    'size' => Storage::disk('public')->size($filePath),
                                    'alt_text' => $fileName,
                                ]);
                                $uploadIds[] = $upload->id;
                            }
                        } catch (\Exception $e) {
                            Log::error("File processing failed for variant_id {$variant->id} file {$filePath}: {$e->getMessage()}");
                            continue;
                        }
                    }

                    // Update the photo_id field (comma-separated IDs or null if no files)
                    $variant->photo_id = $uploadIds ? implode(',', $uploadIds) : null;
                    $variant->save();


                    $summary[] = [
                        'variant_id' => $variant->id,
                        'variant_value' => $variant->variant_value,
                        'status' => $uploadIds ? 'updated' : 'no_files',
                        'photo_id' => $variant->photo_id,
                        'files' => $uploadIds,
                    ];
                } catch (\Exception $e) {
                    Log::error("Variant processing failed for variant_id {$variant->id}: {$e->getMessage()}");
                    $summary[] = [
                        'variant_id' => $variant->id,
                        'variant_value' => $variant->variant_value,
                        'status' => 'error',
                        'photo_id' => null,
                        'error' => $e->getMessage(),
                    ];
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Photo IDs mapped to variants based on folder images.',
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error("Error in mapVariantImagesToPhotoId API: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error mapping images to product variants.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
