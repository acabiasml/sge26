@php($transfer = $evidenceType === 'received-transfer')
<div class="form-group">
    <label for="act-origin">{{ $transfer ? __('Escola de origem') : __('Identificação ou referência do processo') }}</label>
    <input class="form-control" id="act-origin" name="origin" maxlength="250" value="{{ old('origin') }}" required>
</div>
<div class="form-group">
    <label for="act-date">{{ $transfer ? __('Data do recebimento') : __('Data do ato') }}</label>
    <input class="form-control" type="date" id="act-date" name="recorded_on" max="{{ now('America/Cuiaba')->toDateString() }}" value="{{ old('recorded_on') }}" required>
</div>
@if($transfer)
    <div class="form-group">
        <label for="transferred-during-period">{{ __('A transferência ocorreu no meio do período letivo?') }}</label>
        <select class="form-control" name="transfer_during_period" id="transferred-during-period" required>
            <option value="">{{ __('Selecione') }}</option>
            <option value="1" @selected(old('transfer_during_period') === '1')>{{ __('Sim — anexar também a ficha individual') }}</option>
            <option value="0" @selected(old('transfer_during_period') === '0')>{{ __('Não') }}</option>
        </select>
    </div>
@else
    <div class="form-group">
        <label for="process-kind">{{ __('Tipo de processo') }}</label>
        <select class="form-control" id="process-kind" name="process_kind" required>
            <option value="classification" @selected(old('process_kind') === 'classification')>{{ __('Classificação') }}</option>
            <option value="reclassification" @selected(old('process_kind') === 'reclassification')>{{ __('Reclassificação') }}</option>
            <option value="placement" @selected(old('process_kind') === 'placement')>{{ __('Enturmação') }}</option>
        </select>
    </div>
    @foreach($processFields as $field => $label)
        <div class="form-group">
            <label for="process-{{ $field }}">{{ __($label) }}</label>
            <textarea class="form-control" id="process-{{ $field }}" name="{{ $field }}" rows="3" maxlength="6000" required>{{ old($field) }}</textarea>
        </div>
    @endforeach
    <fieldset class="border rounded p-3 mb-3" id="reclassification-summary" @disabled(old('process_kind') !== 'reclassification') @if(old('process_kind') !== 'reclassification') hidden @endif>
        <legend class="h6 w-auto">{{ __('Súmula da reclassificação') }}</legend>
        <p class="small text-muted">{{ __('Identifique os signatários e anexe a súmula separadamente da ata individual e dos demais comprovantes.') }}
            <a href="https://iomat.mt.gov.br/portal/edicoes/download/17304#page=53" target="_blank" rel="noopener">{{ __('Resolução nº 009/2023 do CEE-MT, art. 107, § 2º') }}</a>
        </p>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="reclassification-decision">{{ __('Decisão do processo') }}</label>
                <select class="form-control" id="reclassification-decision" name="reclassification_decision" required>
                    <option value="">{{ __('Selecione') }}</option>
                    <option value="approved" @selected(old('reclassification_decision') === 'approved')>{{ __('Deferida') }}</option>
                    <option value="denied" @selected(old('reclassification_decision') === 'denied')>{{ __('Indeferida') }}</option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label for="reclassification-ground">{{ __('Hipótese de reclassificação') }}</label>
                <select class="form-control" id="reclassification-ground" name="reclassification_ground" required>
                    <option value="">{{ __('Selecione') }}</option>
                    @foreach(\App\Support\ReclassificationProcess::GROUNDS as $value => $label)
                        <option value="{{ $value }}" @selected(old('reclassification_ground') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <fieldset id="reclassification-approved" class="mb-3">
            <legend class="sr-only">{{ __('Dados do deferimento') }}</legend>
            <div class="form-group">
                <label for="reclassification-target">{{ __('Matriz de destino deferida') }}</label>
                <select class="form-control" id="reclassification-target" name="reclassification_target_course_id" required>
                    <option value="">{{ __('Selecione') }}</option>
                    @foreach($reclassificationCourses as $course)
                        <option value="{{ $course->id }}" @selected((int) old('reclassification_target_course_id') === $course->id)>{{ $course->name }} · {{ $course->stageLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" id="reclassification-frequency">
                <label for="reclassification-attendance">{{ __('Frequência comprovada (%)') }}</label>
                <input class="form-control" id="reclassification-attendance" name="reclassification_attendance" type="number" min="75" max="100" step="0.01" value="{{ old('reclassification_attendance') }}">
            </div>
            <label class="d-block">
                <input type="checkbox" name="reclassification_criteria_reviewed" value="1" required @checked(old('reclassification_criteria_reviewed'))>
                {{ __('Conferi o desempenho e os critérios curriculares, de maturidade, competências e habilidades. A avaliação foi conduzida pelo Conselho de Classe, instância similar ou docente unidocente nos primeiros anos do Ensino Fundamental, com orientação e acompanhamento da coordenação ou supervisão pedagógica.') }}
            </label>
        </fieldset>
        @foreach($summarySigners as $field => $label)
            <div class="form-group">
                <label for="process-{{ $field }}">{{ __($label) }}</label>
                <textarea class="form-control" id="process-{{ $field }}" name="{{ $field }}" rows="2" maxlength="2000" required>{{ old($field) }}</textarea>
            </div>
        @endforeach
        <div class="form-group mb-0">
            <label for="process-summary-pdf">{{ __('Súmula assinada (PDF)') }}</label>
            <input class="form-control-file" id="process-summary-pdf" name="summary_pdf" type="file" accept="application/pdf" required>
        </div>
    </fieldset>
    @push('scripts')
        <script>
            (() => {
                const kind = document.getElementById('process-kind');
                const summary = document.getElementById('reclassification-summary');
                const decision = document.getElementById('reclassification-decision');
                const ground = document.getElementById('reclassification-ground');
                const approved = document.getElementById('reclassification-approved');
                const frequency = document.getElementById('reclassification-frequency');
                const attendance = document.getElementById('reclassification-attendance');
                const sync = () => {
                    summary.hidden = summary.disabled = kind.value !== 'reclassification';
                    approved.hidden = approved.disabled = decision.value !== 'approved';
                    frequency.hidden = attendance.disabled = ground.value !== 'attendance';
                    attendance.required = !frequency.hidden;
                };
                [kind, decision, ground].forEach((field) => field.addEventListener('change', sync));
                sync();
            })();
        </script>
    @endpush
@endif
<div class="form-group">
    <label for="act-method">{{ __('Forma do original') }}</label>
    <select class="form-control" id="act-method" name="signature_mode" required>
        <option value="physical">{{ __('Via em papel digitalizada') }}</option>
        <option value="digital">{{ __('Via digital') }}</option>
    </select>
</div>
<div class="form-group">
    <label for="act-original">{{ $transfer ? __('Histórico ou atestado recebido (PDF)') : __('Ata individual assinada (PDF)') }}</label>
    <input class="form-control-file" id="act-original" name="original_pdf" type="file" accept="application/pdf" required>
</div>
<div class="form-group">
    <label for="act-proof">{{ $transfer ? __('Ficha individual dos períodos parciais, quando aplicável (PDF)') : __('Documentação comprobatória (PDF)') }}</label>
    <input class="form-control-file" id="act-proof" name="supporting_pdf" type="file" accept="application/pdf" @required(!$transfer)>
</div>
<label class="d-block"><input type="checkbox" name="reviewed" value="1" required> {{ __('Conferi a identificação, a procedência, as assinaturas e os comprovantes do ato.') }}</label>
