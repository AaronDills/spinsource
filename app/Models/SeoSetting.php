<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SeoSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * Cache duration in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Cache key prefix.
     */
    private const CACHE_PREFIX = 'seo_setting:';

    /**
     * Get a setting value by key with caching.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    /**
     * Set a setting value and clear the cache.
     */
    public static function setValue(string $key, string $value, ?string $description = null): self
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );

        // Clear the cache for this key
        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    /**
     * Clear the cache for a specific key.
     */
    public static function clearCache(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Clear all SEO settings cache.
     */
    public static function clearAllCache(): void
    {
        $keys = self::pluck('key');

        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }
}
