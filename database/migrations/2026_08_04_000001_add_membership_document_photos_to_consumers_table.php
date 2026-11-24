<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->string('membership_document_type')->nullable()->after('proof_of_residency_submitted');
            $table->json('membership_document_paths')->nullable()->after('membership_document_type');
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropColumn(['membership_document_type', 'membership_document_paths']);
        });
    }
};
