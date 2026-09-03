<div class="sge-action-buttons">
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.academic-years.index', $school) }}" aria-label="{{ __('screens.manage_school_years', ['name' => $school->name]) }}" title="{{ __('screens.school_years') }}">
        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
    </a>

    @if (auth()->user()?->canManageSchools())
        <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('schools.edit', $school) }}" aria-label="{{ __('screens.edit_school', ['name' => $school->name]) }}" title="{{ __('screens.edit_school_title') }}">
            <i class="fas fa-pen" aria-hidden="true"></i>
        </a>
    @endif

    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.pdf', $school) }}" aria-label="{{ __('screens.issue_school_pdf', ['name' => $school->name]) }}" title="{{ __('screens.pdf_record') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>

    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('schools.concepts.index', $school) }}" aria-label="{{ __('screens.manage_school_criteria', ['name' => $school->name]) }}" title="{{ __('screens.criteria') }}">
        <i class="fas fa-star-half-alt" aria-hidden="true"></i>
    </a>

    @if (auth()->user()?->canManageSchools())
        <form method="POST" action="{{ route('schools.destroy', $school) }}" onsubmit="return confirm(@js(__('screens.delete_school_confirm')));">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-danger sge-icon-action" type="submit" aria-label="{{ __('screens.delete_school', ['name' => $school->name]) }}" title="{{ __('screens.delete_school_title') }}">
                <i class="fas fa-trash-alt" aria-hidden="true"></i>
            </button>
        </form>
    @endif
</div>
