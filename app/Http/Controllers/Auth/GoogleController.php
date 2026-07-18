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
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
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

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if (! PersonSchoolRole::activeAdministratorQuery()->exists()) {
            [$user, $person] = $this->bootstrapFirstAdministrator($user, $email, $googleUser);
        } else {
            $person = $user?->person ?: $this->findAllowedPerson($email);

            if (! $person) {
                return redirect('/')
                    ->with('status', 'Seu e-mail institucional ainda não foi cadastrado no Beabá. Procure a administração da escola.');
            }

            if (! $person->active) {
                return redirect('/')
                    ->with('status', 'Seu cadastro está inativo no Beabá. Procure a administração da escola.');
            }

            if (! $person->schoolRoles()->where('active', true)->exists()) {
                return redirect('/')
                    ->with('status', 'Seu cadastro ainda não possui um papel ativo no Beabá. Procure a administração da escola.');
            }

            $user ??= $this->createUserForPerson($person, $email, $googleUser);
        }

        $user->forceFill([
            'name' => $user->name ?: $googleUser->getName(),
            'person_id' => $user->person_id ?: $person->id,
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();

        Auth::login($user, remember: true);

        if (! $person->hasCompletedProfile()) {
            return redirect()->route('profile.edit');
        }

        $intended = redirect()->intended(route('dashboard'));

        if ($intended->getTargetUrl() === route('login')) {
            return redirect()->route('dashboard');
        }

        return $intended;
    }

    private function findAllowedPerson(string $email): ?Person
    {
        return Person::query()
            ->where('institutional_email', $email)
            ->where('active', true)
            ->first();
    }

    /**
     * @return array{0: User, 1: Person}
     */
    private function bootstrapFirstAdministrator(?User $user, string $email, mixed $googleUser): array
    {
        $person = $user?->person ?: Person::query()
            ->where('institutional_email', $email)
            ->first();

        if (! $person) {
            $person = Person::query()->create([
                'full_name' => $googleUser->getName() ?: $email,
                'institutional_email' => $email,
                'active' => true,
            ]);
        } elseif (! $person->active) {
            $person->forceFill(['active' => true])->save();
        }

        $person->schoolRoles()->firstOrCreate(
            [
                'school_id' => null,
                'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            ],
            [
                'active' => true,
                'started_at' => now()->toDateString(),
            ],
        );

        $user ??= $this->createUserForPerson($person, $email, $googleUser);

        return [$user, $person];
    }

    private function createUserForPerson(Person $person, string $email, mixed $googleUser): User
    {
        return User::query()->create([
            'person_id' => $person->id,
            'name' => $googleUser->getName() ?: $person->full_name,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
        ]);
    }
}
