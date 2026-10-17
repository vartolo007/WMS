<?php

use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('users')->restrictOnDelete();
            // Index on movement_id comes with the foreign key (SRS 5.9).
            $table->foreignId('movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('movement_item_id')->constrained('movement_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('type', array_column(MovementType::cases(), 'value'));
            $table->date('date');
            $table->decimal('qty_in', 12, 3)->default(0);
            $table->decimal('qty_out', 12, 3)->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('value', 12, 2);
            $table->decimal('net_value', 12, 2);
            $table->timestamps();

            // SRS 5.9
            $table->index(['company_id', 'warehouse_id', 'product_id', 'date']);
            $table->index(['company_id', 'date']);
            $table->index(['party_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
