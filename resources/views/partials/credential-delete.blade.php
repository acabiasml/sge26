@if(auth()->user()->isAdministrator())
<form class="d-inline" method="POST" action="{{ route('credential-archive.destroy',$document) }}" data-confirm-message="Excluir este certificado ou diploma e seu PDF arquivado? A exclusão não poderá ser desfeita e ficará registrada na auditoria.">
@csrf @method('DELETE')
<button class="btn btn-sm btn-outline-danger ml-2" type="submit" aria-label="Excluir {{ $document->payload['title'] ?? 'certificado ou diploma' }}">Excluir</button>
</form>
@endif
