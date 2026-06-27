@props([
    'school' => null,
    'academicYear' => null,
    'course' => null,
    'class' => null,
])

<nav class="sge-academic-trail mb-4" aria-label="Hierarquia acadêmica">
    @if($school)
        <a href="{{ route('schools.academic-years.index', $school) }}">
            <i class="fas fa-school" aria-hidden="true"></i>
            <span>Escola</span>
            <strong>{{ $school->name }}</strong>
        </a>
    @endif

    @if($academicYear)
        <a href="{{ route('academic-years.show', $academicYear) }}">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            <span>Ano letivo</span>
            <strong>{{ $academicYear->name }}</strong>
        </a>
    @endif

    @if($course)
        <a href="{{ route('academic-years.courses.show', [$academicYear, $course]) }}">
            <i class="fas fa-book-open" aria-hidden="true"></i>
            <span>Matriz</span>
            <strong>{{ $course->name }}</strong>
        </a>
    @endif

    @if($class)
        <a href="{{ route('academic-years.classes.show', [$academicYear, $class]) }}">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span>Turma</span>
            <strong>{{ $class->name }}</strong>
        </a>
    @endif
</nav>
