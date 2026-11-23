@extends('layouts.app')
@section('title', 'Certificados arquivados')
@section('page-title', 'Certificados emitidos')
@section('content')
<p>{{ __('Os originais estão na ficha de cada pessoa. Para os certificados registrados no Livro Digital, use Abrir registro para arquivar assinaturas e o protocolo de entrega.') }}</p>
<div class="mb-3"><a class="btn btn-primary" href="{{ route('credential-archive.zip',$batch) }}">Baixar todos os PDFs em ZIP</a> <a class="btn btn-outline-secondary" href="{{ route('document-issuance.index') }}">Voltar à central</a></div>
<div class="card shadow"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Participante</th><th>Curso</th><th>Registro</th><th>Arquivo original</th></tr></thead><tbody>
@foreach($documents as $document)<tr><td><a href="{{ route('people.show',$document->person_id) }}#section-certificados">{{ $document->payload['participant_name'] }}</a></td><td>{{ $document->payload['course_name'] }}</td><td>{{ $document->payload['registration']['registry_number'] }} · Livro {{ $document->payload['registration']['registry_book'] }} @if($document->registration)<a class="d-block" href="{{ route('credential-registry.entry', $document->registration) }}">{{ __('Abrir registro') }}</a>@endif</td><td><a target="_blank" rel="noopener" href="{{ route('credential-archive.pdf',$document) }}">Abrir / reimprimir PDF</a> @include('partials.credential-delete',['document'=>$document])</td></tr>@endforeach
</tbody></table></div></div>
@endsection
