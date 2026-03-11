<?php

namespace App\Http\Requests;

use App\Enums\ArrivalMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncArrivalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'arrivals' => ['required', 'array', 'min:1'],
            'arrivals.*.ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'arrivals.*.method' => ['required', 'string', Rule::in(array_column(ArrivalMethod::cases(), 'value'))],
            'arrivals.*.scanned_at' => ['required', 'date'],
            'arrivals.*.jwt_token' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
