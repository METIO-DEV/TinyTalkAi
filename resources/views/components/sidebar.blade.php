<!-- Composant pour la sidebar -->
<div class="bg-background text-foreground h-full w-full pl-4 sm:pl-6 pt-6 pb-6 pr-4 sm:pr-6 xl:pr-0 flex flex-col overflow-hidden">
    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-2">
                <x-application-logo class="w-8 sm:w-10 md:w-10 lg:w-12" />
                <h2 class="text-base sm:text-md lg:text-lg font-semibold text-foreground">TinyTalk AI</h2>
            </div>
            <div class="flex items-center">
                <div class="relative" id="profile-dropdown">
                    <x-ui.button id="profile-dropdown-button" variant="outline" size="sm" class="max-w-40">
                        <!-- Icône visible en dessous de ~1536px (2xl) -->
                        <span class="inline-flex 2xl:hidden items-center justify-center" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A7 7 0 0118.879 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>

                        <!-- Nom visible seulement à partir de ~1536px (2xl) -->
                        <span class="hidden 2xl:block max-w-[90px] truncate" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</span>

                        <div class="ms-1">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </x-ui.button>

                    <div id="profile-dropdown-menu" class="hidden absolute z-50 mt-2 w-48 rounded-md shadow-lg origin-top-right right-0">
                        <div class="rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md">
                            @php
                                $currentLocale = app()->getLocale();
                                $nextLocale = $currentLocale === 'fr' ? 'en' : 'fr';
                            @endphp
                            <form method="POST" action="{{ route('locale.set') }}">
                                @csrf
                                <input type="hidden" name="locale" value="{{ $nextLocale }}">
                                <button type="submit" class="w-full flex justify-center items-center px-3 py-2 text-sm rounded-md hover:bg-accent hover:text-accent-foreground">
                                    {{ strtoupper($currentLocale) }} / {{ strtoupper($nextLocale) }}
                                </button>
                            </form>
                             <a href="{{ route('profile') }}" class="flex justify-center items-center px-3 py-2 text-sm rounded-md hover:bg-accent hover:text-accent-foreground">
                                 {{ __('Profile') }}
                             </a>
                             @role('admin|super-admin')
                             <a href="{{ url('/admin') }}" class="flex justify-center items-center px-3 py-2 text-sm rounded-md hover:bg-accent hover:text-accent-foreground">
                                 {{ __('Administration') }}
                             </a>
                             @endrole
                             <livewire:logout /> <!-- Utilisation du composant "logout" Livewire -->

                             <!-- Utilisation de la méthode classique, avec un formulaire et une route qui effectue la déconnexion -->
                             <!-- <form method="POST" action="{{ route('logout') }}" id="logout-form">
                                 @csrf
                                 <x-danger-button type="button" id="logout-button" class="w-full flex justify-center items-center px-4 py-2 text-sm text-custom-black dark:text-custom-white rounded-md">
                                     {{ __('Log Out') }}
                                 </x-danger-button>  
                             </form> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section Modèles -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider">
                <button class="flex items-center w-full text-left rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" data-collapse-toggle="models-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    {{ __('Models') }}
                </button>
            </h3>
        </div>
        <div id="models-section" class="space-y-2">
            @livewire('model-selector')
        </div>
    </div>


    <!-- Section Collections -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider">
                <button class="flex items-center w-full text-left rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" data-collapse-toggle="collections-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    {{ __('Collections') }}
                </button>
            </h3>
        </div>
        <div id="collections-section" class="space-y-2">
            @livewire('collection')
        </div>
    </div>
    
    <!-- Section Historique -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider">
                <button class="flex items-center text-left rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" data-collapse-toggle="history-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    {{ __('Conversation') }}
                </button>
            </h3>
            <x-ui.button
                type="button"
                variant="ghost"
                size="icon-sm"
                title="Nouvelle conversation"
                onclick="Livewire.dispatch('newConversation')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                </svg>
            </x-ui.button>
        </div>
        <div id="history-section">
            @livewire('conversation-history')
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du dropdown du profil
        const profileDropdownButton = document.getElementById('profile-dropdown-button');
        const profileDropdownMenu = document.getElementById('profile-dropdown-menu');
        
        if (profileDropdownButton && profileDropdownMenu) {
            profileDropdownButton.addEventListener('click', function() {
                profileDropdownMenu.classList.toggle('hidden');
            });
            
            // Fermer le dropdown quand on clique ailleurs
            document.addEventListener('click', function(event) {
                if (!profileDropdownButton.contains(event.target) && !profileDropdownMenu.contains(event.target)) {
                    profileDropdownMenu.classList.add('hidden');
                }
            });
        }
        
        // Gestion des sections pliables
        const collapsibleButtons = document.querySelectorAll('[data-collapse-toggle]');
        
        collapsibleButtons.forEach(button => {
            const targetId = button.getAttribute('data-collapse-toggle');
            const targetElement = document.getElementById(targetId);
            const chevron = button.querySelector('svg');
            
            if (targetElement && chevron) {
                button.addEventListener('click', () => {
                    if (targetElement.classList.contains('hidden')) {
                        targetElement.classList.remove('hidden');
                        chevron.style.transform = 'rotate(0deg)';
                    } else {
                        targetElement.classList.add('hidden');
                        chevron.style.transform = 'rotate(-90deg)';
                    }
                });
            }
        });
    });
</script>
