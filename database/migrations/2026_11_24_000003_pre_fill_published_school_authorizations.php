<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $liceuAccreditation = $this->act('Portaria 294/2008-CEE/MT · DOE-MT nº 24.954', '2008-11-05', '2008-01-01', '2011-12-31', '1874#page=16');
        $liceuHighSchool = $this->act('Ato 0260/2012-CEE/MT · DOE-MT nº 25.935', '2012-11-28', '2012-01-01', '2016-12-31', '3282#page=11');
        $lauraBasicEducation = $this->act('Ato 350/2022-CEE/MT · DOE-MT nº 28.311', '2022-08-18', '2022-01-01', '2024-12-31', '16925#page=22');
        $larElementary = $this->act('Ato 383/2025-CEE/MT · DOE-MT nº 28.986', '2025-05-12', '2025-01-01', '2027-12-31', '18440#page=38');
        $larHighSchool = $this->act('Ato 078/2023-CEE/MT · DOE-MT nº 28.437', '2023-02-13', '2023-01-01', '2027-12-31', '17136#page=29');

        $publishedByCnpj = [
            '00176974000120' => [
                'accreditation' => $liceuAccreditation,
                'medio' => $liceuHighSchool,
                'medio_regular' => $liceuHighSchool,
            ],
            '00176974000201' => [
                'fundamental' => $lauraBasicEducation,
                'fundamental_regular' => $lauraBasicEducation,
                'medio' => $lauraBasicEducation,
                'medio_regular' => $lauraBasicEducation,
            ],
            '00176974000392' => [
                'fundamental' => $larElementary,
                'fundamental_educacao_do_campo' => $larElementary,
                'medio' => $larHighSchool,
                'medio_educacao_do_campo' => $larHighSchool,
            ],
        ];

        DB::table('schools')->where('state', 'MT')->orderBy('id')->lockForUpdate()
            ->eachById(function (object $school) use ($publishedByCnpj): void {
                $cnpj = preg_replace('/\D/', '', (string) $school->cnpj);
                if (! isset($publishedByCnpj[$cnpj])) {
                    return;
                }

                $current = json_decode($school->document_authorizations ?? '[]', true, flags: JSON_THROW_ON_ERROR) ?? [];
                $updated = $current;
                foreach ($publishedByCnpj[$cnpj] as $key => $act) {
                    if (! collect($current[$key] ?? [])->contains(fn ($value) => filled($value))) {
                        $updated[$key] = $act;
                    }
                }
                if ($updated !== $current) {
                    DB::table('schools')->where('id', $school->id)->update([
                        'document_authorizations' => json_encode($updated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]);
                }

                if ($cnpj === '00176974000120') {
                    $this->fillFurnitureProgram($school->id);
                }
            });
    }

    public function down(): void
    {
        // Os atos são dados cadastrais que podem ter sido conferidos e editados após a importação.
    }

    private function act(string $act, string $publishedOn, string $startsOn, string $endsOn, string $edition): array
    {
        return [
            'act' => $act,
            'published_on' => $publishedOn,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'source_url' => 'https://iomat.mt.gov.br/portal/edicoes/download/'.$edition,
        ];
    }

    private function fillFurnitureProgram(int $schoolId): void
    {
        $published = [
            'authorization_act' => 'Ato 056/2025-CEE/MT',
            'official_gazette_reference' => 'DOE-MT nº 28.918, de 28/01/2025, p. 27 · https://iomat.mt.gov.br/portal/edicoes/download/18275#page=27',
            'authorization_starts_at' => '2025-01-28',
            'authorization_ends_at' => '2029-01-28',
            'regulatory_process' => '7/2023/SIPE/CEE-MT',
            'regulatory_opinion' => 'Parecer CEPS nº 8/2025',
            'technological_axis' => 'Produção Industrial',
            'education_mode' => 'Presencial',
            'offer_forms' => 'Concomitante e subsequente',
            'offer_modes' => '["concomitante","subsequente"]',
        ];
        foreach (DB::table('technical_programs')->where('school_id', $schoolId)->lockForUpdate()->get() as $program) {
            $name = Str::of($program->name)->ascii()->lower()->squish()->toString();
            if (! in_array($name, ['tecnico em moveis', 'curso tecnico em moveis'], true)) {
                continue;
            }
            if (filled($program->authorization_act) && ! preg_match('~\b0?56\s*/\s*2025\b~', $program->authorization_act)) {
                continue;
            }

            $period = ['authorization_starts_at', 'authorization_ends_at'];
            if (collect($period)->contains(fn ($field) => filled($program->{$field}) && $program->{$field} !== $published[$field])) {
                continue;
            }

            $missing = array_filter($published, fn ($field) => blank($program->{$field}), ARRAY_FILTER_USE_KEY);
            if ($missing !== []) {
                DB::table('technical_programs')->where('id', $program->id)->update($missing);
            }
        }
    }
};
