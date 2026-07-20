<?php

namespace App\Models\Concerns;

use App\Support\TextNormalizer;

trait HasTitleCaseAttributes
{
    protected static function bootHasTitleCaseAttributes(): void
    {
        static::saving(function ($model): void {
            $model->applyTitleCaseAttributes();
        });
    }

    public function applyTitleCaseAttributes(): void
    {
        $romanNumeralAttributes = $this->titleCaseAttributesPreservingRomanNumerals();

        foreach ($this->titleCaseAttributes() as $attribute) {
            if ($this->getAttribute($attribute) !== null) {
                $normalizer = in_array($attribute, $romanNumeralAttributes, true)
                    ? TextNormalizer::titleCasePreservingRomanNumerals(...)
                    : TextNormalizer::titleCase(...);

                $this->setAttribute($attribute, $normalizer($this->getAttribute($attribute)));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function titleCaseAttributes(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function titleCaseAttributesPreservingRomanNumerals(): array
    {
        return [];
    }
}
