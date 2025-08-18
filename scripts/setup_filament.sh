#!/bin/bash

# Script de configuration de Filament pour TinyTalkAI
echo "🚀 Configuration de Filament pour TinyTalkAI"

# Vérifier si Sail est disponible
if [ -f "./vendor/bin/sail" ]; then
    ARTISAN="./vendor/bin/sail artisan"
    echo "✅ Utilisation de Laravel Sail"
else
    ARTISAN="php artisan"
    echo "✅ Utilisation de PHP en local"
fi

# Publier les assets de Filament
echo "📦 Publication des assets de Filament..."
$ARTISAN filament:assets

# Vider le cache des routes et des vues
echo "🧹 Nettoyage du cache..."
$ARTISAN route:clear
$ARTISAN view:clear
$ARTISAN cache:clear
$ARTISAN config:clear

# Exécuter les migrations
echo "🗄️ Exécution des migrations..."
$ARTISAN migrate

# Exécuter le seeder de permissions
echo "🔑 Configuration des permissions..."
$ARTISAN db:seed --class=PermissionSeeder

echo "✨ Configuration terminée ! Vous pouvez accéder à l'administration via /admin"
echo "⚠️ N'oubliez pas d'attribuer le rôle 'admin' ou 'super-admin' à votre utilisateur :"
echo "$ARTISAN tinker"
echo ">>> \$user = \App\Models\User::where('email', 'votre-email@exemple.com')->first();"
echo ">>> \$user->assignRole('super-admin');"
echo ">>> exit;"
