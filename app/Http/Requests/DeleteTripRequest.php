<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->trips()->where('trip_id', $this->route('trip')->id)->exists();
    }
}
