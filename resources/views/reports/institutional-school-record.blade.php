<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $document->payload['title'] }}</title>
    <style>
        @page { margin: 42px; }
        body { font: 12px DejaVu Sans, sans-serif; }
        h1 { font-size: 18px; }
        h2 { font-size: 15px; }
        dt { font-weight: bold; margin: 12px 0 4px; }
        dd { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
</head>
<body>
    @php($reference = $document->payload['registration'])
    <h1>{{ $school->name }}</h1>
    @if($school->cnpj)<p>CNPJ {{ $school->cnpj }}</p>@endif
    <h2>{{ $document->payload['title'] }}</h2>
    <p>Livro {{ $reference['registry_book'] }} · Folha {{ $reference['registry_folio'] }} · Registro {{ $reference['registry_number'] }}</p>
    <p>Registro em {{ \Illuminate\Support\Carbon::parse($reference['registration_date'])->format('d/m/Y') }} por {{ $reference['registered_by'] }}.</p>
    <dl>
        <dt>Data do ato</dt>
        <dd>{{ \Illuminate\Support\Carbon::parse($document->payload['act_date'])->format('d/m/Y') }}</dd>
        @foreach($document->payload['institutional_fields'] as $label => $value)
            <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
        @endforeach
    </dl>
    <p>Referência de integridade {{ $document->verification_code }}.</p>
</body>
</html>
