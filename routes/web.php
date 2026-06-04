<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonRelationshipController;
use App\Http\Controllers\PersonRoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordPdfController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('login');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
Route::get('/documentos/verificar/{code}', [ReportController::class, 'verify'])->name('documents.verify');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'profile.complete'])->name('dashboard');

Route::get('/meu-cadastro', [ProfileController::class, 'edit'])->middleware('auth')->name('profile.edit');
Route::put('/meu-cadastro', [ProfileController::class, 'update'])->middleware('auth')->name('profile.update');

Route::middleware(['auth', 'profile.complete'])->group(function (): void {
    Route::resource('escolas', SchoolController::class)
        ->parameters(['escolas' => 'school'])
        ->names('schools')
        ->except(['show', 'destroy']);
    Route::get('escolas/{school}/pdf', [RecordPdfController::class, 'school'])->name('schools.pdf');

    Route::resource('pessoas', PersonController::class)
        ->parameters(['pessoas' => 'person'])
        ->names('people')
        ->except(['destroy']);
    Route::get('pessoas/{person}/pdf', [RecordPdfController::class, 'person'])->name('people.pdf');
    Route::post('pessoas/{person}/relacoes', [PersonRelationshipController::class, 'store'])->name('people.relationships.store');
    Route::delete('pessoas/{person}/relacoes/{relationship}', [PersonRelationshipController::class, 'destroy'])->name('people.relationships.destroy');

    Route::get('vinculos', [PersonRoleController::class, 'index'])->name('people.roles.index');
    Route::post('pessoas/{person}/vinculos', [PersonRoleController::class, 'store'])->name('people.roles.store');
    Route::patch('pessoas/{person}/vinculos/{role}/ativar', [PersonRoleController::class, 'activate'])->name('people.roles.activate');
    Route::patch('pessoas/{person}/vinculos/{role}/desativar', [PersonRoleController::class, 'deactivate'])->name('people.roles.deactivate');
    Route::put('pessoas/{person}/vinculos/{role}', [PersonRoleController::class, 'update'])->name('people.roles.update');
    Route::delete('pessoas/{person}/vinculos/{role}', [PersonRoleController::class, 'destroy'])->name('people.roles.destroy');

    Route::get('auditoria', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::patch('auditoria/fuso-horario', [AuditLogController::class, 'updateTimezone'])->name('audit-logs.timezone.update');
    Route::get('auditoria/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    Route::get('relatorios/{type}/excel', [ReportController::class, 'excel'])->name('reports.excel');
    Route::get('relatorios/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
});

Route::post('/logout', function () {
    auth()->logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');
