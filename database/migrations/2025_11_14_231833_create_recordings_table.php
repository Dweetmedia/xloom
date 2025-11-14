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
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('local_path')->nullable(); // Temporary local storage path
            $table->string('google_drive_file_id')->nullable();
            $table->string('google_drive_folder_id')->nullable();
            $table->string('google_drive_url')->nullable();
            $table->string('share_link')->nullable();
            $table->enum('status', ['uploading', 'converting', 'uploading_to_drive', 'completed', 'failed'])->default('uploading');
            $table->enum('visibility', ['private', 'anyone_with_link', 'public'])->default('private');
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->integer('duration')->nullable(); // in seconds
            $table->string('mime_type')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['user_id', 'status']);
            $table->index('google_drive_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
