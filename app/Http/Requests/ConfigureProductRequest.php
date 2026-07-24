<?php

namespace App\Http\Requests;

use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfigureProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_attributes' => ['required', 'array', 'min:1'],
            'selected_attributes.*' => ['required', 'integer', 'distinct'],
            'super_attributes' => ['required', 'array', 'min:1'],
            'super_attributes.*' => ['required', 'array', 'min:1'],
            'super_attributes.*.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $selections = (array) $this->input('super_attributes', []);
                $selectedAttributeIds = collect((array) $this->input('selected_attributes', []))
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();
                $attributeIds = collect(array_keys($selections))
                    ->filter(fn ($id) => ctype_digit((string) $id))
                    ->map(fn ($id) => (int) $id);

                if ($attributeIds->count() !== count($selections)) {
                    $validator->errors()->add(
                        'super_attributes',
                        'One or more selected attributes are invalid.'
                    );

                    return;
                }

                if ($attributeIds->sort()->values()->all() !== $selectedAttributeIds->all()) {
                    $validator->errors()->add(
                        'super_attributes',
                        'Select at least one option for every selected attribute.'
                    );
                }

                $validAttributeIds = Attribute::query()
                    ->whereIn('id', $attributeIds)
                    ->where('is_active', true)
                    ->where('type', 'select')
                    ->where('is_configurable', true)
                    ->pluck('id');

                if ($validAttributeIds->count() !== $attributeIds->count()) {
                    $validator->errors()->add(
                        'super_attributes',
                        'Only active configurable select attributes may be used.'
                    );
                }

                $requiredAttributeIds = Attribute::query()
                    ->where('is_active', true)
                    ->where('type', 'select')
                    ->where('is_configurable', true)
                    ->where('is_required', true)
                    ->pluck('id');

                if ($requiredAttributeIds->diff($attributeIds)->isNotEmpty()) {
                    $validator->errors()->add(
                        'super_attributes',
                        'Every required configurable attribute must be selected.'
                    );
                }

                $combinationCount = 1;

                foreach ($selections as $attributeId => $optionIds) {
                    if (! is_array($optionIds) || empty($optionIds)) {
                        continue;
                    }

                    $submittedOptionIds = collect($optionIds)
                        ->map(fn ($id) => (int) $id)
                        ->unique();
                    $validOptionCount = AttributeOption::query()
                        ->where('attribute_id', $attributeId)
                        ->whereIn('id', $submittedOptionIds)
                        ->count();

                    if ($validOptionCount !== $submittedOptionIds->count()) {
                        $validator->errors()->add(
                            'super_attributes.'.$attributeId,
                            'One or more selected options do not belong to this attribute.'
                        );
                    }

                    $combinationCount *= count($optionIds);

                    if ($combinationCount > 200) {
                        $validator->errors()->add(
                            'super_attributes',
                            'A configurable product cannot exceed 200 combinations.'
                        );

                        break;
                    }
                }
            },
        ];
    }
}
