<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Support\AcademicContextLabel;
use Tests\TestCase;

class AcademicContextLabelTest extends TestCase
{
    public function test_context_labels_follow_the_active_interface_language(): void
    {
        app()->setLocale('it');
        $course = new AcademicCourse(['stage' => AcademicCourse::STAGE_HIGH_SCHOOL]);
        $this->assertSame('Classe A · Scuola secondaria di secondo grado', AcademicContextLabel::classWithStages('Classe A', [$course]));
        $this->assertSame('Livello non indicato', AcademicContextLabel::stages([]));
    }

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
