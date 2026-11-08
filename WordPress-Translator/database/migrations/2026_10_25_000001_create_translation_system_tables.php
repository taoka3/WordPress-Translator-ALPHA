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
        Schema::create('translation_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_type', 20); // 'post', 'category', 'tag'
            $table->unsignedBigInteger('source_id'); // ID in wp_posts or term_id in wp_terms
            $table->text('title_ja')->nullable();
            $table->text('title_en')->nullable();
            $table->longText('content_ja')->nullable();
            $table->longText('content_en')->nullable();
            $table->string('status', 20)->default('pending'); // 'pending', 'translating', 'completed', 'failed', 'paused'
            $table->text('error_message')->nullable();
            $table->integer('code_blocks_count')->default(0);
            $table->integer('attempts')->default(0);
            $table->timestamp('translated_at')->nullable();
            $table->timestamp('synced_to_wp_at')->nullable();
            $table->timestamps();

            $table->unique(['item_type', 'source_id']);
            $table->index(['item_type', 'status']);
            $table->index('status');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('translation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('level', 20)->default('info');
            $table->text('message');
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index('item_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('translation_items');
    }
};
