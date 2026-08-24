@php($isEdit = $course->exists)

<form method="POST" action="{{ $isEdit ? route('academic-years.courses.update', [$academicYear, $course]) : route('academic-years.courses.store', $academicYear) }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Dados da matriz</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="name">Nome</label>
                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $course->name) }}" placeholder="9º Ano, 3º Ano, Técnico em Móveis..." required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="stage">Etapa</label>
                    <select id="stage" name="stage" class="form-control @error('stage') is-invalid @enderror" required>
                        @foreach (\App\Models\AcademicCourse::STAGE_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('stage', $course->stage) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('stage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="modality">Modalidade</label>
                    <select id="modality" name="modality" class="form-control @error('modality') is-invalid @enderror" required>
                        @foreach (\App\Models\AcademicCourse::MODALITY_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('modality', $course->modality) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('modality') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="itinerary_name">Nome do itinerário formativo <span class="text-muted">(opcional)</span></label>
                <input id="itinerary_name" name="itinerary_name" class="form-control @error('itinerary_name') is-invalid @enderror" value="{{ old('itinerary_name', $course->itinerary_name) }}" placeholder="Ex.: Ciências da Natureza e Projeto de Vida">
                <small class="form-text text-muted">No Ensino Médio, quando ficar vazio, será apresentado como “Aprofundamento de Estudos”. Em curso técnico, o próprio nome da matriz identifica o itinerário.</small>
                @error('itinerary_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <section id="technical-regulation" class="card border-left-primary mb-4" @if(old('stage', $course->stage) !== \App\Models\AcademicCourse::STAGE_TECHNICAL) hidden @endif>
                <div class="card-body">
                    <h3 class="h6 font-weight-bold text-primary">Regulamentação da oferta técnica nesta escola</h3>
                    <p class="text-muted">Estes dados pertencem a esta oferta e serão impressos nos documentos em que o curso técnico aparecer.</p>
                    <div class="form-group">
                        <label for="technical_legal_basis">Fundamento legal da Educação Profissional Técnica</label>
                        <textarea id="technical_legal_basis" name="technical_legal_basis" class="form-control" rows="3" placeholder="Lei, resolução e diretrizes nacionais/estaduais">{{ old('technical_legal_basis', $course->technical_legal_basis ?: 'Lei Federal nº 9.394/1996 (LDB), arts. 36-B a 42; Lei Federal nº 11.741/2008; Resolução CNE/CP nº 1/2021; Resolução CNE/CEB nº 2/2020 (CNCT).') }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 form-group"><label for="accreditation_act">Ato de credenciamento da instituição</label><textarea id="accreditation_act" name="accreditation_act" class="form-control" rows="2">{{ old('accreditation_act', $course->accreditation_act) }}</textarea></div>
                        <div class="col-lg-6 form-group"><label for="authorization_act">Ato de autorização do curso</label><textarea id="authorization_act" name="authorization_act" class="form-control" rows="2">{{ old('authorization_act', $course->authorization_act) }}</textarea></div>
                        <div class="col-md-4 form-group"><label for="regulatory_process">Processo</label><input id="regulatory_process" name="regulatory_process" class="form-control" value="{{ old('regulatory_process', $course->regulatory_process) }}"></div>
                        <div class="col-md-4 form-group"><label for="regulatory_opinion">Parecer</label><input id="regulatory_opinion" name="regulatory_opinion" class="form-control" value="{{ old('regulatory_opinion', $course->regulatory_opinion) }}"></div>
                        <div class="col-md-4 form-group"><label for="technological_axis">Eixo tecnológico</label><input id="technological_axis" name="technological_axis" class="form-control" value="{{ old('technological_axis', $course->technological_axis) }}"></div>
                        <div class="col-md-6 form-group"><label for="offer_forms">Forma(s) de oferta</label><input id="offer_forms" name="offer_forms" class="form-control" value="{{ old('offer_forms', $course->offer_forms) }}" placeholder="Concomitante, subsequente, presencial..."></div>
                        <div class="col-md-6 form-group"><label for="official_gazette_reference">Publicação no Diário Oficial</label><input id="official_gazette_reference" name="official_gazette_reference" class="form-control" value="{{ old('official_gazette_reference', $course->official_gazette_reference) }}"></div>
                        <div class="col-md-3 form-group"><label for="authorization_starts_at">Início da autorização</label><input type="date" id="authorization_starts_at" name="authorization_starts_at" class="form-control" value="{{ old('authorization_starts_at', $course->authorization_starts_at?->format('Y-m-d')) }}"></div>
                        <div class="col-md-3 form-group"><label for="authorization_ends_at">Fim da autorização</label><input type="date" id="authorization_ends_at" name="authorization_ends_at" class="form-control" value="{{ old('authorization_ends_at', $course->authorization_ends_at?->format('Y-m-d')) }}"></div>
                        <div class="col-md-6 form-group"><label for="module_certifications">Certificações intermediárias por módulo</label><textarea id="module_certifications" name="module_certifications" class="form-control" rows="3" placeholder="Uma certificação por linha, na ordem dos períodos avaliativos">{{ old('module_certifications', $course->module_certifications) }}</textarea></div>
                    </div>
                </div>
            </section>

            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="class_hour_minutes">Minutos da hora-aula</label>
                    <input id="class_hour_minutes" name="class_hour_minutes" data-mask="digits" data-mask-max="3" inputmode="numeric" autocomplete="off" class="form-control @error('class_hour_minutes') is-invalid @enderror" value="{{ old('class_hour_minutes', $course->class_hour_minutes ?? 50) }}" required>
                    @error('class_hour_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-9 form-group">
                    <label for="notes">Observações</label>
                    <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $course->notes) }}</textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a class="btn btn-outline-secondary" href="{{ $isEdit ? route('academic-years.courses.show', [$academicYear, $course]) : route('academic-years.show', $academicYear) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Salvar matriz' : 'Cadastrar matriz' }}</button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const stage = document.getElementById('stage');
    const regulation = document.getElementById('technical-regulation');
    const sync = () => { regulation.hidden = stage.value !== @json(\App\Models\AcademicCourse::STAGE_TECHNICAL); };
    stage.addEventListener('change', sync);
    sync();
});
</script>
@endpush
