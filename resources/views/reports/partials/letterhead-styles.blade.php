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
html { font-family: 'Atkinson Hyperlegible Next', DejaVu Sans, sans-serif; }
body { -webkit-font-smoothing: antialiased; }
strong, b, th { font-weight: 600; }
table { border-collapse: collapse; }
.letterhead { border-bottom: 1.4px solid #6B3D2E; margin-bottom: 12px; padding-bottom: 7px; }
.letterhead-table { width: 100%; border-collapse: collapse; }
.letterhead-table td { border: 0; padding: 0; vertical-align: middle; }
.letterhead-logo { width: 82px; text-align: center; }
.letterhead-logo img { max-width: 70px; max-height: 52px; }
.letterhead-center { text-align: center; }
.letterhead-line { font-size: 7.4px; color: #534741; line-height: 1.17; margin-top: 1px; }
.letterhead-line-main { font-size: 9.2px; font-weight: 600; color: #6B3D2E; letter-spacing: .12px; text-transform: uppercase; }
.document-title { font-size: 16px; line-height: 1.12; margin: 8px 0 3px; color: #6B3D2E; font-weight: 600; text-align: center; }
.document-meta { color: #5f5a55; font-size: 8px; line-height: 1.3; }
.document-footer { position: fixed; bottom: -10px; left: 0; right: 0; border-top: .6px solid #d9c9c0; padding-top: 4px; font-size: 7px; line-height: 1.25; color: #5f5a55; text-align: center; }
