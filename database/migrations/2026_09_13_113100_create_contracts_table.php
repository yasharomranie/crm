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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('counterparty_name');
            $table->string('title');
            $table->text('brief');
            $table->longText('body');
            $table->json('audit_warnings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
