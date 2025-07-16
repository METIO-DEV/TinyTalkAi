#!/usr/bin/env bash
set -euo pipefail

MODELS=("llama3" "mistral:7b")

is_macos()  { [[ "$OSTYPE" == "darwin"* ]]; }
is_linux()  { [[ "$OSTYPE" == "linux"* ]]; }
is_wsl()    { grep -qi "microsoft" /proc/version 2>/dev/null; }

# -----------------------------------------------------------------------------
# 0. Détection plateforme
# -----------------------------------------------------------------------------
if ! is_macos && ! is_linux; then
  echo "🪟  Windows détecté sans WSL : utilisez le conteneur Docker Ollama."
  echo "→   docker run -d -p 11434:11434 -v ollama-data:/root/.ollama ollama/ollama"
  exit 0
fi

# -----------------------------------------------------------------------------
# 1. Installer Ollama si absent
# -----------------------------------------------------------------------------
if ! command -v ollama &>/dev/null; then
  echo "🔧 Installing Ollama CLI…"
  if is_macos; then
    brew install ollama
  else  # Linux ou WSL
    curl -fsSL https://ollama.com/install.sh | sh
    sudo systemctl enable --now ollama
  fi
fi

# -----------------------------------------------------------------------------
# 2. S’assurer que le daemon tourne
# -----------------------------------------------------------------------------
if ! pgrep -f "ollama serve" &>/dev/null; then
  echo "🚀 Starting Ollama daemon…"
  if is_macos; then
    brew services start ollama
  else
    sudo systemctl start ollama
  fi
  sleep 3
fi

# -----------------------------------------------------------------------------
# 3. Télécharger les modèles manquants
# -----------------------------------------------------------------------------
for model in "${MODELS[@]}"; do
  if ! ollama list | grep -q "$model"; then
    echo "⬇️  Pulling $model…"
    ollama pull "$model"
  else
    echo "✅ $model already present"
  fi
done

echo "✅ Ollama ready on http://localhost:11434"