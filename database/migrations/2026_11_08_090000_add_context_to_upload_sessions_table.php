<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The context an upload was started in (UploadContext), kept
        // until the bytes land so the handler that owns it can choose the
        // disk and hear about the file. Null for an ordinary upload.
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->string('context_key', 64)->nullable();
            $table->string('context_id', 191)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropColumn(['context_key', 'context_id']);
        });
    }
};
