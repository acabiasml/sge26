<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    private function announcement(array $data = []): Announcement
    {
        return Announcement::create($data + ['title' => 'Aviso importante', 'body' => 'Mensagem original', 'active' => true, 'highlight' => true, 'starts_at' => now()->subHour()]);
    }

    private function manager(?School $school = null): User
    {
        $person = Person::create(['full_name' => 'Gestor']);
        $person->schoolRoles()->create(['school_id' => $school?->id, 'role' => $school ? PersonSchoolRole::ROLE_MANAGER : PersonSchoolRole::ROLE_ADMINISTRATOR, 'active' => true, 'started_at' => now()->subDay()]);

        return User::factory()->create(['person_id' => $person->id]);
    }

    public function test_edit_loads_existing_values_and_updates_the_same_record(): void
    {
        $announcement = $this->announcement();
        $admin = $this->manager();
        $this->actingAs($admin)->get(route('announcements.edit', $announcement))->assertOk()->assertSee('Mensagem original');
        $this->put(route('announcements.update', $announcement), ['title' => 'Novo título', 'body' => 'Novo conteúdo', 'starts_at' => now()->toDateTimeString(), 'active' => 1, 'highlight' => 0])->assertRedirect(route('announcements.index'));
        $this->assertSame('Novo conteúdo', $announcement->fresh()->body);
        $this->assertFalse($announcement->fresh()->highlight);
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_manager_cannot_edit_other_school_or_move_notice_outside_scope(): void
    {
        $school = School::create(['name' => 'Escola A']);
        $other = School::create(['name' => 'Escola B']);
        $own = $this->announcement(['school_id' => $school->id]);
        $foreign = $this->announcement(['school_id' => $other->id]);
        $this->actingAs($this->manager($school))->get(route('announcements.edit', $own))->assertOk();
        $this->get(route('announcements.edit', $foreign))->assertForbidden();
        $this->put(route('announcements.update', $own), ['school_id' => $other->id, 'title' => 'Título', 'body' => 'Texto', 'starts_at' => now()->toDateTimeString()])->assertForbidden();
    }

    public function test_highlights_reappear_after_login_until_acknowledged_and_reads_are_per_user(): void
    {
        $user = User::factory()->create();
        $announcement = $this->announcement();
        $marker = 'id="highlighted-'.$announcement->id.'"';
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee($marker, false);
        $this->get(route('profile.edit'))->assertDontSee($marker, false);
        Event::dispatch(new Login('web', $user, false));
        $this->get(route('profile.edit'))->assertSee($marker, false);
        $this->postJson(route('announcements.seen', $announcement))->assertNoContent();
        $this->postJson(route('announcements.seen', $announcement))->assertNoContent();
        $this->assertDatabaseCount('announcement_reads', 1);
        Event::dispatch(new Login('web', $user, false));
        $this->get(route('profile.edit'))->assertDontSee($marker, false);
        $other = User::factory()->create();
        Event::dispatch(new Login('web', $other, false));
        $this->actingAs($other)->get(route('profile.edit'))->assertSee($marker, false);
    }

    public function test_hidden_expired_and_nonhighlighted_notices_do_not_pop_up(): void
    {
        $school = School::create(['name' => 'Restrita']);
        $hidden = $this->announcement(['school_id' => $school->id]);
        $expired = $this->announcement(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $ordinary = $this->announcement(['highlight' => false]);
        $future = $this->announcement(['starts_at' => now()->addDay()]);
        $inactive = $this->announcement(['active' => false]);
        $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk()->assertDontSee('class="modal fade sge-highlight-modal"', false);
        foreach ([$hidden, $expired, $future, $inactive] as $announcement) {
            $this->postJson(route('announcements.seen', $announcement))->assertForbidden();
        }
    }
}
