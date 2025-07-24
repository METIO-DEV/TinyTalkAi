<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TinyTalkAI') }}</title>

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-custom-light dark:bg-custom-light-dark-mode">

            <!-- Page Content -->
            <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
            
            <!-- Lien de retour vers le chat -->
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="text-custom-black dark:text-custom-white hover:text-gray-700">
                    &larr; Retour au chat
                </a>
            </div>
        </div>
    </body>
</html>
