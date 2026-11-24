<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('valid_id_path')->nullable()->after('valid_id_presented');
            $table->string('proof_of_residency_path')->nullable()->after('proof_of_residency_submitted');
            $table->string('other_document_path')->nullable()->after('other_document_details');
            $table->text('rejection_reason')->nullable()->after('installation_date');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn(['valid_id_path', 'proof_of_residency_path', 'other_document_path', 'rejection_reason', 'rejected_at']);
        });
    }
};
