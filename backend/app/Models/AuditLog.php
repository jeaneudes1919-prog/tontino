<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public static bool $enabled = true;

    protected $guarded = [];

    protected $casts = ['old' => 'array', 'new' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $event, Model $model): void
    {
        if (! static::$enabled) {
            return;
        }
        $coop = $model->cooperative_id ?? request()->attributes->get('coop')?->id;
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if ($event === 'updated' && ! $changes) {
            return;
        }
        static::create([
            'cooperative_id' => $coop,
            'user_id' => auth()->id(),
            'action' => $event,
            'subject_type' => class_basename($model),
            'subject_id' => $model->getKey(),
            'old' => $event === 'updated' ? array_intersect_key($model->getOriginal(), $changes) : ($event === 'deleted' ? $model->getAttributes() : null),
            'new' => $event === 'deleted' ? null : ($event === 'created' ? $model->getAttributes() : $changes),
            'ip' => request()->ip(),
        ]);
    }
}
