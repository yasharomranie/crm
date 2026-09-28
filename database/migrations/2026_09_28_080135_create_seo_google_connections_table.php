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
        Schema::create('seo_google_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_account_email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->string('gsc_site_url')->nullable();
            $table->string('ga4_property_id')->nullable();
            $table->string('status')->default('not_connected');
            $table->dateTime('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_google_connections');
    }
};
