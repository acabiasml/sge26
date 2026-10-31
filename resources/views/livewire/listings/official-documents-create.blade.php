<div>
                <div class="card-body">
                    @include('livewire.listings.search')
                    <nav class="nav nav-pills sge-official-tabs mb-3" aria-label="{{ __('Filtrar emitidos') }}">
                        <a class="nav-link {{ $tab === 'public' ? 'active' : '' }}" wire:click.prevent="selectFilter('visibility', 'public')" href="{{ route('official-documents.create', ['visibility' => 'public']) }}" @if ($tab === 'public') aria-current="page" @endif>{{ __('Públicos') }}</a>
                        <a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}" wire:click.prevent="selectFilter('visibility', 'mine')" href="{{ route('official-documents.create', ['visibility' => 'mine']) }}" @if ($tab === 'mine') aria-current="page" @endif>{{ __('Meus') }}</a>
                        @if (auth()->user()->isAdministrator())
                            <a class="nav-link {{ $tab === 'all' ? 'active' : '' }}" wire:click.prevent="selectFilter('visibility', 'all')" href="{{ route('official-documents.create', ['visibility' => 'all']) }}" @if ($tab === 'all') aria-current="page" @endif>{{ __('Todos') }}</a>
                        @endif
                    </nav>

                    <ul class="list-unstyled mb-2">
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
