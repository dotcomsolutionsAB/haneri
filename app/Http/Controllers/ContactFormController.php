<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactFormModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactFormController extends Controller
{
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:255',
                'mobile'   => 'nullable|string|max:20',
                'comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => 'Validation failed.',
                    'data'    => $validator->errors(),
                ], 422);
            }

            $contact = ContactFormModel::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'mobile'   => $request->mobile,
                'comments' => $request->comments,
            ]);

            return response()->json([
                'code'    => 201,
                'success' => true,
                'message' => 'Contact form submitted successfully.',
                'data'    => $contact,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong.',
                'data'    => [
                    'error' => $e->getMessage()
                ],
            ], 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $email  = trim((string) $request->input('email', ''));
            $mobile = trim((string) $request->input('mobile', ''));

            $query = ContactFormModel::query();

            if ($email !== '') {
                $query->where('email', 'like', '%' . $email . '%');
            }

            if ($mobile !== '') {
                $query->where('mobile', 'like', '%' . $mobile . '%');
            }

            $total = $query->count();

            $contacts = $query->orderBy('id', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Contact forms fetched successfully.',
                'data'    => [
                    'total'   => $total,
                    'limit'   => $limit,
                    'offset'  => $offset,
                    'records' => $contacts,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong.',
                'data'    => [
                    'error' => $e->getMessage()
                ],
            ], 500);
        }
    }
}