<div wire:ignore @if($perPage > 0) x-data="sgeTable($wire, $el, {{ $perPage }})" @endif>
    {!! $content !!}
</div>
