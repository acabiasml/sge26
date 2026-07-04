<div class="sge-action-buttons">
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.academic-years.index', $school) }}" aria-label="Gerenciar anos letivos de {{ $school->name }}" title="Anos letivos">
        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('schools.edit', $school) }}" aria-label="Editar escola {{ $school->name }}" title="Editar escola">
        <i class="fas fa-pen" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.pdf', $school) }}" aria-label="Emitir ficha em PDF da escola {{ $school->name }}" title="Ficha em PDF">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.concepts.index', $school) }}" aria-label="Gerenciar conceitos e critérios da escola {{ $school->name }}" title="Conceitos e critérios">
        <i class="fas fa-star-half-alt" aria-hidden="true"></i>
    </a>
    <form method="POST" action="{{ route('schools.destroy', $school) }}" onsubmit="return confirm('Excluir esta escola? Esta ação só será permitida se não houver vínculos cadastrados.');">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="Excluir escola {{ $school->name }}" title="Excluir escola">
            <i class="fas fa-trash-alt" aria-hidden="true"></i>
        </button>
    </form>
</div>
