<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductModel;
use App\Models\ProductFeatureModel;
use App\Models\ProductVariantModel;

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
            'photo_id' => 'nullable|integer|exists:t_uploads,id',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'hsn' => 'required|string',
            'tax' => 'required|numeric',
            'min_qty' => 'required|integer|min:1',
            'is_cod' => 'required|boolean',
            'weight' => 'nullable|numeric',
            'slug' => 'required|string|unique:t_products,slug',
            'description' => 'required|string',
            'is_active' => 'required|boolean',
            // Validate product features
            'features' => 'nullable|array',
            'features.*.feature_name' => 'required_with:features|string',
            'features.*.feature_value' => 'required_with:features|string',
            'features.*.is_filterable' => 'nullable|boolean',
            // Validate product variants
            'variants' => 'nullable|array',
            'variants.*.variant_type' => 'required_with:variants|string',
            'variants.*.variant_value' => 'required_with:variants|string',
            'variants.*.price' => 'required_with:variants|numeric',
        ]);

        $product = ProductModel::create([
            'name' => $request->input('name'),
            'brand_id' => $request->input('brand_id'),
            'category_id' => $request->input('category_id'),
            'photo_id' => $request->input('photo_id', null),
            'price' => $request->input('price'),
            'discount_price' => $request->input('discount_price', null),
            'hsn' => $request->input('hsn'),
            'tax' => $request->input('tax'),
            'min_qty' => $request->input('min_qty'),
            'is_cod' => $request->input('is_cod'),
            'weight' => $request->input('weight', null),
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
                    'variant_type' => $variant['variant_type'],
                    'variant_value' => $variant['variant_value'],
                    'price' => $variant['price'],
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
        ->select('id', 'name', 'brand_id', 'category_id', 'price', 'discount_price', 'slug', 'description', 'is_active')
        ->get()
        ->makeHidden(['id', 'created_at', 'updated_at']);

        $products = $products->map(function ($product) {
            return [
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
        });

        return $products->isNotEmpty()
            ? response()->json(['message' => 'Fetch data successfully!', 'data' => $products, 'count' => count($products)], 200)
            : response()->json(['message' => 'Sorry, No data Available'], 400);
    }

    // View Single
    public function show($slug)
    {
        $product = ProductModel::with(['photo', 'variants', 'features', 'brand', 'category'])
        ->select('id', 'name', 'brand_id', 'category_id', 'price', 'discount_price', 'slug', 'description', 'is_active')
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
            'photo_id' => 'nullable|integer|exists:t_uploads,id',
            'price' => 'sometimes|numeric',
            'discount_price' => 'nullable|numeric',
            'hsn' => 'sometimes|string',
            'tax' => 'sometimes|numeric',
            'min_qty' => 'sometimes|integer|min:1',
            'is_cod' => 'sometimes|boolean',
            'weight' => 'nullable|numeric',
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
            'variants.*.variant_type' => 'required_with:variants|string',
            'variants.*.variant_value' => 'required_with:variants|string',
            'variants.*.price' => 'required_with:variants|numeric',
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
                    'variant_type' => $variant['variant_type'],
                    'variant_value' => $variant['variant_value'],
                    'price' => $variant['price'],
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
}
