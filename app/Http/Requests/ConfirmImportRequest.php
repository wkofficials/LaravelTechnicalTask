<?php

namespace App\Http\Requests;

use App\Enums\DuplicatePolicy;
use App\Models\ImportBatch;
use App\Support\PlanLimits;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The user's confirmation of how the file's columns map onto the dataset.
 * Only the dataset's own fields can be mapped, onto columns that exist.
 */
class ConfirmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('confirm', $this->importBatch());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $batch = $this->importBatch();
        $lastColumn = max(0, count($batch->headers ?? []) - 1);
        $companyId = app(CurrentCompany::class)->id();

        $rules = [
            'mapping' => ['required', 'array'],
            'duplicate_policy' => ['required', Rule::enum(DuplicatePolicy::class)],
            'template_id' => ['nullable', 'integer', Rule::exists('import_templates', 'id')
                ->where('company_id', $companyId)
                ->where('dataset_key', $batch->dataset_key)],
            'save_template' => ['sometimes', 'boolean'],
            'template_name' => ['nullable', 'required_if_accepted:save_template', 'string', 'max:100',
                Rule::unique('import_templates', 'name')->where('company_id', $companyId)],
        ];

        foreach ($batch->dataset()->fields() as $field) {
            $rules["mapping.{$field->key}"] = [
                $field->required ? 'required' : 'nullable',
                'integer',
                'between:0,'.$lastColumn,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = ['template_name.required_if_accepted' => 'Give the template a name to save it.'];

        foreach ($this->importBatch()->dataset()->fields() as $field) {
            $messages["mapping.{$field->key}.required"] = "Choose the column that contains {$field->label} — it is required.";
            $messages["mapping.{$field->key}.integer"] = "Choose a valid column for {$field->label}.";
            $messages["mapping.{$field->key}.between"] = "Choose a valid column for {$field->label}.";
        }

        return $messages;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $limits = PlanLimits::for($this->importBatch()->company);

                if ($this->boolean('save_template')) {
                    $templateProblem = $this->user()->can('manage templates')
                        ? $limits->checkCanAddTemplate()
                        : 'You are not allowed to save templates.';

                    if ($templateProblem) {
                        $validator->errors()->add('save_template', $templateProblem);
                    }
                }

                // The monthly row quota is checked in the controller, under a
                // company lock, so concurrent confirmations can't both pass.

                // One column can feed only one field.
                $used = [];

                foreach ($this->mappedColumns() as $fieldKey => $column) {
                    if (isset($used[$column])) {
                        $validator->errors()->add("mapping.{$fieldKey}", 'This column is already used for another field.');
                    }

                    $used[$column] = true;
                }
            },
        ];
    }

    /**
     * Field key => column index for the dataset's fields (unmapped => null).
     *
     * @return array<string, int|null>
     */
    public function mapping(): array
    {
        $mapping = [];

        foreach ($this->importBatch()->dataset()->fieldKeys() as $fieldKey) {
            $value = $this->input("mapping.{$fieldKey}");
            $mapping[$fieldKey] = $value === null || $value === '' ? null : (int) $value;
        }

        return $mapping;
    }

    /**
     * @return array<string, int>
     */
    private function mappedColumns(): array
    {
        return array_filter($this->mapping(), fn ($column) => $column !== null);
    }

    private function importBatch(): ImportBatch
    {
        return $this->route('importBatch');
    }
}
