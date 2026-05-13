# Plan d'integration OpenAI dans TinyTalkAI

## Objectif

Ajouter OpenAI comme fournisseur de modeles utilisable dans TinyTalkAI, en complement d'Ollama, avec une configuration par compte utilisateur et une experience de parametres integree a l'interface de chat.

La fonctionnalite doit couvrir le cycle complet :

- connexion d'un compte OpenAI par cle API ;
- validation et stockage securise de la configuration ;
- synchronisation des modeles OpenAI accessibles ;
- selection des modeles par fournisseur ;
- generation et streaming des reponses dans le chat ;
- compatibilite avec l'historique, le RAG, les resumes et les compteurs de tokens ;
- deplacement de l'acces compte/parametres vers un panneau similaire aux interfaces de chatbot.

## Principes d'architecture

OpenAI doit etre integre comme un fournisseur IA a part entiere, pas comme une exception dans les controleurs existants.

TinyTalkAI doit donc utiliser une couche commune :

- `ChatProvider` : contrat commun pour verifier un fournisseur, lister ses modeles, streamer une reponse et recuperer des metadonnees.
- `OllamaProvider` : adaptation de l'integration Ollama actuelle.
- `OpenAIProvider` : integration OpenAI via l'API Responses.
- `ProviderRegistry` : resolution du fournisseur selon le modele selectionne ou la conversation.

Les controleurs et services de chat ne doivent plus construire directement des appels HTTP specifiques a Ollama lorsqu'une reponse modele est attendue.

## Donnees

### Modeles

Ajouter un champ `provider` a la table `models`.

Valeurs prevues :

- `ollama`
- `openai`

La contrainte d'unicite doit etre logique sur `provider + full_name`, afin d'eviter les collisions de noms entre fournisseurs.

Ajouter aussi des metadonnees optionnelles :

- `context_window`
- `max_output_tokens`
- `capabilities`

### Conversations

Ajouter un champ `provider` a la table `conversations`.

Ce champ doit etre renseigne au moment de la creation et conserve lors de la reprise d'une conversation. Il ne faut pas inferer le fournisseur uniquement depuis `model_name`.

### Comptes fournisseurs utilisateur

Creer une table `user_ai_provider_accounts` :

- `id`
- `user_id`
- `provider`
- `label`
- `encrypted_api_key`
- `organization_id`
- `project_id`
- `status`
- `last_verified_at`
- `last_error`
- `metadata`
- timestamps

La cle API OpenAI doit etre chiffree cote serveur et ne jamais etre renvoyee au navigateur.

## Configuration OpenAI

Dans les parametres utilisateur :

- afficher l'etat de connexion OpenAI ;
- permettre la saisie/remplacement de la cle API ;
- permettre la saisie optionnelle de `Organization ID` et `Project ID` ;
- proposer un bouton de test de connexion ;
- afficher la derniere verification et l'erreur eventuelle ;
- permettre la deconnexion.

Apres validation, lancer une synchronisation des modeles OpenAI disponibles.

## Modeles OpenAI

Synchroniser les modeles OpenAI accessibles au compte, puis filtrer l'affichage pour les modeles de chat pertinents.

Selection initiale recommandee :

- `gpt-5.5`
- `gpt-5.4`
- `gpt-5.4-mini`
- `gpt-5.4-nano`
- `gpt-5-mini`
- `gpt-5-nano`
- `gpt-4.1`
- `gpt-4.1-mini`

Chaque modele affiche doit inclure :

- fournisseur ;
- identifiant complet ;
- libelle utilisateur ;
- fenetre de contexte si connue ;
- sortie maximale si connue ;
- support streaming ;
- support raisonnement si connu.

## Selection de modele

Le selecteur de modeles doit afficher des groupes :

- OpenAI
- Ollama

La selection doit stocker :

- `selected_provider`
- `selected_model`

Lorsqu'un utilisateur choisit un modele depuis la sidebar, une nouvelle conversation doit etre preparee comme aujourd'hui.

## Chat et streaming

Le flux de chat doit rester compatible avec le frontend existant :

- `prepareMessage` cree le message utilisateur, la conversation et le payload de streaming ;
- `stream` emet des evenements SSE `chunk`, `complete`, `error`, `close` ;
- le frontend continue de consommer le meme contrat.

Pour OpenAI, le provider doit appeler l'API Responses avec :

- `model`
- `input`
- `stream: true`
- `temperature` si applicable ;
- `max_output_tokens` ;
- `reasoning.effort` pour les modeles qui le supportent.

Les evenements OpenAI doivent etre normalises vers le contrat TinyTalkAI.

## RAG

Le RAG reste gere par TinyTalkAI dans cette phase.

Les extraits Qdrant sont injectes dans le prompt avant appel au fournisseur. OpenAI ne remplace pas Qdrant dans cette premiere integration.

## Resumes et tokens

`ConversationMemoryService` doit utiliser la couche fournisseur pour generer les resumes.

Le compteur de tokens doit utiliser :

- les metadonnees Ollama via `/api/show` pour Ollama ;
- les metadonnees stockees pour OpenAI ;
- une valeur nulle si inconnue.

## Interface compte et parametres

Deplacer l'acces compte/parametres dans un bouton utilisateur en bas de la sidebar, proche des interfaces de chatbot.

Le panneau parametres doit contenir :

- Compte : nom, langue, profil, deconnexion ;
- Modeles : modele courant, temperature, max tokens, effort de raisonnement ;
- Connexions : connexion OpenAI, test, suppression ;
- Administration : lien admin visible uniquement pour les administrateurs.

La page `/profile` peut rester disponible, mais elle ne doit plus etre l'entree principale des parametres.

## Securite

Contraintes minimales :

- aucune cle API dans le frontend ;
- aucune cle API dans les logs ;
- chiffrement des cles en base ;
- affichage masque apres sauvegarde ;
- rate limiting sur les tests de connexion ;
- erreurs normalisees ;
- suppression des payloads sensibles lors des exceptions.

## Verification

Tests a couvrir :

- sauvegarde et chiffrement d'un compte OpenAI ;
- test de connexion OpenAI valide/invalide ;
- synchronisation de modeles OpenAI ;
- liste de modeles groupee par fournisseur ;
- creation de conversation avec `provider=openai` ;
- streaming OpenAI simule ;
- conservation du comportement Ollama ;
- panneau parametres desktop et mobile.

## Ordre d'implementation

1. Ajouter les migrations et modeles de donnees.
2. Introduire le contrat provider et adapter Ollama.
3. Ajouter `OpenAIProvider`.
4. Brancher `ChatAppController` et `ChatStreamController`.
5. Ajouter les routes de configuration OpenAI.
6. Mettre a jour l'interface React.
7. Adapter les resumes et compteurs.
8. Ajouter les tests.
9. Lancer `php artisan test` et `npm run build`.
