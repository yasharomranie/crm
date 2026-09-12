<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_allotments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained('sales_channels')->cascadeOnDelete();
            $table->unsignedInteger('allotment_count');
            $table->timestamps();

            $table->unique(['room_type_id', 'sales_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_allotments');
    }
};
