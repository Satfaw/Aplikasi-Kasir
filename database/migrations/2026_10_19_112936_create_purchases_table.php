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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('purchase_date')->nullable();
            $table->date('received_date')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate', 10, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('discount', 5, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('total_payment', 10, 2);
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->enum('status_payment', ['paid', 'unpaid']);
            $table->enum('payment_method', ['cash', 'credit', 'debit'])->default('cash');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
