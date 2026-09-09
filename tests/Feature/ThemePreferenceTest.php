<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_themes_and_preference_is_rendered_on_later_requests(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->assertSame('beaba', $user->fresh()->theme);

        foreach (['govbr', 'beaba'] as $theme) {
            $this->actingAs($user)->from('/meu-cadastro')
                ->patch(route('theme.update'), ['theme' => $theme, 'id' => $otherUser->id])
                ->assertRedirect('/meu-cadastro');

            $this->assertSame($theme, $user->fresh()->theme);
            $this->assertSame('beaba', $otherUser->fresh()->theme);

            $this->actingAs($user->fresh())->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('data-theme="'.$theme.'"', false)
                ->assertSee('Tema da interface')
                ->assertSee(route('theme.update'), false);
        }
    }

    public function test_unsupported_or_missing_theme_does_not_replace_saved_preference(): void
    {
        $user = User::factory()->create(['theme' => 'govbr']);

        foreach (['dark', '', null, ['govbr']] as $theme) {
            $this->actingAs($user)->patch(route('theme.update'), ['theme' => $theme])
                ->assertSessionHasErrors('theme');
            $this->assertSame('govbr', $user->fresh()->theme);
        }
    }

    public function test_guest_cannot_change_theme(): void
    {
        $this->patch(route('theme.update'), ['theme' => 'govbr'])
            ->assertRedirect(route('login'));
    }
}
