<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_modal_contains_full_safe_message_and_only_visible_announcements(): void
    {
        $user = User::factory()->create();
        $body = str_repeat('Mensagem completa. ', 15)."\nÚltima linha <script>alert(1)</script>";
        $announcement = Announcement::query()->create([
            'title' => 'Recado completo', 'body' => $body, 'active' => true, 'starts_at' => now()->subHour(),
        ]);
        $school = School::query()->create(['name' => 'Outra escola']);
        Announcement::query()->create([
            'school_id' => $school->id, 'title' => 'Recado restrito', 'body' => 'Conteúdo restrito',
            'active' => true, 'starts_at' => now()->subHour(),
        ]);
        Announcement::query()->create([
            'title' => 'Recado vencido', 'body' => 'Conteúdo vencido', 'active' => true,
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('data-target="#announcement-alert-'.$announcement->id.'"', false)
            ->assertSee('id="announcement-alert-'.$announcement->id.'"', false)
            ->assertSee($body)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Conteúdo restrito')->assertDontSee('Conteúdo vencido');
    }
}
