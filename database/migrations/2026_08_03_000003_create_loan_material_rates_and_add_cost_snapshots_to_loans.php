<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_material_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('water_meter_cost', 10, 2)->default(0);
            $table->decimal('hose_cost', 10, 2)->default(0);
            $table->decimal('other_materials_cost', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('configured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('material_meter_cost', 10, 2)->nullable()->after('purpose');
            $table->decimal('material_hose_cost', 10, 2)->nullable()->after('material_meter_cost');
            $table->decimal('material_other_cost', 10, 2)->nullable()->after('material_hose_cost');
            $table->decimal('installation_cost_total', 10, 2)->nullable()->after('material_other_cost');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'material_meter_cost',
                'material_hose_cost',
                'material_other_cost',
                'installation_cost_total',
            ]);
        });

        Schema::dropIfExists('loan_material_rates');
    }
};
