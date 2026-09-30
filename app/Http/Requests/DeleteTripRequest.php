<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A trip participant, or a platform admin. Not is_admin: that is true for
        // every staff role (duty/access officer, data admin).
        if ($this->user()->hasRole('platform_admin')) {
            return true;
        }

        return $this->user()->trips()->where('trip_id', $this->route('trip')->id)->exists();
    }
}
