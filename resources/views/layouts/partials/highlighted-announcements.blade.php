@foreach ($highlightedAnnouncements ?? [] as $highlighted)
<div class="modal fade sge-highlight-modal" id="highlighted-{{ $highlighted->id }}" tabindex="-1" role="dialog" aria-labelledby="highlighted-title-{{ $highlighted->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header align-items-start">
                <div>
                    <div class="text-primary small font-weight-bold mb-3"><i class="fas fa-bullhorn mr-2" aria-hidden="true"></i>{{ __('Recado em destaque') }}</div>
                    <h2 class="modal-title h4 font-weight-bold" id="highlighted-title-{{ $highlighted->id }}">{{ $highlighted->title }}</h2>
                    <p class="text-muted small mt-2 mb-0">{{ $highlighted->school?->name ?? __('screens.global_all_schools') }} · {{ $highlighted->starts_at->format('d/m/Y') }}</p>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="{{ __('alerts.close') }}"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body"><div style="white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.8">{{ $highlighted->body }}</div></div>
            <div class="modal-footer flex-column align-items-stretch">
                <p class="small text-muted mb-2">{{ __('Ao marcar como visto, este recado não abrirá novamente nos próximos acessos.') }}</p>
                <p class="text-danger small d-none" role="alert" data-seen-error>{{ __('Não foi possível salvar. Tente novamente.') }}</p>
                <div class="d-flex flex-wrap justify-content-end" style="gap: .75rem">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('Fechar por enquanto') }}</button>
                    <button type="button" class="btn btn-primary" data-mark-seen="{{ route('announcements.seen', $highlighted) }}"><i class="fas fa-check mr-2" aria-hidden="true"></i>{{ __('Marcar como já visto') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach
@push('scripts')
<script src="{{ asset('template/js/sge-highlighted-announcements.js') }}"></script>
@endpush
