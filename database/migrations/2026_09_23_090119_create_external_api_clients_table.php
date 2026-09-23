<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A POS terminal or an online-sales integration - either way, an
     * external caller of routes/api.php's v1/external namespace. The
     * secret is stored hashed (api_secret_hash) exactly like a user
     * password: shown once at creation, never again (section 103).
     */
    public function up(): void
    {
        Schema::create('external_api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('company')->nullable();
            $table->string('terminal_id')->nullable();
            $table->string('api_key')->unique();
            $table->string('api_secret_hash');
            $table->json('allowed_operations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->dateTime('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // orders.external_api_client_id was created unconstrained (this
        // table didn't exist yet at that point) - wire the FK up now.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('external_api_client_id')->references('id')->on('external_api_clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['external_api_client_id']);
        });

        Schema::dropIfExists('external_api_clients');
    }
};
