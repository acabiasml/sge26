@foreach(($technicalCourses ?? collect()) as $technicalCourse)
    <div class="technical-regulation" style="margin:3px 0 5px;padding:3px 5px;border:.5px solid #d8c8bf;background:#faf8f6;font-size:11px;line-height:1.16;page-break-inside:avoid;">
        <strong>{{ $technicalCourse->name }} — regulamentação:</strong>
        {{ $technicalCourse->conciseRegulatoryReference() }}
        @if($technicalCourse->technological_axis) Eixo tecnológico: {{ $technicalCourse->technological_axis }}. @endif
        @if($technicalCourse->offer_forms) Forma de oferta: {{ $technicalCourse->offer_forms }}. @endif
        @if($technicalCourse->authorization_starts_at || $technicalCourse->authorization_ends_at)
            Vigência: {{ $technicalCourse->authorization_starts_at?->format('d/m/Y') ?: '-' }} a {{ $technicalCourse->authorization_ends_at?->format('d/m/Y') ?: '-' }}.
        @endif
    </div>
@endforeach
