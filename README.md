# Tiny Talk AI

*Mini‑ChatGPT auto‑hébergé powered by [Ollama](https://ollama.com) + Laravel + Livewire*

> **Quick pitch :** Tiny Talk AI fournit une interface de chat ultra‑simple pour interroger des modèles LLM (Llama 3, Mistral‑7B…). Toute la stack est conteneurisée et tient sur une machine < 2 vCPU / 8 Go RAM.

---

## ✨ Fonctionnalités clés

* **UI minimaliste** : une zone de dialogue + dark / light mode.
* **Modèles interchangeables** : configuration dans `.env` (ex. `llama3`, `mistral:7b`).
* **Self‑host** : Ollama tourne localement → aucune fuite de données.
* **Authentification Breeze** : email / password, protection CSRF.
* **Historique persistant chiffré** (à venir).
* **CI/CD GitHub Actions** : lint + tests + build Docker.

---

## 🏗️ Architecture

```
Browser → Laravel Livewire (app) → Ollama daemon
             ↘                ↘
              ↘                → MySQL (data)
               → Redis (queues/cache)
```

* **Sail (Docker Compose)** lance `laravel.test`, `mysql`, `redis`.
* **Ollama** écoute sur `localhost:11434` (ou conteneur `ollama:` selon l’OS).

---

## 🚀 Mise en route rapides

### 1. Cloner & préparer l’app

```bash
git clone git@github.com:Metio-DEV/TinyTalkAI.git
cd TinyTalkAI
cp .env.example .env
composer install
npm install && npm run build
```

### 2. Préparer Ollama (modèles, service)

```bash
bash scripts/setup_ollama.sh     # macOS, Linux, WSL 2
```

### 3. Démarrer la stack

```bash
./vendor/bin/sail up -d          # PHP, Nginx, MySQL, Redis
./vendor/bin/sail artisan migrate
open http://localhost            # Page d’accueil Breeze
```

---

## ⚙️ Tableau d’installation Ollama

| OS                        | Commandes                                                                       | Remarques                                               |
| ------------------------- | ------------------------------------------------------------------------------- | ------------------------------------------------------- |
| **macOS**                 | `brew install ollama`<br>`./scripts/setup_ollama.sh`                            | Service `launchd` auto‑start                            |
| **Linux**                 | `curl -fsSL https://ollama.com/install.sh \| sh`<br>`./scripts/setup_ollama.sh` | systemd `ollama.service`                                |
| **Windows 11/10 + WSL 2** | Ouvrir Ubuntu WSL → `bash scripts/setup_ollama.sh`                              | WSL détecté comme Linux                                 |
| **Windows (sans WSL)**    | `docker run -d -p 11434:11434 -v ollama-data:/root/.ollama ollama/ollama`       | Configure `.env OLLAMA_BASE_URL=http://localhost:11434` |

---

## 📂 Arborescence importante

```
TinyTalkAI/
├── app/                # Code Laravel
├── public/             # Front assets compilés (Vite)
├── scripts/            # utilitaires (setup_ollama.sh …)
├── docker-compose.yml  # Sail services
└── .github/workflows/  # CI (lint, tests, build)
```

---

## 💡 Scripts utiles

| Commande                                         | Action                            |
| ------------------------------------------------ | --------------------------------- |
| `./vendor/bin/sail up -d`                        | Démarrer la stack en arrière‑plan |
| `./vendor/bin/sail artisan migrate:fresh --seed` | Reset DB de dev                   |
| `./vendor/bin/sail artisan queue:work`           | Traiter les jobs Laravel          |
| `npm run dev`                                    | Vite en mode watch                |

---

## 🧪 Tests

```bash
./vendor/bin/sail test      # exécute Pest en parallèle
```

---

## 📜 Licence

MIT – © Metio 2025
