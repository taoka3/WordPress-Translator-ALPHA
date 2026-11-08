<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationLog extends Model
{
    protected $connection = 'sqlite';
    protected $table = 'translation_logs';

    protected $fillable = [
        'item_id',
        'level',
        'message',
        'details',
    ];

    public function item()
    {
        return $this->belongsTo(TranslationItem::class, 'item_id');
    }

    public static function log(string $message, string $level = 'info', ?int $itemId = null, ?string $details = null): self
    {
        return static::create([
            'item_id' => $itemId,
            'level' => $level,
            'message' => $message,
            'details' => $details,
        ]);
    }
}
