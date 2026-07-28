<?php

namespace App\Http\Requests;

use App\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class ExportActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ActivityLogsExport->value) === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'causer_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:96'],
            'log_name' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'in:success,failure,warning'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'actor_type' => ['nullable', 'in:authenticated,system'],
        ];
    }
}
