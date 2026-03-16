<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $table = 'email_logs';

    protected $fillable = [
        'recipient_email',
        'recipient_user_id',
        'mailable_class',
        'subject',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * Record an email send attempt (success or failure).
     *
     * @param  string  $recipientEmail
     * @param  string  $mailableClass  Fully qualified class name, e.g. App\Mail\WelcomeUserMail
     * @param  string  $status  'sent' or 'failed'
     * @param  array{subject?: string, error_message?: string, recipient_user_id?: int}  $options
     */
    public static function record(
        string $recipientEmail,
        string $mailableClass,
        string $status,
        array $options = []
    ): self {
        return self::create([
            'recipient_email'     => $recipientEmail,
            'recipient_user_id'  => $options['recipient_user_id'] ?? null,
            'mailable_class'     => $mailableClass,
            'subject'            => $options['subject'] ?? null,
            'status'             => $status,
            'error_message'      => $options['error_message'] ?? null,
            'sent_at'            => $options['sent_at'] ?? now(),
        ]);
    }
}
