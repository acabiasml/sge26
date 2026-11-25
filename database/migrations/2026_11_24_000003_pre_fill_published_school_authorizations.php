<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Os arts. 2º e 31 da Resolução 630/2008-CEE/MT prorrogam sem novo prazo
        // os credenciamentos que venceriam a partir de 2009 (DOE nº 24.993, p. 21–23).
        $liceuAccreditation = $this->act('Portaria 294/2008-CEE/MT · DOE-MT nº 24.954 · Vigência: Res. 630/2008, art. 31', '2008-11-05', '2008-01-01', null, '1874#page=16');
        $lauraAccreditation = $this->act('Portaria 261/2008-CEE/MT · DOE-MT nº 24.930 · Vigência: Res. 630/2008, art. 31', '2008-09-30', '2008-01-01', null, '1850#page=30');
        // A Portaria 042/05 reconhece o Ensino Médio. O credenciamento decorre
        // do art. 44 da Resolução 384/04-CEE/MT, como consta no relatório do Topázio.
        $larAccreditation = $this->act('Portaria 042/05-CEE/MT c/c Res. 384/04, art. 44 · DOE-MT nº 24.067 · Vigência: Res. 630/2008, art. 31', '2005-03-14', '2005-01-01', null, '13720#page=28');
        $liceuBasicEducation = $this->act('Ato 104/2021-CEE/MT · DOE-MT nº 27.989', '2021-04-30', '2021-01-01', '2025-12-31', '16306#page=34')
            + ['renewal_protocol' => '1757/2025', 'renewal_filed_on' => '2025-10-23'];
        $lauraBasicEducation = $this->act('Ato 486/2025-CEE/MT · DOE-MT nº 29.007', '2025-06-10', '2025-01-01', '2029-12-31', '18503#page=39');
        $larElementary = $this->act('Ato 383/2025-CEE/MT · DOE-MT nº 28.986', '2025-05-12', '2025-01-01', '2027-12-31', '18440#page=38');
        $larHighSchool = $this->act('Ato 078/2023-CEE/MT · DOE-MT nº 28.437', '2023-02-13', '2023-01-01', '2027-12-31', '17136#page=29');

        $publishedByCnpj = [
            '00176974000120' => [
                'accreditation' => $liceuAccreditation,
                'fundamental' => $liceuBasicEducation,
                'fundamental_regular' => $liceuBasicEducation,
                'medio' => $liceuBasicEducation,
                'medio_regular' => $liceuBasicEducation,
            ],
            '00176974000201' => [
                'accreditation' => $lauraAccreditation,
                'fundamental' => $lauraBasicEducation,
                'fundamental_regular' => $lauraBasicEducation,
                'medio' => $lauraBasicEducation,
                'medio_regular' => $lauraBasicEducation,
            ],
            '00176974000392' => [
                'accreditation' => $larAccreditation,
                'fundamental' => $larElementary,
                'fundamental_educacao_do_campo' => $larElementary,
                'medio' => $larHighSchool,
                'medio_educacao_do_campo' => $larHighSchool,
            ],
        ];

        DB::transaction(function () use ($publishedByCnpj): void {
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
                    $changes = $this->updatedLetterhead($school, $cnpj);
                    if ($updated !== $current) {
                        $changes['document_authorizations'] = json_encode($updated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    }
                    if ($changes !== []) {
                        DB::table('schools')->where('id', $school->id)->update($changes);
                    }

                    if ($cnpj === '00176974000120') {
                        $this->fillFurnitureProgram($school->id);
                    }
                });
        });
    }

    public function down(): void
    {
        // Os atos são dados cadastrais que podem ter sido conferidos e editados após a importação.
    }

    private function updatedLetterhead(object $school, string $cnpj): array
    {
        if ($cnpj === '00176974000120'
            && $school->letterhead_text === 'Cred. Port. 294/2008, D.O. MT 05/11/2008. Aut. 104/2021-CEE/MT D.O. MT 30/04/21.') {
            return ['letterhead_text' => 'Cred. Portaria 294/2008-CEE/MT · DOE-MT de 05/11/2008.'];
        }
        if ($cnpj !== '00176974000392'
            || $school->letterhead_text !== 'Credenciada pela Portaria Nº 042/2005 CEE/MT e autorizada pelo Ato nº 674/2019 CEE/MT publicado em 12/12/2019.') {
            return [];
        }

        $accreditation = 'Cred. Portaria 042/05-CEE/MT c/c Res. 384/04, art. 44.';
        $changes = ['letterhead_text' => $accreditation];
        if (blank($school->letterhead_text_elementary)) {
            $changes['letterhead_text_elementary'] = $accreditation.' Fundamental do Campo: Ato 383/2025-CEE/MT · DOE-MT de 12/05/2025.';
        }
        if (blank($school->letterhead_text_high_school)) {
            $changes['letterhead_text_high_school'] = $accreditation.' Médio do Campo: Ato 078/2023-CEE/MT · DOE-MT de 13/02/2023.';
        }

        return $changes;
    }

    private function act(string $act, string $publishedOn, string $startsOn, ?string $endsOn, string $edition): array
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
