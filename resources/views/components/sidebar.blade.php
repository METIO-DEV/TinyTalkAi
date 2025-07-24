<!-- Composant pour la sidebar -->
<div class="bg-custom-light dark:bg-custom-white-dark-mode dark:text-custom-white h-full w-full pl-6 pt-6 pb-6 flex flex-col overflow-hidden">
    <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-custom-black dark:text-custom-white">TinyTalk AI</h2>
            <div class="flex items-center">
                <div class="relative" id="profile-dropdown">
                    <button id="profile-dropdown-button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-custom-black bg-custom-white dark:bg-custom-light-dark-mode dark:text-custom-white hover:bg-custom-light-dark-mode dark:hover:bg-custom-mid-dark-mode focus:outline-none transition ease-in-out duration-150">
                        <span>{{ auth()->user()->name }}</span>
                        <div class="ms-1">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>

                    <div id="profile-dropdown-menu" class="hidden absolute z-50 mt-2 w-48 rounded-md shadow-lg origin-top-right right-0">
                        <div class="rounded-md ring-1 ring-black ring-opacity-5 bg-custom-white dark:bg-custom-light-dark-mode">
                            <a href="{{ route('profile') }}" class="flex justify-center items-center px-4 py-2 text-sm text-custom-black dark:text-custom-white rounded-md hover:bg-custom-mid-dark-mode dark:hover:bg-custom-mid-dark-mode">
                                {{ __('Profile') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" id="logout-form">
                                @csrf
                                <x-danger-button type="button" id="logout-button" class="w-full flex justify-center items-center px-4 py-2 text-sm text-custom-black dark:text-custom-white rounded-md">
                                    {{ __('Log Out') }}
                                </x-danger-button>  
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section Modèles -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-custom-black dark:text-custom-white uppercase tracking-wider">
                <button class="flex items-center w-full text-left focus:outline-none" data-collapse-toggle="models-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Modèles
                </button>
            </h3>
        </div>
        <div id="models-section" class="space-y-2">
            <x-model-list :models="$models" :selectedModel="$selectedModel ?? null" /> <!-- Composant pour la liste des modèles -->
        </div>
    </div>
    
    <!-- Section Réglages -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-custom-black dark:text-custom-white uppercase tracking-wider">
                <button class="flex items-center w-full text-left focus:outline-none" data-collapse-toggle="settings-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Réglages
                </button>
            </h3>
        </div>
        <div id="settings-section" class="space-y-2 overflow-y-auto max-h-48">
            <x-settings-panel :temperature="$temperature ?? 0.7" :maxTokens="$maxTokens ?? 1024" />
        </div>
    </div>

    
    
    <!-- Section Historique -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-custom-black dark:text-custom-white uppercase tracking-wider">
                <button class="flex items-center w-full text-left focus:outline-none" data-collapse-toggle="history-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Historique
                </button>
            </h3>
        </div>
        <div id="history-section" class="space-y-2 overflow-y-auto max-h-48">
            <!-- Cette section sera remplie dynamiquement avec l'historique des conversations -->
            <div class="text-sm text-gray-500 dark:text-gray-300 px-3 py-2">
                Aucune conversation enregistrée
            </div>  
        </div>
    </div>
</div>

<script>
    const profileDropdownButton = document.getElementById('profile-dropdown-button');
    const profileDropdownMenu = document.getElementById('profile-dropdown-menu');
    const logoutButton = document.getElementById('logout-button');
    const logoutForm = document.getElementById('logout-form');

    profileDropdownButton.addEventListener('click', () => {
        profileDropdownMenu.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!profileDropdownButton.contains(e.target) && !profileDropdownMenu.contains(e.target)) {
            profileDropdownMenu.classList.add('hidden');
        }
    });

    logoutButton.addEventListener('click', () => {
        if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
            logoutForm.submit();
        }
    });
</script>
