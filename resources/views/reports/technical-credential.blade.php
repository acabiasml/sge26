<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@font-face { font-family: 'Great Vibes'; src: url('{{ resource_path('fonts/great-vibes/GreatVibes-Regular.ttf') }}') format('truetype'); font-weight: normal; }
@font-face { font-family: 'Open Sans'; src: url('{{ resource_path('fonts/open-sans/OpenSans-Regular.ttf') }}') format('truetype'); font-weight:normal; font-style:normal; }
@font-face { font-family: 'Open Sans'; src: url('{{ resource_path('fonts/open-sans/OpenSans-Italic.ttf') }}') format('truetype'); font-weight:normal; font-style:italic; }
@font-face { font-family: 'Open Sans'; src: url('{{ resource_path('fonts/open-sans/OpenSans-Bold.ttf') }}') format('truetype'); font-weight:bold; }
@page { size: A4 landscape; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Open Sans', DejaVu Sans, sans-serif; color: #2f241f; }
.sheet { position: relative; width: 841.89pt; height: 594.8pt; page-break-after: always; overflow: hidden; background-color: #fffaf1; }
.sheet.last { page-break-after: auto; }
.paper { position: absolute; top:0; left:0; width:841.89pt; height:594.8pt; z-index:0; }
.frame { position:absolute; top:24pt; left:24pt; right:24pt; bottom:24pt; border:2.5pt double {{ $data['kind']==='diploma' ? '#59334e' : '#b95425' }}; }
.coat { position:absolute; left:58pt; top:43pt; width:73pt; height:auto; max-height:73pt; }
.seal { position:absolute; right:47pt; top:43pt; width:73pt; height:73pt; }
.header { position:absolute; top:43pt; left:166pt; width:510pt; text-align:center; font-size:10pt; line-height:12pt; }
.institution { margin-top:12pt; padding-bottom:4pt; margin-bottom:3pt; border-bottom:.6pt solid #bc8b64; font-weight:bold; font-size:12.5pt; line-height:14pt; letter-spacing:.2pt; color:{{ $data['kind']==='diploma' ? '#59334e' : '#a34724' }}; }
.address { font-size:9.5pt; line-height:11.5pt; font-weight:normal; }
.title { position:absolute; top:202pt; left:45pt; width:752pt; text-align:center; font-family:'Great Vibes'; font-size:51pt; color:{{ $data['kind']==='diploma' ? '#59334e' : '#a34724' }}; line-height:52pt; }
.main { position:absolute; top:281pt; left:48pt; width:746pt; font-size:{{ mb_strlen(implode(' ', $data['student']))>310 ? 10.9 : 11.5 }}pt; line-height:14.5pt; text-align:justify; font-style:italic; }
.with-preview-label .main { top:302pt; }
.with-preview-label .place { top:438pt; }
.with-preview-label .provisional { top:276pt; font-size:9.5pt; }
.student-name { font-family:'Great Vibes'; font-style:normal; font-size:18pt; }
.place { position:absolute; top:416pt; right:48pt; width:510pt; text-align:right; font-size:10.5pt; }
.signature { position:absolute; top:480pt; width:200pt; text-align:center; font-size:10pt; }
.signature .name { min-height:24pt; font-size:8.5pt; line-height:10pt; }
.signature-role { font-size:9pt; font-weight:normal; }
.signature .line { font-size:9.2pt; line-height:10pt; border-top:.6pt solid #6b3d2e; padding-top:3pt; }
.signature.first { left:63pt; }.signature.middle { left:307pt; }.signature.third { left:566pt; }
.contact { position:absolute; right:42pt; top:43pt; width:485pt; text-align:right; font-size:9.5pt; line-height:12pt; }
.course-heading { position:absolute; left:42pt; top:114pt; right:42pt; font-size:11pt; line-height:13pt; }
.components { position:absolute; left:225pt; width:390pt; border-collapse:collapse; font-size:9pt; }
.components th,.components td { border:.55pt solid #4f4650; padding:.8pt 4pt; line-height:10.8pt; }
.components.compact { font-size:8.5pt; }
.components.compact th,.components.compact td { padding:.25pt 4pt; line-height:8.5pt; }
.components th { text-align:left; background:#fffaf1; }
.components .hours { text-align:center; width:78pt; }
.components .average { text-align:center; width:62pt; }
.module-legend { position:absolute; left:225pt; width:390pt; border-collapse:collapse; }
.module-legend td { padding:1pt 4pt; text-align:center; border:.55pt solid #8a7468; }
.registry { position:absolute; right:43pt; width:365pt; font-size:9.3pt; line-height:12pt; }
.provisional { position:absolute; top:258pt; left:48pt; right:48pt; text-align:center; color:#8b3725; font-size:11pt; font-weight:bold; }
.watermark { position:absolute; top:290pt; left:52pt; width:735pt; text-align:center; color:#7a4b60; opacity:.10; font-size:35pt; transform:rotate(-22deg); }
.authentication-qr { position:absolute; left:43pt; bottom:66pt; width:92pt; text-align:center; font-size:7pt; line-height:10pt; }
.authentication-qr img { width:72pt; height:72pt; background:#fff; }
.verification { position:absolute; left:43pt; right:43pt; bottom:39pt; text-align:center; font-size:6.7pt; color:#6b3d2e; }
</style></head><body>
@php
 $program=$data['program'];$student=$data['student'];$registry=$data['registration'];$diploma=$data['kind']==='diploma';$final=$data['is_final'];$preview=$data['layout_preview']??false;
 $longDate=fn($v)=>blank($v)?'a preencher':\Carbon\CarbonImmutable::parse($v)->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y');
 $signatureBlank=fn($name)=>mb_strlen($name)>120?0:(mb_strlen($name)>95?12:24);
 $signatureSize=fn($name)=>mb_strlen($name)>90?7.2:(mb_strlen($name)>60?8:9.2);
 $parents=collect([$student['mother_name'],$student['father_name']])->filter()->join(' e ');
 $image=fn($name)=>'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/technical-credentials/'.$name)));
 $bgFile=$diploma?'background-diploma.png':'background-certificate.png';
 $bg=file_exists(resource_path('images/technical-credentials/'.$bgFile))?$image($bgFile):null;
 $chunks=array_chunk($data['components'],15);
 $formatHours=fn($v)=>number_format($v,abs((float)$v-round((float)$v))<.001?0:2,',','.');
 $total=$formatHours($data['total_hours']);
 $modulePalette=['#f3e5c4','#e9deeb','#f4dcd0','#e2ebe6','#eadfcf','#e5dfe9','#f2e5dd','#e9e7cf','#e4d8de','#eee0c8','#e4e8dc','#eadfe3'];
 $roman=['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
 $moduleByComponent=collect($program['modules'])->flatMap(fn($m)=>collect($m['components'])->mapWithKeys(fn($c)=>[$c['key']=>$m['position']]));
 $modulePosition=fn($c)=>(int)($moduleByComponent[$c['key']]??($data['module']['position']??1));
 $moduleColor=fn($position)=>$modulePalette[($position-1)%count($modulePalette)];
 $verificationUrl=$preview?$data['preview_url']:route('documents.verify',$document->verification_code);
 $verificationQr=(new \chillerlan\QRCode\QRCode)->render($verificationUrl);
@endphp
<div class="sheet {{ ($preview || !$final)?'with-preview-label':'' }}">
@if($bg)<img class="paper" src="{{ $bg }}" alt="">@endif<div class="frame"></div>
<img class="coat" src="{{ $image('mt-coat.png') }}" alt="Brasão de Mato Grosso"><img class="seal" src="{{ $image('brazil-seal.png') }}" alt="Selo da República">
<div class="header"><strong>REPÚBLICA FEDERATIVA DO BRASIL<br>{{ $program['state_heading'] }}<br>{{ $diploma ? 'MINISTÉRIO DA EDUCAÇÃO · SECRETARIA DE ESTADO DE EDUCAÇÃO' : $program['education_system'] }}</strong><div class="institution">{{ mb_strtoupper($data['school_name']) }}</div><div class="address">{{ $program['front_address'] }}<br>Ato legal de criação: {{ $program['creation_act'] }}<br>Autorização do curso: {{ $program['authorization_act'] }}</div></div>
<div class="title">{{ $diploma ? 'Diploma' : 'Certificado' }}</div>
@if($preview || !$final)<div class="watermark">{{ !$final?'AINDA NÃO FINALIZADO':'PRÉVIA SEM REGISTRO' }}</div><div class="provisional">{{ !$final?'AINDA NÃO FINALIZADO — ':'' }}PRÉVIA PARA CONFERÊNCIA — SEM REGISTRO</div>@endif
<div class="main">
@if($diploma && !$final)
A Direção do {{ $data['school_name'] }}, mantida pelo {{ $program['maintainer'] }}, apresenta, para acompanhamento, esta prévia do diploma do {{ $program['name'] }}, {{ mb_strtolower($program['education_mode']) }}, <u>{{ $data['offer_form'] }}</u>, no Eixo de {{ $program['technological_axis'] }}, em nome de <span class="student-name">{{ $student['full_name'] }}</span>, nascido(a) em {{ $longDate($student['birth_date']) }}, nacionalidade {{ mb_strtolower($student['nationality'] ?? 'a preencher') }}, natural de {{ $student['birth_city'] }}-{{ $student['birth_state'] }}, filho(a) de {{ $parents }}. <strong>O curso ainda não foi finalizado. Esta prévia não confere título nem comprova conclusão.</strong>
@elseif($diploma)
A Direção do {{ $data['school_name'] }}, mantida pelo {{ $program['maintainer'] }}, no uso de suas atribuições legais e tendo em vista a conclusão em {{ $longDate($data['completed_at']) }} do {{ $program['name'] }}, {{ mb_strtolower($program['education_mode']) }}, <u>{{ $data['offer_form'] }}</u>, confere o Título de <strong>{{ $program['graduate_title'] }}</strong>, no Eixo de {{ $program['technological_axis'] }}, a <span class="student-name">{{ $student['full_name'] }}</span>, nascido(a) em {{ $longDate($student['birth_date']) }}, nacionalidade {{ mb_strtolower($student['nationality'] ?? 'a preencher') }}, natural de {{ $student['birth_city'] }}-{{ $student['birth_state'] }}, documento oficial {{ $student['photo_identification'] ?? 'a preencher' }}, filho(a) de {{ $parents }}, e outorga-lhe o presente Diploma com base nos seguintes dispositivos: {{ $program['legal_basis'] }}, a fim de que possa gozar de todos os direitos e prerrogativas legais.
@else
A Direção do {{ $data['school_name'] }}, mantida pelo {{ $program['maintainer'] }}, no uso de suas atribuições legais, certifica que <span class="student-name">{{ $student['full_name'] }}</span>, nascido(a) em {{ $longDate($student['birth_date']) }}, nacionalidade {{ mb_strtolower($student['nationality'] ?? 'a preencher') }}, natural de {{ $student['birth_city'] }}-{{ $student['birth_state'] }}, filho(a) de {{ $parents }}, concluiu a qualificação profissional em <strong>{{ $data['module']['qualification'] }} — {{ $total }}h</strong>, em {{ $longDate($data['completed_at']) }}, {{ mb_strtolower($program['education_mode']) }}, <u>{{ $data['offer_form'] }}</u>, no Eixo de {{ $program['technological_axis'] }}, outorgando-lhe o presente Certificado com base nos seguintes dispositivos: {{ $program['legal_basis'] }}, a fim de que possa gozar de todos os direitos e prerrogativas legais.
@endif
</div>
<div class="place">{{ $registry['issued_place'] }}, {{ $longDate($registry['issued_date']) }}.</div>
<div class="signature first"><div class="name" style="min-height:{{ $signatureBlank($registry['secretary_name']) }}pt">&nbsp;</div><div class="line" style="font-size:{{ $signatureSize($registry['secretary_name']) }}pt">{{ $registry['secretary_name'] }}<br><span class="signature-role">Secretaria</span></div></div>
@if($diploma)<div class="signature middle"><div class="name" style="min-height:{{ $signatureBlank($student['full_name']) }}pt">&nbsp;</div><div class="line" style="font-size:{{ $signatureSize($student['full_name']) }}pt">{{ $student['full_name'] }}<br><span class="signature-role">{{ $final?'Diplomado':'Estudante' }}</span></div></div>@endif
<div class="signature third"><div class="name" style="min-height:{{ $signatureBlank($registry['director_name']) }}pt">&nbsp;</div><div class="line" style="font-size:{{ $signatureSize($registry['director_name']) }}pt">{{ $registry['director_name'] }}<br><span class="signature-role">Direção</span></div></div>
</div>
@foreach($chunks as $chunk)
@php($last=$loop->last)
@php($shownModules=collect($chunk)->map($modulePosition)->unique()->sort()->values())
<div class="sheet {{ $last?'last':'' }}">
@if($bg)<img class="paper" src="{{ $bg }}" alt="">@endif<div class="frame"></div>
@if($preview || !$final)<div class="watermark">{{ !$final?'AINDA NÃO FINALIZADO':'PRÉVIA SEM REGISTRO' }}</div><div class="provisional" style="top:32pt;font-size:7.5pt">{{ !$final?'AINDA NÃO FINALIZADO — ':'' }}PRÉVIA PARA CONFERÊNCIA — SEM REGISTRO</div>@endif
<div class="contact">@if($preview)Prévia para conferência, sem emissão ou registro definitivo no @elseif($final)Todos os {{ $diploma?'diplomas':'certificados' }} podem ter sua autenticidade comprovada e estão registrados no @else Prévia de acompanhamento, sem registro definitivo ou comprovação de conclusão no @endif<br><strong>{{ mb_strtoupper($data['school_name']) }}</strong> — CNPJ: {{ $data['school_cnpj'] }}.<br>Endereço: {{ $program['back_address'] }}</div>
<div class="course-heading"><strong>{{ mb_strtoupper($diploma?$program['name']:$data['module']['qualification']) }} — {{ $total }}h</strong>@if((int)$data['total_hours']===1200) (mil e duzentas horas).@elseif((int)$data['total_hours']===400) (quatrocentas horas).@endif<br>EIXO DE {{ mb_strtoupper($program['technological_axis']) }}@if(count($chunks)>1)<br>Componentes — parte {{ $loop->iteration }} de {{ count($chunks) }}@endif</div>
<table class="module-legend" aria-label="Legenda de cores dos módulos" style="top:{{ $diploma?156:175 }}pt;font-size:{{ $shownModules->count()>4?6:8 }}pt"><tr>@foreach($shownModules as $position)<td style="background-color:{{ $moduleColor($position) }}">Módulo {{ $roman[$position-1]??$position }}</td>@endforeach</tr></table>
<table class="components {{ count($chunk)>13?'compact':'' }}" style="top:{{ $diploma?178:197 }}pt;"><thead><tr><th>Componente Curricular</th><th class="hours">Carga horária</th><th class="average">Média final</th></tr></thead><tbody>@foreach($chunk as $index=>$component)<tr style="background-color:{{ $moduleColor($modulePosition($component)) }}"><td>{{ $component['name'] }}</td><td class="hours">{{ $formatHours($component['hours']) }}h</td><td class="average">{{ isset($component['final_average'])?number_format($component['final_average'],1,',','.'):'—' }}</td></tr>@endforeach</tbody></table>
@if($last)<div class="registry" style="top:{{ $diploma?455:(count($chunk)>6?455:327) }}pt;@if(mb_strlen(implode(' ', $registry))>300)font-size:8pt;line-height:11pt;@endif">
@if($preview)<strong>Prévia para conferência — sem registro definitivo.</strong><br>Livro, folha, número, responsável e data do registro serão preenchidos antes da emissão oficial.
@else
@if(!$final)<strong>PRÉVIA — dados de registro para conferência, sem registro definitivo.</strong><br>@else Nos termos da {{ $program['registry_resolution'] }}, {{ $diploma?'diploma':'certificado' }} registrado sob o<br>@endif
nº <strong>{{ $registry['registry_number'] }}</strong>, Folha nº <strong>{{ $registry['registry_folio'] }}</strong>, Livro nº <strong>{{ $registry['registry_book'] }}</strong>,<br>por {{ $registry['registered_by'] }}, em {{ $longDate($registry['registration_date']) }}.<br>@if($final && ($diploma || filled($registry['sistec_code'] ?? null)))Registro no SISTEC: <strong>{{ $registry['sistec_code'] ?? 'não informado no acervo anterior' }}</strong>.<br>@endif
@if($diploma && $final)
<span style="font-size:7.4pt">Registro com validade em todo o território nacional, conforme art. 2º, § 3º, da Lei 11.892/2008 e arts. 24 e 36-D da Lei 9.394/1996.</span>
<br><span style="font-size:7.4pt">{{ $registry['issued_place'] }}. Assinatura do responsável pelo registro e carimbos de tempo na via assinada.</span>
@elseif(filled($registry['observations'] ?? null))
Observações: {{ $registry['observations'] }}
@endif
@endif
</div>@endif
<div class="authentication-qr"><a href="{{ $verificationUrl }}"><img src="{{ $verificationQr }}" alt="QR Code de autenticação no site"></a><br>{{ $preview?'Prévia temporária, sem registro':($final?'Autenticação no site':'Autenticar prévia') }}</div>
<div class="verification">@if($preview)Prévia para conferência — sem registro · {{ $verificationUrl }}@else{{ $final?'Autenticidade':'Prévia de acompanhamento — ainda não finalizado' }}: {{ $document->verification_code }} · {{ $verificationUrl }}@endif</div>
</div>
@endforeach
</body></html>
