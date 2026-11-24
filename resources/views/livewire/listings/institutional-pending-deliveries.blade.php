<section class="card sge-panel-card mb-3" aria-labelledby="pending-delivery-title">
    <div class="card-header"><h2 id="pending-delivery-title" class="h6 mb-0">{{ __('Entregas a lançar no livro') }}</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th scope="col">{{ __('Documento e titular') }}</th>
                    <th scope="col">{{ __('Data da entrega') }}</th>
                    <th scope="col">{{ __('Ações') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pending as $delivery)
                    <tr wire:key="pending-delivery-{{ $delivery['id'] }}">
                        <th scope="row">{{ $delivery['details']['subject'] }}</th>
                        <td>{{ \Illuminate\Support\Carbon::parse($delivery['details']['recorded_on'])->format('d/m/Y') }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('institutional-records.index', ['school' => $school->id, 'kind' => 'delivery', 'delivery_event' => $delivery['id']]) }}">{{ __('Conferir e lançar') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="3">{{ __('Nenhum protocolo de entrega pendente de lançamento.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pending->hasPages())
        <div class="card-body">{{ $pending->links('livewire::bootstrap') }}</div>
    @endif
</section>
