<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('unit');
            $table->string('location')->nullable();
            $table->decimal('custom_sale_price', 12, 2)->nullable();
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // SRS 5.9: the barcode is unique inside one company; several NULLs are allowed.
            $table->unique(['company_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
