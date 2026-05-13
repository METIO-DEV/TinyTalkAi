# Audit Technique Et UI/UX

Date: 2026-05-07

## Synthese

TinyTalkAI repose sur une base saine: Laravel 12, Inertia, React, shadcn/ui, Filament, Ollama et Qdrant. Le build frontend passe et la suite Pest existante est verte. Les risques principaux se situent sur la securite des endpoints chat/RAG, la robustesse des appels reseau externes, la dette de migration Livewire vers React et l'absence de tests sur le coeur produit.

Les MCP shadcn et Playwright n'etaient pas disponibles dans l'environnement d'audit. Playwright n'est pas installe dans le projet.

## Verifications Effectuees

- `npm run build`: OK.
- `composer test`: OK, 26 tests passes.
- `./vendor/bin/pint --test`: KO, plusieurs fichiers PHP doivent etre reformates.
- `npm ls @playwright/test playwright`: aucun package Playwright installe.
- `php artisan route:list`: routes applicatives et Filament chargees correctement.

## Risques Prioritaires

### 1. Streaming Chat Trop Confiant

`/api/chat/stream` acceptait directement le modele, les messages et la conversation depuis le client. Un utilisateur authentifie pouvait donc appeler l'endpoint directement avec un payload forge.

Correction recommandee: ne streamer que des payloads prepares cote serveur et lies a la session utilisateur.

### 2. Suppression De Collection Trop Large

Une collection disponible via groupe pouvait etre supprimee par un utilisateur non proprietaire.

Correction recommandee: limiter la suppression au proprietaire ou a un administrateur, et exposer cet etat a l'UI.

### 3. Appels Ollama/Qdrant Sans Timeout

Plusieurs appels HTTP critiques vers Ollama et Qdrant n'avaient pas de timeout explicite.

Correction recommandee: ajouter des timeouts differencies pour embeddings, creation/upsert/recherche Qdrant et operations de collection.

### 4. Option Ollama De Longueur Potentiellement Incorrecte

Le code utilisait `max_tokens` dans les options Ollama. L'API Ollama attend generalement `num_predict` pour limiter la generation.

Correction recommandee: envoyer `num_predict` et conserver la valeur dans les settings applicatifs sous `max_tokens` si souhaite.

### 5. UX Des Actions Destructives

Les suppressions de conversations et collections etaient immediates, sans confirmation.

Correction recommandee: confirmation minimale avant suppression, surtout pour les collections RAG.

### 6. Viewport Mobile

L'interface principale utilise `h-screen`, moins fiable sur mobile a cause des barres navigateur.

Correction recommandee: utiliser `h-dvh` avec fallback si necessaire.

## Dette Technique

- `resources/js/Pages/Chat.jsx` concentre trop de responsabilites: etat, API, streaming, parsing markdown, sidebar, dialogs, rendu messages.
- Anciennes vues Livewire/Blade et scripts historiques cohabitent avec l'UI React/Inertia.
- Le parser Markdown maison est sur, car il n'utilise pas `dangerouslySetInnerHTML`, mais incomplet.
- Les tests actuels couvrent surtout auth/profil, pas chat, streaming, RAG ni UI React.
- Les logs RAG sont verbeux et peuvent exposer des extraits de documents utilisateur.

## Recommandations Par Etapes

1. Securiser le streaming via un payload prepare cote serveur et un token de session a usage court.
2. Restreindre la suppression de collection au proprietaire ou admin.
3. Ajouter timeouts et options Ollama correctes.
4. Ajouter confirmations UI pour suppressions et ajuster le viewport mobile.
5. Reformatter les fichiers PHP avec Pint.
6. Ajouter Playwright et couvrir login, chargement chat, responsive mobile, creation/suppression collection et streaming mocke.
7. Decouper `Chat.jsx` en composants/hooks testables.

## Statut D'Application

- Les etapes 1 a 4 sont candidates a correction immediate dans le code.
- Les etapes 5 a 7 necessitent une decision d'equipe sur le perimetre, car elles peuvent modifier beaucoup de fichiers ou ajouter une dependance de test.
