/**
 * TinyTalk AI - Script principal pour l'interface de chat
 */


// recharger la page au changement de thème systeme
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    location.reload();
});

// Écouter les événements de changement de collections
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking for Echo...');
    
    // Vérifier si Echo est disponible
    if (window.Echo) {
        console.log('Echo is available, subscribing to channels...');
        
        // S'abonner au canal 'collections'
        window.Echo.channel('collections')
            .listen('.CollectionChanged', (event) => {
                console.log('CollectionChanged event received:', event);
                
                // Déclencher un événement Livewire pour rafraîchir les collections
                if (window.Livewire) {
                    console.log('Dispatching refreshCollections event to Livewire...');
                    window.Livewire.dispatch('refreshCollections');
                    console.log('refreshCollections event dispatched');
                } else {
                    console.error('Livewire not available, cannot dispatch event');
                }
            })
            .listen('.CollectionGroupChanged', (event) => {
                console.log('CollectionGroupChanged event received:', event);
                console.log('Event details - Action:', event.action, 'Collection ID:', event.collection_id, 'Group IDs:', event.group_ids);
                
                // Déclencher un événement Livewire pour rafraîchir les collections
                if (window.Livewire) {
                    console.log('Dispatching refreshCollections event to Livewire...');
                    window.Livewire.dispatch('refreshCollections');
                    console.log('refreshCollections event dispatched');
                } else {
                    console.error('Livewire not available, cannot dispatch event');
                }
            });
        
        // S'abonner au canal 'models'
        window.Echo.channel('models')
            .listen('.ModelGroupChanged', (event) => {
                console.log('ModelGroupChanged event received:', event);

                // Déclencher un événement Livewire pour rafraîchir les modèles
                if (window.Livewire) {
                    console.log('Dispatching refreshModels event to Livewire...');
                    window.Livewire.dispatch('refreshModels');
                    console.log('refreshModels event dispatched');
                } else {
                    console.error('Livewire not available, cannot dispatch event');
                }
            });
        
        console.log('Successfully subscribed to collections and models channels');
    } else {
        console.error('Echo is not available. Check if Laravel Echo is properly initialized in bootstrap.js');
    }
});
