<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolConceptVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_concept_keeps_the_other_previously_effective_concepts_available(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        $school->concepts()->createMany([
            $this->concept('Bom', 'B', 7.5, 10, 1, '2025-01-01'),
            $this->concept('Suficiente', 'S', 6, 7.5, 2, '2025-01-01'),
            $this->concept('Insuficiente', 'I', 3, 6, 3, '2025-01-01'),
            $this->concept('Insuficiente grave', 'IG', null, 3, 4, '2025-01-01'),
            $this->concept('Insuficiente gravissimo', 'IGV', 2, 3, 5, '2026-01-01'),
        ]);

        $school->load('concepts');

        $this->assertSame('B', $school->conceptForScore(8, '2026-06-01')?->abbreviation);
        $this->assertSame('S', $school->conceptForScore(6.5, '2026-06-01')?->abbreviation);
        $this->assertCount(5, $school->conceptsForDate('2026-06-01'));
    }

    public function test_the_newest_version_of_the_same_concept_takes_precedence(): void
    {
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        $school->concepts()->createMany([
            $this->concept('Bom', 'B', 7.5, 10, 1, '2025-01-01'),
            $this->concept('Bom', 'MB', 8, 10, 1, '2026-01-01'),
        ]);

        $school->load('concepts');

        $this->assertSame('MB', $school->conceptForScore(8.5, '2026-06-01')?->abbreviation);
        $this->assertNull($school->conceptForScore(7.5, '2026-06-01'));
    }

    /** @return array<string, mixed> */
    private function concept(
        string $name,
        string $abbreviation,
        ?float $minimum,
        ?float $maximum,
        int $order,
        string $effectiveFrom,
    ): array {
        return [
            'name' => $name,
            'abbreviation' => $abbreviation,
            'minimum_score' => $minimum,
            'maximum_score' => $maximum,
            'minimum_inclusive' => true,
            'maximum_inclusive' => true,
            'sort_order' => $order,
            'effective_from' => $effectiveFrom,
        ];
    }
}
