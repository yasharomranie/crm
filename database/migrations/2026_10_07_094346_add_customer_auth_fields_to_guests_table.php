<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->string('google_id')->nullable()->unique()->after('password');
            $table->timestamp('mobile_verified_at')->nullable()->after('google_id');
            $table->rememberToken()->after('mobile_verified_at');
            $table->unique('mobile');
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->dropUnique(['email']);
            $table->dropColumn(['password', 'google_id', 'mobile_verified_at', 'remember_token']);
        });
    }
};
