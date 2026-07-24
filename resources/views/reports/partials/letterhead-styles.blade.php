@php
    $atkinsonFontDir = public_path('template/fonts/atkinson-hyperlegible-next');
    $atkinsonFontPath = str_replace('\\', '/', $atkinsonFontDir);
    $atkinsonFontBase = (str_starts_with($atkinsonFontPath, '/') ? 'file://' : 'file:///').$atkinsonFontPath;
    $atkinsonFonts = [
        ['file' => 'AtkinsonHyperlegibleNext-Regular.ttf', 'style' => 'normal', 'weight' => 400],
        ['file' => 'AtkinsonHyperlegibleNext-Italic.ttf', 'style' => 'italic', 'weight' => 400],
        ['file' => 'AtkinsonHyperlegibleNext-SemiBold.ttf', 'style' => 'normal', 'weight' => 600],
        ['file' => 'AtkinsonHyperlegibleNext-Bold.ttf', 'style' => 'normal', 'weight' => 700],
    ];
@endphp
@foreach ($atkinsonFonts as $atkinsonFont)
@if (is_file($atkinsonFontDir.DIRECTORY_SEPARATOR.$atkinsonFont['file']))
@font-face {
    font-family: 'Atkinson Hyperlegible Next';
    font-style: {{ $atkinsonFont['style'] }};
    font-weight: {{ $atkinsonFont['weight'] }};
    src: url('{{ $atkinsonFontBase }}/{{ $atkinsonFont['file'] }}') format('truetype');
}
@endif
@endforeach
.letterhead { border-bottom: 2px solid #6B3D2E; margin-bottom: 14px; padding-bottom: 8px; }
.letterhead-table { width: 100%; border-collapse: collapse; }
.letterhead-table td { border: 0; padding: 0; vertical-align: middle; }
.letterhead-logo { width: 86px; text-align: center; }
.letterhead-logo img { max-width: 74px; max-height: 54px; }
.letterhead-center { text-align: center; }
.letterhead-line { font-size: 8px; color: #534741; line-height: 1.22; margin-top: 1px; }
.letterhead-line-main { font-size: 10px; font-weight: 700; color: #6B3D2E; text-transform: uppercase; }
.document-title { font-size: 18px; margin: 9px 0 3px; color: #6B3D2E; }
.document-meta { color: #5f5a55; font-size: 9px; line-height: 1.45; }
.document-footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e6ddd8; padding-top: 7px; font-size: 8.5px; color: #5f5a55; }
