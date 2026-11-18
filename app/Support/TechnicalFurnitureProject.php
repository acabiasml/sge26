<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\TechnicalProgram;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class TechnicalFurnitureProject
{
    /** Matriz e modelos das páginas 24–27 e 33–34 do projeto apresentado. */
    public static function modules(): array
    {
        $definitions = [
            ['Introdução à Marcenaria', 'Auxiliar de Projetista de Móveis', [
                ['Desenho Técnico I', 120], ['Equipamentos e Tecnologia da Madeira', 120], ['História da Arte e do Mobiliário', 60], ['Informática Básica', 100],
            ]],
            ['Criação, Projeto e Fabricação de Móveis', 'Auxiliar de Marcenaria', [
                ['Desenho Assistido por Computador I', 80], ['Desenho Técnico II', 80], ['Contabilidade Aplicada ao Setor Moveleiro', 60],
                ['Processo de Fabricação I', 120], ['Projeto e Design de Móveis', 60],
            ]],
            ['Indústria Moveleira', null, [
                ['Desenho Assistido por Computador II', 80], ['Administração Aplicada ao Setor Moveleiro', 60],
                ['Processo de Fabricação II', 200], ['Segurança do Trabalho, Meio Ambiente e Saúde', 60],
            ]],
        ];
        $modules = [];
        foreach ($definitions as $index => [$name, $qualification, $components]) {
            $key = 'moveis-modulo-'.($index + 1);
            $modules[] = ['key' => $key, 'position' => $index + 1, 'name' => $name, 'qualification' => $qualification,
                'components' => array_map(fn ($c, $i) => ['key' => $key.'-componente-'.($i + 1), 'name' => $c[0], 'hours' => $c[1]], $components, array_keys($components))];
        }

        return $modules;
    }

    public static function registerExisting(): void
    {
        // Vincula a oferta já cadastrada do Liceu, preservando aulas, notas e cargas existentes.
        $courses = AcademicCourse::where('stage', 'tecnico')->with('academicYear.school', 'components')->orderByDesc('workload_hours')->get()
            ->filter(fn ($c) => str_contains(Str::lower(Str::ascii($c->name)), 'moveis')
                && str_contains(Str::lower(Str::ascii($c->academicYear->school->name)), 'liceu pedagogico sao francisco de assis'));
        foreach ($courses->groupBy('academicYear.school_id') as $schoolId => $offers) {
            $source = $offers->first();
            $program = TechnicalProgram::firstOrCreate(['school_id' => $schoolId, 'name' => 'Curso Técnico em Móveis'], [
                'graduate_title' => 'Técnico em Móveis',
                'technological_axis' => 'Produção Industrial', 'education_mode' => 'Presencial', 'offer_forms' => 'Concomitante e Subsequente',
                'maintainer' => 'Centro Técnico Juvenil de Jarudore – CTJJ', 'state_heading' => 'ESTADO DE MATO GROSSO', 'education_system' => 'SISTEMA ESTADUAL DE ENSINO',
                'creation_act' => 'C. 4668 D.O.M.T. 07/11/1990.', 'authorization_act' => $source->getRawOriginal('authorization_act'),
                'accreditation_act' => $source->getRawOriginal('accreditation_act'), 'regulatory_process' => $source->getRawOriginal('regulatory_process'),
                'regulatory_opinion' => $source->getRawOriginal('regulatory_opinion'), 'official_gazette_reference' => $source->getRawOriginal('official_gazette_reference'),
                'authorization_starts_at' => $source->getRawOriginal('authorization_starts_at'), 'authorization_ends_at' => $source->getRawOriginal('authorization_ends_at'),
                'legal_basis' => 'Lei nº 9.394, de 20/12/1996; Lei nº 11.741 de 16/07/2008; e respectivos atos de Autorização de Curso',
                'registry_resolution' => 'Resolução nº 249/07 – CEE/MT',
                'front_address' => 'Av. São João, s/n. Jarudore, Poxoréu-MT. CEP 78800-000.',
                'back_address' => 'Rua São João, s/n. Jarudore, Poxoréu-MT. CEP 78800-000. Tel.: (66) 99613-6796.',
                'source_project' => 'PROJETO APRESENTADO CORRIGIDO.pdf — modelos p. 24–27; matriz p. 33–34.', 'modules' => TechnicalFurnitureProject::modules(),
            ]);
            foreach ($offers as $course) {
                if ($course->technical_program_id && $course->technical_program_id !== $program->id) {
                    continue;
                }
                $course->update(['technical_program_id' => $program->id]);
                app(TechnicalProgramMatrix::class)->linkExisting($course, $program);
                foreach ($course->enrollments()->with('courses', 'student')->get() as $enrollment) {
                    app(TechnicalEnrollmentOffer::class)->record($enrollment, [], false);
                }
            }
        }
        if (Schema::hasColumn('technical_programs', 'offer_modes')) {
            app(TechnicalProgramCatalog::class)->backfill();
        }
    }
}
