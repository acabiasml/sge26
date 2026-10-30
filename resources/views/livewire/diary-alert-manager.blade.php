<div wire:ignore.self class="modal fade sge-diary-alert-manager" id="diaryAlertManager" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="diary-manager-title" aria-describedby="diary-manager-context">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="diary-manager-title">{{ __('Alertas do diário') }}</h2>
                    <p id="diary-manager-context" class="small mb-0 mt-1">@if($context){{ $context[0]->name }} · {{ $context[1]->name }} · {{ $context[2]->name }}@endif</p>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                @if($context)
                    @if($notice)<div class="alert alert-success" role="status">{{ $notice }}</div>@endif
                    <form wire:submit="save" class="mb-4">
                        <label for="managed-alert-message" class="font-weight-bold">{{ $editingAlertId ? __('Editar alerta') : __('Novo alerta') }}</label>
                        <textarea id="managed-alert-message" wire:model="message" class="form-control @error('message') is-invalid @enderror" rows="3" maxlength="2000" required></textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap mt-2" style="gap: .5rem">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ $editingAlertId ? __('Salvar alterações') : __('Enviar alerta') }}</button>
                            @if($editingAlertId)<button type="button" class="btn btn-outline-secondary" wire:click="cancelEdit">{{ __('Cancelar edição') }}</button>@endif
                        </div>
                    </form>
                    <h3 class="h6">{{ __('Alertas deste período') }} ({{ $alerts->count() }})</h3>
                    @forelse($alerts as $alert)
                        <article class="border rounded p-3 mt-2" wire:key="managed-alert-{{ $alert->id }}">
                            <div class="d-flex justify-content-between flex-wrap mb-2" style="gap: .5rem">
                                <strong>{{ $alert->fromPerson?->full_name ?? __('Gestão') }}</strong>
                                <span class="badge badge-{{ $alert->resolved_at ? 'success' : ($alert->dismissed_at ? 'secondary' : 'warning') }}">{{ $alert->resolved_at ? __('Resolvido') : ($alert->dismissed_at ? __('Já visto') : __('Não visto')) }}</span>
                            </div>
                            <p class="sge-managed-alert-message mb-2">{{ $alert->message }}</p>
                            <div class="small text-muted">{{ __('Destinatário:') }} {{ $alert->toPerson?->full_name ?? __('Não definido') }} · {{ $alert->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</div>
                            @if($alert->canBeManagedBy(auth()->user()))
                                @if($deletingAlertId === $alert->id)
                                    <div class="alert alert-warning mt-2 mb-0" role="alert">
                                        <p>{{ __('Excluir este alerta do diário? Esta ação é permanente.') }}</p>
                                        <button type="button" class="btn btn-sm btn-danger" wire:click="delete" wire:loading.attr="disabled">{{ __('Excluir alerta') }}</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelDelete">{{ __('Cancelar') }}</button>
                                    </div>
                                @else
                                    <div class="d-flex mt-2" style="gap: .5rem">
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $alert->id }})">{{ __('Editar alerta') }}</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="requestDelete({{ $alert->id }})">{{ __('Excluir alerta') }}</button>
                                    </div>
                                @endif
                            @endif
                        </article>
                    @empty
                        <p class="text-muted">{{ __('Nenhum alerta neste diário e período.') }}</p>
                    @endforelse
                @endif
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ __('Fechar') }}</button></div>
        </div>
    </div>
</div>
