<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleWorkspaceService
{
    private const SCOPE = 'https://www.googleapis.com/auth/admin.directory.user';

    public function isConfigured(): bool
    {
        return (bool) config('services.google_workspace.enabled')
            && filled(config('services.google_workspace.credentials_path'))
            && filled(config('services.google_workspace.administrator_email'));
    }

    /** @return array{created: bool, temporary_password: ?string} */
    public function provision(Person $person): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('A integração com o Google Workspace ainda não está configurada.');
        }

        if (blank($person->institutional_email) || blank($person->full_name)) {
            throw new RuntimeException('Informe o nome e o e-mail institucional antes de criar a conta Google.');
        }

        $existing = $this->request()->get('users/'.rawurlencode($person->institutional_email));

        if ($existing->successful()) {
            $this->markProvisioned($person, (string) $existing->json('id'));

            return ['created' => false, 'temporary_password' => null];
        }

        if ($existing->status() !== 404) {
            $this->throwGoogleError($existing->json('error.message'), $existing->status());
        }

        $password = Str::password(16, symbols: true);
        [$givenName, $familyName] = $this->splitName($person->full_name);

        $response = $this->request()->post('users', [
            'primaryEmail' => $person->institutional_email,
            'name' => ['givenName' => $givenName, 'familyName' => $familyName],
            'password' => $password,
            'changePasswordAtNextLogin' => true,
            'orgUnitPath' => config('services.google_workspace.organizational_unit', '/'),
        ]);

        if (! $response->successful()) {
            $this->throwGoogleError($response->json('error.message'), $response->status());
        }

        $this->markProvisioned($person, (string) $response->json('id'));

        return ['created' => true, 'temporary_password' => $password];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl('https://admin.googleapis.com/admin/directory/v1')
            ->acceptJson()
            ->withToken($this->accessToken())
            ->timeout(20);
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $credentials['client_email'],
            'sub' => config('services.google_workspace.administrator_email'),
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        if (! openssl_sign($header.'.'.$claims, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Não foi possível assinar a credencial do Google Workspace.');
        }

        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $header.'.'.$claims.'.'.$this->base64Url($signature),
        ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException('O Google recusou a credencial: '.($response->json('error_description') ?: 'verifique a delegação do domínio.'));
        }

        return (string) $response->json('access_token');
    }

    /** @return array{client_email: string, private_key: string} */
    private function credentials(): array
    {
        $path = (string) config('services.google_workspace.credentials_path');
        $path = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('O arquivo de credenciais do Google Workspace não foi encontrado.');
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (blank($data['client_email'] ?? null) || blank($data['private_key'] ?? null)) {
            throw new RuntimeException('O arquivo de credenciais do Google Workspace é inválido.');
        }

        return $data;
    }

    /** @return array{string, string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $givenName = array_shift($parts) ?: $name;

        return [$givenName, $parts ? implode(' ', $parts) : $givenName];
    }

    private function markProvisioned(Person $person, string $id): void
    {
        $person->forceFill([
            'google_workspace_id' => $id,
            'google_workspace_provisioned_at' => now(),
        ])->save();
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function throwGoogleError(mixed $message, int $status): never
    {
        throw new RuntimeException('O Google Workspace não concluiu a operação ('.$status.'): '.($message ?: 'erro não informado.'));
    }
}
