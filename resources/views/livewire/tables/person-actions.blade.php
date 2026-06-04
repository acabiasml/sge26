<div class="d-inline-flex flex-wrap justify-content-end">
    <a class="btn btn-sm btn-primary mb-1" href="{{ route('people.show', $person) }}">Abrir</a>
    <a class="btn btn-sm btn-outline-primary ml-1 mb-1" href="{{ route('people.pdf', $person) }}">
        <i class="fas fa-file-pdf fa-sm"></i> PDF
    </a>
</div>
