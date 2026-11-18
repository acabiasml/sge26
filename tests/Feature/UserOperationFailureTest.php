<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use App\Models\UserOperationFailure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserOperationFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['web', 'auth'])->post('/test-failure/validation', function (Request $request) {
            $request->validate(['name' => ['required']], ['name.required' => 'Informe o nome antes de salvar.']);

            return response('ok');
        })->name('test-failure.validation');
        Route::middleware(['web', 'auth'])->post('/test-failure/forbidden', fn () => abort(403, 'Você não pode concluir esta operação.'))->name('test-failure.forbidden');
        Route::middleware(['web', 'auth'])->get('/test-failure/blocked', fn () => redirect('/test-failure/form')->with('status', 'Não é possível excluir este cadastro com vínculos.'))->name('test-failure.blocked');
        Route::middleware(['web', 'auth'])->get('/test-failure/form', fn () => response('Formulário'))->name('test-failure.form');
        Route::middleware(['web', 'auth'])->post('/test-failure/crash', function () {
            throw new \RuntimeException('Falha em password=senha-super-secreta token=token-oculto URL https://example.test/api?secret=chave-privada');
        })->name('test-failure.crash');
        Route::middleware(['web', 'auth'])->post('/test-failure/database', function () {
            throw new QueryException('sqlite', 'insert into people (cpf,password) values (?,?)', ['CPF-SIGILOSO', 'SEGREDO-DO-BANCO'], new \PDOException('Banco indisponível'));
        })->name('test-failure.database');
        Route::middleware(['web', 'auth'])->post('/test-failure/json', fn () => response()->json(['message' => 'Não foi possível salvar a chamada.'], 409))->name('test-failure.json');
        Route::middleware(['web', 'auth'])->post('/test-failure/livewire', fn () => response()->json(['components' => [['snapshot' => json_encode(['memo' => ['name' => 'ficha-teste', 'errors' => ['name' => ['Nome obrigatório.']]]])]]]))->name('app.livewire.update');
    }

    public function test_validation_failure_is_recorded_once_with_the_actor_and_without_request_values(): void
    {
        [$user,$school] = $this->user();
        $response = $this->actingAs($user)->from('/test-failure/form')->post('/test-failure/validation', ['cpf' => '12345678901']);
        $response->assertRedirect('/test-failure/form')->assertSessionHasErrors('name')->assertHeader('X-Beaba-Failure-ID');
        $failure = UserOperationFailure::sole();
        $this->assertSame($user->id, $failure->actor_user_id);
        $this->assertSame($school->id, $failure->school_id);
        $this->assertSame('validation', $failure->category);
        $this->assertSame(422, $failure->status_code);
        $this->assertStringContainsString('Informe o nome', $failure->message);
        $this->assertStringNotContainsString('12345678901', json_encode($failure->getAttributes()));
        $this->get('/test-failure/form')->assertOk();
        $this->assertDatabaseCount('user_operation_failures', 1);
    }

    public function test_permission_errors_blocked_redirects_and_http_errors_are_captured(): void
    {
        [$user] = $this->user();
        $this->actingAs($user);
        $this->post('/test-failure/forbidden')->assertForbidden();
        $this->get('/test-failure/blocked')->assertRedirect('/test-failure/form');
        $this->postJson('/test-failure/json')->assertStatus(409);
        $this->assertDatabaseCount('user_operation_failures', 3);
        $this->assertDatabaseHas('user_operation_failures', ['category' => 'authorization', 'status_code' => 403]);
        $this->assertDatabaseHas('user_operation_failures', ['message' => 'Não é possível excluir este cadastro com vínculos.']);
        $this->assertDatabaseHas('user_operation_failures', ['message' => 'Não foi possível salvar a chamada.', 'status_code' => 409]);
    }

    public function test_unexpected_exceptions_keep_the_reason_and_source_without_secrets_or_query_parameters(): void
    {
        [$user] = $this->user();
        $this->actingAs($user)->postJson('/test-failure/crash?token=token-oculto', ['password' => 'senha-super-secreta', 'api_token' => 'token-oculto'])->assertStatus(500);
        $failure = UserOperationFailure::sole();
        $serialized = json_encode($failure->getAttributes());
        $this->assertSame('exception', $failure->category);
        $this->assertSame(\RuntimeException::class, $failure->exception_class);
        $this->assertStringContainsString('Falha', $failure->reason);
        $this->assertStringNotContainsString('senha-super-secreta', $serialized);
        $this->assertStringNotContainsString('token-oculto', $serialized);
        $this->assertStringNotContainsString('chave-privada', $serialized);
        $this->assertSame('/test-failure/crash', $failure->path);
        $this->assertNotNull($failure->source_file);
    }

    public function test_database_exception_does_not_store_sql_bindings(): void
    {
        [$user] = $this->user();
        $this->actingAs($user)->postJson('/test-failure/database')->assertStatus(500);
        $failure = UserOperationFailure::sole();
        $text = json_encode($failure->getAttributes());
        $this->assertStringContainsString('SQLSTATE', $failure->reason);
        $this->assertStringNotContainsString('CPF-SIGILOSO', $text);
        $this->assertStringNotContainsString('SEGREDO-DO-BANCO', $text);
        $this->assertStringNotContainsString('insert into people', $text);
    }

    public function test_client_event_is_idempotent_and_cannot_impersonate_another_user(): void
    {
        [$user] = $this->user();
        $id = (string) Str::uuid();
        $data = ['event_uuid' => $id, 'category' => 'network', 'message' => 'Não foi possível consultar a turma.', 'operation' => 'Adicionar participantes', 'path' => '/cursos-livres/1/pessoas', 'method' => 'GET', 'status_code' => 0, 'actor_user_id' => 99999];
        $this->actingAs($user)->postJson(route('operation-failures.store'), $data)->assertNoContent();
        $this->postJson(route('operation-failures.store'), $data)->assertNoContent();
        $failure = UserOperationFailure::sole();
        $this->assertSame($user->id, $failure->actor_user_id);
        $this->assertSame('browser', $failure->channel);
        $this->assertSame('Adicionar participantes', $failure->operation);
    }

    public function test_only_administrators_can_open_the_global_error_panel_and_card(): void
    {
        [$manager] = $this->user();
        $this->actingAs($manager)->post('/test-failure/forbidden')->assertForbidden();
        $this->get(route('audit-logs.index'))->assertOk()->assertDontSee('Abrir log de erros');
        $this->get(route('operation-failures.index'))->assertForbidden();
        [$admin] = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk()->assertSee('Abrir log de erros');
        $this->get(route('operation-failures.index', ['to' => '2026-11-18']))->assertOk()->assertSee($manager->person->full_name);
        $this->get(route('operation-failures.index', ['q' => $manager->person->full_name]))->assertOk()->assertSee($manager->person->full_name)->assertSee('Ver contexto e diagnóstico');
        if (is_dir('/setup')) {
            file_put_contents('/setup/operation-failures-panel.html', $this->get(route('operation-failures.index'))->getContent());
        }
    }

    public function test_livewire_validation_with_http_200_is_captured_without_passive_rerender_duplicates(): void
    {
        [$user] = $this->user();
        $this->actingAs($user);
        $empty = json_encode(['memo' => ['name' => 'ficha-teste', 'errors' => []]]);
        $failed = json_encode(['memo' => ['name' => 'ficha-teste', 'errors' => ['name' => ['Nome obrigatório.']]]]);
        $this->postJson('/test-failure/livewire', ['components' => [['snapshot' => $empty, 'calls' => [['method' => 'save']]]]])->assertOk()->assertHeader('X-Beaba-Failure-ID');
        $this->postJson('/test-failure/livewire', ['components' => [['snapshot' => $failed, 'calls' => []]]])->assertOk();
        $this->assertDatabaseCount('user_operation_failures', 1);
        $this->postJson('/test-failure/livewire', ['components' => [['snapshot' => $failed, 'calls' => [['method' => 'save']]]]])->assertOk();
        $this->assertDatabaseCount('user_operation_failures', 2);
    }

    public function test_teacher_failure_keeps_the_school_context_without_management_permissions(): void
    {
        [$teacher,$school] = $this->user(PersonSchoolRole::ROLE_TEACHER);
        $this->actingAs($teacher)->post('/test-failure/forbidden')->assertForbidden();
        $failure = UserOperationFailure::sole();
        $this->assertSame($school->id, $failure->school_id);
        $this->assertSame($teacher->id, $failure->actor_user_id);
    }

    public function test_logger_failure_never_changes_the_original_error_response(): void
    {
        [$user] = $this->user();
        Schema::drop('user_operation_failures');
        $this->actingAs($user)->post('/test-failure/forbidden')->assertForbidden();
        $this->postJson(route('operation-failures.store'), ['event_uuid' => (string) Str::uuid(), 'category' => 'browser', 'message' => 'Não foi possível salvar.', 'path' => '/teste', 'method' => 'POST'])->assertStatus(503);
    }

    public function test_capture_endpoint_remains_available_while_the_user_completes_the_profile(): void
    {
        [$user] = $this->user();
        $user->person->update(['profile_completed_at' => null, 'address' => null]);
        $this->actingAs($user)->postJson(route('operation-failures.store'), ['event_uuid' => (string) Str::uuid(), 'category' => 'validation', 'message' => 'Confira os campos do cadastro.', 'path' => '/meu-cadastro', 'method' => 'PUT'])->assertNoContent();
        $this->assertDatabaseCount('user_operation_failures', 1);
    }

    private function user(string $role = PersonSchoolRole::ROLE_MANAGER): array
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(12, 0));
        $school = School::create(['name' => 'Escola do diagnóstico '.School::count(), 'active' => true]);
        $person = Person::create(['full_name' => 'Usuário do Diagnóstico '.Person::count(), 'cpf' => str_pad((string) (Person::count() + 1), 11, '0', STR_PAD_LEFT), 'birth_date' => '1980-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria Fictícia', 'phone' => '66999990000', 'address' => 'Rua de Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000', 'active' => true, 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['school_id' => $role === PersonSchoolRole::ROLE_ADMINISTRATOR ? null : $school->id, 'role' => $role, 'active' => true, 'started_at' => '2020-01-01']);

        return [User::factory()->create(['person_id' => $person->id]), $school];
    }
}
