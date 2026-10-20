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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('base_unit')->nullable();
            $table->string('purchase_unit')->nullable();
            $table->string('conversion_factor')->nullable();
            $table->decimal('gross_margin',10,2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
