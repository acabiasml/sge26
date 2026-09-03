<div class="sge-action-buttons">
    <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('people.show', $person) }}" aria-label="{{ __('screens.open_record', ['name' => $person->full_name]) }}" title="{{ __('screens.open_record_title') }}">
        <i class="fas fa-folder-open" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('people.pdf', $person) }}" aria-label="{{ __('screens.issue_person_pdf', ['name' => $person->full_name]) }}" title="{{ __('screens.pdf_record') }}">
        <i class="fas fa-file-pdf" aria-hidden="true"></i>
    </a>
</div>
