<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAttendanceRequest extends FormRequest
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
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.shift_signup_id' => ['required', 'integer', 'exists:shift_signups,id'],
            'attendance.*.status' => ['required', 'string', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'attendance.*.scanned_at' => ['required', 'date'],
        ];
    }
}
