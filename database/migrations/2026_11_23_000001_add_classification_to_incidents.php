<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('priority', 20)->default('Needs Review')->index();
            $table->string('automatic_priority', 20)->default('Needs Review');
            $table->string('classification_reason')->default('No automatic classification was recorded for this report. Review the incident details.');
            $table->foreignId('priority_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('priority_reviewed_at')->nullable();
            $table->text('priority_review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('priority_reviewed_by');
            $table->dropIndex(['priority']);
            $table->dropColumn(['priority', 'automatic_priority', 'classification_reason', 'priority_reviewed_at', 'priority_review_note']);
        });
    }
};
