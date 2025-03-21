<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CategoryModel;

class CategoryController extends Controller
{
    //
    // Store
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|string',
            'photo' => 'nullable|string',
            'custom_sort' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $category = CategoryModel::create([
            'name' => $request->input('name'),
            'parent_id' => $request->input('parent_id', null),
            'photo' => $request->input('photo', null),
            'custom_sort' => $request->input('custom_sort', 0),
            'description' => $request->input('description', null),
        ]);

        unset($category['id'], $category['created_at'], $category['updated_at']);

        return response()->json(['message' => 'Category created successfully!', 'data' => $category], 201);
    }

    // View All
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $query = CategoryModel::select('id', 'name', 'parent_id', 'photo', 'custom_sort', 'description');

        if ($request->has('name')) {
            $query->where('name', 'LIKE', '%' . $request->input('name') . '%');
        }

        // Get total record count before applying limit
        $totalRecords = $query->count();

        // Apply pagination
        $categories = $query->offset($offset)->limit($limit)->get();

        return $categories->isNotEmpty()
            ? response()->json(['message' => 'Categories fetched successfully!', 'data' => $categories, 'count' => count($categories), 'records' => $totalRecords], 200)
            : response()->json(['message' => 'No categories available.'], 400);
    }

    // View Single
    public function show($id)
    {
        $category = CategoryModel::find($id);

        if ($category) {
            return response()->json(['message' => 'Category fetched successfully!', 'data' => $category->makeHidden(['id', 'created_at', 'updated_at'])], 200);
        } else {
            return response()->json(['message' => 'Category not found.'], 404);
        }
    }

    // Update
    public function update(Request $request, $id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'parent_id' => 'nullable|string',
            'photo' => 'nullable|string',
            'custom_sort' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $category->update([
            'name' => $request->input('name', $category->name),
            'parent_id' => $request->input('parent_id', $category->parent_id),
            'photo' => $request->input('photo', $category->photo),
            'custom_sort' => $request->input('custom_sort', $category->custom_sort),
            'description' => $request->input('description', $category->description),
        ]);

        unset($category['id'], $category['created_at'], $category['updated_at']);

        return response()->json(['message' => 'Category updated successfully!', 'data' => $category], 200);
    }

    // Delete
    public function destroy($id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully!'], 200);
    }
}
