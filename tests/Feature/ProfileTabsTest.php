<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_groups_fields_into_four_accessible_tabs_including_editable_socioeconomic_data(): void
    {
        [$user, $person] = $this->fixture();
        $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(4, $xpath->query('//form[@data-profile-form]//button[@role="tab"]')->length);
        $groups = ['identificacao' => ['full_name', 'cpf', 'mother_name'], 'contatos' => ['phone', 'personal_email'],
            'endereco' => ['address', 'city', 'postal_code'], 'socioeconomica' => ['socio-rg_number', 'socio-family_income_range', 'socio-has_allergies']];
        foreach ($groups as $key => $fields) {
            $this->assertSame(1, $xpath->query('//button[@id="profile-tab-'.$key.'" and @aria-controls="profile-panel-'.$key.'"]')->length);
            $this->assertSame(1, $xpath->query('//section[@id="profile-panel-'.$key.'" and @role="tabpanel" and @aria-labelledby="profile-tab-'.$key.'"]')->length);
            foreach ($fields as $field) $this->assertSame(1, $xpath->query('//section[@id="profile-panel-'.$key.'"]//*[@id="'.$field.'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//input[@id="socio-benefit_year" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="socio-benefits-alimentacao"]/ancestor::fieldset[@disabled]')->length);
        $this->assertSame(0, $xpath->query('//section[@id="profile-panel-socioeconomica"]//details')->length);
        $this->assertSame(0, $xpath->query('//input[@id="socio-rg_number" and @disabled]')->length);
    }

    public function test_people_can_update_only_their_own_data_preserving_identity_locks_and_management_fields(): void
    {
        [$user, $person] = $this->fixture();
        $person->update(['socioeconomic_data' => ['benefit_year' => 2026, 'benefits' => ['alimentacao'], 'assessed_at' => '2026-11-01', 'lives_with' => ['pai']]]);
        $other = Person::create(['full_name' => 'Outra pessoa']);
        $beforeOther = $other->fresh()->getAttributes();
        $this->actingAs($user)->put(route('profile.update'), array_replace($this->payload($person), [
            'full_name' => 'Nome forjado', 'cpf' => '99999999999', 'birth_date' => '1995-01-01', 'mother_name' => 'Filiação forjada',
            'phone' => '(66) 99999-2222', 'nis' => '00123456789', 'student_inep' => '001234567890', 'receives_federal_aid' => '1',
            'person_id' => $other->id, 'socioeconomic_profile_present' => 1,
            'socioeconomic_data' => ['rg_number' => '000123RG', 'family_income_range' => '2_a_3', 'has_disability' => '0', 'image_use_authorized' => null, 'assessed_at' => '2026-11-09'],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $updated = $person->fresh();
        foreach (['full_name', 'cpf', 'mother_name'] as $key) $this->assertSame($person->$key, $updated->$key);
        $this->assertSame($person->birth_date->toDateString(), $updated->birth_date->toDateString());
        $this->assertSame('00123456789', $updated->nis);
        $this->assertSame('001234567890', $updated->student_inep);
        $this->assertTrue($updated->receives_federal_aid);
        $this->assertSame('000123RG', $updated->socioeconomic_data['rg_number']);
        $this->assertFalse($updated->socioeconomic_data['has_disability']);
        $this->assertNull($updated->socioeconomic_data['image_use_authorized']);
        $this->assertSame([], $updated->socioeconomic_data['lives_with']);
        foreach (['benefit_year', 'benefits'] as $key) $this->assertSame($person->socioeconomic_data[$key], $updated->socioeconomic_data[$key]);
        $this->assertSame('2026-11-09', $updated->socioeconomic_data['assessed_at']);
        $this->assertSame($beforeOther, $other->fresh()->getAttributes());
    }

    public function test_self_service_rejects_forged_benefits_and_preserves_they_are_read_only_after_validation_errors(): void
    {
        [$user, $person] = $this->fixture();
        $person->update(['socioeconomic_data' => ['benefit_year' => 2026, 'benefits' => ['alimentacao'], 'assessed_at' => '2026-11-01']]);
        $before = $person->fresh()->getAttributes();
        foreach (['benefits' => [], 'benefit_year' => 2025] as $key => $value) {
            $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update'), $this->payload($person) + ['socioeconomic_data' => [$key => $value]])
                ->assertSessionHasErrors('socioeconomic_data');
            $this->assertSame($before, $person->fresh()->getAttributes());
        }
        $html = $this->get(route('profile.edit'))->assertOk()->assertSee('data-default-tab="socioeconomica"', false)->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//input[@id="socio-benefit_year" and @value="2026" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="socio-benefits-alimentacao" and @checked]')->length);
    }

    public function test_management_can_edit_its_own_benefit_fields_and_errors_open_the_correct_tab(): void
    {
        [$user, $person] = $this->fixture(PersonSchoolRole::ROLE_MANAGER);
        $this->actingAs($user)->put(route('profile.update'), $this->payload($person) + ['socioeconomic_data' => ['benefit_year' => 2026, 'benefits' => ['alimentacao'], 'assessed_at' => '2026-11-01']])->assertSessionHasNoErrors();
        $this->assertSame(['alimentacao'], $person->fresh()->socioeconomic_data['benefits']);
        foreach (['contatos' => ['personal_email' => 'inválido'], 'endereco' => ['city' => ''], 'socioeconomica' => ['socioeconomic_data' => ['sus_card' => '123']]] as $tab => $invalid) {
            $this->from(route('profile.edit'))->put(route('profile.update'), array_replace($this->payload($person), $invalid) + ['profile_tab' => 'identificacao'])->assertSessionHasErrors();
            $this->get(route('profile.edit'))->assertOk()->assertSee('data-default-tab="'.$tab.'"', false);
        }
    }

    public function test_incomplete_profiles_can_complete_registration_from_the_new_tabs(): void
    {
        [$user, $person] = $this->fixture();
        $person->update(['profile_completed_at' => null, 'address' => null]);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Ficha socioeconômica');
        $payload = $this->payload($person);
        $payload['address'] = 'Rua confirmada';
        $this->put(route('profile.update'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertTrue($person->fresh()->hasCompletedProfile());
    }

    public function fixture(string $role = PersonSchoolRole::ROLE_STUDENT): array
    {
        $school = School::create(['name' => 'Escola de teste']);
        $person = Person::create(['full_name' => 'Pessoa de teste', 'cpf' => '12345678901', 'birth_date' => '2007-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Maria de teste', 'address' => 'Rua da escola', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800-000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'school_id' => $school->id, 'active' => true]);

        return [User::factory()->create(['person_id' => $person->id]), $person];
    }

    private function payload(Person $person): array
    {
        return $person->only(['full_name', 'cpf', 'birth_city', 'birth_state', 'nationality', 'mother_name', 'address', 'city', 'state', 'postal_code'])
            + ['birth_date' => $person->birth_date->toDateString()];
    }
}
