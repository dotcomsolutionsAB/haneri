<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormSubmittedMail;
use App\Models\ContactFormModel;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

            $recipient = config('mail.contact_form_recipient', 'info@haneri.com');
            if (! empty($recipient)) {
                try {
                    Mail::to($recipient)->send(new ContactFormSubmittedMail($contact));
                    EmailLog::record($recipient, ContactFormSubmittedMail::class, 'sent', [
                        'subject' => 'New contact form submission • #' . $contact->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Contact form notification email failed: ' . $e->getMessage(), [
                        'contact_id' => $contact->id,
                    ]);
                    EmailLog::record($recipient, ContactFormSubmittedMail::class, 'failed', [
                        'subject'       => 'New contact form submission • #' . $contact->id,
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

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