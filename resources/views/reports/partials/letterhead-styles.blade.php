@php
    $atkinsonFontBase = 'file:///'.str_replace('\\', '/', public_path('template/fonts/atkinson-hyperlegible-next'));
@endphp
@font-face {
    font-family: 'Atkinson Hyperlegible Next';
    font-style: normal;
    font-weight: 400;
    src: url('{{ $atkinsonFontBase }}/AtkinsonHyperlegibleNext-Regular.ttf') format('truetype');
}
@font-face {
    font-family: 'Atkinson Hyperlegible Next';
    font-style: italic;
    font-weight: 400;
    src: url('{{ $atkinsonFontBase }}/AtkinsonHyperlegibleNext-Italic.ttf') format('truetype');
}
@font-face {
    font-family: 'Atkinson Hyperlegible Next';
    font-style: normal;
    font-weight: 600;
    src: url('{{ $atkinsonFontBase }}/AtkinsonHyperlegibleNext-SemiBold.ttf') format('truetype');
}
@font-face {
    font-family: 'Atkinson Hyperlegible Next';
    font-style: normal;
    font-weight: 700;
    src: url('{{ $atkinsonFontBase }}/AtkinsonHyperlegibleNext-Bold.ttf') format('truetype');
}
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
