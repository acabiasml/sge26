<div>
@if($versions->total() > 0)
    <section class="card sge-panel-card">
        <div class="card-header"><h2 class="h6 mb-0">{{ __('Versões arquivadas') }}</h2></div>
        <ul class="list-group list-group-flush">@foreach($versions as $version)
            <li class="list-group-item"><strong>{{ $version->complete ? __('Completa') : __('Parcial') }}</strong> · {{ $version->signers }} · {{ $version->created_at->timezone('America/Cuiaba')->format('d/m/Y H:i') }}
                <div><a href="{{ route('document-signatures.download', [$version, 'assinado']) }}">{{ __('PDF assinado') }}</a> · <a href="{{ route('document-signatures.download', [$version, 'validacao']) }}">{{ __('Relatório de validação') }}</a></div>
            </li>
        @endforeach</ul>
        <div class="card-body">{{ $versions->links('livewire::bootstrap') }}</div>
    </section>
@endif
</div>
