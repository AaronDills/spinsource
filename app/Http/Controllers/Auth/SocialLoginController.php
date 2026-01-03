<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SocialLoginController extends Controller
{
    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirect(string $provider): RedirectResponse|SymfonyRedirectResponse
    {
        $this->guardAgainstUnsupportedProvider($provider);

        $driver = $this->driverFor($provider);
        $scopes = $this->providerScopes($provider);

        if (! empty($scopes)) {
            $driver->scopes($scopes);
        }

        return $driver->redirect();
    }

    /**
     * Obtain the user information from provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->guardAgainstUnsupportedProvider($provider);

        try {
            $socialUser = $this->driverFor($provider)->user();
        } catch (Throwable $throwable) {
            report($throwable);

            return redirect()
                ->route('login')
                ->withErrors(['oauth' => "Unable to connect with {$this->displayName($provider)}. Please try again."]);
        }

        if (! $socialUser->getEmail()) {
            return redirect()
                ->route('login')
                ->withErrors(['oauth' => "We couldn't find an email address on your {$this->displayName($provider)} profile. Please update your {$this->displayName($provider)} account to share an email and try again."]);
        }

        $user = User::query()
            ->where('provider_name', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user) {
            $user = User::where('email', $socialUser->getEmail())->first();

            $payload = [
                'provider_name' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar_url' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
            ];

            if ($user) {
                $user->fill($payload);
                $user->save();
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?: ($socialUser->getNickname() ?: 'Music Lover'),
                    'email' => $socialUser->getEmail(),
                    'password' => Str::random(32),
                    ...$payload,
                ]);
            }
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Ensure provider is one we expect.
     */
    protected function guardAgainstUnsupportedProvider(string $provider): void
    {
        abort_unless(in_array($provider, $this->supportedProviders(), true), 404);
    }

    /**
     * Return configured providers.
     */
    protected function supportedProviders(): array
    {
        return array_filter(Config::get('services.socialite.providers', []));
    }

    /**
     * Build the driver for a provider.
     */
    protected function driverFor(string $provider)
    {
        if ($provider === 'spotify') {
            return Socialite::buildProvider(\SocialiteProviders\Spotify\Provider::class, Config::get('services.spotify', []));
        }

        return Socialite::driver($provider);
    }

    /**
     * Provider-specific scopes, if any.
     */
    protected function providerScopes(string $provider): array
    {
        return Config::get("services.{$provider}.scopes", []);
    }

    /**
     * Human readable provider name.
     */
    protected function displayName(string $provider): string
    {
        return ucfirst($provider);
    }
}
