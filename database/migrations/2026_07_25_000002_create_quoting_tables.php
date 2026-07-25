<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->string('customer_reference')->nullable();
            $table->enum('status', ['draft', 'issued', 'ordered'])->default('draft');
            $table->string('currency', 3)->default('AED');
            $table->decimal('material_total_aed', 12, 2)->default(0);
            $table->decimal('cutting_total_aed', 12, 2)->default(0);
            $table->decimal('subtotal_aed', 12, 2)->default(0);
            $table->decimal('vat_pct', 5, 2)->default(5);
            $table->decimal('vat_aed', 12, 2)->default(0);
            $table->decimal('total_aed', 12, 2)->default(0);
            $table->date('promised_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->enum('mode', ['fixed', 'optimized'])->default('optimized');
            $table->unsignedInteger('sheets_consumed')->default(0);
            $table->decimal('cut_metres', 10, 3)->default(0);
            $table->decimal('material_total_aed', 12, 2)->default(0);
            $table->decimal('cutting_total_aed', 12, 2)->default(0);
            $table->decimal('line_total_aed', 12, 2)->default(0);
            // Frozen at issue time: engine result plus every rate, price and
            // parameter used. An issued quote is rendered from this alone.
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::create('cut_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_line_id')->constrained()->cascadeOnDelete();
            $table->decimal('cut_metres', 10, 3);
            $table->date('scheduled_date')->index();
            $table->timestamps();
        });

        Schema::create('soft_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('qty_sheets');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soft_allocations');
        Schema::dropIfExists('cut_jobs');
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');
    }
};
