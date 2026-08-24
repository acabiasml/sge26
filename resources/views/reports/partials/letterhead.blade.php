<header class="letterhead {{ ($repeatOnEveryPage ?? true) ? 'letterhead-repeat' : '' }}">
    <table class="letterhead-table">
        <tr>
            <td class="letterhead-logo">
                @if ($letterhead['maintainer_logo'] ?? null)
                    <img src="{{ $letterhead['maintainer_logo'] }}" alt="Centro Técnico Juvenil de Jarudore">
                @endif
            </td>
            <td class="letterhead-center">
                @foreach (($letterhead['lines'] ?? []) as $index => $line)
                    <div class="letterhead-line {{ $index === 0 || ($index === 2 && ($letterhead['school'] ?? null)) ? 'letterhead-line-main' : '' }}">{{ $line }}</div>
                @endforeach

                <h1 class="document-title">{{ $title }}</h1>
            </td>
            <td class="letterhead-logo">
                @if ($letterhead['school_logo'] ?? null)
                    <img src="{{ $letterhead['school_logo'] }}" alt="Logo da escola">
                @endif
            </td>
        </tr>
    </table>
</header>
@php
    $documentCourses = collect();
    if (isset($course) && $course instanceof \App\Models\AcademicCourse) $documentCourses->push($course);
    if (isset($courses) && $courses instanceof \Illuminate\Support\Collection) $documentCourses = $documentCourses->concat($courses);
    if (isset($enrollment) && $enrollment instanceof \App\Models\StudentEnrollment) $documentCourses = $documentCourses->concat($enrollment->courses);
    if (isset($schoolClass) && $schoolClass instanceof \App\Models\SchoolClass) $documentCourses = $documentCourses->concat($schoolClass->courses);
    if (isset($class) && $class instanceof \App\Models\SchoolClass) $documentCourses = $documentCourses->concat($class->courses);
    if (isset($matrixGroup['courses'])) $documentCourses = $documentCourses->concat($matrixGroup['courses']);
    $documentTechnicalCourses = $documentCourses->where('stage', \App\Models\AcademicCourse::STAGE_TECHNICAL)->unique('id');
@endphp
@foreach($documentTechnicalCourses as $documentTechnicalCourse)
    <div style="margin:3px 0 5px;padding:3px 5px;border:.5px solid #d8c8bf;background:#faf8f6;font-size:11px;page-break-inside:avoid;">
        <strong>{{ $documentTechnicalCourse->name }} — regulamentação:</strong>
        {{ $documentTechnicalCourse->regulatoryReference() }}
        @if($documentTechnicalCourse->technological_axis) Eixo tecnológico: {{ $documentTechnicalCourse->technological_axis }}. @endif
        @if($documentTechnicalCourse->offer_forms) Forma de oferta: {{ $documentTechnicalCourse->offer_forms }}. @endif
        @if($documentTechnicalCourse->authorization_starts_at || $documentTechnicalCourse->authorization_ends_at)
            Vigência: {{ $documentTechnicalCourse->authorization_starts_at?->format('d/m/Y') ?: '-' }} a {{ $documentTechnicalCourse->authorization_ends_at?->format('d/m/Y') ?: '-' }}.
        @endif
    </div>
@endforeach
