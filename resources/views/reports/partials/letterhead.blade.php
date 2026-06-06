<header class="letterhead">
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
