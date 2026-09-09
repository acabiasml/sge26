@extends('layouts.app')

@section('title', __('Matrículas - ').$class->name)
@section('page-title', __('Matrículas: ').$class->name)

@section('page-actions')
    <form method="POST" action="{{ route('classes.final-results.calculate', $class) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" type="submit" aria-label="{{ __('Calcular resultados finais da turma') }}" title="{{ __('Calcular resultados finais') }}">
            <i class="fas fa-check-double" aria-hidden="true"></i>
        </button>
    </form>
    <a class="btn btn-sm btn-outline-primary shadow-sm sge-icon-action" href="{{ route('classes.final-results.pdf', $class) }}" aria-label="{{ __('Emitir ata de resultados finais em PDF') }}" title="{{ __('Ata de resultados finais em PDF') }}">
        <i class="fas fa-file-signature" aria-hidden="true"></i>
    </a>
    <a class="btn btn-sm btn-outline-secondary shadow-sm sge-icon-action" href="{{ route('academic-years.classes.show', [$academicYear, $class]) }}" aria-label="{{ __('Voltar à turma') }} {{ $class->name }}" title="{{ __('Voltar à turma') }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
@endsection

@section('content')
    <nav class="sge-section-nav sge-academic-tabs mb-4" aria-label="{{ __('Áreas das matrículas') }}" role="tablist" data-section-tabs data-default-tab="{{ $errors->any() ? 'nova' : 'matriculas' }}">
        <a href="#section-contexto" class="sge-section-nav-item" data-academic-tab="contexto" role="tab"><i class="fas fa-info-circle"></i><span>{{ __('Contexto') }}</span><small>{{ __('turma e matrizes') }}</small></a>
        <a href="#section-nova" class="sge-section-nav-item" data-academic-tab="nova" role="tab"><i class="fas fa-user-plus"></i><span>{{ __('Nova matrícula') }}</span><small>{{ __('incluir estudante') }}</small></a>
        <a href="#section-matriculas" class="sge-section-nav-item" data-academic-tab="matriculas" role="tab"><i class="fas fa-user-graduate"></i><span>{{ __('Matrículas') }}</span><small>{{ $class->enrollments->count() }} {{ __('estudantes') }}</small></a>
    </nav>

    <div class="row">
        <div id="section-contexto" class="col-12 mb-4" data-academic-panel="contexto" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Contexto') }}</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>{{ __('Escola') }}</dt>
                        <dd>{{ $academicYear->school?->name }}</dd>
                        <dt>{{ __('Ano letivo') }}</dt>
                        <dd>{{ $academicYear->name }}</dd>
                        <dt>{{ __('Turma') }}</dt>
                        <dd>{{ $class->name }}</dd>
                        <dt>{{ __('Vigência acadêmica da turma') }}</dt>
                        <dd>{{ $class->startsPeriod?->name ?? __('Período inicial não definido') }} {{ __('até') }} {{ $class->endsPeriod?->name ?? __('período final não definido') }}</dd>
                        <dt>{{ __('Matrizes vinculadas') }}</dt>
                        <dd>{{ $class->courses->pluck('name')->join(' + ') ?: '-' }}</dd>
                        <dt>{{ __('Matrizes disponíveis para matrícula') }}</dt>
                        <dd>{{ $availableCourses->pluck('name')->join(' + ') ?: __('Nenhuma matriz vinculada') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div id="section-nova" class="col-12 mb-4" data-academic-panel="nova" role="tabpanel">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Nova matrícula') }}</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('classes.enrollments.store', $class) }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="enrollment_person_id">{{ __('Estudante') }}</label>
                                <select id="enrollment_person_id" name="person_id" class="form-control @error('person_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Selecione') }}</option>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}" @selected((int) old('person_id') === $student->id)>{{ $student->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('person_id') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Matrizes vinculadas à turma') }}</label>
                                @if ($availableCourses->isNotEmpty())
                                    <div class="border rounded p-2 bg-light">
                                        @foreach ($availableCourses as $course)
                                            <div class="small">{{ $course->name }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">{{ __('Nenhuma matriz vinculada a esta turma.') }}</p>
                                @endif
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="enrollment_enrolled_at">{{ __('Data da matrícula') }}</label>
                                <input id="enrollment_enrolled_at" name="enrolled_at" type="date" min="{{ $enrollmentStartsAt }}" max="{{ $enrollmentEndsAt }}" class="form-control @error('enrolled_at') is-invalid @enderror" value="{{ old('enrolled_at', $enrollmentStartsAt) }}" required>
                                @error('enrolled_at') <div class="invalid-feedback">{{ __($message) }}</div> @enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="enrollment_type">{{ __('Modalidade da matrícula') }}</label>
                                <select id="enrollment_type" name="type" class="form-control" required>
                                    @foreach (\App\Models\StudentEnrollment::TYPE_LABELS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', \App\Models\StudentEnrollment::TYPE_REGULAR) === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 form-group">
                                <label for="enrollment_notes">{{ __('Observações') }}</label>
                                <input id="enrollment_notes" name="notes" class="form-control" value="{{ old('notes') }}">
                            </div>
                        </div>
                        @if ($availableCourses->isEmpty())
                            <p class="text-muted small mb-3">{{ __('Vincule uma matriz curricular a esta turma antes de cadastrar novas matrículas.') }}</p>
                        @else
                            <p class="small text-muted">{{ __('A matrícula será registrada na turma e automaticamente associada às matrizes vinculadas a ela.') }}</p>
                        @endif
                        <p class="small text-muted">{{ __('Transferência, reclassificação ou cancelamento ficam registrados no histórico.') }}</p>
                        <button class="btn btn-primary" type="submit" @disabled($students->isEmpty() || $availableCourses->isEmpty())>{{ __('Adicionar matrícula') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="section-matriculas" class="card shadow mb-4" data-academic-panel="matriculas" role="tabpanel">
        <div class="card-header py-3">
            <h2 class="h6 m-0 font-weight-bold text-primary">{{ __('Matrículas') }}</h2>
        </div>
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
                    @forelse ($class->enrollments->sortBy(fn ($enrollment) => $enrollment->student?->full_name) as $enrollment)
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
                                @else
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
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.individual-record.pdf', $enrollment) }}" aria-label="{{ __('Emitir ficha individual em PDF de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Ficha individual em PDF') }}">
                                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.pdf', $enrollment) }}" aria-label="{{ __('Emitir ficha de matrícula em PDF de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Ficha de matrícula em PDF') }}">
                                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.attendance-certificate.pdf', $enrollment) }}" aria-label="{{ __('Emitir atestado de frequência de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Atestado de frequência') }}">
                                    <i class="fas fa-user-check" aria-hidden="true"></i>
                                </a>
                                @if ($enrollment->status === \App\Models\StudentEnrollment::STATUS_TRANSFERRED)
                                    <a class="btn btn-sm btn-outline-primary sge-icon-action" href="{{ route('enrollments.transfer-certificate.pdf', $enrollment) }}" aria-label="{{ __('Emitir atestado de transferência de') }} {{ $enrollment->student?->full_name }}" title="{{ __('Atestado de transferência') }}">
                                        <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                    </a>
                                @endif
                                @if ($enrollment->isActive() || in_array($enrollment->status, [\App\Models\StudentEnrollment::STATUS_TRANSFERRED, \App\Models\StudentEnrollment::STATUS_CANCELLED], true))
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
    </div>

    @foreach ($class->enrollments->sortBy(fn ($enrollment) => $enrollment->student?->full_name) as $enrollment)
        @if ($enrollment->isActive() || in_array($enrollment->status, [\App\Models\StudentEnrollment::STATUS_TRANSFERRED, \App\Models\StudentEnrollment::STATUS_CANCELLED], true))
            <div class="modal fade" id="enrollmentMovementModal{{ $enrollment->id }}" tabindex="-1" role="dialog" aria-labelledby="enrollmentMovementTitle{{ $enrollment->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title h5" id="enrollmentMovementTitle{{ $enrollment->id }}">{{ __('Movimentar matrícula') }}</h2>
                                <p class="mb-0 small text-muted">{{ $enrollment->student?->full_name }} · {{ __($enrollment->statusLabel()) }} · {{ $enrollment->courses->pluck('name')->join(' + ') ?: '-' }}</p>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fechar') }}">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            @if ($enrollment->status === \App\Models\StudentEnrollment::STATUS_CANCELLED)
                                <div class="alert alert-warning" role="alert">
                                    {{ __('Esta matrícula está cancelada desde') }} {{ $enrollment->cancelled_at?->format('d/m/Y') ?? __('data não informada') }}{{ __('. Use esta ação apenas para corrigir cancelamento feito por engano.') }}
                                </div>
                                <form method="POST" action="{{ route('enrollments.restore-cancellation', $enrollment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-group">
                                        <label for="restore_notes_{{ $enrollment->id }}">{{ __('Observações da reversão') }}</label>
                                        <textarea id="restore_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="{{ __('Opcional') }}"></textarea>
                                    </div>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-undo mr-1" aria-hidden="true"></i>{{ __('Desfazer cancelamento') }}
                                    </button>
                                </form>
                            @elseif ($enrollment->status === \App\Models\StudentEnrollment::STATUS_TRANSFERRED)
                                <div class="alert alert-warning" role="alert">
                                    {{ __('Esta matrícula está transferida desde') }} {{ $enrollment->transferred_at?->format('d/m/Y') ?? __('data não informada') }}{{ __('. Use esta ação somente quando a transferência foi registrada por engano.') }}
                                </div>
                                <form method="POST" action="{{ route('enrollments.restore-transfer', $enrollment) }}" onsubmit="return confirm(@js(__('Desfazer a transferência desta matrícula? O estudante voltará a ficar ativo nesta turma se não houver outra matrícula ativa no mesmo ano letivo.')))">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-group">
                                        <label for="restore_transfer_notes_{{ $enrollment->id }}">{{ __('Observações da reversão') }}</label>
                                        <textarea id="restore_transfer_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="{{ __('Opcional') }}"></textarea>
                                    </div>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-undo mr-1" aria-hidden="true"></i>{{ __('Desfazer transferência') }}
                                    </button>
                                </form>
                            @else
                                <div class="row">
                                    <div class="col-lg-6 mb-4">
                                        <section class="sge-movement-panel h-100">
                                            <h3 class="h6">{{ __('Transferência') }}</h3>
                                            <p class="small text-muted">{{ __('Registra a saída do estudante preservando o histórico desta matrícula.') }}</p>
                                            <form method="POST" action="{{ route('enrollments.transfer', $enrollment) }}" onsubmit="return confirm(@js(__('Confirmar transferência desta matrícula? Antes de registrar, confira se as notas do período atual estão completas.')))">
                                                @csrf
                                                @method('PATCH')
                                                <div class="form-group">
                                                    <label for="transferred_at_{{ $enrollment->id }}">{{ __('Data de transferência') }}</label>
                                                    <input id="transferred_at_{{ $enrollment->id }}" name="transferred_at" type="date" min="{{ $enrollment->enrolled_at?->format('Y-m-d') }}" max="{{ $enrollmentEndsAt }}" class="form-control" required>
                                                </div>
                                                <div class="custom-control custom-checkbox mb-3">
                                                    <input class="custom-control-input" type="checkbox" name="confirm_transfer" value="1" id="confirm_transfer_{{ $enrollment->id }}" required>
                                                    <label class="custom-control-label" for="confirm_transfer_{{ $enrollment->id }}">{{ __('Conferi a matrícula, a data e as notas do período atual.') }}</label>
                                                </div>
                                                <div class="form-group">
                                                    <label for="transfer_notes_{{ $enrollment->id }}">{{ __('Observações') }}</label>
                                                    <textarea id="transfer_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="{{ __('Opcional') }}"></textarea>
                                                </div>
                                                <button class="btn btn-warning btn-block" type="submit">
                                                    <i class="fas fa-exchange-alt mr-1" aria-hidden="true"></i>{{ __('Transferir') }}
                                                </button>
                                            </form>
                                        </section>
                                    </div>
                                    <div class="col-lg-6 mb-4">
                                        <section class="sge-movement-panel h-100">
                                            <h3 class="h6">{{ __('Reclassificação') }}</h3>
                                            <p class="small text-muted">{{ __('Move o estudante para outra turma e mantém o vínculo com os lançamentos já realizados.') }}</p>
                                            <form method="POST" action="{{ route('enrollments.reclassify', $enrollment) }}">
                                                @csrf
                                                <div class="form-group">
                                                    <label for="target_school_class_id_{{ $enrollment->id }}">{{ __('Turma de destino') }}</label>
                                                    <select id="target_school_class_id_{{ $enrollment->id }}" name="target_school_class_id" class="form-control" data-reclassify-target required>
                                                        <option value="">{{ __('Selecione') }}</option>
                                                        @foreach ($targetClasses as $targetClass)
                                                            <option value="{{ $targetClass->id }}">{{ $targetClass->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reclassify_course_ids_{{ $enrollment->id }}">{{ __('Matrizes de destino') }}</label>
                                                    <select id="reclassify_course_ids_{{ $enrollment->id }}" name="course_ids[]" class="form-control" data-reclassify-courses multiple required disabled></select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reclassified_at_{{ $enrollment->id }}">{{ __('Data de reclassificação') }}</label>
                                                    <input id="reclassified_at_{{ $enrollment->id }}" name="reclassified_at" type="date" min="{{ $enrollment->enrolled_at?->format('Y-m-d') }}" max="{{ $enrollmentEndsAt }}" class="form-control" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reclassify_notes_{{ $enrollment->id }}">{{ __('Observações') }}</label>
                                                    <textarea id="reclassify_notes_{{ $enrollment->id }}" name="notes" class="form-control" rows="3" placeholder="{{ __('Opcional') }}"></textarea>
                                                </div>
                                                <button class="btn btn-info btn-block" type="submit">
                                                    <i class="fas fa-random mr-1" aria-hidden="true"></i>{{ __('Reclassificar') }}
                                                </button>
                                            </form>
                                        </section>
                                    </div>
                                </div>

                                <section class="sge-movement-panel sge-movement-danger">
                                    <h3 class="h6">{{ __('Cancelamento') }}</h3>
                                    <p class="small text-muted">{{ __('Use apenas quando a matrícula foi registrada indevidamente. Transferência deve ser usada para saída para outra escola.') }}</p>
                                    <form method="POST" action="{{ route('enrollments.cancel', $enrollment) }}" onsubmit="return confirm(@js(__('Cancelar esta matrícula? O histórico será preservado.')))">
                                        @csrf
                                        @method('PATCH')
                                        <div class="row">
                                            <div class="col-md-4 form-group">
                                                <label for="cancelled_at_{{ $enrollment->id }}">{{ __('Data de cancelamento') }}</label>
                                                <input id="cancelled_at_{{ $enrollment->id }}" name="cancelled_at" type="date" min="{{ $enrollment->enrolled_at?->format('Y-m-d') }}" max="{{ $enrollmentEndsAt }}" class="form-control" required>
                                            </div>
                                            <div class="col-md-8 form-group">
                                                <label for="cancellation_notes_{{ $enrollment->id }}">{{ __('Motivo') }}</label>
                                                <input id="cancellation_notes_{{ $enrollment->id }}" name="notes" class="form-control" placeholder="{{ __('Motivo do cancelamento') }}" required>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline-danger" type="submit">
                                            <i class="fas fa-ban mr-1" aria-hidden="true"></i>{{ __('Cancelar matrícula') }}
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
