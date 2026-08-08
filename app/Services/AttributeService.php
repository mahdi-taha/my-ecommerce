<?php

namespace App\Services;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttributeService
{
    public function create(array $data): Attribute
    {
        return DB::transaction(function () use ($data) {
            $supportsOptions = in_array($data['attribute_type'], [AttributeType::Select->value, AttributeType::Multiselect->value], true);
            $attribute = Attribute::create([
                'code' => $data['attribute_code'], 'type' => $data['attribute_type'],
                'swatch_type' => $supportsOptions ? $data['attribute_swatch_type'] : null,
                'is_required' => $data['is_required'],
                'is_configurable' => $data['attribute_type'] === AttributeType::Select->value ? $data['is_configurable'] : false,
                'is_filterable' => $data['is_filterable'], 'is_visible_on_front' => $data['is_visible_on_front'],
                'is_active' => true, 'sort_order' => $data['attribute_sort_order'] ?? 0,
            ]);
            $this->syncTranslations($attribute, $data);

            return $attribute;
        });
    }

    public function update(Attribute $attribute, array $data): Attribute
    {
        return DB::transaction(function () use ($attribute, $data) {
            $supportsOptions = in_array($attribute->type, [AttributeType::Select->value, AttributeType::Multiselect->value], true);
            if ($supportsOptions && empty($data['attribute_swatch_type'])) {
                throw ValidationException::withMessages(['attribute_swatch_type' => 'The swatch type is required for select and multiselect attributes.']);
            }
            $attribute->update([
                'swatch_type' => $supportsOptions ? $data['attribute_swatch_type'] : null,
                'sort_order' => $data['attribute_sort_order'] ?? 0, 'is_required' => $data['is_required'],
                'is_configurable' => $attribute->type === AttributeType::Select->value ? $data['is_configurable'] : false,
                'is_filterable' => $data['is_filterable'], 'is_visible_on_front' => $data['is_visible_on_front'],
                'is_active' => $data['is_active'],
            ]);
            $this->syncTranslations($attribute, $data);

            return $attribute->refresh();
        });
    }

    public function saveOptions(Attribute $attribute, array $data): void
    {
        DB::transaction(function () use ($attribute, $data) {
            $attribute = Attribute::whereKey($attribute->id)->lockForUpdate()->firstOrFail();
            $existingOptions = $attribute->options()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $attribute->update(['swatch_type' => $data['swatch_type']]);

            foreach ($data['deleted_options'] ?? [] as $id) {
                $option = $existingOptions->get((int) $id);

                if (! $option) {
                    throw ValidationException::withMessages([
                        'deleted_options' => 'One or more options do not belong to this attribute.',
                    ]);
                }

                if ($this->optionIsReferenced($option)) {
                    throw ValidationException::withMessages(['deleted_options' => "Option {$option->code} is in use and cannot be deleted."]);
                }

                $option->delete();
                $existingOptions->forget((int) $id);
            }

            $submittedIds = collect($data['options'])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id);
            $usedCodes = $existingOptions
                ->except($submittedIds->all())
                ->pluck('code')
                ->flip()
                ->all();
            $preparedRows = [];

            foreach ($data['options'] as $index => $row) {
                $option = null;

                if (! empty($row['id'])) {
                    $option = $existingOptions->get((int) $row['id']);

                    if (! $option) {
                        throw ValidationException::withMessages([
                            "options.{$index}.id" => 'The option does not belong to this attribute.',
                        ]);
                    }

                    $code = array_key_exists('code', $row)
                        ? $this->normalizeSubmittedOptionCode((string) $row['code'], $index)
                        : $option->code;

                    if ($code !== $option->code && $this->optionIsReferenced($option)) {
                        throw ValidationException::withMessages([
                            "options.{$index}.code" => "Option code {$option->code} is immutable because it is in use.",
                        ]);
                    }
                } else {
                    $code = $this->generatedOptionCode((string) $row['label_en'], $usedCodes);
                }

                if (isset($usedCodes[$code])) {
                    throw ValidationException::withMessages([
                        "options.{$index}.code" => 'Option codes must be unique within the attribute.',
                    ]);
                }

                $usedCodes[$code] = true;
                $preparedRows[] = [$option, $row, $code];
            }

            foreach ($preparedRows as [$option, $row, $code]) {
                $option ??= new AttributeOption(['attribute_id' => $attribute->id]);
                $option->fill([
                    'code' => $code,
                    'sort_order' => $row['sort_order'] ?? 0,
                    'swatch_value' => $data['swatch_type'] === 'color'
                        ? strtoupper($row['swatch_value'])
                        : null,
                ]);
                $option->save();

                foreach (['en', 'ar'] as $locale) {
                    $option->translations()->updateOrCreate(['locale' => $locale], ['label' => $row['label_'.$locale]]);
                }
            }
        });
    }

    private function generatedOptionCode(string $englishLabel, array $usedCodes): string
    {
        $base = Str::slug($englishLabel, '_');
        $base = $base !== ''
            ? mb_substr($base, 0, 100)
            : 'option_'.substr(hash('sha256', trim($englishLabel)), 0, 12);
        $code = $base;
        $suffixNumber = 2;

        while (isset($usedCodes[$code])) {
            $suffix = '_'.$suffixNumber++;
            $code = mb_substr($base, 0, 100 - mb_strlen($suffix)).$suffix;
        }

        return $code;
    }

    private function normalizeSubmittedOptionCode(string $code, int $index): string
    {
        $normalized = mb_substr(Str::slug(trim($code), '_'), 0, 100);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                "options.{$index}.code" => 'The option code must contain letters or numbers.',
            ]);
        }

        return $normalized;
    }

    private function optionIsReferenced(AttributeOption $option): bool
    {
        return $option->productValues()->exists()
            || $option->productSuperAttributes()->exists();
    }

    public function delete(Attribute $attribute): void
    {
        DB::transaction(function () use ($attribute) {
            $attribute = Attribute::query()
                ->whereKey($attribute->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isReferenced = $attribute->productValues()->exists()
                || $attribute->productSuperAttributes()->exists()
                || $attribute->categories()->exists()
                || $attribute->options()->whereHas('productValues')->exists()
                || $attribute->options()->whereHas('productSuperAttributes')->exists();

            if ($isReferenced) {
                throw ValidationException::withMessages([
                    'attribute' => 'This attribute is in use and cannot be deleted.',
                ]);
            }

            $attribute->delete();
        });
    }

    private function syncTranslations(Attribute $attribute, array $data): void
    {
        foreach (['en', 'ar'] as $locale) {
            $attribute->translations()->updateOrCreate(['locale' => $locale], ['admin_name' => $data['attribute_name_'.$locale]]);
        }
    }
}
