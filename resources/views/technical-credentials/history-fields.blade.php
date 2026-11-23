<div class="form-group">
    <label for="transcript-module">{{ __('Abrangência do histórico técnico') }}</label>
    <select class="form-control" id="transcript-module" name="module_key">
        <option value="">{{ __('Curso completo') }}</option>
        @foreach($technicalModules as $module)
            <option value="{{ $module['key'] }}" @selected(old('module_key') === $module['key'])>{{ $module['qualification'] }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label for="professional-profile">{{ __('Perfil profissional e competências do plano aprovado') }}</label>
    <textarea class="form-control" id="professional-profile" name="professional_profile" maxlength="5000" rows="4" required>{{ old('professional_profile') }}</textarea>
</div>
<div class="form-group">
    <label for="professional-practice">{{ __('Estágio ou prática profissional e carga horária. Informe se não se aplica.') }}</label>
    <textarea class="form-control" id="professional-practice" name="professional_practice" maxlength="3000" rows="3" required>{{ old('professional_practice') }}</textarea>
</div>
