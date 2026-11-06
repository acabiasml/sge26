<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\StudentAcademicHistoryComponent;
use App\Support\TextNormalizer;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    public function test_initials_ignore_connectors_and_handle_accents_and_punctuation(): void
    {
        $this->assertSame('CTJJ', TextNormalizer::initials('Centro Técnico Juvenil de Jarudore'));
        $this->assertSame('LPSFA', TextNormalizer::initials('Liceu Pedagógico São Francisco de Assis'));
        $this->assertSame('ELV', TextNormalizer::initials('Escola Laura Vicuña'));
        $this->assertSame('EÁSJ', TextNormalizer::initials('  Escola — Águas de São-João  '));
        $this->assertSame('D', TextNormalizer::initials('de'));
        $this->assertSame('', TextNormalizer::initials(null));
    }

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

    public function test_period_names_keep_roman_numerals_and_history_component_names_keep_the_original_spelling(): void
    {
        $period = new AcademicPeriod(['name' => 'II BIMESTRE']);
        $course = new AcademicCourse(['name' => 'CURSO TÉCNICO II']);
        $historyComponent = new StudentAcademicHistoryComponent(['name' => 'DESENHO TÉCNICO II']);

        $period->applyTitleCaseAttributes();
        $course->applyTitleCaseAttributes();
        $historyComponent->applyTitleCaseAttributes();

        $this->assertSame('II Bimestre', $period->name);
        $this->assertSame('Curso Técnico Ii', $course->name);
        $this->assertSame('DESENHO TÉCNICO II', $historyComponent->name);
    }
}
