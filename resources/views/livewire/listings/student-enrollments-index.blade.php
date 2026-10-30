<div>
@include('livewire.listings.search')
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Estudante') }}</th>
                        <th>{{ __('Matrizes') }}</th>
                        <th>{{ __('Matrícula') }}</th>
                        <th>{{ __('Situação') }}</th>
                        <th>{{ __('Resultado final') }}</th>
                        <th class="text-right">{{ __('Ações') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($listingRows as $enrollment)
                        <tr>
                            <td>{{ $enrollment->student?->full_name }}</td>
                            <td>{{ $enrollment->courses->pluck('name')->join(' + ') ?: '-' }}</td>
                            <td>
                                {{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}
                                @if ($enrollment->transferred_at)
                                    <span class="d-block small text-muted">{{ __('Saída:') }} {{ $enrollment->transferred_at->format('d/m/Y') }}</span>
                                @endif
                                @if ($enrollment->cancelled_at)
                                    <span class="d-block small text-muted">{{ __('Cancelada em') }} {{ $enrollment->cancelled_at->format('d/m/Y') }}</span>
                                @endif
                                @if ($enrollment->reclassifiedFrom)
                                    <span class="d-block small text-muted">{{ __('Reclassificada de') }} {{ $enrollment->reclassifiedFrom->schoolClass?->name }}</span>
                                @endif
                            </td>
                            <td>{{ __($enrollment->statusLabel()) }} · {{ __($enrollment->typeLabel()) }}</td>
                            <td>
                                @php
                                    $finalTone = match ($enrollment->final_result_status) {
                                        \App\Models\StudentEnrollment::FINAL_APPROVED => 'success',
                                        \App\Models\StudentEnrollment::FINAL_DEPENDENCY => 'warning',
                                        \App\Models\StudentEnrollment::FINAL_RETAINED_POINTS,
                                        \App\Models\StudentEnrollment::FINAL_RETAINED_ATTENDANCE => 'danger',
                                        \App\Models\StudentEnrollment::FINAL_TRANSFERRED,
                                        \App\Models\StudentEnrollment::FINAL_RECLASSIFIED,
                                        \App\Models\StudentEnrollment::FINAL_CANCELLED => 'secondary',
                                        \App\Models\StudentEnrollment::FINAL_PENDING => 'info',
                                        default => 'light',
                                    };
                                @endphp
                                <span class="badge badge-{{ $finalTone }}">{{ __($enrollment->finalResultLabel()) }}</span>
                                @if ($enrollment->final_result_calculated_at)
                                    <span class="d-block small text-muted">{{ $enrollment->final_result_calculated_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
                                @elseif(! $enrollment->isActive())
                                    <span class="d-block small text-muted">{{ __('Ainda não calculado') }}</span>
                                @endif
                                @if (($enrollment->final_result_details['reason'] ?? null))
                                    <span class="d-block small text-muted">{{ $enrollment->final_result_details['reason'] }}</span>
                                @endif
                            </td>
                            <td class="text-right sge-actions-cell">
                                <div class="sge-row-actions sge-enrollment-actions" role="group" aria-label="{{ __('Ações da matrícula de') }} {{ $enrollment->student?->full_name }}">
                                <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('enrollments.documents', $enrollment) }}" aria-label="{{ __('Abrir documentos da matrícula de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Documentos da matrícula') }}">
                                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-success sge-icon-action" href="{{ route('enrollments.report-card.show', $enrollment) }}" aria-label="{{ __('Abrir boletim de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Boletim') }}">
                                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.origin-sheet', $enrollment) }}" title="{{ __('Notas e faltas da escola de origem') }}" aria-label="{{ __('Notas e faltas da escola de origem') }}"><i class="fas fa-file-import" aria-hidden="true"></i></a>
                                <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.individual-record.pdf', $enrollment) }}" aria-label="{{ __('Emitir ficha individual em PDF de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Ficha individual em PDF') }}">
                                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                                </a>
                                <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.pdf', $enrollment) }}" aria-label="{{ __('Emitir ficha de matrícula em PDF de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Ficha de matrícula em PDF') }}">
                                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                </a>
                                <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.attendance-certificate.pdf', $enrollment) }}" aria-label="{{ __('Emitir atestado de frequência de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Atestado de frequência') }}">
                                    <i class="fas fa-user-check" aria-hidden="true"></i>
                                </a>
                                    <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.transfer-certificate.pdf', $enrollment) }}" aria-label="{{ __('Emitir atestado de transferência de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Atestado de transferência') }}">
                                        <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                    </a>
                                @if ($canManageEnrollments && ($enrollment->isActive() || in_array($enrollment->status, [\App\Models\StudentEnrollment::STATUS_TRANSFERRED, \App\Models\StudentEnrollment::STATUS_CANCELLED], true)))
                                    <button class="btn btn-sm btn-outline-secondary sge-icon-action" type="button" data-toggle="modal" data-target="#enrollmentMovementModal{{ $enrollment->id }}" aria-label="{{ __('Abrir movimentações da matrícula de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Movimentar matrícula') }}">
                                        <i class="fas fa-random" aria-hidden="true"></i>
                                    </button>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('Nenhuma matrícula cadastrada.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

@if(isset($listingPages))<div class="mt-3">{{ $listingPages->links('livewire::bootstrap') }}</div>@endif
</div>
