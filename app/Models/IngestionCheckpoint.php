<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class IngestionCheckpoint extends Model
{
    protected $fillable = [
        'key',
        'last_seen_oid',
        'last_changed_at',
        'meta',
    ];

    protected $casts = [
        'last_seen_oid' => 'integer',
        'last_changed_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Get or create a checkpoint for the given key.
     */
    public static function forKey(string $key): self
    {
        return self::firstOrCreate(['key' => $key]);
    }

    /**
     * Bump the last seen O-ID (only increases, never decreases).
     */
    public function bumpSeenOid(int $oid): void
    {
        if ($this->last_seen_oid === null || $oid > $this->last_seen_oid) {
            $this->last_seen_oid = $oid;
            $this->save();
        }
    }

    /**
     * Bump the last changed timestamp (only increases, never decreases).
     */
    public function bumpChangedAt(Carbon $ts): void
    {
        if ($this->last_changed_at === null || $ts->greaterThan($this->last_changed_at)) {
            $this->last_changed_at = $ts;
            $this->save();
        }
    }

    /**
     * Get the last changed timestamp with an overlap buffer for safety.
     */
    public function getChangedAtWithBuffer(int $hoursBuffer = 48): ?Carbon
    {
        if ($this->last_changed_at === null) {
            return null;
        }

        return $this->last_changed_at->copy()->subHours($hoursBuffer);
    }

    /**
     * Update a meta key.
     */
    public function setMeta(string $key, mixed $value): void
    {
        $meta = $this->meta ?? [];
        $meta[$key] = $value;
        $this->meta = $meta;
        $this->save();
    }

    /**
     * Get a meta key.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
