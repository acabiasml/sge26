@php($itineraryKey = 'matrix-itinerary-'.($course->id ?: 'new'))
@php($selectedTechnicalIds = old('technical_itinerary_ids', $course->exists ? $course->technicalItineraries->pluck('id')->all() : []))
<fieldset data-matrix-itinerary>
    <legend class="h5">{{ __('Itinerário Formativo') }}</legend>
    <p class="text-muted">{{ __('Indique quais componentes compõem o itinerário desta matriz de Ensino Médio. Você pode usar os componentes da própria matriz ou incorporar um curso técnico já cadastrado.') }}</p>
    <div class="form-group">
        <label for="{{ $itineraryKey }}-kind">{{ __('Como o itinerário será oferecido?') }}</label>
        <select class="form-control" id="{{ $itineraryKey }}-kind" name="itinerary_kind" data-itinerary-kind>
            <option value="own" @selected(old('itinerary_kind', count($selectedTechnicalIds) ? 'technical' : 'own') === 'own')>{{ __('Componentes da própria matriz ou turma') }}</option>
            <option value="technical" @selected(old('itinerary_kind', count($selectedTechnicalIds) ? 'technical' : 'own') === 'technical')>{{ __('Curso técnico já cadastrado') }}</option>
        </select>
        @error('itinerary_kind')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group" data-itinerary-own>
        <label for="{{ $itineraryKey }}-name">{{ __('Nome do itinerário formativo') }}</label>
        <input id="{{ $itineraryKey }}-name" name="itinerary_name" class="form-control" value="{{ old('itinerary_name', $course->itinerary_name) }}">
        <small class="form-text text-muted">{{ __('Cadastre os componentes na formação Itinerário Formativo da própria matriz, ou utilize a oferta já vinculada à turma.') }}</small>
        @error('itinerary_name')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group" data-itinerary-technical>
        <label for="{{ $itineraryKey }}-course">{{ __('Curso técnico que compõe o itinerário') }}</label>
        <select id="{{ $itineraryKey }}-course" name="technical_itinerary_ids[]" class="form-control" aria-describedby="{{ $itineraryKey }}-help">
            <option value="">{{ __('Selecione o curso') }}</option>
            @foreach($technicalOffers as $technicalOffer)
                <option value="{{ $technicalOffer->id }}" @selected(in_array($technicalOffer->id, $selectedTechnicalIds))>{{ $technicalOffer->name }} · {{ ($technicalOffer->startsPeriod?->starts_at ?? $technicalOffer->academicYear->starts_at)?->format('d/m/Y') }} – {{ ($technicalOffer->endsPeriod?->ends_at ?? $technicalOffer->academicYear->ends_at)?->format('d/m/Y') }}@if($technicalOffer->matrix_students_count) · {{ __(':matched de :total alunos já matriculados', ['matched' => $technicalOffer->matching_students_count, 'total' => $technicalOffer->matrix_students_count]) }}@endif</option>
            @endforeach
        </select>
        <small id="{{ $itineraryKey }}-help" class="form-text text-muted">{{ __('São listadas ofertas da mesma escola com datas compatíveis com esta matriz. A escolha vale para todas as turmas vinculadas. Os módulos são incorporados no ano em que terminam, usando os registros da matrícula técnica de cada estudante.') }}</small>
        @if($technicalOffers->isEmpty())<p class="text-muted mt-2">{{ __('Nenhuma oferta técnica compatível. Cadastre primeiro a matriz do curso técnico e confira suas datas.') }}</p>@endif
        @error('technical_itinerary_ids')<div class="text-danger">{{ $message }}</div>@enderror
        @error('technical_itinerary_ids.*')<div class="text-danger">{{ $message }}</div>@enderror
    </div>
</fieldset>
@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-matrix-itinerary]').forEach(fields => {
        const kind = fields.querySelector('[data-itinerary-kind]');
        const stage = document.getElementById('stage');
        const sync = () => {
            const enabled = !stage || stage.value === 'medio';
            fields.hidden = !enabled;
            kind.disabled = !enabled;
            [['[data-itinerary-own]', 'own'], ['[data-itinerary-technical]', 'technical']].forEach(([selector, value]) => {
                const group = fields.querySelector(selector);
                group.hidden = !enabled || kind.value !== value;
                group.querySelectorAll('input, select').forEach(control => { control.disabled = group.hidden; });
            });
        };
        kind.addEventListener('change', sync);
        if (stage) stage.addEventListener('change', sync);
        sync();
    });
});
</script>
@endpush
@endonce
