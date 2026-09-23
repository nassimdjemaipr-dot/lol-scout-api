# Déploiement — LoL Scout

Procédure de mise en production de l'application complète : API Symfony, front React,
base MySQL. Elle est écrite pour être exécutable par un tiers sans connaissance préalable
du projet.

---

## 1. Prérequis

| Outil | Version | Vérification |
|---|---|---|
| Docker Engine | ≥ 24 | `docker --version` |
| Docker Compose | ≥ 2.24 | `docker compose version` |
| Git | — | `git --version` |

Compose 2.24 est le minimum : la configuration de production utilise les directives
`!reset` et `!override`, introduites à cette version.

Aucune autre dépendance. Ni PHP, ni Node, ni MySQL ne sont nécessaires sur la machine
hôte : tout est conteneurisé.

---

## 2. Installation locale

Les deux dépôts doivent être clonés **côte à côte** : le service `front` du fichier
Compose construit son image depuis `../lol-scout-front`.

```bash
mkdir lol-scout && cd lol-scout
git clone https://github.com/nassimdjemaipr-dot/lol-scout-front.git
git clone https://github.com/nassimdjemaipr-dot/lol-scout-api.git
cd lol-scout-api
```

L'arborescence attendue :

```
lol-scout/
├── lol-scout-api/     ← on travaille ici
└── lol-scout-front/
```

### Démarrage

```bash
docker compose up -d --build
```

Cette commande construit et démarre les quatre services : `php`, `nginx`, `front`,
`database`.

### Initialisation

```bash
docker compose exec php composer install
docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### Clé API Riot

La synchronisation des statistiques exige une clé de développement Riot, à créer sur
<https://developer.riotgames.com>. Elle se place dans `.env.local`, **jamais dans `.env`**,
qui est versionné :

```bash
echo 'RIOT_API_KEY=RGAPI-votre-cle' >> .env.local
```

Une clé de développement expire au bout de 24 heures. Le reste de l'application
fonctionne sans elle.

### Vérification

| URL | Attendu |
|---|---|
| <http://localhost:3000> | Interface React |
| <http://localhost:8000/api/players> | JSON, 6 joueurs de démonstration |
| <http://localhost:8000/api/doc> | Documentation Swagger interactive |

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3000/players   # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/players # 200
```

### Comptes de démonstration

| Rôle | Adresse |
|---|---|
| Joueur | `joueur1@lolscout.gg` |
| Club | `club1@lolscout.gg` |
| Administrateur | `admin@lolscout.gg` |

Mot de passe commun : `LolScout2026!`

### Arrêt

```bash
docker compose down        # arrête, conserve la base
docker compose down -v     # arrête et supprime la base
```

---

## 3. Les trois environnements

| | `dev` | `test` | `prod` |
|---|---|---|---|
| Fichiers Compose | `compose.yaml` | `compose.yaml` | `compose.yaml` + `compose.prod.yaml` |
| Image PHP | `docker/php/Dockerfile` | idem dev | `docker/php/Dockerfile.prod` |
| Sources | montées depuis l'hôte | montées | **copiées dans l'image** |
| Dépendances | avec `--dev` | avec `--dev` | `--no-dev`, autoloader optimisé |
| OPcache | validation à chaque requête | idem | `validate_timestamps=0` |
| Debug | activé | activé | désactivé |
| Port MySQL | publié sur 3306 | publié | **non publié** |
| `X-Powered-By` | exposé | exposé | masqué (`expose_php = Off`) |
| Migrations | manuelles | automatiques (CI) | **au démarrage du conteneur** |

La différence de fond est le montage des sources. En développement, le code est monté
depuis l'hôte : une modification est visible sans reconstruction. En production, il est
copié dans l'image, ce qui rend le conteneur immuable et reproductible — c'est ce qui
permet de revenir à la version précédente en redéployant une image antérieure.

---

## 4. Variables d'environnement

