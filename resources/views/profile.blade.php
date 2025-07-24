<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TinyTalkAI') }} - {{ __('Profile') }}</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-custom-light dark:bg-custom-white-dark-mode">
    <div class="min-h-screen py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-custom-black dark:text-custom-white">{{ __('Profile') }}</h1>
                <a href="{{ route('home') }}" class="inline-flex items-center px-4 py-2 bg-custom-white dark:bg-custom-mid-dark-mode dark:text-custom-white border border-custom-mid rounded-md font-semibold text-xs text-custom-black uppercase tracking-widest hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back to Chat') }}
                </a>
            </div>

            <div class="space-y-6">
                <div class="p-4 sm:p-8 bg-custom-white dark:bg-custom-mid-dark-mode dark:text-custom-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <livewire:profile.update-profile-information-form />
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-custom-white dark:bg-custom-mid-dark-mode dark:text-custom-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <livewire:profile.update-password-form />
                    </div>
                </div>
                
                <div class="p-4 sm:p-8 bg-custom-white dark:bg-custom-mid-dark-mode dark:text-custom-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <!-- TODO: Add logout button -->
                        <h2 class="text-lg font-medium text-custom-black dark:text-custom-white">
                            {{ __('Log Out') }}
                        </h2>
                        <p class="mt-1 mb-6 text-sm text-gray-600 dark:text-gray-300">
                            {{ __('Log out of your account.') }}
                        </p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-secondary-button type="submit" class="text-center block px-4 py-2 text-sm text-custom-black dark:text-custom-white hover:bg-custom-light dark:hover:bg-custom-mid-dark-mode">
                                {{ __('Log Out') }}
                            </x-secondary-button>
                        </form>
                    </div>
                </div>  

                <div class="p-4 sm:p-8 bg-custom-white dark:bg-custom-mid-dark-mode dark:text-custom-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <livewire:profile.delete-user-form />
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

<script src="{{ asset('js/chat.js') }}"></script>
<!-- Script pour gérer le thème sombre -->
<script>
    // Initialiser le thème au chargement de la page
    document.addEventListener('DOMContentLoaded', () => {
        updateThemeClass();
    });

    // Fonction pour mettre à jour les classes de thème
    function updateThemeClass() {
        const darkMode = localStorage.getItem('darkMode') === null
            ? window.matchMedia('(prefers-color-scheme: dark)').matches
            : localStorage.getItem('darkMode') === 'true';
            
        if (darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
</script>
</html>
