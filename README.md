# LoL Scout — Backend API

[![CI Backend](https://github.com/nassimdjemaipr-dot/lol-scout-api/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/nassimdjemaipr-dot/lol-scout-api/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/nassimdjemaipr-dot/lol-scout-api?include_prereleases&label=release)](https://github.com/nassimdjemaipr-dot/lol-scout-api/releases)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.2-000000?logo=symfony&logoColor=white)
![License](https://img.shields.io/badge/license-Educational-blue)

> Plateforme de recrutement esport pour joueurs et clubs **League of Legends**.
> Backend REST en **Symfony 7.2**, base de données **MySQL 8**, intégration **API Riot Games**.

**Projet fil-rouge CDA** — IPSSI — Auteur : Nassim Djemai — Période : Jan-Juin 2026.

---

## 🛠️ Stack technique

| Couche | Techno |
|---|---|
| Framework | Symfony 7.2 + Doctrine ORM 3.6 |
| Auth | JWT (lexik/jwt-authentication-bundle) — RS256 |
| Base de données | MySQL 8 (InnoDB, utf8mb4) |
| HTTP client | symfony/http-client |
| Cache | symfony/cache (filesystem en dev) |
| Rate limiter | symfony/rate-limiter |
| CORS | nelmio/cors-bundle |
| Conteneurisation | Docker Compose (PHP-FPM 8.2 + Nginx + MySQL) |

---

## 🚀 Lancement rapide (Docker)

### Prérequis
- **Docker Desktop** (avec WSL 2 sous Windows)
- **Git** + **GitHub CLI** (optionnel)

### Installation en 4 commandes

```bash
git clone https://github.com/nassimdjemaipr-dot/lol-scout-api.git
cd lol-scout-api

# 1. Construire et démarrer les conteneurs (PHP, Nginx, MySQL)
docker compose up -d

# 2. Installer les dépendances PHP
docker compose exec php composer install

# 3. Générer les clés JWT
docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists

# 4. Créer la base + appliquer les migrations + charger les fixtures de démo
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

→ L'API tourne sur **http://localhost:8000** ✅

### Vérification rapide

```bash
curl http://localhost:8000/api/players
# → liste les 6 joueurs de démo
```

### 📖 Documentation API interactive (Swagger)

Une fois l'API démarrée, ouvre **http://localhost:8000/api/doc** dans ton navigateur pour accéder à la documentation **Swagger UI** interactive :

- 18 endpoints documentés (auto-générés via `nelmio/api-doc-bundle`)
- Authentification Bearer JWT testable directement depuis l'UI (bouton **Authorize**)
- Spec OpenAPI 3.0 brute exposée sur **`/api/doc.json`**

---

## 👤 Comptes de démonstration

> Mot de passe commun à tous les comptes : **`password`**

| Rôle | Email | Profil lié |
|---|---|---|
| 🛡️ Admin | `admin@lolscout.gg` | — |
| 🎮 Joueur | `joueur1@lolscout.gg` | ShadowMid (MID, Diamond II) |
| 🎮 Joueur | `joueur2@lolscout.gg` | JungleKing (JUNGLE, Master I) |
| 🎮 Joueur | `joueur3@lolscout.gg` | TopDiff (TOP, Diamond IV) |
| 🎮 Joueur | `joueur4@lolscout.gg` | ADCarry (ADC, Emerald I) |
| 🎮 Joueur | `joueur5@lolscout.gg` | SupDiffAndy (SUPPORT, Platinum II) |
| 🎮 Joueur | `joueur6@lolscout.gg` | FaastHands (MID, Grandmaster) |
| 🏆 Club | `club1@lolscout.gg` | Phoenix Esport |
| 🏆 Club | `club2@lolscout.gg` | Nova Gaming |
| 🏆 Club | `club3@lolscout.gg` | Shadow Wolves |

### Test du flow auth + candidature

```bash
# 1. Login en tant que joueur
TOKEN=$(curl -s -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"joueur1@lolscout.gg","password":"password"}' \
  | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

# 2. Récupérer son profil
curl http://localhost:8000/api/players/me -H "Authorization: Bearer $TOKEN"

# 3. Postuler à l'offre #1
curl -X POST http://localhost:8000/api/applications \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"offerId":1,"message":"Bonjour, je suis très motivé pour rejoindre votre équipe."}'
```

---

## 📡 Endpoints API

### Public (sans authentification)

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/players` | Liste des joueurs |
| GET | `/api/players/{id}` | Détail d'un joueur |
| GET | `/api/players/search?role=MID&available=true` | Recherche filtrée |
| GET | `/api/offers` | Liste des offres actives |
| GET | `/api/offers/{id}` | Détail d'une offre |
| GET | `/api/clubs` | Liste des clubs |
| GET | `/api/clubs/{id}` | Détail d'un club |
| POST | `/api/register` | Créer un compte |
| POST | `/api/login_check` | Login → renvoie un JWT |

### Authentification requise (header `Authorization: Bearer <token>`)

| Méthode | Route | Rôle | Description |
|---|---|---|---|
| GET | `/api/me` | tous | Profil utilisateur courant |
| GET | `/api/players/me` | PLAYER | Mon profil joueur |
| POST | `/api/players` | PLAYER | Créer mon profil joueur |
| PATCH | `/api/players/{id}` | PLAYER (owner) | Modifier mon profil |
| POST | `/api/players/me/riot-account` | PLAYER | Lier mon compte Riot |
| POST | `/api/players/me/sync-riot` | PLAYER | Synchroniser mes stats Riot |
| POST | `/api/offers` | CLUB | Publier une offre |
| PATCH | `/api/offers/{id}` | CLUB (owner) | Modifier une offre |
| DELETE | `/api/offers/{id}` | CLUB (owner) | Supprimer une offre |
| POST | `/api/applications` | PLAYER | Postuler à une offre |
| GET | `/api/applications/me` | PLAYER | Mes candidatures |
| GET | `/api/clubs/me/applications` | CLUB | Candidatures reçues |
| PATCH | `/api/applications/{id}` | CLUB (owner) | Accepter/Refuser |

---

## 🏗️ Architecture

```
src/
├── Controller/           # Endpoints REST (Auth, Player, Club, Offer, Application)
├── Entity/               # Modèle Doctrine (10 entités)
├── Enum/                 # Enums (UserRole, PlayerRole, ApplicationStatus)
├── Repository/           # Couche d'accès BDD
├── Service/
│   └── RiotSyncService   # Intégration API Riot (link + sync stats)
└── DataFixtures/         # Données de démo (1 admin + 6 joueurs + 3 clubs + 5 offres + 5 candidatures)

config/
├── packages/security.yaml   # Firewall JWT + access_control par méthode
├── jwt/                     # Clés RSA (générées localement, .gitignore)
└── services.yaml            # Injection RIOT_API_KEY

migrations/                  # Migrations Doctrine versionnées
docker/                      # Dockerfile PHP + config Nginx
```

Architecture **MVC distribué** :
- **Modèle** = entités Doctrine + repositories
- **Contrôleur** = controllers Symfony (REST, JSON-only)
- **Vue** = application React indépendante ([lol-scout-front](https://github.com/nassimdjemaipr-dot/lol-scout-front))

---

## 🔌 Intégration API Riot Games

Le service `RiotSyncService` (`src/Service/RiotSyncService.php`) :

- Résout un **Riot ID** (`pseudo#tagLine`) en **PUUID** via Account-V1
- Récupère le **rang Solo/Duo** (League-V4) et le **top 3 champions** (Champion-Mastery-V4)
- **Cache 1h** sur les PUUID pour économiser le quota Riot
- Mapping intelligent **région → regional router** (europe / americas / asia / sea)
- Gestion d'erreur centralisée (401/403/404/429/5xx)

### Configuration

Crée un fichier `.env.local` à la racine :

```env
RIOT_API_KEY=RGAPI-votre-cle-ici
```

> Récupère une dev key sur **https://developer.riotgames.com** (expire toutes les 24h).
> Pour la production, demander une **Personal API Key** (90 jours, formulaire Riot).

---

## 🔐 Sécurité

Le détail complet est dans [`docs/SECURITY.md`](docs/SECURITY.md). Les mesures clés :

- **Auth JWT** RS256 (lexik/jwt-authentication-bundle)
- **Mots de passe** hashés en **bcrypt** (jamais en clair, jamais sérialisés)
- **Injection SQL** : Doctrine ORM uniquement (pas de SQL brut)
- **CORS** strict via `nelmio/cors-bundle`
- **Rate limiting** sur les appels Riot (`symfony/rate-limiter`)
- **Validation** déclarative sur les entités (`#[Assert\*]`)
- **Access control par méthode** : listings publics en GET, écriture protégée
- **Contraintes ownership** : un joueur n'édite que son profil, un club que ses offres

---

## 🧪 Tests & CI

Le détail est dans [`docs/TESTS.md`](docs/TESTS.md). En bref :

- ✅ Smoke tests manuels via curl pour chaque endpoint
- ✅ Tests E2E manuels du flow auth + candidature avec les comptes démo
- ⚠️ Tests automatisés PHPUnit prévus pour Jalon 6 (juin)
- 🔄 CI GitHub Actions : install + lint à chaque push (cf. `.github/workflows/`)

---

## 📋 Statut du projet (Jalon 5 — fin mai 2026)

✅ **Livré Jalon 5 (v1.0-beta)** :
- Authentification JWT + register/login
- CRUD complet : joueurs, clubs, offres, candidatures
- Intégration **API Riot Games live** (lien compte + sync stats + mapping noms de champions via Data Dragon)
- Sérialisation imbriquée (player → riotAccount → stats → playedChampions)
- Documentation Swagger interactive sur `/api/doc`
- Sécurité : access_control par méthode, ownership, hashing bcrypt, JWT RS256
- Docker Compose (PHP 8.2 + Nginx + MySQL 8) prêt à l'emploi
- CI GitHub Actions (install + lint container) verte
- 5 jeux de données de démo via fixtures

⏳ **Prévu Jalon 6 (juin)** : tests PHPUnit, voters Symfony, déploiement production.

Bilan détaillé dans [`docs/BILAN.md`](docs/BILAN.md).

---

## 📂 Documentation projet

- [`docs/SECURITY.md`](docs/SECURITY.md) — analyse de sécurité (Jalon 5)
- [`docs/TESTS.md`](docs/TESTS.md) — politique de tests (Jalon 5)
- [`docs/BILAN.md`](docs/BILAN.md) — bilan d'avancement (Jalon 5)

> Les dossiers de conception (Jalon 1 — CDCF, Jalon 2 — UI/UX, Jalon 3 — MERISE, Jalon 4 — UML & architecture) sont livrés séparément avec chaque jalon mensuel.

Frontend du projet : **https://github.com/nassimdjemaipr-dot/lol-scout-front**

---

## 🆘 Dépannage

| Problème | Solution |
|---|---|
| `docker compose up` échoue | Vérifie que Docker Desktop est lancé (icône verte) |
| Migrations bloquées | `docker compose exec php php bin/console doctrine:database:drop --force && doctrine:database:create && migrations:migrate -n` |
| Pas d'auth (401) | Vérifie que tu envoies le header `Authorization: Bearer <token>` |
| API Riot 401/403 | Ta dev key Riot a expiré (24h), régénère sur developer.riotgames.com |
| Port 8000 occupé | Change le port dans `compose.yaml` ligne 14 (ex: `"8080:80"`) |