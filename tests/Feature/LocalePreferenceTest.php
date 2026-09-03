<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_save_interface_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/meu-cadastro')
            ->patch(route('locale.update'), ['locale' => 'it'])
            ->assertRedirect('/meu-cadastro');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'it',
        ]);
    }

    public function test_language_is_rejected_when_it_is_not_supported(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('locale.update'), ['locale' => 'en'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('pt_BR', $user->fresh()->locale);
    }

    public function test_saved_language_is_applied_to_later_html_requests(): void
    {
        Route::middleware('web')->get('/_test/locale-interface', function () {
            return response(app()->getLocale().'|'.__('navigation.home'));
        });

        $user = User::factory()->create(['locale' => 'it']);

        $this->actingAs($user)
            ->get('/_test/locale-interface')
            ->assertOk()
            ->assertSeeText('it|Inizio');
    }

    public function test_issued_documents_are_always_processed_in_portuguese(): void
    {
        Route::middleware('web')->get('/_test/documento', function () {
            return response(app()->getLocale().'|'.__('navigation.home'));
        })->name('test-document.pdf');

        $user = User::factory()->create(['locale' => 'it']);

        $this->actingAs($user)
            ->get('/_test/documento')
            ->assertOk()
            ->assertSeeText('pt_BR|Início');
    }
}
