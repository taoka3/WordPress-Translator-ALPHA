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
        $schema = Schema::connection('wp_mysql');

        // 1. wp_posts テーブルへの翻訳用カラム追加
        if ($schema->hasTable('wp_posts')) {
            $hasPostTitleEn      = $schema->hasColumn('wp_posts', 'post_title_en');
            $hasPostContentEn    = $schema->hasColumn('wp_posts', 'post_content_en');
            $hasPostTranslatedAt = $schema->hasColumn('wp_posts', 'post_translated_at');

            // 対象カラムが存在しない場合のみ追加
            if (!$hasPostTitleEn || !$hasPostContentEn || !$hasPostTranslatedAt) {
                $schema->table('wp_posts', function (Blueprint $table) use (
                    $schema,
                    $hasPostTitleEn,
                    $hasPostContentEn,
                    $hasPostTranslatedAt
                ) {
                    $hasTitle   = $schema->hasColumn('wp_posts', 'post_title');
                    $hasContent = $schema->hasColumn('wp_posts', 'post_content');

                    if (!$hasPostTitleEn) {
                        $hasTitle
                            ? $table->text('post_title_en')->nullable()->after('post_title')
                            : $table->text('post_title_en')->nullable();
                    }

                    if (!$hasPostContentEn) {
                        $hasContent
                            ? $table->longText('post_content_en')->nullable()->after('post_content')
                            : $table->longText('post_content_en')->nullable();
                    }

                    if (!$hasPostTranslatedAt) {
                        $table->timestamp('post_translated_at')->nullable();
                    }
                });
            }
        }

        // 2. wp_terms テーブルへの翻訳用カラム追加
        if ($schema->hasTable('wp_terms')) {
            $hasNameEn           = $schema->hasColumn('wp_terms', 'name_en');
            $hasTermTranslatedAt = $schema->hasColumn('wp_terms', 'term_translated_at');

            // 対象カラムが存在しない場合のみ追加
            if (!$hasNameEn || !$hasTermTranslatedAt) {
                $schema->table('wp_terms', function (Blueprint $table) use (
                    $schema,
                    $hasNameEn,
                    $hasTermTranslatedAt
                ) {
                    $hasName = $schema->hasColumn('wp_terms', 'name');

                    if (!$hasNameEn) {
                        $hasName
                            ? $table->string('name_en', 200)->nullable()->after('name')
                            : $table->string('name_en', 200)->nullable();
                    }

                    if (!$hasTermTranslatedAt) {
                        $table->timestamp('term_translated_at')->nullable();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('wp_mysql');

        if ($schema->hasTable('wp_posts')) {
            $schema->table('wp_posts', function (Blueprint $table) use ($schema) {
                $columns = [
                    'post_title_en',
                    'post_content_en',
                    'post_translated_at',
                ];
                $dropList = [];
                foreach ($columns as $column) {
                    if ($schema->hasColumn('wp_posts', $column)) {
                        $dropList[] = $column;
                    }
                }
                if (!empty($dropList)) {
                    $table->dropColumn($dropList);
                }
            });
        }

        if ($schema->hasTable('wp_terms')) {
            $schema->table('wp_terms', function (Blueprint $table) use ($schema) {
                $columns = [
                    'name_en',
                    'term_translated_at',
                ];
                $dropList = [];
                foreach ($columns as $column) {
                    if ($schema->hasColumn('wp_terms', $column)) {
                        $dropList[] = $column;
                    }
                }
                if (!empty($dropList)) {
                    $table->dropColumn($dropList);
                }
            });
        }
    }
};
