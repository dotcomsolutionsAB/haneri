<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_email');
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('mailable_class'); // e.g. App\Mail\WelcomeUserMail
            $table->string('subject')->nullable();
            $table->string('status', 20); // 'sent', 'failed'
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_email', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
