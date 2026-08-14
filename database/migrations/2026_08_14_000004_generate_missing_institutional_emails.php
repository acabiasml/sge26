<?php

use App\Models\Person;
use App\Support\InstitutionalEmailGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $generator = app(InstitutionalEmailGenerator::class);
        $occupiedEmails = Person::query()
            ->whereNotNull('institutional_email')
            ->where('institutional_email', '!=', '')
            ->pluck('institutional_email')
            ->mapWithKeys(fn (string $email): array => [mb_strtolower($email) => true])
            ->all();

        DB::transaction(function () use ($generator, &$occupiedEmails): void {
            Person::query()
                ->where(fn ($query) => $query
                    ->whereNull('institutional_email')
                    ->orWhere('institutional_email', ''))
                ->orderBy('id')
                ->chunkById(500, function ($people) use ($generator, &$occupiedEmails): void {
                    foreach ($people as $person) {
                        $baseEmail = $generator->baseEmail($person->full_name);
                        [$localPart, $domain] = explode('@', $baseEmail, 2);
                        $email = $baseEmail;
                        $suffix = 2;

                        while (isset($occupiedEmails[mb_strtolower($email)])) {
                            $email = $localPart.$suffix.'@'.$domain;
                            $suffix++;
                        }

                        DB::table('people')->where('id', $person->getKey())->update([
                            'institutional_email' => $email,
                        ]);
                        $occupiedEmails[mb_strtolower($email)] = true;
                    }
                });
        });
    }

    public function down(): void
    {
        // Os endereços podem já estar em uso e não devem ser apagados no rollback.
    }
};
