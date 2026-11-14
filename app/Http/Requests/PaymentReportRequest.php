<?php

namespace App\Http\Requests;

use App\Services\Reports\PaymentReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(['admin', 'cashier']);
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'required', Rule::in(array_keys(PaymentReportFilters::PERIODS))],
            'type' => ['sometimes', 'required', Rule::in(['all', 'enrollment', 'documents'])],
            'method' => ['sometimes', 'required', Rule::in(['all', 'cash', 'gcash'])],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:2100-12-31'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function filters(): PaymentReportFilters
    {
        return PaymentReportFilters::fromArray($this->validated());
    }
}
