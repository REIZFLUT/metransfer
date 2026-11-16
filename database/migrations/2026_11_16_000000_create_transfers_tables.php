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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('password')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->boolean('notify_on_download')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_downloads');
        Schema::dropIfExists('transfer_recipients');
        Schema::dropIfExists('transfer_files');
        Schema::dropIfExists('transfers');
    }
};
