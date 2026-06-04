<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect('/')
                ->with('status', 'Configure GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET no arquivo .env para ativar o login com Google.');
        }

        return Socialite::driver('google')
            ->with(['hd' => config('services.google.allowed_domain')])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $exception) {
            Log::warning('Google login callback state mismatch.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
            } catch (Throwable $statelessException) {
                Log::warning('Google stateless login callback failed.', [
                    'exception' => $statelessException::class,
                    'message' => $statelessException->getMessage(),
                ]);

                return redirect('/')
                    ->with('status', 'Não foi possível validar o retorno do Google. Tente entrar novamente.');
            }
        } catch (Throwable $exception) {
            Log::warning('Google login callback failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $message = app()->isLocal()
                ? 'Falha no login com Google: '.$exception::class.' - '.$exception->getMessage()
                : 'Não foi possível autenticar com o Google. Tente novamente.';

            return redirect('/')
                ->with('status', $message);
        }

        if (! $googleUser->getEmail()) {
            return redirect('/')
                ->with('status', 'A conta Google precisa informar um e-mail para acessar o Beabá.');
        }

        $email = strtolower($googleUser->getEmail());
        $allowedDomain = strtolower((string) config('services.google.allowed_domain'));

        if ($allowedDomain && ! str_ends_with($email, '@'.$allowedDomain)) {
            return redirect('/')
                ->with('status', 'Use sua conta institucional @'.$allowedDomain.' para acessar o Beabá.');
        }

        $isFirstUser = PersonSchoolRole::query()
            ->where('role', PersonSchoolRole::ROLE_ADMINISTRATOR)
            ->where('active', true)
            ->doesntExist();
        $person = $isFirstUser
            ? $this->createFirstAdministratorPerson($googleUser, $email)
            : $this->findAllowedPerson($email);

        if (! $person) {
            return redirect('/')
                ->with('status', 'Seu e-mail institucional ainda não foi cadastrado no Beabá. Procure a administração da escola.');
        }

        if (! $isFirstUser && ! $person->schoolRoles()->where('active', true)->exists()) {
            return redirect('/')
                ->with('status', 'Seu cadastro ainda não possui um papel ativo no Beabá. Procure a administração da escola.');
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user) {
            $user->forceFill([
                'name' => $user->name ?: $googleUser->getName(),
                'person_id' => $user->person_id ?: $person->id,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        } else {
            $user = User::query()->create([
                'person_id' => $person->id,
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $email,
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        Auth::login($user, remember: true);

        if (! $person->hasCompletedProfile()) {
            return redirect()->route('profile.edit');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function createFirstAdministratorPerson(mixed $googleUser, string $email): Person
    {
        $person = Person::query()->firstOrCreate(
            ['institutional_email' => $email],
            [
                'full_name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $email,
                'active' => true,
            ]
        );

        $person->schoolRoles()->firstOrCreate(
            [
                'school_id' => null,
                'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            ],
            [
                'position' => null,
                'active' => true,
                'started_at' => now()->toDateString(),
            ]
        );

        return $person;
    }

    private function findAllowedPerson(string $email): ?Person
    {
        return Person::query()
            ->where('institutional_email', $email)
            ->where('active', true)
            ->first();
    }
}
