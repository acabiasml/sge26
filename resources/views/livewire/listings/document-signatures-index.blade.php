<div>
<div class="card sge-panel-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <caption class="sr-only">{{ __('Arquivos preparados para assinatura') }}</caption>
            <thead><tr><th scope="col">{{ __('Documento') }}</th><th scope="col">{{ __('Código') }}</th><th scope="col">{{ __('Data') }}</th><th scope="col">{{ __('Versões assinadas') }}</th></tr></thead>
            <tbody>
            @forelse($documents as $document)
                <tr><td><a href="{{ route('document-signatures.show', $document) }}">{{ $document->payload['title'] ?? \App\Support\DocumentVerificationPresenter::typeLabel($document->type) }}</a></td><td>{{ $document->verification_code }}</td><td>{{ $document->issued_at?->timezone('America/Cuiaba')->format('d/m/Y H:i') }}</td><td>{{ $document->signature_versions_count }}</td></tr>
            @empty
                <tr><td colspan="4">{{ __('Escolha a assinatura digital na emissão para preparar um PDF.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $documents->links('livewire::bootstrap') }}</div>
</div>
</div>
