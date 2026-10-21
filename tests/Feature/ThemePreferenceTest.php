<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_choose_a_palette_without_light_or_dark_options(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->assertSame('beaba', $user->fresh()->theme);

        foreach (array_keys(User::INTERFACE_THEMES) as $theme) {
            $this->actingAs($user)->from('/meu-cadastro')
                ->patch(route('theme.update'), ['theme' => $theme, 'id' => $otherUser->id])
                ->assertRedirect('/meu-cadastro');

            $this->assertSame($theme, $user->fresh()->theme);
            $this->assertSame('beaba', $otherUser->fresh()->theme);

            $response = $this->actingAs($user->fresh())->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('data-theme="'.$theme.'"', false)
                ->assertSee('Tema da interface')
                ->assertSee('Claro ou escuro conforme o dispositivo.')
                ->assertSee('sge-system-theme.js')
                ->assertSee(route('theme.update'), false);

            foreach (['aurora', 'instagram', 'tiktok', 'gmail', 'outlook'] as $family) {
                $response->assertDontSee('value="'.$family.'-dark"', false);
            }
        }
    }

    public function test_existing_dark_preferences_keep_their_palette_and_follow_the_system(): void
    {
        $user = User::factory()->create();

        foreach (['aurora', 'instagram', 'tiktok', 'gmail', 'outlook'] as $family) {
            $user->update(['theme' => $family.'-dark']);

            $this->assertSame($family.'-light', $user->fresh()->interfaceTheme());
            $this->actingAs($user->fresh())->get(route('profile.edit'))
                ->assertOk()->assertSee('data-theme="'.$family.'-light"', false)
                ->assertDontSee('value="'.$family.'-dark"', false);
        }
    }

    public function test_unsupported_or_manual_brightness_theme_does_not_replace_saved_preference(): void
    {
        $user = User::factory()->create(['theme' => 'govbr']);

        foreach (['dark', 'aurora-dark', 'instagram-dark', '', null, ['govbr']] as $theme) {
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
