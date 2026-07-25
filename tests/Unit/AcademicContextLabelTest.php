<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Support\AcademicContextLabel;
use PHPUnit\Framework\TestCase;

class AcademicContextLabelTest extends TestCase
{
    public function test_class_context_includes_each_distinct_stage(): void
    {
        $regular = new AcademicCourse(['stage' => AcademicCourse::STAGE_HIGH_SCHOOL]);
        $technical = new AcademicCourse(['stage' => AcademicCourse::STAGE_TECHNICAL]);

        $this->assertSame(
            '3º Ano A · Ensino Médio / Educação Profissional Técnica de Nível Médio',
            AcademicContextLabel::classWithStages('3º Ano A', collect([$regular, $technical, $regular])),
        );
        $this->assertSame('Etapas', AcademicContextLabel::stageHeading([$regular, $technical]));
    }
}
