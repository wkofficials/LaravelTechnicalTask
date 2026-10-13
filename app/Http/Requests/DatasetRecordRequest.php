<?php

namespace App\Http\Requests;

use App\Datasets\Dataset;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a record of any dataset. Rules come from the dataset's field
 * definitions — the same rules imports apply — so a record is valid the same
 * way whether it was typed in or imported. Only the dataset's fields are
 * ever accepted (no mass assignment of company_id etc.).
 */
class DatasetRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware (permission:create/edit records) already gates access.
        return true;
    }

    /**
     * Same clean-up as imports: trim, empty → null, lower-case emails.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ($this->dataset()->fields() as $field) {
            if ($this->has($field->key)) {
                $normalized[$field->key] = $field->normalize($this->input($field->key));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dataset = $this->dataset();
        $rules = [];

        foreach ($dataset->fields() as $field) {
            $fieldRules = [$field->required ? 'required' : 'nullable', 'string', 'max:'.$field->maxLength];

            if ($field->type === $field::TYPE_EMAIL) {
                $fieldRules[] = 'email';
            }

            if ($field->key === $dataset->uniqueField()) {
                // Unique per company only — the rule queries the table directly,
                // so it must be scoped by hand or it would leak other tenants' data.
                $fieldRules[] = Rule::unique((new ($dataset->modelClass()))->getTable(), $field->key)
                    ->where('company_id', app(CurrentCompany::class)->id())
                    ->ignore($this->route('record'));
            }

            $rules[$field->key] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->dataset()->fields())->mapWithKeys(fn ($field) => [$field->key => $field->label])->all();
    }

    private function dataset(): Dataset
    {
        return $this->route('dataset');
    }
}
