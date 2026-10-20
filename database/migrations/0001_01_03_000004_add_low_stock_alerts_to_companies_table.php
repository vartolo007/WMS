<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lets an owner switch the daily low-stock e-mail off (company settings page). On by default.
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('low_stock_alerts')->default(true)->after('currency_symbol');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('low_stock_alerts');
        });
    }
};
