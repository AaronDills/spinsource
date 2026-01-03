<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Review') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="card">
                <div class="p-6">
                    <!-- Album Info -->
                    <div class="flex gap-4 mb-6 pb-6 border-b border-gray-200">
                        @if($rating->album?->cover_image_url)
                            <img src="{{ $rating->album->cover_image_url }}"
                                 alt="{{ $rating->album->title }}"
                                 class="w-24 h-24 rounded object-cover">
                        @else
                            <div class="w-24 h-24 rounded bg-gray-200 flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                </svg>
                            </div>
                        @endif
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                <a href="{{ route('album.show', $rating->album) }}" class="hover:text-blue-600">
                                    {{ $rating->album?->title ?? 'Unknown Album' }}
                                </a>
                            </h3>
                            @if($rating->album?->artist)
                                <p class="text-gray-500">
                                    <a href="{{ route('artist.show', $rating->album->artist) }}" class="hover:text-gray-700">
                                        {{ $rating->album->artist->name }}
                                    </a>
                                </p>
                            @endif
                            @if($rating->album?->release_year)
                                <p class="text-sm text-gray-400">{{ $rating->album->release_year }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- Edit Form -->
                    <form method="POST" action="{{ route('account.reviews.update', $rating) }}">
                        @csrf
                        @method('PATCH')

                        <div class="space-y-6">
                            <!-- Rating -->
                            <div>
                                <x-input-label for="rating" value="Rating" />
                                <div class="mt-2">
                                    <div class="flex gap-2">
                                        @for($i = 1; $i <= 10; $i++)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="rating" value="{{ $i }}"
                                                       class="sr-only peer"
                                                       {{ old('rating', $rating->rating) == $i ? 'checked' : '' }}>
                                                <span class="flex items-center justify-center w-10 h-10 rounded-lg border-2
                                                    peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-600
                                                    border-gray-200 text-gray-600 hover:border-gray-300 transition-colors">
                                                    {{ $i }}
                                                </span>
                                            </label>
                                        @endfor
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('rating')" class="mt-2" />
                            </div>

                            <!-- Listened At -->
                            <div>
                                <x-input-label for="listened_at" value="Date Listened (optional)" />
                                <x-text-input type="date" name="listened_at" id="listened_at"
                                              class="mt-1 block w-full"
                                              :value="old('listened_at', $rating->listened_at?->format('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('listened_at')" class="mt-2" />
                            </div>

                            <!-- Notes -->
                            <div>
                                <x-input-label for="notes" value="Notes (optional)" />
                                <textarea name="notes" id="notes" rows="4"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                          placeholder="Share your thoughts about this album...">{{ old('notes', $rating->notes) }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-6 pt-6 border-t border-gray-200">
                            <a href="{{ route('account.reviews') }}" class="text-sm text-gray-600 hover:text-gray-800">
                                Cancel
                            </a>
                            <x-primary-button>
                                Save Changes
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
