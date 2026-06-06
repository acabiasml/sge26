<div class="d-inline-flex flex-wrap justify-content-end">
    <a class="btn btn-sm btn-primary mb-1" href="{{ route('schools.edit', $school) }}">Editar</a>
    <a class="btn btn-sm btn-outline-primary ml-1 mb-1" href="{{ route('schools.pdf', $school) }}">
        <i class="fas fa-file-pdf fa-sm"></i> PDF
    </a>
    <form method="POST" action="{{ route('schools.destroy', $school) }}" class="ml-1 mb-1" onsubmit="return confirm('Excluir esta escola? Esta ação só será permitida se não houver vínculos cadastrados.');">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
    </form>
</div>
