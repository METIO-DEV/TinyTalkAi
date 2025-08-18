document.addEventListener('DOMContentLoaded', function() {
    // Éléments de la sidebar
    const sidebar = document.querySelector('.fi-sidebar');
    const sidebarCollapseButton = document.querySelector('.fi-sidebar-collapse-button');
    const sidebarItems = document.querySelectorAll('.fi-sidebar-item');
    
    // État initial
    let sidebarOpen = true;
    
    // Fonction pour mettre à jour l'état de la sidebar
    function updateSidebarState() {
        if (sidebarOpen) {
            sidebar.classList.add('fi-sidebar-open');
            sidebar.classList.remove('fi-sidebar-closed');
        } else {
            sidebar.classList.add('fi-sidebar-closed');
            sidebar.classList.remove('fi-sidebar-open');
        }
    }
    
    // Gestionnaire d'événement pour le bouton de collapse
    if (sidebarCollapseButton) {
        sidebarCollapseButton.addEventListener('click', function() {
            sidebarOpen = !sidebarOpen;
            updateSidebarState();
        });
    }
    
    // Gestion du responsive pour les appareils mobiles
    function handleResponsive() {
        if (window.innerWidth < 768) {
            // Sur mobile, la sidebar est fermée par défaut
            sidebarOpen = false;
            updateSidebarState();
            
            // Ajouter un overlay pour fermer la sidebar quand on clique en dehors
            const overlay = document.createElement('div');
            overlay.classList.add('fi-sidebar-overlay');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
            overlay.style.zIndex = '40';
            overlay.style.display = 'none';
            
            document.body.appendChild(overlay);
            
            // Fermer la sidebar quand on clique sur l'overlay
            overlay.addEventListener('click', function() {
                sidebarOpen = false;
                updateSidebarState();
                overlay.style.display = 'none';
            });
            
            // Afficher l'overlay quand la sidebar est ouverte
            const hamburgerButton = document.querySelector('.fi-sidebar-open-button');
            if (hamburgerButton) {
                hamburgerButton.addEventListener('click', function() {
                    sidebarOpen = true;
                    updateSidebarState();
                    overlay.style.display = 'block';
                });
            }
        } else {
            // Sur desktop, la sidebar est ouverte par défaut
            sidebarOpen = true;
            updateSidebarState();
        }
    }
    
    // Exécuter au chargement et au redimensionnement
    handleResponsive();
    window.addEventListener('resize', handleResponsive);
    
    // Animation fluide pour les éléments de la sidebar
    sidebarItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            if (!sidebarOpen && window.innerWidth >= 768) {
                this.style.transform = 'translateX(5px)';
            }
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
});
