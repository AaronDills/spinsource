@props([
    'type' => 'cover', // 'cover' or 'tracklist'
    'size' => 'default', // 'small', 'default', 'large', 'fill'
])

@php
    $sizeClasses = match($size) {
        'small' => 'w-10 h-10',
        'medium' => 'w-24 h-24',
        'large' => 'w-28 h-28 sm:w-32 sm:h-32',
        'fill' => 'w-full h-full',
        default => 'w-16 h-16',
    };

    $iconSize = match($size) {
        'small' => 'w-4 h-4',
        'medium' => 'w-8 h-8',
        'large' => 'w-8 h-8',
        'fill' => 'w-8 h-8',
        default => 'w-6 h-6',
    };

    $textSize = match($size) {
        'small' => 'text-[10px]',
        'medium' => 'text-xs',
        'large' => 'text-xs',
        'fill' => 'text-xs',
        default => 'text-[11px]',
    };
@endphp

@if($type === 'cover')
    <div {{ $attributes->merge(['class' => "$sizeClasses rounded-lg bg-gray-200 dark:bg-gray-700 flex flex-col items-center justify-center"]) }}>
        <svg class="{{ $iconSize }} text-gray-400 dark:text-gray-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span class="{{ $textSize }} text-gray-400 dark:text-gray-500 mt-1 text-center px-1">Importing...</span>
    </div>
@elseif($type === 'tracklist')
    <div class="space-y-2">
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Tracklist importing...</span>
        </div>
        {{-- Skeleton rows --}}
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @for($i = 1; $i <= 4; $i++)
                <div class="flex items-center py-2">
                    <span class="w-8 h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></span>
                    <span class="flex-1 h-4 bg-gray-200 dark:bg-gray-700 rounded ml-2 animate-pulse" style="width: {{ rand(40, 70) }}%"></span>
                    <span class="w-10 h-4 bg-gray-200 dark:bg-gray-700 rounded ml-4 animate-pulse"></span>
                </div>
            @endfor
        </div>
    </div>
@endif
