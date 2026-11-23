<?php

use App\Models\CredentialBook;
use App\Support\CredentialBookArchive;
use App\Support\CredentialRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('credentials:check', function () {
    $failed = 0;
    foreach (CredentialBook::cursor() as $book) {
        $valid = app(CredentialRegistry::class)->integrity($book);
        $this->line('Livro #'.$book->id.': '.($valid ? 'íntegro' : 'DIVERGENTE'));
        if (! $valid) {
            $failed++;
        }
    }

    return $failed ? 1 : 0;
})->purpose('Confere a cadeia de integridade dos livros digitais sem expor dados pessoais');

Artisan::command('credentials:backup {--book=}', function () {
    foreach (CredentialBook::when($this->option('book'), fn ($q) => $q->whereKey($this->option('book')))->cursor() as $book) {
        $file = app(CredentialBookArchive::class)->backup($book, null);
        $this->line('Cópia privada do livro #'.$book->id.': arquivo #'.$file->id);
    }
})->purpose('Gera cópias privadas completas dos livros digitais para recuperação');

Schedule::command('credentials:backup')->dailyAt('02:30')->withoutOverlapping();
