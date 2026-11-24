@props(['perPage' => 10])

<livewire:table-viewport :content="new \Illuminate\Support\HtmlString($slot->toHtml())" :per-page="(int) $perPage" />
