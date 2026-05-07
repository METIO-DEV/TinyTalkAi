# Plan de refonte - Laravel, React/Inertia, PostgreSQL, RAG

Objectif : changer l'architecture de TinyTalkAI pour gagner en fiabilite, performance et maintenabilite.

Branche de travail : `refactor/inertia-postgres-architecture`

## Legende

- `[ ]` Non commence
- `[~]` En cours
- `[x]` Termine
- `[!]` Bloque / decision requise

## Suivi global

| Statut | Etape | Objectif |
| --- | --- | --- |
| `[x]` | 0. Modernisation dependances | Monter le socle PHP/frontend au niveau requis par Laravel, Pest et shadcn actuels |
| `[x]` | 1. Fondation Docker/PostgreSQL | Remplacer Sail par un Docker Compose autonome et basculer la base applicative sur PostgreSQL |
| `[x]` | 2. Configuration Laravel/Postgres | Adapter `.env.example`, `config/database.php`, migrations et extension pgvector |
| `[x]` | 3. Installation Inertia React TypeScript | Ajouter Inertia et React comme socle frontend |
| `[x]` | 4. Initialisation shadcn/ui | Installer shadcn/ui, aliases, utilitaires et composants de base |
| `[~]` | 5. App shell React | Refaire le layout principal : sidebar, header mobile, zone chat |
| `[~]` | 6. Chat sans RAG | Rebrancher conversations, messages, selection modele et streaming Ollama |
| `[ ]` | 7. RAG documents/collections | Rebrancher Qdrant, upload documents, collections et prompt enrichi |
| `[ ]` | 8. Administration | Garder Filament dans un premier temps et verifier compatibilite Postgres/Docker |
| `[ ]` | 9. Nettoyage Livewire/Blade | Retirer les composants devenus obsoletes quand React couvre les parcours |
| `[~]` | 10. Tests et verification | Adapter/ajouter tests backend, build frontend, verification navigateur |
| `[~]` | 11. Documentation finale | Mettre a jour README, docs d'installation et notes d'architecture |

## 0. Modernisation dependances

Statut : `[x]` Termine

Taches :

- `[x]` Monter PHP cible a `^8.3` et utiliser PHP 8.4 dans Docker.
- `[x]` Monter Laravel 12 sur une version recente.
- `[x]` Monter Livewire, Volt et Breeze.
- `[x]` Monter Pest/PHPUnit sur les versions majeures actuelles.
- `[x]` Retirer `laravel/sail` des dependances de developpement.
- `[x]` Monter Vite en version majeure actuelle.
- `[x]` Monter `@vitejs/plugin-react` sur la version compatible Vite actuel.
- `[x]` Monter `laravel-vite-plugin`.
- `[x]` Monter Tailwind CSS en v4.
- `[x]` Ajouter `@tailwindcss/vite` et `@tailwindcss/postcss`.
- `[x]` Adapter `resources/css/app.css` au mode CSS-first Tailwind 4.
- `[x]` Sortir le theme Filament legacy du build Vite principal en attendant l'audit admin.
- `[x]` Garder `resources/js/app.js` dans le build Vite pour les vues Blade/Livewire encore actives.
- `[x]` Verifier `composer audit --locked`.
- `[x]` Verifier `npm audit --audit-level=moderate`.

## 1. Fondation Docker/PostgreSQL

Statut : `[x]` Termine

Taches :

- `[x]` Remplacer le compose Sail par un compose autonome.
- `[x]` Ajouter un Dockerfile PHP-FPM applicatif.
- `[x]` Ajouter une configuration Nginx locale.
- `[x]` Ajouter les services `app`, `nginx`, `postgres`, `redis`, `queue`, `scheduler`, `qdrant`, `node`, avec Ollama consomme depuis l'hote.
- `[x]` Ajouter des healthchecks utiles.
- `[x]` Ajouter des volumes persistants pour PostgreSQL, Qdrant, Ollama et le storage Laravel.
- `[x]` Retirer MySQL du chemin nominal.

Livrables :

- `docker-compose.yml`
- `docker/php/Dockerfile`
- `docker/nginx/default.conf`
- `docker/postgres/init/01-enable-pgvector.sql`

## 2. Configuration Laravel/Postgres

Statut : `[x]` Termine

Taches :

- `[x]` Passer `DB_CONNECTION=pgsql` dans `.env.example`.
- `[x]` Ajouter les variables Docker/Postgres attendues.
- `[x]` Verifier la compatibilite des migrations avec PostgreSQL.
- `[x]` Ajouter une migration ou un init SQL pour `CREATE EXTENSION IF NOT EXISTS vector`.
- `[x]` Ajouter une configuration explicite `services.qdrant`.
- `[x]` Ajouter une configuration explicite `services.ollama.embedding_model` si necessaire.