Aucun secret n'est versionné. En développement, ils vont dans `.env.local` ; en
production, ils sont injectés par le fournisseur d'hébergement ou par les *secrets*
GitHub Actions.

| Variable | Rôle | Obligatoire en production |
|---|---|---|
| `APP_SECRET` | Secret applicatif Symfony | oui |
| `DATABASE_URL` | DSN MySQL | oui |
| `JWT_PASSPHRASE` | Phrase secrète de la clé privée RSA | oui |
| `RIOT_API_KEY` | Clé d'accès à l'API Riot | oui |
| `CORS_ALLOW_ORIGIN` | Origine autorisée du front | oui |
| `VITE_API_URL` | URL de l'API, **compilée dans le bundle** | oui |
| `MYSQL_ROOT_PASSWORD` · `MYSQL_USER` · `MYSQL_PASSWORD` | Base de données | oui |
| `HTTP_PORT` · `FRONT_PORT` | Ports publiés | non (8000 / 3000) |

`compose.prod.yaml` refuse de démarrer si l'une des variables obligatoires est absente —
la syntaxe `${VAR:?message}` provoque une erreur explicite plutôt qu'un démarrage
silencieux avec une valeur par défaut.

`VITE_API_URL` mérite une attention particulière : elle est injectée **à la construction**
de l'image, pas à son exécution. Changer l'URL de l'API impose de reconstruire l'image du
front.

---

## 5. Mise en production

### 5.1. Préparer le fichier de secrets

Sur le serveur cible, créer un fichier `prod.env` hors du dépôt :

```bash
cat > /etc/lol-scout/prod.env <<'EOF'
APP_SECRET=<32 caractères hexadécimaux aléatoires>
DATABASE_URL=mysql://app:<mot de passe>@database:3306/lolscout?serverVersion=8.0
JWT_PASSPHRASE=<phrase secrète>
RIOT_API_KEY=<clé de production Riot>
CORS_ALLOW_ORIGIN=^https://lol-scout\.fr$
VITE_API_URL=https://lol-scout.fr/api
MYSQL_ROOT_PASSWORD=<mot de passe root>
MYSQL_USER=app
MYSQL_PASSWORD=<mot de passe applicatif>
EOF
chmod 600 /etc/lol-scout/prod.env
```

`APP_SECRET` se génère avec `openssl rand -hex 16`.

### 5.2. Déployer

```bash
cd /srv/lol-scout/lol-scout-api
git pull --ff-only origin main
git -C ../lol-scout-front pull --ff-only origin main

docker compose \
  -f compose.yaml -f compose.prod.yaml \
  --env-file /etc/lol-scout/prod.env \
  up -d --build
```

Au premier démarrage uniquement, générer la paire de clés JWT :

```bash
docker compose -f compose.yaml -f compose.prod.yaml --env-file /etc/lol-scout/prod.env \
  exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

**Les migrations Doctrine sont jouées automatiquement** par
`docker/php/entrypoint.sh` à chaque démarrage du conteneur. C'est le point le plus
souvent oublié d'une mise en production : sans lui, on livre du code qui attend un schéma
qui n'existe pas encore.

### 5.3. Vérifier

```bash
docker compose -f compose.yaml -f compose.prod.yaml \
  --env-file /etc/lol-scout/prod.env ps

curl -s -o /dev/null -w "%{http_code}\n" https://lol-scout.fr/api/players
```

Aucun en-tête `X-Powered-By` ne doit apparaître, et `Server` ne doit pas porter de
numéro de version.

### 5.4. Revenir en arrière

```bash
git checkout <tag précédent>
docker compose -f compose.yaml -f compose.prod.yaml \
  --env-file /etc/lol-scout/prod.env up -d --build
