<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\CurriculumComponent;
use App\Models\StudentAcademicHistoryComponent;
use App\Support\TextNormalizer;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    public function test_school_acronyms_are_uppercase_in_existing_histories_and_when_saved(): void
    {
        foreach (['Emef Vila Paulista' => 'EMEF Vila Paulista', 'Emref São Domingos Sávio' => 'EMREF São Domingos Sávio', 'Ee Maria Silva' => 'EE Maria Silva'] as $input => $expected) {
            $year = new \App\Models\StudentAcademicHistoryYear;
            $year->setRawAttributes(['school_name' => $input]);
            $this->assertSame($expected, $year->school_name);
            $year->applyTitleCaseAttributes();
            $this->assertSame($expected, $year->getAttributes()['school_name']);
        }
        $this->assertSame('Escola Municipal Indígena', TextNormalizer::schoolAcronym('Escola Municipal Indígena'));
        $this->assertNull(TextNormalizer::schoolAcronym(null));
    }

    public function test_it_preserves_roman_numerals_only_when_requested(): void
    {
        $this->assertSame('Desenho Técnico Ii', TextNormalizer::titleCase('DESENHO TÉCNICO II'));
        $this->assertSame('Desenho Técnico II', TextNormalizer::titleCasePreservingRomanNumerals('DESENHO TÉCNICO II'));
        $this->assertSame('IV Bimestre', TextNormalizer::titleCasePreservingRomanNumerals('iv bimestre'));
        $this->assertSame('Módulo VII-A', TextNormalizer::titleCasePreservingRomanNumerals('MÓDULO vii-a'));
    }

    public function test_period_and_component_names_preserve_roman_numerals_without_changing_other_models(): void
    {
        $period = new AcademicPeriod(['name' => 'II BIMESTRE']);
        $component = new CurriculumComponent(['name' => 'DESENHO TÉCNICO II']);
        $course = new AcademicCourse(['name' => 'CURSO TÉCNICO II']);
        $historyComponent = new StudentAcademicHistoryComponent(['name' => 'DESENHO TÉCNICO II']);

        $period->applyTitleCaseAttributes();
        $component->applyTitleCaseAttributes();
        $course->applyTitleCaseAttributes();
        $historyComponent->applyTitleCaseAttributes();

        $this->assertSame('II Bimestre', $period->name);
        $this->assertSame('Desenho Técnico II', $component->name);
        $this->assertSame('Curso Técnico Ii', $course->name);
        $this->assertSame('Desenho Técnico II', $historyComponent->name);
    }
}
