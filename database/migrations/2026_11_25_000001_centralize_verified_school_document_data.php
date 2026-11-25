<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $knownSchools = [
            // Referências: DOE-MT 16306, p. 34; 18503, p. 39; 18440, p. 38; 17136, p. 29.
            '00176974000120' => ['regular', ['fundamental' => '104/2021', 'medio' => '104/2021'], [
                'Cred. Portaria 294/2008-CEE/MT · DOE-MT de 05/11/2008.', null, null,
            ]],
            '00176974000201' => ['regular', ['fundamental' => '486/2025', 'medio' => '486/2025'], [
                'Ato de criação 001/93 C.S.G.C. Autorização 486/2025-CEE/MT.', null, null,
            ]],
            '00176974000392' => ['educacao_do_campo', ['fundamental' => '383/2025', 'medio' => '078/2023'], [
                'Cred. Portaria 042/05-CEE/MT c/c Res. 384/04, art. 44.',
                'Cred. Portaria 042/05-CEE/MT c/c Res. 384/04, art. 44. Fundamental do Campo: Ato 383/2025-CEE/MT · DOE-MT de 12/05/2025.',
                'Cred. Portaria 042/05-CEE/MT c/c Res. 384/04, art. 44. Médio do Campo: Ato 078/2023-CEE/MT · DOE-MT de 13/02/2023.',
            ]],
        ];

        DB::transaction(function () use ($knownSchools): void {
            DB::table('schools')->where('state', 'MT')->orderBy('id')->lockForUpdate()
                ->eachById(function (object $school) use ($knownSchools): void {
                    $cnpj = preg_replace('/\D/', '', (string) $school->cnpj);
                    if (! isset($knownSchools[$cnpj])) {
                        return;
                    }

                    [$modality, $confirmedByStage, $oldLetterhead] = $knownSchools[$cnpj];
                    $changes = [];
                    $acts = json_decode($school->document_authorizations ?? '[]', true, flags: JSON_THROW_ON_ERROR) ?? [];
                    $updated = $acts;
                    foreach ($confirmedByStage as $stage => $number) {
                        $specificAct = $acts[$stage.'_'.$modality] ?? [];
                        if (str_starts_with($specificAct['act'] ?? '', 'Ato '.$number.'-CEE/MT')
                            && ($acts[$stage] ?? null) == $specificAct) {
                            unset($updated[$stage]);
                        }
                    }
                    if ($updated !== $acts) {
                        $changes['document_authorizations'] = json_encode($updated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    }

                    $maintainer = 'Centro Técnico Juvenil de Jarudore';
                    if (blank($school->maintainer)
                        || ($cnpj === '00176974000201' && $school->maintainer === 'Centro Social General Carneiro')) {
                        $changes['maintainer'] = $maintainer;
                    }
                    if (blank($school->education_system)) {
                        $changes['education_system'] = 'Sistema Estadual de Ensino de Mato Grosso';
                    }

                    if ($cnpj === '00176974000201') {
                        // A mudança da mantenedora de General Carneiro consta da Resolução 012/09-CEE/MT.
                        if ($school->legal_name === 'Centro Social General Carneiro') {
                            $changes['legal_name'] = $maintainer;
                        }
                        if (blank($school->creation_act) && str_starts_with((string) $school->letterhead_text, 'Ato de criação 001/93 C.S.G.C.')) {
                            $changes['creation_act'] = 'Ato 001/93 · Centro Social de General Carneiro';
                        }
                    }

                    $letterheadFields = ['letterhead_text', 'letterhead_text_elementary', 'letterhead_text_high_school'];
                    $currentLetterhead = array_map(fn (string $field) => $school->{$field}, $letterheadFields);
                    if ($currentLetterhead === $oldLetterhead && filled($updated['accreditation']['act'] ?? null)) {
                        $changes += array_fill_keys($letterheadFields, null);
                    }

                    if ($changes !== []) {
                        DB::table('schools')->where('id', $school->id)->update($changes);
                    }
                });
        });
    }

    public function down(): void
    {
        // Preserva os dados cadastrais que a escola pode ter atualizado desde a publicação.
    }
};
