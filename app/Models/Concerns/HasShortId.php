<?php

namespace App\Models\Concerns;

trait HasShortId
{
    /**
     * Boot the trait.
     */
    protected static function bootHasShortId(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getShortIdColumn()})) {
                $model->{$model->getShortIdColumn()} = static::generateShortId();
            }
        });
    }

    /**
     * Generate a unique short ID.
     */
    protected static function generateShortId(): string
    {
        $attempts = 0;
        $maxAttempts = 10;

        do {
            // Generate a random 8-character alphanumeric string
            // Using base62 (0-9, a-z, A-Z) for URL-safe IDs
            $shortId = static::generateRandomString(8);

            ++$attempts;

            // Check if this ID already exists
            $exists = static::where(static::make()->getShortIdColumn(), $shortId)->exists();

            if (!$exists) {
                return $shortId;
            }
        } while ($attempts < $maxAttempts);

        // Fallback to a longer ID if we couldn't generate a unique one
        return static::generateRandomString(10);
    }

    /**
     * Generate a random alphanumeric string.
     */
    protected static function generateRandomString(int $length): string
    {
        // Use base62: 0-9, a-z, A-Z (62 characters)
        // This gives us 62^8 = 218 trillion possible combinations for 8 chars
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; ++$i) {
            $string .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $string;
    }

    /**
     * Get the column name for the short ID.
     */
    public function getShortIdColumn(): string
    {
        return 'short_id';
    }

    /**
     * Get the route key name for Laravel.
     * This makes route model binding use short_id instead of id.
     */
    public function getRouteKeyName(): string
    {
        return 'short_id';
    }

    /**
     * Retrieve the model for a bound value (route model binding).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Try to find by short_id first
        return $this->where($field ?? $this->getRouteKeyName(), $value)->firstOrFail();
    }
}
