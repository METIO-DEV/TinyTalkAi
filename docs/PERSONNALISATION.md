# Guide de personnalisation de TinyTalkAI

Ce document explique comment personnaliser les couleurs et le logo de l'application TinyTalkAI.

## Architecture des composants

TinyTalkAI est construit avec une architecture modulaire basée sur des composants Blade et Livewire. Voici la structure principale :

```
chat.blade.php                    # Vue principale autonome (page de chat)
├── x-sidebar                     # Composant de la barre latérale
│   ├── Logo et titre de l'app
│   ├── Liste des modèles
│   ├── Panneau des paramètres
│   └── Menu utilisateur
└── x-chat-interface              # Interface principale de chat
    ├── Zone d'affichage des messages
    └── Formulaire de saisie (livewire/chat-form)

profile.blade.php                 # Page de profil utilisateur
layouts/
├── app.blade.php                 # Layout générique (pour pages futures)
└── guest.blade.php               # Layout pour pages d'authentification
```

### Emplacement des fichiers principaux

- **Vue principale de chat** : `resources/views/chat.blade.php`
- **Composant sidebar** : `resources/views/components/sidebar.blade.php`
- **Interface de chat** : `resources/views/components/chat-interface.blade.php`
- **Formulaire de chat** : `resources/views/livewire/chat-form.blade.php`
- **Logo de l'application** : `resources/views/components/application-logo.blade.php`
- **Page de profil** : `resources/views/profile.blade.php`
- **Layouts** : `resources/views/layouts/`

## Configuration des couleurs

Toutes les couleurs personnalisées sont définies dans le fichier `tailwind.config.js` à la racine du projet, dans la section `theme.extend.colors` :

```javascript
// Fichier: tailwind.config.js
colors: {
  'custom-light': '#E0E0E0',    // Arrière-plan principal
  'custom-white': '#FFFFFF',    // Composants, cartes
  'custom-mid': '#CDCDCD',      // Bordures
  'custom-black': '#000000',    // Texte principal
  'custom-light-dark-mode': '#323232',  // Arrière-plan (mode sombre)
  'custom-white-dark-mode': '#141313',  // Composants (mode sombre)
  'custom-mid-dark-mode': '#606060',    // Bordures (mode sombre)
}
```

Pour modifier les couleurs, il suffit de changer les codes hexadécimaux dans ce fichier, puis d'exécuter `npm run build` pour recompiler les assets.

## Personnalisation par composant

### 1. Layout principal

**Fichier**: `resources/views/layouts/app.blade.php`

- Arrière-plan principal: `bg-custom-light dark:bg-custom-light-dark-mode` (ligne 22)
- Texte du lien "Retour au chat": `text-custom-black dark:text-custom-white` (ligne 31)

### 2. Sidebar

**Fichier**: `resources/views/components/sidebar.blade.php`

- Arrière-plan: `bg-custom-light dark:bg-custom-white-dark-mode` (ligne 2)
- Texte: `text-custom-black dark:text-custom-white` (diverses lignes)
- Logo et titre: lignes 6-7
- Bouton de profil: `bg-custom-white dark:bg-custom-light-dark-mode` (ligne 11)

### 3. Interface de chat

**Fichier**: `resources/views/components/chat-interface.blade.php`

- Arrière-plan: `bg-custom-white dark:bg-custom-light-dark-mode` (ligne 2)
- Messages utilisateur: `bg-custom-black text-white` (ligne 21)
- Messages IA: `bg-custom-light text-custom-black` (ligne 29)
- Message de chargement: `bg-custom-light text-custom-black dark:text-custom-white dark:bg-custom-light-dark-mode` (ligne 37)

### 4. Formulaire de chat

**Fichier**: `resources/views/livewire/chat-form.blade.php`

- Champ de saisie: classes Tailwind standard
- Bouton d'envoi: vérifier les classes dans le fichier

## Modification du logo

Le logo principal de l'application est défini ici :

**Logo PNG**: `public/TinyTalkAi_Logo.png`

Pour modifier le logo, remplacez simplement ce fichier par votre propre version en conservant le même nom et format.

Le logo est affiché dans la sidebar via le composant :
```html
<x-application-logo class="w-14" />
```

Ce composant est défini dans `resources/views/components/application-logo.blade.php`.

### Favicon

Le favicon (icône dans l'onglet du navigateur) doit être modifié dans **tous** les templates principaux :

- `resources/views/chat.blade.php` (interface principale)
- `resources/views/layouts/app.blade.php` (layout générique)
- `resources/views/layouts/guest.blade.php` (pages d'authentification)
- `resources/views/profile.blade.php` (si autonome)

Dans chaque fichier, assurez-vous que la balise suivante pointe vers votre favicon :
```html
<link rel="icon" href="{{ asset('TinyTalkAi_Logo.png') }}" type="image/png">
```

Le fichier favicon est situé dans `public/TinyTalkAi_Logo.png`.

## Titre de l'application

Le titre de l'application (affiché dans l'onglet du navigateur) est défini dans les différents templates :

- `resources/views/chat.blade.php` :
  ```html
  <title>{{ config('app.name', 'TinyTalkAI') }}</title>
  ```

- `resources/views/layouts/app.blade.php` :
  ```html
  <title>{{ config('app.name', 'TinyTalkAI') }}</title>
  ```

- `resources/views/layouts/guest.blade.php` :
  ```html
  <title>{{ config('app.name', 'TinyTalkAI') }}</title>
  ```

- `resources/views/profile.blade.php` :
  ```html
  <title>{{ config('app.name', 'TinyTalkAI') }} - {{ __('Profile') }}</title>
  ```

Pour modifier le titre de l'application, vous pouvez :

1. Modifier la valeur dans le fichier `.env` :
   ```
   APP_NAME="Votre Titre"
   ```

2. Ou modifier directement la valeur dans `config/app.php` :
   ```php
   'name' => env('APP_NAME', 'Votre Titre'),
   ```

## Après les modifications

Après avoir modifié les couleurs dans `tailwind.config.js` ou le fichier de logo, exécutez :

```bash
npm run build
```

Cela recompilera les assets et appliquera vos modifications.

## Notes importantes

- Les classes commençant par `bg-` contrôlent les couleurs d'arrière-plan
- Les classes commençant par `text-` contrôlent les couleurs de texte
- Les classes commençant par `border-` contrôlent les couleurs de bordure
- Les classes avec le préfixe `dark:` s'appliquent uniquement en mode sombre
