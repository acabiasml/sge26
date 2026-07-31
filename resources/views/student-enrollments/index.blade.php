@extends('layouts.app')

@section('title', 'Matrículas - '.$class->name)
@section('page-title', 'Matrículas: '.$class->name)

@section('page-actions')
    <form method="POST" action="{{ route('classes.final-results.calculate', $class) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-outline-primary shadow-sm" type="submit">
            <i class="fas fa-check-double mr-1" aria-hidden="true"></i>Calcular resultados finais
        </button>
    </form>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('classes.final-results.pdf', $class) }}" aria-label="Emitir ata de resultados finais em PDF" title="Ata de resultados finais em PDF">
        <i class="fas fa-file-signature" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.show', [$academicYear, $class]) }}" aria-label="Voltar à turma {{ $class->name }}" title="Voltar à turma">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Contexto</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Escola</dt>
                        <dd>{{ $academicYear->school?->name }}</dd>
                        <dt>Ano letivo</dt>
                        <dd>{{ $academicYear->name }}</dd>
                        <dt>Turma</dt>
                        <dd>{{ $class->name }}</dd>
                        <dt>Período da turma</dt>
                        <dd>{{ $class->starts_at?->format('d/m/Y') ?? '-' }} a {{ $class->ends_at?->format('d/m/Y') ?? '-' }}</dd>
                        <dt>Matrizes vinculadas</dt>
                        <dd>{{ $class->courses->pluck('name')->join(' + ') ?: '-' }}</dd>
                        <dt>Matrizes disponíveis para matrícula</dt>
                        <dd>{{ $availableCourses->pluck('name')->join(' + ') ?: 'Nenhuma matriz vinculada' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">Nova matrícula</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('classes.enrollments.store', $class) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="enrollment_person_id">Estudante</label>
                                <select id="enrollment_person_id" name="person_id" class="form-control @error('person_id') is-invalid @enderror" required>
                                    <option value="">Selecione</option>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}" @selected((int) old('person_id') === $student->id)>{{ $student->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('person_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Matrizes vinculadas à turma</label>
                                @if ($availableCourses->isNotEmpty())
                                    <div class="border rounded p-2 bg-light">
                                        @foreach ($availableCourses as $course)
                                            <div class="small">{{ $course->name }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">Nenhuma matriz vinculada a esta turma.</p>
                                @endif
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="enrollment_enrolled_at">Data da matrícula</label>
                                <input id="enrollment_enrolled_at" name="enrolled_at" type="date" min="{{ $enrollmentStartsAt }}" max="{{ $enrollmentEndsAt }}" class="form-control @error('enrolled_at') is-invalid @enderror" value="{{ old('enrolled_at', $enrollmentStartsAt) }}" required>
                                @error('enrolled_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="enrollment_type">Modalidade da matrícula</label>
                                <select id="enrollment_type" name="type" class="form-control" required>
                                    @foreach (\App\Models\StudentEnrollment::TYPE_LABELS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', \App\Models\StudentEnrollment::TYPE_REGULAR) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 form-group">
                                <label for="enrollment_notes">Observações</label>
                                <input id="enrollment_notes" name="notes" class="form-control" value="{{ old('notes') }}">
                            </div>
                        </div>
                        @if ($availableCourses->isEmpty())
                            <p class="text-muted small mb-3">Vincule uma matriz curricular a esta turma antes de cadastrar novas matrículas.</p>
                        @else
                            <p class="small text-muted">A matrícula será registrada na turma e automaticamente associada às matrizes vinculadas a ela.</p>
                        @endif
                        <p class="small text-muted">Transferência, reclassificação ou cancelamento ficam registrados no histórico.</p>
                        <button class="btn btn-primary" type="submit" @disabled($students->isEmpty() || $availableCourses->isEmpty())>Adicionar matrícula</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">Matrículas</h2>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Estudante</th>
                        <th>Matrizes</th>
                        <th>Matrícula</th>
                        <th>Situação</th>
                        <th>Resultado final</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($class->enrollments->sortBy(fn ($enrollment) => $enrollment->student?->full_name) as $enrollment)
                        <tr>
                            <td>{{ $enrollment->student?->full_name }}</td>
                            <td>{{ $enrollment->courses->pluck('name')->join(' + ') ?: '-' }}</td>
                            <td>
                                {{ $enrollment->enrolled_at?->format('d/m/Y') ?? '-' }}
                                @if ($enrollment->transferred_at)
                                    <span class="d-block small text-muted">Saída: {{ $enrollment->transferred_at->format('d/m/Y') }}</span>
                                @endif
                                @if ($enrollment->cancelled_at)
                                    <span class="d-block small text-muted">Cancelada em {{ $enrollment->cancelled_at->format('d/m/Y') }}</span>
                                @endif
                                @if ($enrollment->reclassifiedFrom)
                                    <span class="d-block small text-muted">Reclassificada de {{ $enrollment->reclassifiedFrom->schoolClass?->name }}</span>
                                @endif
                            </td>
                            <td>{{ $enrollment->statusLabel() }} · {{ $enrollment->typeLabel() }}</td>
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
                                <span class="badge badge-{{ $finalTone }}">{{ $enrollment->finalResultLabel() }}</span>
                                @if ($enrollment->final_result_calculated_at)
                                    <span class="d-block small text-muted">{{ $enrollment->final_result_calculated_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="d-block small text-muted">Ainda não calculado</span>
                                @endif
                                @if (($enrollment->final_result_details['reason'] ?? null))
                                    <span class="d-block small text-muted">{{ $enrollment->final_result_details['reason'] }}</span>
                                @endif
                            </td>
                            <td class="text-right sge-actions-cell">
                                <div class="sge-row-actions sge-enrollment-actions" role="group" aria-label="Ações da matrícula de {{ $enrollment->student?->full_name }}">
                                <a class="btn btn-sm btn-primary sge-icon-action" href="{{ route('enrollments.documents', $enrollment) }}" aria-label="Abrir documentos da matrícula de {{ $enrollment->student?->full_name }}" title="Documentos da matrícula">
                                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-success sge-icon-action" href="{{ route('enrollments.report-card.show', $enrollment) }}" aria-label="Abrir boletim de {{ $enrollment->student?->full_name }}" title="Boletim">
                                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.individual-record.pdf', $enrollment) }}" aria-label="Emitir ficha individual em PDF de {{ $enrollment->student?->full_name }}" title="Ficha individual em PDF">
                                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.pdf', $enrollment) }}" aria-label="Emitir ficha de matrícula em PDF de {{ $enrollment->student?->full_name }}" title="Ficha de matrícula em PDF">
                                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.attendance-certificate.pdf', $enrollment) }}" aria-label="Emitir atestado de frequência de {{ $enrollment->student?->full_name }}" title="Atestado de frequência">
                                    <i class="fas fa-user-check" aria-hidden="true"></i>
                                </a>
                                @if ($enrollment->status === \App\Models\StudentEnrollment::STATUS_TRANSFERRED)
                                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.transfer-certificate.pdf', $enrollment) }}" aria-label="Emitir atestado de transferência de {{ $enrollment->student?->full_name }}" title="Atestado de transferência">
                                        <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                    </a>
                                @endif
                                @if ($enrollment->isActive() || in_array($enrollment->status, [\App\Models\StudentEnrollment::STATUS_TRANSFERRED, \App\Models\StudentEnrollment::STATUS_CANCELLED], true))
                                    <button class="btn btn-sm btn-outline-secondary sge-icon-action" type="button" data-toggle="modal" data-target="#enrollmentMovementModal{{ $enrollment->id }}" aria-label="Abrir movimentações da matrícula de {{ $enrollment->student?->full_name }}" title="Movimentar matrícula">
                                        <i class="fas fa-random" aria-hidden="true"></i>
                                    </button>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Nenhuma matrícula cadastrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($class->enrollments->sortBy(fn ($enrollment) => $enrollment->student?->full_name) as $enrollment)
        @if ($enrollment->isActive() || in_array($enrollment->status, [\App\Models\StudentEnrollment::STATUS_TRANSFERRED, \App\Models\StudentEnrollment::STATUS_CANCELLED], true))
            <div class="modal fade" id="enrollmentMovementModal{{ $enrollment->id }}" tabindex="-1" role="dialog" aria-labelledby="enrollmentMovementTitle{{ $enrollment->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title h5" id="enrollmentMovementTitle{{ $enrollment->id }}">Movimentar matrícula</h2>
                                <p class="mb-0 small text-muted">{{ $enrollment->student?->full_name }} · {{ $enrollment->statusLabel() }} · {{ $enrollment->courses->pluck('name')->join(' + ') ?: '-' }}</p>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            @if ($enrollment->status === \App\Models\StudentEnrollment::STATUS_CANCELLED)
                                <div class="alert alert-warning" role="alert">
                                    Esta matrícula está cancelada desde {{ $enrollment->cancelled_at?->format('d/m/Y') ?? 'data não informada' }}. Use esta ação apenas para corrigir cancelamento feito por engano.
                                </div>
                                <form method="POST" action="{{ route('enrollments.restore-cancellation', $enrollment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-group">
                                        <label for="restore_notes_{{ $enrollment->id }}">Observações da reversão</label>
                                        <textarea id="restore_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="Opcional"></textarea>
                                    </div>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-undo mr-1" aria-hidden="true"></i>Desfazer cancelamento
                                    </button>
                                </form>
                            @elseif ($enrollment->status === \App\Models\StudentEnrollment::STATUS_TRANSFERRED)
                                <div class="alert alert-warning" role="alert">
                                    Esta matrícula está transferida desde {{ $enrollment->transferred_at?->format('d/m/Y') ?? 'data não informada' }}. Use esta ação somente quando a transferência foi registrada por engano.
                                </div>
                                <form method="POST" action="{{ route('enrollments.restore-transfer', $enrollment) }}" onsubmit="return confirm('Desfazer a transferência desta matrícula? O estudante voltará a ficar ativo nesta turma se não houver outra matrícula ativa no mesmo ano letivo.')">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-group">
                                        <label for="restore_transfer_notes_{{ $enrollment->id }}">Observações da reversão</label>
                                        <textarea id="restore_transfer_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="Opcional"></textarea>
                                    </div>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-undo mr-1" aria-hidden="true"></i>Desfazer transferência
                                    </button>
                                </form>
                            @else
                                <div class="row">
                                    <div class="col-lg-6 mb-4">
                                        <section class="sge-movement-panel h-100">
                                            <h3 class="h6">Transferência</h3>
                                            <p class="small text-muted">Registra a saída do estudante preservando o histórico desta matrícula.</p>
                                            <form method="POST" action="{{ route('enrollments.transfer', $enrollment) }}" onsubmit="return confirm('Confirmar transferência desta matrícula? Antes de registrar, confira se as notas do período atual estão completas.')">
                                                @csrf
                                                @method('PATCH')
                                                <div class="form-group">
                                                    <label for="transferred_at_{{ $enrollment->id }}">Data de transferência</label>
                                                    <input id="transferred_at_{{ $enrollment->id }}" name="transferred_at" type="date" min="{{ $enrollment->enrolled_at?->format('Y-m-d') }}" max="{{ $enrollmentEndsAt }}" class="form-control" required>
                                                </div>
                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input class="custom-control-input" type="checkbox" name="confirm_transfer" value="1" id="confirm_transfer_{{ $enrollment->id }}" required>
                                                    <label class="custom-control-label" for="confirm_transfer_{{ $enrollment->id }}">Conferi a matrícula, a data e as notas do período atual.</label>
                                                </div>
                                                <div class="form-group">
                                                    <label for="transfer_notes_{{ $enrollment->id }}">Observações</label>
                                                    <textarea id="transfer_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="Opcional"></textarea>
                                                </div>
                                                <button class="btn btn-warning btn-block" type="submit">
                                                    <i class="fas fa-exchange-alt mr-1" aria-hidden="true"></i>Transferir
                                                </button>
                                            </form>
                                        </section>
                                    </div>
                                    <div class="col-lg-6 mb-4">
                                        <section class="sge-movement-panel h-100">
                                            <h3 class="h6">Reclassificação</h3>
                                            <p class="small text-muted">Move o estudante para outra turma e mantém o vínculo com os lançamentos já realizados.</p>
                                            <form method="POST" action="{{ route('enrollments.reclassify', $enrollment) }}">
                                                @csrf
                                                <div class="form-group">
                                                    <label for="target_school_class_id_{{ $enrollment->id }}">Turma de destino</label>
                                                    <select id="target_school_class_id_{{ $enrollment->id }}" name="target_school_class_id" class="form-control" data-reclassify-target required>
                                                        <option value="">Selecione</option>
                                                        @foreach ($targetClasses as $targetClass)
                                                            <option value="{{ $targetClass->id }}">{{ $targetClass->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reclassify_course_ids_{{ $enrollment->id }}">Matrizes de destino</label>
                                                    <select id="reclassify_course_ids_{{ $enrollment->id }}" name="course_ids[]" class="form-control" data-reclassify-courses multiple required disabled></select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reclassified_at_{{ $enrollment->id }}">Data de reclassificação</label>
                                                    <input id="reclassified_at_{{ $enrollment->id }}" name="reclassified_at" type="date" min="{{ $enrollment->enrolled_at?->format('Y-m-d') }}" max="{{ $enrollmentEndsAt }}" class="form-control" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reclassify_notes_{{ $enrollment->id }}">Observações</label>
                                                    <textarea id="reclassify_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="Opcional"></textarea>
                                                </div>
                                                <button class="btn btn-info btn-block" type="submit">
                                                    <i class="fas fa-random mr-1" aria-hidden="true"></i>Reclassificar
                                                </button>
                                            </form>
                                        </section>
                                    </div>
                                </div>

                                <section class="sge-movement-panel sge-movement-danger">
                                    <h3 class="h6">Cancelamento</h3>
                                    <p class="small text-muted">Use apenas quando a matrícula foi registrada indevidamente. Transferência deve ser usada para saída para outra escola.</p>
                                    <form method="POST" action="{{ route('enrollments.cancel', $enrollment) }}" onsubmit="return confirm('Cancelar esta matrícula? O histórico será preservado.')">
                                        @csrf
                                        @method('PATCH')
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label for="cancelled_at_{{ $enrollment->id }}">Data de cancelamento</label>
                                                <input id="cancelled_at_{{ $enrollment->id }}" name="cancelled_at" type="date" min="{{ $enrollment->enrolled_at?->format('Y-m-d') }}" max="{{ $enrollmentEndsAt }}" class="form-control" required>
                                            </div>
                                            <div class="col-md-8 form-group">
                                                <label for="cancellation_notes_{{ $enrollment->id }}">Motivo</label>
                                                <input id="cancellation_notes_{{ $enrollment->id }}" name="notes" class="form-control" placeholder="Motivo do cancelamento" required>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline-danger" type="submit">
                                            <i class="fas fa-ban mr-1" aria-hidden="true"></i>Cancelar matrícula
                                        </button>
                                    </form>
                                </section>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>
        const targetClassCourseOptions = @json($targetClassCourseOptions);

        document.querySelectorAll('[data-reclassify-target]').forEach((targetSelect) => {
            const coursesSelect = targetSelect.closest('form')?.querySelector('[data-reclassify-courses]');

            const syncTargetCourses = () => {
                if (!coursesSelect) {
                    return;
                }

                const courses = targetClassCourseOptions[targetSelect.value] || [];
                coursesSelect.replaceChildren();
                coursesSelect.disabled = courses.length === 0;

                courses.forEach((course) => {
                    const option = new Option(course.name, course.id);
                    coursesSelect.add(option);
                });
            };

            targetSelect.addEventListener('change', syncTargetCourses);
            syncTargetCourses();
        });
    </script>
@endpush
