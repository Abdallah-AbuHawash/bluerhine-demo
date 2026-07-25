<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_submissions', function (Blueprint $table) {
            $table->id();
            $table->longText('raw_input');
            $table->enum('source_type', ['paste', 'file'])->default('paste');
            $table->string('file_name')->nullable();
            $table->json('parse_result')->nullable();
            $table->enum('status', ['parsed', 'reviewed', 'quoted'])->default('parsed');
            $table->decimal('confidence', 4, 3)->nullable();
            // Whether the canned fixture parse was used instead of the API.
            $table->boolean('offline_fallback')->default(false);
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_submissions');
    }
};
