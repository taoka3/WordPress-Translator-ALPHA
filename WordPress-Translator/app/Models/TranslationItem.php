<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TranslationItem extends Model
{
    use HasFactory;

    protected $connection = 'sqlite';
    protected $table = 'translation_items';

    protected $fillable = [
        'item_type',
        'source_id',
        'title_ja',
        'title_en',
        'content_ja',
        'content_en',
        'status',
        'error_message',
        'code_blocks_count',
        'attempts',
        'translated_at',
        'synced_to_wp_at',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'code_blocks_count' => 'integer',
        'attempts' => 'integer',
        'translated_at' => 'datetime',
        'synced_to_wp_at' => 'datetime',
    ];

    public function logs()
    {
        return $this->hasMany(TranslationLog::class, 'item_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'failed']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
