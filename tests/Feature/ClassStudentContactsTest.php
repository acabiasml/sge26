<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonContact;
use App\Models\PersonRelationship;
use App\Support\ClassStudentContacts;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ClassStudentContactsTest extends TestCase
{
    public function test_age_and_all_contact_sources_are_preserved_without_duplicates(): void
    {
        $person = new Person(['full_name' => 'Aluno', 'birth_date' => '2010-10-07', 'cpf' => '12345678901',
            'mother_name' => 'Maria', 'father_name' => 'João']);
        $person->setRelation('contacts', collect([
            new PersonContact(['name' => 'Maria', 'relationship_type' => 'mae', 'phone' => '1111', 'secondary_phone' => '2222']),
        ]));
        $relation = new PersonRelationship(['relationship_type' => 'mae']);
        $relation->setRelation('relatedPerson', new Person(['full_name' => 'Maria', 'phone' => '1111']));
        $person->setRelation('relationships', collect([$relation]));
        $date = CarbonImmutable::parse('2026-10-06', 'America/Cuiaba');
        $row = ClassStudentContacts::student($person, $date);
        $this->assertSame(15, $row['age']);
        $this->assertSame(16, ClassStudentContacts::student($person, $date->addDay())['age']);
        $this->assertSame('123.456.789-01', $row['cpf']);
        $this->assertSame('07/10/2010', $row['birth_date']);
        $this->assertSame('Maria', $row['mother']);
        $this->assertSame('João', $row['father']);
        $this->assertCount(1, $row['contacts']);
        $this->assertSame(['1111', '2222'], $row['contacts'][0]['phones']);
        $person->birth_date = null;
        $this->assertNull(ClassStudentContacts::student($person, $date)['age']);
        $person->birth_date = '2030-01-01';
        $this->assertNull(ClassStudentContacts::student($person, $date)['age']);
    }
}
