<?php

namespace App\Actions\Packages;

use App\Models\PackageRequirement;
use Illuminate\Support\Collection;

class ResolvePackageRequirements
{
    /**
     * @param  Collection<int, PackageRequirement>  $requirements
     * @return array{
     *   schema: array<int, array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     is_required: bool,
     *     validation_rules: ?string,
     *     options: array<int, string>
     *   }>,
     *   rules: array<string, array<int, string>>,
     *   attributes: array<string, string>
     * }
     */
    public function handle(Collection $requirements): array
    {
        $schema = [];
        $rules = [];
        $attributes = [];

        foreach ($requirements->sortBy('order') as $requirement) {
            $normalized = $this->normalizeRequirement($requirement);

            $schema[] = [
                'key' => $normalized['key'],
                'label' => $normalized['label'],
                'type' => $normalized['type'],
                'is_required' => $normalized['is_required'],
                'validation_rules' => $normalized['validation_rules'],
                'options' => $normalized['options'],
            ];

            $rules[$normalized['key']] = $normalized['rules'];
            $attributes[$normalized['key']] = $normalized['label'] !== '' ? $normalized['label'] : $normalized['key'];
        }

        return [
            'schema' => $schema,
            'rules' => $rules,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   type: string,
     *   is_required: bool,
     *   validation_rules: ?string,
     *   options: array<int, string>,
     *   rules: array<int, string>
     * }
     */
    private function normalizeRequirement(PackageRequirement $requirement): array
    {
        $extraRules = $this->parseExtraRules($requirement->validation_rules);
        $options = $this->extractOptions($extraRules);

        $rules = [];
        $rules[] = $requirement->is_required ? 'required' : 'nullable';

        $typeRule = $requirement->type === 'number' ? 'numeric' : 'string';
        $rules[] = $typeRule;

        $rules = array_merge($rules, $extraRules);
        $rules = $this->dedupeRules($rules);

        return [
            'key' => $requirement->key,
            'label' => $requirement->label,
            'type' => $requirement->type,
            'is_required' => $requirement->is_required,
            'validation_rules' => $requirement->validation_rules,
            'options' => $options,
            'rules' => $rules,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseExtraRules(?string $rules): array
    {
        if ($rules === null || trim($rules) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $rule): string => trim($rule),
            explode('|', $rules)
        ), fn (string $rule): bool => $rule !== '' && ! in_array($rule, ['required', 'nullable'], true)));
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<int, string>
     */
    private function dedupeRules(array $rules): array
    {
        $unique = [];

        foreach ($rules as $rule) {
            if (in_array($rule, $unique, true)) {
                continue;
            }

            $unique[] = $rule;
        }

        return $unique;
    }

    /**
     * @param  array<int, string>  $rules
     * @return array<int, string>
     */
    private function extractOptions(array $rules): array
    {
        foreach ($rules as $rule) {
            if (! str_starts_with($rule, 'in:')) {
                continue;
            }

            $values = array_map('trim', explode(',', substr($rule, 3)));
            $values = array_values(array_filter($values, fn (string $value): bool => $value !== ''));

            return $values;
        }

        return [];
    }
}
