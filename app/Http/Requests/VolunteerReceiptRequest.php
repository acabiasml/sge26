<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VolunteerReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManagePeople() ?? false;
    }

    public function rules(): array
    {
        return self::rulesForReceipt();
    }

    public static function rulesForReceipt(): array
    {
        return [
            'receipt_school_id' => ['required', 'integer', 'exists:schools,id'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999999.99'],
            'issued_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
