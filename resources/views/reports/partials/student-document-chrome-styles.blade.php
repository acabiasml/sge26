.letterhead-table { table-layout: fixed; }
.letterhead { width: 100%; margin-bottom: 2px; padding-bottom: 5px; }
.letterhead-table .letterhead-logo { width: 14%; }
.letterhead-table .letterhead-center { width: 72%; }
.letterhead-logo img { max-width: 66px; max-height: 50px; }
.letterhead-line, .document-title, .document-footer { line-height: 1.06; }
.document-title { font-size: 14px; margin: 5px 0 0; }
.document-footer-layout .document-footer-qr,
.document-footer-layout .document-footer-balance { width: 14%; }
.document-footer-layout .document-footer-text { width: 72%; }
.document-footer-text > div { white-space: normal; word-wrap: break-word; }
.student-identification .identity-inep { white-space: normal; }
@if(isset($pageChrome))
@page { margin: {{ $pageChrome['top'] }}px {{ $pageChrome['inline'] }}px {{ $pageChrome['bottom'] }}px; }
.letterhead-repeat { top: {{ $pageChrome['header_offset'] }}px; }
.document-footer { bottom: {{ $pageChrome['footer_offset'] }}px !important; }
@endif
