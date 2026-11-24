@extends(auth()->check() ? 'layouts.app' : 'layouts.verification')

@section('title', __('Emissão urgente de certificado ou diploma'))
@section('page-title', __('Emissão urgente de certificado ou diploma'))

@section('content')
<article class="card shadow mx-auto sge-narrow-card">
    <div class="card-body p-4">
        <h1 class="h4">{{ __('Pedir emissão urgente') }}</h1>
        <p>{{ __('O concluinte pode solicitar prioridade na expedição do certificado ou diploma nas seguintes situações.') }}</p>
        <ul>
            <li>{{ __('Nomeação em concurso público.') }}</li>
            <li>{{ __('Mudança de residência para outro estado ou país.') }}</li>
            <li>{{ __('Aprovação em pós-graduação stricto sensu.') }}</li>
        </ul>
        <ol>
            <li>{{ __('Procure a secretaria da escola que ofertou o curso e informe qual documento precisa receber.') }}</li>
            <li>{{ __('Apresente documento de identificação e a comprovação da situação. Se houver prazo para apresentar o certificado ou diploma, informe a data.') }}</li>
            <li>{{ __('Solicite o protocolo do pedido para acompanhar a análise e combine com a secretaria a forma de retirada ou entrega.') }}</li>
        </ol>
        <p>{{ __('A escola analisa a comprovação e confere os requisitos de conclusão, registro e assinatura para expedir o documento.') }}</p>
        <x-school-document-regulation :articles="__('art. 37')" :page="23" />
    </div>
</article>
@endsection
