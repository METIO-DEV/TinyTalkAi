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
            @livewire('model-selector')
        </div>
    </div>
    
    <!-- Section Historique -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-custom-black dark:text-custom-white uppercase tracking-wider">
                <button class="flex items-center text-left focus:outline-none" data-collapse-toggle="history-section">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Historique
                </button>
            </h3>
            <button 
                class="flex items-center rounded-md hover:bg-custom-mid cursor-pointer transition-colors duration-200 text-gray-500 hover:text-custom-black dark:text-white dark:hover:text-custom-white dark:hover:bg-custom-mid-dark-mode dark:hover:border-custom-black border-none" 
                title="Nouvelle conversation"
                onclick="Livewire.dispatch('newConversation')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                </svg>
            </button>
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