```

Les images de production étant immuables, un retour arrière est une reconstruction depuis
un tag antérieur. **Attention** : une migration Doctrine n'est pas annulée par ce retour.
Une migration destructive doit être déployée séparément du code qui en dépend.

---

## 6. Stratégie de mise en production

Trois stratégies ont été considérées.

| Stratégie | Principe | Retenue ? |
|---|---|---|
| **Fenêtre de maintenance** | Arrêt, mise à jour, redémarrage | **oui** |
| Rolling update | Remplacement progressif des conteneurs | non |
| Bleu/vert | Deux environnements complets, bascule du routage | non |

**La fenêtre de maintenance est retenue**, pour une raison assumée : l'application est
déployée sur un hôte unique, avec une seule instance de chaque service. Un *rolling
update* suppose plusieurs instances derrière un répartiteur de charge ; un déploiement
bleu/vert suppose de dupliquer toute l'infrastructure, base comprise. Ni l'un ni l'autre
n'a de sens à cette échelle, et l'interruption réelle est de l'ordre de la minute.

Ce choix n'est pas un cul-de-sac. L'architecture est en couches séparées (§ 6 du dossier
projet) : passer à plusieurs instances derrière un répartiteur ne demanderait **aucune
modification du code applicatif**, l'API étant sans état — l'authentification repose sur
un jeton JWT, pas sur une session serveur.

Le jour où le bleu/vert deviendrait nécessaire, le point dur ne serait pas l'application
mais la base : il faudrait que deux versions du schéma coexistent, donc n'écrire que des
migrations rétrocompatibles.

---

## 7. Sauvegarde et restauration

La base vit dans le volume Docker `db_data`. Elle survit à un `docker compose down`, mais
**pas** à un `down -v`.

```bash
# Sauvegarde
docker compose exec database \
  mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" lolscout > backup-$(date +%F).sql

# Restauration
docker compose exec -T database \
  mysql -u root -p"$MYSQL_ROOT_PASSWORD" lolscout < backup-2026-09-23.sql
```

En production, cette commande doit être planifiée quotidiennement et la sauvegarde copiée
hors du serveur — une sauvegarde qui vit sur la machine qu'elle protège ne protège de
rien.

---

## 8. Dépannage

| Symptôme | Cause probable | Correctif |
|---|---|---|
| `port is already allocated` | Un conteneur d'un autre projet occupe 3000, 8000 ou 3306 | `docker ps`, puis `docker compose down` dans le projet concerné |
| Front en 404 sur une URL directe | Repli SPA absent | Vérifier `try_files` dans `docker/nginx.conf` du front |
| API en 502 | php-fpm pas encore prêt | Attendre quelques secondes, puis `docker compose logs php` |
| `Invalid JWT Token` | Clés absentes ou passphrase erronée | Régénérer avec `lexik:jwt:generate-keypair` |
| Statistiques Riot en échec | Clé expirée (24 h en développement) | Régénérer la clé et mettre à jour `.env.local` |
| Le front appelle la mauvaise API | `VITE_API_URL` est compilée dans le bundle | Reconstruire : `docker compose build --no-cache front` |
| Migration non appliquée en production | — | Elle l'est au démarrage ; consulter `docker compose logs php` |

---

## 9. État

| Élément | État |
|---|---|
| Environnement local, une commande | ✅ vérifié |
| Configuration de production complète | ✅ construite et démarrée, **non hébergée** |
| Migrations au démarrage du conteneur | ✅ vérifié |
| Secrets hors du dépôt | ✅ |
| Procédure de retour arrière | ✅ documentée |
| Hébergement public | ❌ non réalisé |
| Déploiement continu (`cd.yml`) | ❌ non réalisé |

La configuration de production a été **construite et démarrée localement** — les quatre
services répondent, les migrations se jouent au démarrage, les en-têtes de version sont
masqués. Elle n'a pas été déployée sur un serveur public, ce que le cahier des charges
technique n'exige pas : *« le déploiement continu n'est pas forcément vers un serveur réel
dans notre contexte pédagogique, mais au minimum, vous devez être en mesure de fournir une
procédure claire de mise en production »*.
