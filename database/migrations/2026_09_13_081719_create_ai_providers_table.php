<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // OpenAI-compatible base URL (e.g. https://api.openai.com/v1 or
            // a proxy service's equivalent) - every provider this connects
            // to must speak the same /chat/completions + /models schema as
            // OpenAI's own API.
            $table->string('base_url');
            $table->text('api_key');
            $table->string('default_model');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
