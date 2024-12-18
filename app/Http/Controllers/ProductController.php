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

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            'variants.*.selling_price' => 'required_with:variants|numeric',
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
                    'selling_price' => $variant['selling_price'],
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
    public function index()
    {
        $products = ProductModel::with(['photo', 'variants', 'features', 'brand', 'category'])
        ->select('id', 'name', 'brand_id', 'category_id', 'slug', 'description', 'is_active')
        ->get()
        ->makeHidden(['id', 'created_at', 'updated_at']);

        $products = $products->map(function ($product) {
            return [
                'name' => $product->name,
                'brand' => $product->brand ? $product->brand->makeHidden(['id', 'created_at', 'updated_at']) : null,
                'category' => $product->category ? $product->category->makeHidden(['id', 'created_at', 'updated_at']) : null,
                'slug' => $product->slug,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'variants' => $product->variants ? $product->variants->makeHidden(['id', 'created_at', 'updated_at']) : null,
                'features' => $product->features ? $product->features->makeHidden(['id', 'created_at', 'updated_at']) : null,
            ];
        });

        return $products->isNotEmpty()
            ? response()->json(['message' => 'Fetch data successfully!', 'data' => $products, 'count' => count($products)], 200)
            : response()->json(['message' => 'Sorry, No data Available'], 400);
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
                return response()->json(['message' => 'Product not found.'], 404);
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
            'variants.*.selling_price' => 'sometimes|numeric',
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
                    'selling_price' => $variant['selling_price'],
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
                                'selling_price' => $sellingPrice,
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

}
