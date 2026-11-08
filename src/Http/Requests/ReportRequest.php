<?php

namespace Goodport\JsErrorLogger\Http\Requests;

use Goodport\JsErrorLogger\Config\ReportingSettings;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ReportRequest extends FormRequest
{
    public function __construct(private ReportingSettings $settings)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:'.$this->settings->maxUrlLength()],
            'message' => ['required', 'string', 'max:'.$this->settings->maxMessageLength()],
            'file' => ['nullable', 'string', 'max:'.$this->settings->maxUrlLength()],
            'line' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Returns a bodiless 422 response because sendBeacon cannot set the Accept header.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->noContent(422));
    }
}
