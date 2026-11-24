<?php

namespace App\Livewire;

use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Livewire\Component;

class TableViewport extends Component
{
    public array $pages = [];

    // The enclosing screen has already authorized and rendered these rows.
    // Keep the markup and form values out of the Livewire snapshot.
    protected string $content = '';

    protected int $perPage = 0;

    public function mount(HtmlString $content, int $perPage): void
    {
        $this->content = $content->toHtml();
        $this->perPage = $perPage;
    }

    public function hydrate(): void
    {
        $this->skipRender();
    }

    public function render(): View
    {
        return view('livewire.table-viewport', [
            'content' => $this->content,
            'perPage' => $this->perPage,
        ]);
    }
}
