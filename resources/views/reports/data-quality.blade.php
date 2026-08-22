<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 20px 24px 34px; }
        body { color: #2f241f; font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.28; }
        @include('reports.partials.letterhead-styles')
        h2 { color: #6B3D2E; font-size: 15px; margin: 14px 0 6px; }
        h3 { color: #44693D; font-size: 12px; margin: 10px 0 4px; }
        p { margin: 0 0 5px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #6B3D2E; color: #fff; font-weight: 600; padding: 4.5px 5px; text-align: left; }
        td { border-bottom: .6px solid #e5d8cf; padding: 4px 5px; vertical-align: top; }
        .summary { display: table; margin: 10px 0 12px; width: 100%; }
        .summary div { border: 1px solid #d8c8bd; display: table-cell; padding: 7px; text-align: center; width: 25%; }
        .summary span { color: #6d5a51; display: block; font-size: 11px; text-transform: uppercase; }
        .summary strong { color: #6B3D2E; display: block; font-size: 18px; }
        .workflow { border: 1px solid #d8c8bd; margin-bottom: 6px; padding: 6px; }
        .workflow strong { color: #6B3D2E; }
        .badge { border-radius: 9px; color: #fff; display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 6px; }
        .danger { background: #b54a34; }
        .warning { background: #c88722; }
        .info { background: #3f89a1; }
        .muted { color: #6d5a51; }
        .group { page-break-inside: avoid; }
    </style>
</head>
<body>
    @include('reports.partials.letterhead', [
        'title' => 'Relatório de conformidade documental e acadêmica',
        'letterhead' => $letterhead,
        'issuedDocument' => $issuedDocument,
        'verificationUrl' => $verificationUrl,
    ])

    <p class="muted">
        Conferência emitida em {{ now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}.
        @if ($selectedSeverity)
            Filtro de gravidade aplicado.
        @endif
    </p>

    <div class="summary">
        <div><span>Total</span><strong>{{ number_format($summary['total'], 0, ',', '.') }}</strong></div>
        <div><span>Bloqueios</span><strong>{{ number_format($summary['danger'], 0, ',', '.') }}</strong></div>
        <div><span>Avisos</span><strong>{{ number_format($summary['warning'], 0, ',', '.') }}</strong></div>
        <div><span>Atenções</span><strong>{{ number_format($summary['info'], 0, ',', '.') }}</strong></div>
    </div>

    <h2>Fluxos acompanhados</h2>
    <table>
        <thead>
            <tr>
                <th>Fluxo</th>
                <th>O que verifica</th>
                <th style="width: 70px;">Ocorrências</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($workflows as $workflow)
                <tr>
                    <td><strong>{{ $workflow['title'] }}</strong></td>
                    <td>{{ $workflow['description'] }}</td>
                    <td>{{ number_format($workflow['count'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($displayGroups as $group)
        <section class="group">
            <h2>{{ $group['title'] }}</h2>
            <p class="muted">{{ $group['description'] }}</p>

            <table>
                <thead>
                    <tr>
                        <th style="width: 170px;">Verificação</th>
                        <th style="width: 60px;">Gravidade</th>
                        <th style="width: 55px;">Total</th>
                        <th>Amostra dos registros</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['checks'] as $check)
                        @php
                            $severityLabel = match ($check['severity']) {
                                'danger' => 'Bloqueio',
                                'warning' => 'Aviso',
                                default => 'Atenção',
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $check['title'] }}</strong><br>
                                <span class="muted">{{ $check['description'] }}</span>
                            </td>
                            <td><span class="badge {{ $check['severity'] }}">{{ $severityLabel }}</span></td>
                            <td>{{ number_format($check['count'], 0, ',', '.') }}</td>
                            <td>
                                @forelse ($check['items'] as $item)
                                    @php
                                        $name = match ($check['type']) {
                                            'people' => $item->full_name,
                                            'roles' => $item->person?->full_name ?? 'Pessoa não localizada',
                                            'contacts' => $item->name,
                                            'schools' => $item->name,
                                            'years' => $item->name,
                                            'enrollments' => $item->student?->full_name ?? 'Estudante não localizado',
                                            'periods' => $item->name,
                                            'classes' => $item->name,
                                            'assignments' => ($item->schoolClass?->name ?? 'Turma').' / '.($item->component?->name ?? 'Componente'),
                                            default => 'Registro',
                                        };
                                    @endphp
                                    {{ $name }}@if (! $loop->last); @endif
                                @empty
                                    Nenhum registro.
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    @include('reports.partials.document-footer', ['issuedDocument' => $issuedDocument])
</body>
</html>
