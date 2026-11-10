<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class SocioeconomicProfile
{
    public const MANAGEMENT_FIELDS = ['benefit_year', 'benefits'];

    public static function editableFields(bool $canManageBenefits): array
    {
        $keys = array_keys(self::fields());

        return $canManageBenefits ? $keys : array_values(array_diff($keys, self::MANAGEMENT_FIELDS));
    }

    public static function fields(): array
    {
        return [
            'sex' => ['label' => 'Sexo', 'type' => 'select', 'options' => ['masculino' => 'Masculino', 'feminino' => 'Feminino']],
            'rg_number' => ['label' => 'RG', 'type' => 'text', 'max' => 40],
            'rg_issued_at' => ['label' => 'Data de emissão do RG', 'type' => 'date'],
            'rg_issuer' => ['label' => 'Órgão expedidor / UF', 'type' => 'text', 'max' => 60],
            'sus_card' => ['label' => 'Cartão do SUS', 'type' => 'digits', 'max' => 15],
            'race' => ['label' => 'Cor / raça declarada', 'type' => 'select', 'options' => ['branca' => 'Branca', 'preta' => 'Preta', 'parda' => 'Parda', 'amarela' => 'Amarela', 'indigena' => 'Indígena']],
            'blood_type' => ['label' => 'Tipo sanguíneo', 'type' => 'select', 'options' => array_combine(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'has_disability' => ['label' => 'Possui deficiência (PCD)', 'type' => 'boolean'],
            'disability_details' => ['label' => 'Descrição da deficiência', 'type' => 'text', 'max' => 500],
            'residential_zone' => ['label' => 'Localização residencial', 'type' => 'select', 'options' => ['urbana' => 'Urbana', 'rural' => 'Rural']],
            'consumer_unit' => ['label' => 'Código da unidade consumidora', 'type' => 'text', 'max' => 60],
            'lives_with' => ['label' => 'Reside com', 'type' => 'choices', 'options' => ['mae' => 'Mãe', 'pai' => 'Pai', 'madrasta' => 'Madrasta', 'padrasto' => 'Padrasto', 'avos_maternos' => 'Avós maternos', 'avos_paternos' => 'Avós paternos', 'outros' => 'Outros']],
            'lives_with_other' => ['label' => 'Outras pessoas com quem reside', 'type' => 'text', 'max' => 500],
            'family_income_range' => ['label' => 'Renda familiar em salários mínimos', 'type' => 'select', 'options' => ['menos_2' => 'Menos de 2 salários mínimos', '2_a_3' => 'De 2 a 3 salários mínimos', '3_a_4' => 'Mais de 3 até 4 salários mínimos', '4_a_5' => 'Mais de 4 até 5 salários mínimos', 'mais_5' => 'Mais de 5 salários mínimos']],
            'receives_bolsa_familia' => ['label' => 'Recebe Bolsa Família', 'type' => 'boolean'],
            'bolsa_familia_nis' => ['label' => 'NIS do beneficiário do Bolsa Família', 'type' => 'digits', 'max' => 11],
            'guardians_work_outside' => ['label' => 'Mãe ou responsáveis trabalham fora', 'type' => 'boolean'],
            'authorized_pickup' => ['label' => 'Pessoas autorizadas a buscar o estudante', 'type' => 'textarea', 'max' => 1000],
            'uses_school_transport' => ['label' => 'Utiliza transporte escolar', 'type' => 'boolean'],
            'image_use_authorized' => ['label' => 'Autoriza o uso de imagem', 'type' => 'boolean'],
            'has_allergies' => ['label' => 'Apresenta algum tipo de alergia', 'type' => 'boolean'],
            'allergy_details' => ['label' => 'Descrição das alergias', 'type' => 'text', 'max' => 500],
            'has_medication_food_allergies' => ['label' => 'Possui alergia a medicamentos ou alimentos', 'type' => 'boolean'],
            'medication_food_allergy_details' => ['label' => 'Medicamentos ou alimentos que causam alergia', 'type' => 'text', 'max' => 500],
            'benefit_year' => ['label' => 'Ano de referência dos benefícios', 'type' => 'year'],
            'benefits' => ['label' => 'Benefícios concedidos', 'type' => 'choices', 'options' => ['material_didatico' => 'Material didático', 'alimentacao' => 'Alimentação', 'acompanhamento_pedagogico' => 'Acompanhamento pedagógico', 'estrutura_educacional' => 'Estrutura física educacional adequada', 'orientacao_familiar' => 'Acompanhamento e orientação familiar', 'acompanhamento_psicopedagogico' => 'Acompanhamento psicopedagógico']],
            'notes' => ['label' => 'Observações socioeconômicas', 'type' => 'textarea', 'max' => 2000],
        ];
    }

    public static function sections(): array
    {
        return [
            'Identificação complementar' => ['sex', 'rg_number', 'rg_issued_at', 'rg_issuer', 'sus_card', 'race', 'blood_type'],
            'Família e residência' => ['residential_zone', 'consumer_unit', 'lives_with', 'lives_with_other', 'family_income_range', 'receives_bolsa_familia', 'bolsa_familia_nis', 'guardians_work_outside', 'authorized_pickup', 'uses_school_transport', 'image_use_authorized'],
            'Saúde e cuidados' => ['has_disability', 'disability_details', 'has_allergies', 'allergy_details', 'has_medication_food_allergies', 'medication_food_allergy_details'],
            'Concessão de benefícios' => ['benefit_year', 'benefits', 'notes'],
        ];
    }

    public static function rules(?array $allowedFields = null): array
    {
        $fields = $allowedFields === null ? self::fields() : array_intersect_key(self::fields(), array_flip($allowedFields));
        $rules = [
            'socioeconomic_data' => ['sometimes', 'nullable', 'array:'.implode(',', [...array_keys($fields), 'assessed_at'])],
            // Formulários antigos podem enviá-la; o servidor sempre ignora essa data.
            'socioeconomic_data.assessed_at' => ['exclude'],
        ];
        foreach ($fields as $key => $field) {
            $name = 'socioeconomic_data.'.$key;
            $rules[$name] = match ($field['type']) {
                'boolean' => ['nullable', 'boolean'],
                'select' => ['nullable', Rule::in(array_keys($field['options']))],
                'choices' => ['nullable', 'array', 'max:'.count($field['options'])],
                'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'year' => ['nullable', 'integer', 'between:1900,2100', 'required_with:socioeconomic_data.benefits'],
                'digits' => ['nullable', 'string', 'regex:/^[0-9]{'.$field['max'].'}$/'],
                default => ['nullable', 'string', 'max:'.$field['max']],
            };
            if ($field['type'] === 'choices') $rules[$name.'.*'] = ['string', 'distinct', Rule::in(array_keys($field['options']))];
        }

        return $rules;
    }

    public static function normalize(array $data, bool $completeForm): array
    {
        foreach (self::fields() as $key => $field) {
            if ($completeForm && $field['type'] === 'choices') $data[$key] ??= [];
            if (! array_key_exists($key, $data)) continue;
            if ($data[$key] === '') $data[$key] = null;
            if ($data[$key] !== null && $field['type'] === 'boolean') $data[$key] = (bool) $data[$key];
            if ($data[$key] !== null && $field['type'] === 'year') $data[$key] = (int) $data[$key];
        }

        return $data;
    }

    public static function label(string $key, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) return __('Não informado');
        $field = self::fields()[$key];
        if ($field['type'] === 'boolean') return $value ? __('Sim') : __('Não');
        if ($field['type'] === 'choices') return collect($value)->map(fn ($item) => __($field['options'][$item] ?? $item))->join(', ');

        return isset($field['options'][$value]) ? __($field['options'][$value]) : (string) $value;
    }

    public static function saved(array $profile): array
    {
        $profile['assessed_at'] = now()->utc()->toIso8601String();

        return $profile;
    }

    public static function lastSavedLabel(array $profile): ?string
    {
        $value = $profile['assessed_at'] ?? null;
        if (blank($value)) return null;
        $date = \Illuminate\Support\Carbon::parse($value);
        // Preserva a interpretação das datas antigas que ainda não possuem horário.
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value)) return $date->format('d/m/Y');

        return $date->timezone('America/Cuiaba')->format('d/m/Y H:i');
    }

    public static function attributes(): array
    {
        $attributes = ['socioeconomic_data' => __('Dados socioeconômicos')];
        foreach (self::fields() as $key => $field) {
            $attributes['socioeconomic_data.'.$key] = __($field['label']);
            if ($field['type'] === 'choices') $attributes['socioeconomic_data.'.$key.'.*'] = __($field['label']);
        }

        return $attributes;
    }
}
