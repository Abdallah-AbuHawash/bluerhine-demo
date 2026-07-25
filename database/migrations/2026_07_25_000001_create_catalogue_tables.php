<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            // Groups the material for cutting-rate lookup: acrylic_cast,
            // acrylic_mirror, polycarbonate, hdpe.
            $table->string('material_group')->index();
            $table->decimal('thickness_mm', 6, 2);
            $table->decimal('sheet_w_mm', 8, 2);
            $table->decimal('sheet_h_mm', 8, 2);
            $table->string('color_code')->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('selling_price_aed', 10, 2);
            $table->unsignedInteger('stock_qty')->default(0);
            $table->boolean('rotation_allowed')->default(true);
            $table->boolean('is_cut_eligible')->default(true);
            $table->timestamps();
        });

        Schema::create('cutting_rates', function (Blueprint $table) {
            $table->id();
            $table->string('material_group')->index();
            $table->decimal('thickness_mm', 6, 2);
            $table->decimal('rate', 8, 2);
            // The client has not confirmed the unit yet, so it is switchable
            // in admin rather than baked into the pricing code.
            $table->enum('rate_unit', ['per_cut_metre', 'per_piece', 'per_sheet'])
                ->default('per_cut_metre');
            $table->timestamps();

            $table->unique(['material_group', 'thickness_mm']);
        });

        // Singleton row (id = 1).
        Schema::create('cut_parameters', function (Blueprint $table) {
            $table->id();
            $table->decimal('kerf_mm', 6, 2)->default(4.4);
            $table->decimal('trim_mm', 6, 2)->default(10);
            $table->decimal('vat_pct', 5, 2)->default(5);
            $table->unsignedInteger('quote_validity_days')->default(7);
            $table->boolean('include_trim_in_cut_length')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_time_rules', function (Blueprint $table) {
            $table->id();
            // ISO weekday: 1 = Monday ... 7 = Sunday.
            $table->unsignedTinyInteger('weekday')->unique();
            $table->unsignedInteger('capacity_cut_metres')->default(400);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_time_rules');
        Schema::dropIfExists('cut_parameters');
        Schema::dropIfExists('cutting_rates');
        Schema::dropIfExists('materials');
    }
};
