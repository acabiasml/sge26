<?php

namespace App\Support;

use App\Models\CurriculumComponent;
use App\Models\FreeCourseOffering;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\UserOperationFailure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UserOperationFailureRecorder
{
    public function exception(Request $request, Response $response, Throwable $exception): void
    {
        $status = $response->getStatusCode();
        $category = $exception instanceof ValidationException ? 'validation' : ($status === 403 ? 'authorization' : ($status >= 500 ? 'exception' : 'http'));
        $message = $exception instanceof ValidationException ? implode(' ', Arr::flatten($exception->errors())) : ($status < 500 && filled($exception->getMessage()) ? $exception->getMessage() : $this->statusMessage($status));
        $reason = $exception instanceof QueryException || $exception instanceof \PDOException
            ? 'Falha no banco de dados. SQLSTATE: '.($exception->errorInfo[0] ?? $exception->getCode()).'.'
            : $exception->getMessage();
        $source = collect([['file' => $exception->getFile(), 'line' => $exception->getLine()], ...$exception->getTrace()])
            ->first(fn ($frame) => isset($frame['file']) && str_starts_with($frame['file'], base_path().DIRECTORY_SEPARATOR) && ! str_contains($frame['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR));
        $record = $this->record($request, ['category' => $category, 'message' => $message, 'reason' => $reason,
            'status_code' => $exception instanceof ValidationException ? 422 : $status, 'exception_class' => $exception::class,
            'source_file' => isset($source['file']) ? Str::after($source['file'], base_path().DIRECTORY_SEPARATOR) : null, 'source_line' => $source['line'] ?? null]);
        $this->markResponse($request, $response, $record);
    }

    public function response(Request $request, Response $response): void
    {
        if ($request->attributes->has('beaba.failure.record')) {
            $this->markResponse($request, $response, $request->attributes->get('beaba.failure.record'));

            return;
        }
        $data = [];
        if ($response->getStatusCode() >= 400) {
            $json = $response instanceof JsonResponse ? $response->getData(true) : [];
            $message = is_string($json) ? $json : (is_array($json) ? ($json['message'] ?? implode(' ', Arr::flatten($json['errors'] ?? []))) : null);
            $data = ['category' => $response->getStatusCode() === 403 ? 'authorization' : ($response->getStatusCode() >= 500 ? 'exception' : 'http'),
                'status_code' => $response->getStatusCode(), 'message' => is_string($message) && $message !== '' ? $message : $this->statusMessage($response->getStatusCode())];
        } elseif ($response->isRedirection() && $request->hasSession()) {
            $session = $request->session();
            $new = $session->get('_flash.new', []);
            if (in_array('errors', $new, true) && ($bag = $session->get('errors')) instanceof ViewErrorBag) {
                $data = ['category' => 'validation', 'status_code' => 422, 'message' => implode(' ', $bag->all())];
            } else {
                foreach (['error', 'warning', 'status'] as $key) {
                    $message = $session->get($key);
                    if (in_array($key, $new, true) && is_string($message) && ($key === 'error' || $this->isFailureMessage($message))) {
                        $data = ['category' => 'validation', 'message' => $message, 'reason' => 'Operação interrompida pelas regras do sistema.'];
                        break;
                    }
                }
            }
        } elseif ($response instanceof JsonResponse && $request->routeIs('livewire.update', 'app.livewire.update')) {
            foreach ($response->getData(true)['components'] ?? [] as $index => $component) {
                $snapshot = json_decode($component['snapshot'] ?? '{}', true);
                $old = json_decode($request->input('components.'.$index.'.snapshot', '{}'), true);
                $errors = $snapshot['memo']['errors'] ?? [];
                if ($errors && ($errors !== ($old['memo']['errors'] ?? []) || $request->input('components.'.$index.'.calls'))) {
                    $data = ['category' => 'validation', 'status_code' => 422, 'message' => implode(' ', Arr::flatten($errors))];
                    break;
                }
            }
        }
        if ($data) {
            $this->markResponse($request, $response, $this->record($request, $data));
        }
    }

    public function record(Request $request, array $data): ?UserOperationFailure
    {
        if ($request->attributes->has('beaba.failure.record')) {
            return $request->attributes->get('beaba.failure.record');
        }
        if ($request->routeIs('operation-failures.store') && ($data['channel'] ?? null) !== 'browser') {
            return null;
        }
        if (! $request->user() && ! $request->hasCookie(config('session.cookie')) && ! $request->is('login')) {
            return null;
        }
        try {
            if (! Schema::hasTable('user_operation_failures')) {
                return null;
            }
            $context = [];
            $schoolId = null;
            foreach ($request->route()?->parameters() ?? [] as $name => $value) {
                if (preg_match('/password|senha|secret|token|signature|code/i', $name)) {
                    continue;
                }
                if ($value instanceof Model) {
                    $context[$name] = ['id' => $value->getKey(), 'nome' => $value->getAttribute('full_name') ?? $value->getAttribute('name') ?? $value->getAttribute('title')];
                    if ($value instanceof StudentEnrollment) {
                        $context['estudante'] = ['id' => $value->person_id, 'nome' => $value->student?->full_name];
                        $context['turma'] = $value->schoolClass?->name;
                        $schoolId = $value->schoolClass?->academicYear?->school_id;
                    } else {
                        $schoolId ??= $name === 'school' ? $value->getKey() : $value->getAttribute('school_id');
                        if ($value instanceof SchoolClass) {
                            $schoolId ??= $value->academicYear?->school_id;
                        } elseif ($value instanceof CurriculumComponent) {
                            $schoolId ??= $value->course?->academicYear?->school_id;
                        } elseif ($value instanceof FreeCourseOffering) {
                            $schoolId ??= $value->freeCourse?->school_id;
                        }
                    }
                } elseif (is_scalar($value)) {
                    $context[$name] = mb_substr((string) $value, 0, 100);
                }
            }
            foreach (['transferred_at', 'reclassified_at', 'starts_at', 'ends_at', 'issued_date'] as $field) {
                if (is_string($request->input($field)) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $request->input($field))) {
                    $context[$field] = $request->input($field);
                }
            }
            if ($request->routeIs('livewire.update', 'app.livewire.update')) {
                $context['componentes'] = collect($request->input('components', []))->map(function ($component) {
                    $snapshot = json_decode($component['snapshot'] ?? '{}', true);

                    return ['componente' => $snapshot['memo']['name'] ?? null, 'acoes' => collect($component['calls'] ?? [])->pluck('method')->all()];
                })->all();
            }
            $schoolIds = $request->user()?->visibleSchoolIds() ?? [];
            $schoolId ??= count($schoolIds) === 1 ? $schoolIds[0] : null;
            $record = UserOperationFailure::firstOrCreate(['event_uuid' => $data['event_uuid'] ?? (string) Str::uuid()], [
                'actor_user_id' => $request->user()?->id, 'actor_name' => $request->user()?->person?->full_name ?? $request->user()?->name,
                'school_id' => $schoolId, 'channel' => $data['channel'] ?? 'server', 'category' => $data['category'] ?? 'browser',
                'operation' => $this->clean($data['operation'] ?? $this->operation($request), $request, 255),
                'route_name' => $request->route()?->getName(), 'method' => $data['method'] ?? $request->method(),
                'path' => $this->clean($this->path($data['path'] ?? $request->getPathInfo()), $request, 1024),
                'screen_path' => $this->clean($this->path($request->headers->get('referer')) ?? '', $request, 1024) ?: null,
                'status_code' => $data['status_code'] ?? null, 'message' => $this->clean($data['message'], $request),
                'reason' => $this->clean($data['reason'] ?? '', $request, 4000) ?: null,
                'exception_class' => $data['exception_class'] ?? null, 'source_file' => $data['source_file'] ?? null,
                'source_line' => $data['source_line'] ?? null, 'context' => $this->cleanContext($context, $request), 'occurred_at' => now(),
            ]);
            if ($record->actor_user_id !== $request->user()?->id) {
                return null;
            }
            $request->attributes->set('beaba.failure.record', $record);

            return $record;
        } catch (Throwable) {
            // O diagnóstico nunca pode impedir a operação ou substituir o erro original.
            Log::warning('Não foi possível guardar uma ocorrência no painel de erros.');

            return null;
        }
    }

    private function markResponse(Request $request, Response $response, ?UserOperationFailure $record): void
    {
        if (! $record) {
            return;
        }
        $response->headers->set('X-Beaba-Failure-ID', (string) $record->id);
        if ($response->isRedirection() && $request->hasSession()) {
            $request->session()->flash('_operation_failure_id', $record->id);
        }
    }

    public function isFailureMessage(string $message): bool
    {
        return (bool) preg_match('/não\s+(?:foi|é|pode|podem|consegui|será)|bloquead|falh|impossív|unable|could not|cannot|not allowed|non\s+(?:è|e|può|puoi|consentito)|errore|impossibile/iu', $message);
    }

    private function path(?string $url): ?string
    {
        return $url ? mb_substr(parse_url($url, PHP_URL_PATH) ?: '/', 0, 1024) : null;
    }

    private function cleanContext(array $context, Request $request): array
    {
        return collect($context)->map(fn ($value) => is_array($value) ? $this->cleanContext($value, $request) : (is_string($value) ? $this->clean($value, $request, 300) : $value))->all();
    }

    private function clean(string $text, Request $request, int $limit = 8000): string
    {
        $text = strip_tags($text);
        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            if (is_string($value) && $value !== '' && preg_match('/password|senha|secret|token|signature|code/i', $name)) {
                $text = str_replace([$value, rawurlencode($value)], '[omitido]', $text);
            }
        }
        foreach (['Authorization', 'Cookie', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN'] as $header) {
            if ($value = $request->headers->get($header)) {
                $text = str_replace($value, '[omitido]', $text);
            }
        }
        foreach (Arr::dot($request->all()) as $key => $value) {
            if (is_string($value) && $value !== '' && preg_match('/password|senha|secret|token|authorization|cookie|private.key|api.key|recovery.code/i', $key)) {
                $text = str_replace($value, '[omitido]', $text);
            }
        }
        $text = preg_replace('/(senha|password|secret|token|authorization|api[_-]?key)\s*[:=]\s*[^\s,;]+/iu', '$1=[omitido]', $text);
        $text = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=\-]+/i', '$1 [omitido]', $text);
        $text = preg_replace('~(https?://[^\s?]+)\?[^\s]+~iu', '$1?[parâmetros omitidos]', $text);
        $text = preg_replace('~(/(?:reset-password|password/reset)/)[^/\s]+~i', '$1[omitido]', $text);

        return mb_substr($text, 0, $limit);
    }

    private function operation(Request $request): string
    {
        return match ($request->route()?->getName()) {
            'enrollments.transfer' => 'Registrar transferência de estudante',
            'enrollments.transfer-certificate.pdf' => 'Emitir atestado de transferência',
            'document-issuance.issue' => 'Emitir documento pela Central de Emissão',
            'document-issuance.targets' => 'Localizar destinatário na Central de Emissão',
            'free-course-certificates.store' => 'Emitir certificados de curso livre',
            'technical-credentials.store' => 'Emitir certificado ou diploma técnico',
            'credential-archive.destroy' => 'Excluir certificado ou diploma',
            'teacher-diaries.attendance.batch-update' => 'Salvar frequência no diário',
            'teacher-diaries.grades.update' => 'Salvar notas no diário',
            'operation-failures.index' => 'Consultar painel de erros',
            'audit-logs.index' => 'Consultar auditoria',
            'teacher-diaries.contents.update' => 'Salvar conteúdos no diário',
            'people.update' => 'Atualizar cadastro de pessoa',
            'schools.update' => 'Atualizar cadastro da escola',
            default => ($request->isMethod('GET') ? 'Consultar' : 'Executar').' '.Str::headline(str_replace('.', ' ', $request->route()?->getName() ?? $request->getPathInfo())),
        };
    }

    private function statusMessage(int $status): string
    {
        return match ($status) {
            401 => 'É necessário entrar no sistema para continuar.', 403 => 'Você não tem permissão para esta operação.',
            404 => 'Registro ou página não encontrado.', 419 => 'A sessão expirou. Entre novamente para continuar.',
            422 => 'Confira os dados informados.', 429 => 'Muitas tentativas. Aguarde antes de tentar novamente.',
            default => $status >= 500 ? 'Não foi possível concluir a operação por uma falha do sistema.' : 'Não foi possível concluir a solicitação.',
        };
    }
}
