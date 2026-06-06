<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\PersonContact;
use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class NormalizeTitleCaseData extends Command
{
    protected $signature = 'data:normalize-title-case';

    protected $description = 'Normaliza nomes e enderecos de cadastros para iniciais maiusculas.';

    public function handle(): int
    {
        $total = 0;

        $total += $this->normalize(Person::class, 'pessoas');
        $total += $this->normalize(School::class, 'escolas');
        $total += $this->normalize(PersonContact::class, 'contatos');
        $total += $this->normalize(User::class, 'usuarios');

        $this->info("Normalizacao concluida. Registros revisados: {$total}.");

        return self::SUCCESS;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function normalize(string $modelClass, string $label): int
    {
        $count = 0;

        $modelClass::query()->chunkById(200, function ($records) use (&$count, $label): void {
            foreach ($records as $record) {
                if (method_exists($record, 'applyTitleCaseAttributes')) {
                    $record->applyTitleCaseAttributes();
                }

                $record->saveQuietly();
                $count++;
            }

            $this->line("{$label}: {$count}");
        });

        return $count;
    }
}
