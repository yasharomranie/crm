<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per placed element (text, dynamic_text, image, qr_code,
     * line, rectangle - section 40). Visual settings (font, color,
     * border, alignment, image fit, ...) live in one JSON `settings`
     * column rather than ~25 nullable typed columns (section 42) - still
     * fully admin-configurable, just without a column per property.
     * `content` holds literal text for a static text element, or the
     * `{{variable}}` token for a dynamic_text element (section 41).
     */
    public function up(): void
    {
        Schema::create('ticket_template_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_template_version_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('content')->nullable();
            $table->unsignedInteger('x')->default(0);
            $table->unsignedInteger('y')->default(0);
            $table->unsignedInteger('width')->default(100);
            $table->unsignedInteger('height')->default(30);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_template_elements');
    }
};
