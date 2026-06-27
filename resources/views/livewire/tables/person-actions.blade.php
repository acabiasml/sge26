<div class="sge-action-buttons">
    <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('people.show', $person) }}" aria-label="Abrir cadastro de {{ $person->full_name }}" title="Abrir cadastro">
        <i class="fas fa-folder-open" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.pdf', $person) }}" aria-label="Emitir ficha em PDF de {{ $person->full_name }}" title="Ficha em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
</div>
