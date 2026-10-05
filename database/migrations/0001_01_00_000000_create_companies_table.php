<?php

use App\Enums\CompanyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', array_column(CompanyStatus::cases(), 'value'))->default(CompanyStatus::Trial->value);
            $table->date('trial_ends_at');
            $table->decimal('markup_percent', 6, 2)->default(200);
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->default('#1F4E79');
            $table->string('currency_symbol')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
