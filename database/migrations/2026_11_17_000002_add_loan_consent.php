<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('terms_agreed_at')->nullable();
            $table->timestamp('submission_confirmed_at')->nullable();
            $table->string('terms_version', 100)->nullable();
            $table->text('terms_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['terms_agreed_at', 'submission_confirmed_at', 'terms_version', 'terms_text']);
        });
    }
};

