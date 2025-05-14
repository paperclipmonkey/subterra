<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if the user was a trip participant or is an admin
        if($this->user()->is_admin) {
            return true;
        }
        return $this->user()->trips()->where('trip_id', $this->route('trip')->id)->exists();
    }
}
