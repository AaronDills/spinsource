<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center">
        <div class="text-center">
            @auth
                <a
                    href="{{ url('/dashboard') }}"
                    class="inline-flex items-center px-6 py-3 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-white dark:text-gray-800 hover:bg-gray-700 dark:hover:bg-white transition"
                >
                    Dashboard
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center px-6 py-3 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-white dark:text-gray-800 hover:bg-gray-700 dark:hover:bg-white transition"
                >
                    Login
                </a>
            @endauth
        </div>
    </body>
</html>
