<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TinyTalkAI') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
</head>
<body class="font-sans antialiased h-full transition-colors duration-200 bg-custom-light dark:bg-custom-white-dark-mode dark:text-custom-white">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="w-1/5 shrink-0 h-screen overflow-hidden ">
            <x-sidebar />
        </div>
        
        <!-- Chat main area -->
        <div class="w-4/5 flex-1 flex flex-col h-screen overflow-hidden p-6">
            <x-chat-interface />
        </div>
    </div>
    
    
    
    <!-- Inclusion du script externe -->
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

        // Fonction pour basculer manuellement le thème
        window.toggleDarkMode = function() {
            const currentDarkMode = localStorage.getItem('darkMode') === 'true';
            const newDarkMode = !currentDarkMode;
            
            // Enregistrer la préférence utilisateur
            localStorage.setItem('darkMode', newDarkMode);
            
            // Mettre à jour les classes
            if (newDarkMode) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            // Déclencher un événement personnalisé pour informer les composants du changement de thème
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { darkMode: newDarkMode } }));
        }

        // Fonction pour réinitialiser aux préférences du système
        window.resetToSystemTheme = function() {
            // Supprimer la préférence utilisateur
            localStorage.removeItem('darkMode');
            
            // Utiliser la préférence du système
            const systemDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Mettre à jour les classes
            if (systemDarkMode) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            
            // Déclencher un événement personnalisé
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { darkMode: systemDarkMode } }));
        }
    </script>
</body>
</html>