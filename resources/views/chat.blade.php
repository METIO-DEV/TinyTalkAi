<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TinyTalkAI') }}</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('TinyTalkAi_Logo.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
</head>
<body class="font-sans antialiased h-full transition-colors duration-200 bg-background text-foreground">
    <div class="min-h-screen flex relative">
        <!-- Responsive sidebar: drawer on mobile, static column on xl+ -->
        <div id="responsive-sidebar" class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] transform -translate-x-full transition-transform duration-200 ease-in-out bg-background shadow-xl overflow-y-auto
                                        xl:static xl:translate-x-0 xl:shadow-none xl:w-1/5 xl:shrink-0 xl:h-screen xl:overflow-hidden">
            <x-sidebar />
        </div>
        <!-- Overlay (only for mobile drawer) -->
        <div id="mobile-overlay" class="xl:hidden hidden fixed inset-0 z-40 bg-black/40"></div>
        
        <!-- Chat main area -->
        <div class="w-full xl:w-4/5 flex-1 flex flex-col h-screen overflow-hidden p-0 xl:p-6 ">
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

    <!-- Script pour le toggle de la sidebar mobile -->
    <script>
        (function() {
            const sidebar = document.getElementById('responsive-sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const openBtns = [
                document.getElementById('open-sidebar'),
                document.getElementById('open-sidebar-floating')
            ].filter(Boolean);

            const isDesktop = () => window.matchMedia('(min-width: 1280px)').matches; // xl breakpoint

            function openDrawer() {
                if (!sidebar || !overlay || isDesktop()) return;
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            function closeDrawer() {
                if (!sidebar || !overlay) return;
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }

            openBtns.forEach(btn => btn.addEventListener('click', openDrawer));
            if (overlay) overlay.addEventListener('click', closeDrawer);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeDrawer();
            });
            // Ensure correct state when resizing
            const mq = window.matchMedia('(min-width: 1280px)');
            function handleMQ(e){
                if (e.matches) {
                    // On xl+, ensure sidebar is visible and overlay hidden
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.add('hidden');
                    document.body.style.overflow = '';
                } else {
                    // On smaller screens, start hidden
                    sidebar.classList.add('-translate-x-full');
                }
            }
            handleMQ(mq);
            if (mq.addEventListener) mq.addEventListener('change', handleMQ); else mq.addListener(handleMQ);
        })();
    </script>

    <!-- Script pour le dropdown profil mobile -->
    <script>
        (function(){
            const btn = document.getElementById('mobile-profile-dropdown-button');
            const menu = document.getElementById('mobile-profile-dropdown-menu');
            if (!btn || !menu) return;
            function close(){ menu.classList.add('hidden'); }
            btn.addEventListener('click', (e)=>{
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            document.addEventListener('click', (e)=>{
                if (!menu.contains(e.target) && !btn.contains(e.target)) close();
            });
            document.addEventListener('keydown', (e)=>{
                if (e.key === 'Escape') close();
            });
        })();
    </script>
</body>
</html>
