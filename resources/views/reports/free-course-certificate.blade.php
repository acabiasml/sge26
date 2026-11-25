<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
@font-face{font-family:'Open Sans';src:url('{{ resource_path('fonts/open-sans/OpenSans-Regular.ttf') }}') format('truetype');font-weight:normal}
@font-face{font-family:'Open Sans';src:url('{{ resource_path('fonts/open-sans/OpenSans-Bold.ttf') }}') format('truetype');font-weight:bold}
@font-face{font-family:'Great Vibes';src:url('{{ resource_path('fonts/great-vibes/GreatVibes-Regular.ttf') }}') format('truetype')}
@page{size:A4 landscape;margin:0}*{box-sizing:border-box}body{margin:0;font-family:'Open Sans',sans-serif;color:#254c47}
.sheet{position:relative;width:841.89pt;height:594.8pt;background:#fcfaf4;page-break-after:always;overflow:hidden}.sheet.last{page-break-after:auto}
.rail{position:absolute;left:0;top:0;bottom:0;width:15pt;background:#286d63}.rail-inner{position:absolute;left:15pt;top:0;bottom:0;width:5pt;background:#c5d8c8}
.frame{position:absolute;top:24pt;left:37pt;right:24pt;bottom:24pt;border:.7pt solid #b7c7b7}.corner{position:absolute;right:24pt;top:24pt;width:97pt;height:5pt;background:#ae8859}
.logos{position:absolute;top:44pt;left:56pt;right:44pt;height:70pt}.logo{position:absolute;top:0;width:111pt;height:65pt;text-align:center}.logo img{max-width:106pt;max-height:64pt}.logo.school{right:0}
.school-name{position:absolute;top:45pt;left:188pt;width:478pt;text-align:center;font-size:14pt;line-height:18pt;font-weight:bold;color:#24574c}.eyebrow{font-size:8pt;letter-spacing:2pt;font-weight:normal;color:#7c7457;margin-top:7pt}
.title{position:absolute;top:104pt;left:60pt;right:44pt;text-align:center;font-family:'Great Vibes';font-size:49pt;line-height:58pt;color:#286d63}
.intro{position:absolute;top:184pt;left:70pt;right:55pt;text-align:center;font-size:10.5pt}
.name{position:absolute;top:199pt;left:73pt;right:58pt;text-align:center;font-family:'Great Vibes';line-height:32pt;color:#294c42}
.identity{position:absolute;top:270pt;left:77pt;right:61pt;text-align:center;font-size:9.2pt;line-height:12pt;color:#52685a}
.awarded{position:absolute;top:314pt;left:70pt;right:55pt;text-align:center;font-size:11pt}
.course-name{position:absolute;top:334pt;left:79pt;right:63pt;text-align:center;font-weight:bold;line-height:24pt;color:#286d63}
.period{position:absolute;top:399pt;left:77pt;right:61pt;text-align:center;font-size:10pt;line-height:17pt}
.place{position:absolute;top:443pt;left:70pt;right:55pt;text-align:center;font-size:10pt}
.signature{position:absolute;top:506pt;width:290pt;text-align:center;border-top:.6pt solid #537c6b;padding-top:5pt;line-height:11pt}.signature.first{left:95pt}.signature.second{right:79pt}.signature small{font-size:8pt;color:#696c5d}
.footer{position:absolute;bottom:35pt;left:60pt;right:45pt;text-align:center;font-size:6.8pt;color:#5c7368;line-height:10pt}
.preview{position:absolute;top:476pt;left:60pt;right:45pt;text-align:center;color:#9a573d;font-size:10pt;font-weight:bold;letter-spacing:1pt}
.back-title{position:absolute;top:116pt;left:64pt;right:49pt;font-size:17pt;font-weight:bold}.back-course{position:absolute;top:146pt;left:64pt;right:49pt;font-size:10pt;line-height:15pt}
.syllabus{position:absolute;top:218pt;left:64pt;right:49pt;font-size:10pt;line-height:13pt;color:#344f46}.syllabus div{height:13pt;white-space:pre-wrap}
.registry{position:absolute;top:466pt;left:166pt;right:52pt;font-size:8pt;line-height:11pt;color:#4f675b}.qr{position:absolute;top:462pt;left:65pt;width:73pt;font-size:6pt;text-align:center;line-height:9pt}.qr img{width:65pt;height:65pt}
</style></head><body>
@php
 $data=$document->payload;$reg=$data['registration'];$pages=\App\Support\FreeCourseCertificate::syllabusPages($data['syllabus']);
 $date=fn($v)=>\Carbon\CarbonImmutable::parse($v)->format('d/m/Y');
 $longDate=\Carbon\CarbonImmutable::parse($reg['issued_date'])->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y');
 $hours=number_format($data['workload_hours'],fmod((float)$data['workload_hours'],1)==0?0:2,',','.');
 $url=$preview?route('free-course-certificates.prepare',$data['offering_id']):route('documents.verify',$document->verification_code);
 $qr=(new \chillerlan\QRCode\QRCode)->render($url);
@endphp
<div class="sheet"><div class="rail"></div><div class="rail-inner"></div><div class="frame"></div><div class="corner"></div>
@include('reports.partials.free-course-logos')
<div class="title">Certificado</div><div class="intro">Certificamos que</div>
<div class="name" style="font-size:{{ mb_strlen($data['participant_name'])>180?17:(mb_strlen($data['participant_name'])>130?20:(mb_strlen($data['participant_name'])>100?23:32)) }}pt;line-height:{{ mb_strlen($data['participant_name'])>180?22:(mb_strlen($data['participant_name'])>130?24:(mb_strlen($data['participant_name'])>100?28:32)) }}pt">{{ $data['participant_name'] }}</div>
@php($identity=$data['participant_identity'])
@php($cpf=preg_replace('/\D/','',$identity['cpf']??''))
<div class="identity">CPF: {{ strlen($cpf)===11?substr($cpf,0,3).'.'.substr($cpf,3,3).'.'.substr($cpf,6,3).'-'.substr($cpf,9,2):'a preencher' }} · Nascimento: {{ $identity['birth_date']?$date($identity['birth_date']):'a preencher' }}<br>Naturalidade: {{ $identity['birth_city']?:'a preencher' }}{{ $identity['birth_state']?'-'.$identity['birth_state']:'' }} · Nacionalidade: {{ $identity['nationality']?:'a preencher' }}</div>
<div class="awarded">concluiu o curso livre</div>
<div class="course-name" style="font-size:{{ mb_strlen($data['course_name'])>180?13:(mb_strlen($data['course_name'])>110?15:20) }}pt;line-height:{{ mb_strlen($data['course_name'])>180?18:(mb_strlen($data['course_name'])>110?20:24) }}pt">{{ $data['course_name'] }}</div>
<div class="period" @if(mb_strlen($data['location'])>100) style="font-size:8pt;line-height:11pt" @endif>Carga horária de <strong>{{ $hours }} horas-relógio</strong> · {{ $data['delivery_mode'] }}<br>Realizado de {{ $date($data['starts_at']) }} a {{ $date($data['ends_at']) }}, em {{ $data['location'] }}.</div>
<div class="place">{{ $reg['issued_place'] }}, {{ $longDate }}.</div>
@if($preview)<div class="preview">PRÉVIA PARA CONFERÊNCIA — SEM REGISTRO</div>@endif
<div class="signature first" style="font-size:{{ mb_strlen($reg['instructor_name'])>80?7.5:9 }}pt">{{ $reg['instructor_name'] }}<br><small>{{ $reg['instructor_role'] }}</small></div>
<div class="signature second" style="font-size:{{ mb_strlen($reg['director_name'])>80?7.5:9 }}pt">{{ $reg['director_name'] }}<br><small>Direção</small></div>
<div class="footer">{{ $preview?'Prévia sem emissão oficial':('Autenticação: '.$document->verification_code) }} · Conteúdo programático no verso.</div>
</div>
@foreach($pages as $page)
<div class="sheet {{ $loop->last?'last':'' }}"><div class="rail"></div><div class="rail-inner"></div><div class="frame"></div><div class="corner"></div>
@include('reports.partials.free-course-logos')
<div class="back-title">Conteúdo programático @if(count($pages)>1)<span style="font-size:9pt;font-weight:normal">· {{ $loop->iteration }} de {{ count($pages) }}</span>@endif</div>
<div class="back-course"><strong>{{ $data['course_name'] }}</strong> · {{ $hours }} h<br>{{ $data['participant_name'] }}</div>
<div class="syllabus">@foreach($page as $line)<div>{{ $line?:' ' }}</div>@endforeach</div>
<div class="qr"><a href="{{ $url }}"><img src="{{ $qr }}" alt="QR Code"></a><br>{{ $preview?'Conferir cadastro':'Autenticar no site' }}</div>
<div class="registry" @if(mb_strlen($data['school_address'].($reg['registered_by'] ?? '').($reg['registry_book'] ?? '').($reg['registry_folio'] ?? ''))>300) style="font-size:7pt;line-height:10pt" @endif>
    @if($preview)
        <strong>PRÉVIA PARA CONFERÊNCIA</strong><br>Confira conteúdo, identificação e assinaturas antes da emissão.
    @else
        @isset($reg['registry_number'])
            <strong>Registro nº {{ $reg['registry_number'] }}</strong> · Livro {{ $reg['registry_book'] }} · Folha {{ $reg['registry_folio'] }}<br>
            Registrado por {{ $reg['registered_by'] }}, em {{ $date($reg['issued_date']) }}.<br>
        @endisset
        CNPJ: {{ $data['school_cnpj'] }} · {{ $data['school_address'] }}
    @endif
</div>
<div class="footer">{{ $preview?'Prévia sem emissão oficial':('Autenticidade: '.$document->verification_code) }} · {{ $url }}</div>
</div>
@endforeach
</body></html>
