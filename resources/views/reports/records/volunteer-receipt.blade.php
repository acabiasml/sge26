<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 36px 42px 108px; }
        body { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 12px; color: #262626; line-height: 1.65; }
        @include('reports.partials.letterhead-styles')
        .receipt-body, .receipt-truth, .receipt-date, .receipt-signature { font-size: 12pt; }
        .receipt-body { margin-top: 45px; text-align: justify; text-indent: 35px; }
        .receipt-truth { margin-top: 27px; text-indent: 35px; }
        .receipt-date { text-align: center; margin-top: 70px; }
        .receipt-signature { text-align: center; margin: 85px auto 0; width: 320px; border-top: 1px solid #333; line-height: 1.3; padding-top: 5px; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Recibo mensal de atividade de voluntário',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'repeatOnEveryPage' => false,
        'showTechnicalRegulation' => false,
    ])

    <p class="receipt-body">Eu, abaixo assinado, <strong>{{ $person->full_name }}</strong>, residente e domiciliado(a) em {{ $address }}, inscrito(a) no CPF nº {{ $person->cpf }}, recebi o valor de <strong>R$ {{ $amount }} ({{ $amountWords }})</strong> a título de ressarcimento de despesas com transporte e alimentação referentes ao serviço voluntário em atividades desenvolvidas no {{ \App\Support\PdfLetterhead::MAINTAINER_NAME }} – {{ $school->name }}, no período de {{ $periodLabel }}.</p>

    <p class="receipt-truth">Por ser verdade, assino o presente.</p>
    <p class="receipt-date">{{ $school->city }}/{{ $school->state }}, {{ $issuedDate->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y') }}.</p>
    <div class="receipt-signature">Assinatura do Voluntário</div>

    @include('reports.partials.document-footer', [
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
    ])
</body>
</html>
