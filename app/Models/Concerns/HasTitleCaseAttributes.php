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
        foreach ($this->titleCaseAttributes() as $attribute) {
            if ($this->getAttribute($attribute) !== null) {
                $this->setAttribute($attribute, TextNormalizer::titleCase($this->getAttribute($attribute)));
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
}
