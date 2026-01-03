@php
    $providers = config('services.socialite.providers', []);
@endphp

@if(!empty($providers))
    <div class="mt-6">
        <p class="text-sm text-gray-400 text-center mb-3">Or continue with</p>
        <div class="grid gap-2">
            @foreach($providers as $provider)
                <a href="{{ route('socialite.redirect', $provider) }}"
                   class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-gray-700 bg-gray-900 text-gray-100 hover:border-blue-500 hover:bg-gray-800 transition-colors">
                    <span class="text-sm font-medium">Continue with {{ ucfirst($provider) }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
