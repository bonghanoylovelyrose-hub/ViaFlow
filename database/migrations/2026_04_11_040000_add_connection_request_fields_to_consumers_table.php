<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('property_type')->nullable()->after('membership_type');
            $table->string('ownership_status')->nullable()->after('property_type');
            $table->string('valid_id_presented')->nullable()->after('ownership_status');
            $table->boolean('resident_of_community')->default(false)->after('housing_rights_documented');
            $table->boolean('housing_loan_contract_submitted')->default(false)->after('association_registered');
            $table->boolean('valid_id_submitted')->default(false)->after('housing_loan_contract_submitted');
            $table->boolean('proof_of_residency_submitted')->default(false)->after('valid_id_submitted');
            $table->string('other_document_details')->nullable()->after('proof_of_residency_submitted');
            $table->boolean('acknowledgment_accepted')->default(false)->after('contact_number');
            $table->boolean('agreement_accepted')->default(false)->after('acknowledgment_accepted');
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn([
                'property_type',
                'ownership_status',
                'valid_id_presented',
                'resident_of_community',
                'housing_loan_contract_submitted',
                'valid_id_submitted',
                'proof_of_residency_submitted',
                'other_document_details',
                'acknowledgment_accepted',
                'agreement_accepted',
            ]);
        });
    }
};