## 3. Installation Inertia React TypeScript

Statut : `[x]` Termine

Taches :

- `[x]` Installer `inertiajs/inertia-laravel`.
- `[x]` Installer `@inertiajs/react`, `react`, `react-dom`, TypeScript et types.
- `[x]` Ajouter le middleware Inertia.
- `[x]` Creer le root Blade Inertia.
- `[x]` Adapter Vite pour React.
- `[x]` Remplacer progressivement les routes Blade principales par des pages Inertia.

## 4. Initialisation shadcn/ui

Statut : `[x]` Termine

Taches :

- `[x]` Initialiser shadcn/ui en mode non interactif.
- `[x]` Configurer aliases `@/components`, `@/lib`, `@/hooks`.
- `[x]` Ajouter `cn()`.
- `[x]` Ajouter les composants de base : button, input, textarea, dialog, dropdown-menu, select, tabs, scroll-area, badge, progress, toast/sonner, sheet, separator, tooltip.
- `[x]` Harmoniser les tokens Tailwind avec le design cible.

## 5. App shell React

Statut : `[~]` En cours

Taches :

- `[x]` Creer `resources/js/Layouts/AppLayout.tsx`.
- `[x]` Creer `resources/js/Pages/Chat/Index.tsx`.
- `[~]` Creer les composants de layout : sidebar, mobile drawer, profile menu.
- `[ ]` Ajouter dark mode robuste.
- `[x]` Ajouter structure responsive initiale.
- `[x]` Brancher selection modele, collection et conversation sur des actions Inertia.
- `[x]` Afficher les messages de la conversation selectionnee.

## 6. Chat sans RAG

Statut : `[~]` En cours

Taches :

- `[x]` Creer un endpoint de bootstrap pour modeles/conversations ou passer les props via Inertia.
- `[x]` Rebrancher selection de modele.
- `[x]` Rebrancher historique conversations.
- `[x]` Rebrancher creation conversation/message.
- `[x]` Rebrancher streaming Ollama avec rendu progressif React.
- `[ ]` Corriger le calcul/retour des tokens du streaming.
- `[ ]` Ajouter gestion erreurs et annulation.

## 7. RAG documents/collections

Statut : `[ ]` Non commence

Taches :

- `[ ]` Rebrancher toggle RAG.
- `[ ]` Rebrancher selection collection.
- `[ ]` Rebrancher upload documents conversation.
- `[ ]` Rebrancher upload documents collection.
- `[ ]` Deplacer les ingestions lourdes en queue si necessaire.
- `[ ]` Centraliser la config Qdrant.
- `[ ]` Conserver pgvector pret pour usages futurs, sans dupliquer Qdrant inutilement.

## 8. Administration

Statut : `[ ]` Non commence

Taches :

- `[ ]` Garder Filament accessible sur `/admin`.
- `[ ]` Verifier les resources sous PostgreSQL.
- `[ ]` Verifier les broadcasts de changement groupes/modeles/collections.
- `[ ]` Verifier le widget d'installation modeles.

## 9. Nettoyage Livewire/Blade

Statut : `[ ]` Non commence

Taches :

- `[ ]` Supprimer les composants Livewire remplaces.
- `[ ]` Supprimer les vues Blade obsoletes.
- `[ ]` Garder uniquement les vues auth/admin necessaires ou les migrer plus tard.
- `[ ]` Retirer assets publics obsoletes.

## 10. Tests et verification

Statut : `[~]` En cours

Taches :

- `[ ]` Adapter les tests existants a PostgreSQL.
- `[x]` Ajouter tests feature pour acces modeles/groupes.
- `[x]` Ajouter tests feature pour conversations/messages.
- `[ ]` Ajouter tests feature pour collections/RAG avec mocks.
- `[x]` Verifier `composer test`.
- `[x]` Verifier `npm run build`.
- `[x]` Verifier `docker compose config`.
- `[x]` Verifier `docker compose build app`.
- `[ ]` Demarrer le compose et verifier l'application dans un navigateur.

## 11. Documentation finale

Statut : `[~]` En cours

Taches :

- `[x]` Mettre a jour `README.md`.
- `[ ]` Mettre a jour la documentation d'architecture.
- `[ ]` Documenter les commandes Docker.
- `[ ]` Documenter les variables d'environnement.
- `[ ]` Documenter la strategie RAG Qdrant/pgvector.
