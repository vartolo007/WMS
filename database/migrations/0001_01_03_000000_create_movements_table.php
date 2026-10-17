<?php

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('type', array_column(MovementType::cases(), 'value'));
            $table->unsignedInteger('number');
            $table->string('document_no')->nullable();
            $table->enum('status', array_column(MovementStatus::cases(), 'value'))->default(MovementStatus::Confirmed->value);
            // Index on related_movement_id comes with the foreign key (SRS 5.9: returned quantities).
            $table->foreignId('related_movement_id')->nullable()->constrained('movements')->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // SRS 5.9
            $table->unique(['company_id', 'type', 'number']);
            $table->index(['company_id', 'warehouse_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
