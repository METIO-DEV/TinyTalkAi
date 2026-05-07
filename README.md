# Tiny Talk AI

Mini-ChatGPT auto-heberge powered by Ollama, Laravel et React/Inertia.

## Fonctionnalites

- UI React/Inertia avec composants shadcn/ui pour le chat principal.
- Modeles Ollama synchronises et controles par groupes.
- Stack self-hosted via Docker Compose : PHP-FPM, Nginx, PostgreSQL, Redis, Qdrant et Vite, avec Ollama reutilise depuis l'hote.
- RAG conserve avec Qdrant, pgvector active cote PostgreSQL pour les besoins vectoriels futurs.
- Authentification Breeze/Livewire conservee pendant la migration progressive.

## Architecture

```text
Browser -> Nginx -> Laravel PHP-FPM -> Ollama (host)
              |           |          -> Qdrant
              |           |          -> Redis
              |           -> PostgreSQL + pgvector
              -> Vite dev server
```

Sail n'est plus utilise. Le projet se lance avec le `docker-compose.yml` du depot.

## Mise En Route

```bash
cp .env.example .env
docker compose run --rm app composer install
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Application : http://localhost:8080

## Ollama

Le projet suppose qu'Ollama tourne deja sur la machine hote. Depuis macOS et Docker Desktop, les conteneurs y accedent via `host.docker.internal`.

Pour installer des modeles :

```bash
ollama pull llama3.2
ollama pull nomic-embed-text
```

## Arborescence

```text
TinyTalkAI/
├── app/                # Code Laravel
├── docker/             # Images PHP, Nginx et init PostgreSQL
├── docs/               # Architecture et plan de refonte
├── public/             # Assets publics et build Vite
├── resources/js/       # Application React/Inertia
├── resources/views/    # Root Inertia + vues Blade encore conservees
├── docker-compose.yml  # Stack Docker Compose autonome
└── tests/              # Tests Pest
```

## Commandes Utiles

| Commande                                                   | Action                            |
| ---------------------------------------------------------- | --------------------------------- |
| `docker compose up -d`                                     | Demarrer la stack en arriere-plan |
| `docker compose exec app php artisan migrate:fresh --seed` | Reset DB de dev                   |
| `docker compose exec app php artisan queue:work`           | Traiter les jobs Laravel          |
| `docker compose logs -f node`                              | Suivre Vite en mode watch         |
| `composer test`                                            | Lancer les tests Pest             |
| `npm run build`                                            | Builder le frontend               |

## Documentation

- [Architecture initiale](docs/PROJET_ARCHITECTURE.md)
- [Plan de refonte](docs/REFONTE_INERTIA_POSTGRES_PLAN.md)

## Licence

MIT - Metio
