<div>
                <div class="card-body">
                    @php
                        $documentScopes = [
                            'public' => ['label' => __('Públicos'), 'icon' => 'fa-globe-americas', 'hint' => __('Públicos das escolas às quais você tem acesso.')],
                            'mine' => ['label' => __('Meus'), 'icon' => 'fa-user', 'hint' => __('Seus públicos e os privados sob sua responsabilidade.')],
                        ];
                        if (auth()->user()->isAdministrator()) {
                            $documentScopes['all'] = ['label' => __('Todos'), 'icon' => 'fa-layer-group', 'hint' => __('Todos os públicos e privados. Visão exclusiva da administração.')];
                        }
                    @endphp
                    <nav class="sge-official-tabs {{ count($documentScopes) === 3 ? 'has-all' : '' }}" aria-label="{{ __('Filtrar emitidos') }}">
                        @foreach ($documentScopes as $scope => $scopeInfo)
                            <a class="sge-official-tab {{ $tab === $scope ? 'is-active' : '' }}"
                                wire:click.prevent="selectFilter('visibility', '{{ $scope }}')"
                                href="{{ route('official-documents.create', ['visibility' => $scope]) }}"
                                aria-controls="official-document-results" aria-label="{{ $scopeInfo['label'] }}: {{ $scopeInfo['hint'] }}"
                                @if ($tab === $scope) aria-current="page" @endif>
                                <i class="fas {{ $scopeInfo['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $scopeInfo['label'] }}</span>
                                @if ($tab === $scope)<i class="fas fa-check sge-official-tab-check" aria-hidden="true"></i>@endif
                            </a>
                        @endforeach
                    </nav>
                    <p class="sge-official-scope-hint" role="status" aria-live="polite" aria-atomic="true">{{ $documentScopes[$tab]['hint'] }}</p>
                    @include('livewire.listings.search')

                    <ul id="official-document-results" class="list-unstyled mb-2">
                    @forelse ($recentDocuments as $document)
                        <li class="border-bottom pb-3 mb-3">
                            <h3 class="h6 font-weight-bold mb-1">{{ $document->title }}</h3>
                            @if ($tab !== 'public')
                                <span class="badge badge-{{ $document->visibility === 'public' ? 'primary' : 'secondary' }}">{{ $document->visibility === 'public' ? __('Público') : __('Privado') }}</span>
                            @endif
                            <span class="d-block small text-muted">{{ $document->school?->name }}</span>
                            @if (auth()->user()->isAdministrator() && $document->visibility === 'private')
                                <span class="d-block small text-muted">{{ __('Proprietário:') }} {{ $document->privateOwner?->person?->full_name ?: $document->privateOwner?->name ?: __('Usuário não disponível') }}</span>
                            @endif
                            @if ($document->issuedDocument)
                                @php($issuer = $document->issuedDocument->issuedBy ?? $document->createdBy)
                                <p class="sge-official-metadata small mb-0">
                                    <span><strong>{{ __('Código:') }}</strong> {{ $document->issuedDocument->verification_code }}</span>
                                    <span><strong>{{ __('Emitido em:') }}</strong> <time datetime="{{ $document->issuedDocument->issued_at->toIso8601String() }}">{{ $document->issuedDocument->issued_at->copy()->timezone('America/Cuiaba')->format('d/m/Y H:i') }}</time> {{ __('(MT)') }}</span>
                                    <span><strong>{{ __('Emitido por:') }}</strong> {{ $issuer?->person?->full_name ?: $issuer?->name ?: $issuer?->email ?: __('Usuário não disponível') }}</span>
                                </p>
                            @endif
                            <div class="sge-action-buttons justify-content-start sge-official-actions" role="group" aria-label="{{ __('Ações para :title', ['title' => $document->title]) }}">
                                @if ($document->issuedDocument)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('official-documents.reissue', $document) }}" target="_blank" rel="noopener" aria-label="{{ __('Abrir PDF de :title em nova aba', ['title' => $document->title]) }}" title="{{ __('Abrir PDF') }}">
                                        <i class="fas fa-print" aria-hidden="true"></i> {{ __('Abrir PDF') }}
                                    </a>
                                @endif
                                @if (auth()->user()->canManageSchool($document->school_id))
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('official-documents.edit', $document) }}" aria-label="{{ __('Editar cópia de :title', ['title' => $document->title]) }}" title="{{ __('Editar cópia') }}">
                                        <i class="fas fa-edit" aria-hidden="true"></i> {{ __('Editar cópia') }}
                                    </a>
                                @endif
                                @if (auth()->user()->isAdministrator())
                                    @if ($document->visibility === 'public')
                                        <a href="{{ route('official-documents.private-owner', $document) }}" class="btn btn-sm btn-outline-warning" aria-label="{{ __('Definir proprietário e tornar privado: :title', ['title' => $document->title]) }}" title="{{ __('Tornar privado') }}">
                                            <i class="fas fa-lock" aria-hidden="true"></i> {{ __('Tornar privado') }}
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('official-documents.visibility', $document) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="visibility" value="public">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" aria-label="{{ __('Tornar público: :title', ['title' => $document->title]) }}" title="{{ __('Tornar público') }}">
                                                <i class="fas fa-globe-americas" aria-hidden="true"></i> {{ __('Tornar público') }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                @if (auth()->user()->isAdministrator() || ($document->visibility === 'private' && $document->private_owner_user_id === auth()->id()))
                                    <form method="POST" action="{{ route('official-documents.destroy', $document) }}" data-confirm-message="{{ __('Deseja excluir este documento? Esta ação é permanente.') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Excluir :title', ['title' => $document->title]) }}" title="{{ __('Excluir') }}">
                                            <i class="fas fa-trash-alt" aria-hidden="true"></i> {{ __('Excluir') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('Nenhum item encontrado.') }}</li>
                    @endforelse
                    </ul>
                    <p class="small text-muted">{{ __('Página :page de :pages · :total documentos', ['page' => $recentDocuments->currentPage(), 'pages' => $recentDocuments->lastPage(), 'total' => $recentDocuments->total()]) }}</p>
                    {{ $recentDocuments->links('livewire::bootstrap') }}
                </div>
</div>
