# Audit de sécurité des dépendances

**Date de l'audit : 23 septembre 2026.**
Outils : `composer audit` (back), `npm audit` (front).

Ce document consigne des résultats réels, datés et reproductibles. Chaque avis a été
confronté au code du projet : un avis publié ne vaut pas vulnérabilité exploitable ici.

---

## 1. Synthèse

| Périmètre | Avant | Après | Reste |
|---|---:|---:|---|
| Back — `composer audit` | 14 avis, 7 paquets | 14 | non corrigeables sans monter de version mineure |
| Front — `npm audit`, toutes dépendances | 15 | **3** | outillage de test uniquement |
| Front — dépendances de **production** | 4 (1 faible, 3 élevées) | **0** | — |

S'y ajoutent deux failles trouvées dans le code du projet lui-même, toutes deux
corrigées, décrites en section 4.

---

## 2. Front — corrigé

`npm audit fix` a résolu **12 des 15** avis sans changement de version majeure.

| Paquet | Sévérité | Nature | État |
|---|---|---|---|
| `react-router` / `react-router-dom` | élevée | Déni de service par appariement de routes inefficace ; contournement CSRF en mode RSC | ✅ corrigé |
| `undici` | élevée | Désynchronisation de réponses, injection CRLF, fuite inter-utilisateurs par directives de cache | ✅ corrigé |
| `vite` | élevée | Contournement de `server.fs.deny` sur chemins alternatifs Windows | ✅ corrigé |
| `vitest`, `@vitest/coverage-v8` | modérée | — | ⬜ **non corrigé** |

**Les trois avis restants portent sur Vitest**, le lanceur de tests. Il n'apparaît ni dans
le bundle de production, ni dans l'image Docker du front — le build multi-étapes ne
conserve que le `dist/` et Nginx. Le risque pour l'utilisateur final est nul ; le risque
résiduel concerne le poste de développement.

Vérification après correction : **152 tests au vert, build de production réussi.**

---

## 3. Back — analysé, non corrigé

Les 14 avis concernent exclusivement des composants Symfony. Le projet est figé sur
`7.2.*` (`extra.symfony.require` dans `composer.json`), or **aucun correctif n'existe dans
la branche 7.2** : les versions corrigées sont en 7.3, 7.4 et 8.0.

`composer update` sur les sept paquets ne change donc rien au décompte. Le seul correctif
possible est une **montée de version mineure du framework**, décidée en section 3.3.

### 3.1. Avis confrontés au code

| Paquet | Sévérité | Avis | Applicable ici ? |
|---|---|---|---|
| `symfony/http-foundation` | **élevée** | Contournement d'autorisation par analyse incorrecte de `PATH_INFO` (CVE-2025-64500) | **oui** — le contrôle d'accès du projet est déclaré par chemin dans `security.yaml` |
| `symfony/runtime` | moyenne | `APP_ENV` modifiable par une requête web (CVE-2026-47767, CVE-2026-46626) | **oui** — forcer `APP_ENV=dev` en production exposerait le profileur |
| `symfony/routing` | moyenne | Contournement de contrainte de route par alternance de regex non ancrée | marginal — les routes du projet n'utilisent pas de `requirements` |
| `symfony/routing` | moyenne | Encodage des segments `../` dans `UrlGenerator` | non — concerne la *génération* d'URL, que le projet n'utilise pas |
| `symfony/http-foundation` | moyenne | `IpUtils::PRIVATE_SUBNETS` omet des formes de transition IPv6 | non — aucun contrôle fondé sur l'adresse IP |
| `symfony/cache` | moyenne | Injection SQL dans `PdoAdapter::doClear()` | non — `config/packages/cache.yaml` laisse l'adaptateur par défaut, sur système de fichiers ; `PdoAdapter` n'est pas instancié |
| `symfony/security-http` | **élevée** | Contournement du pare-feu via sous-requête `failure_forward` | non — le pare-feu n'utilise pas `failure_forward` mais un `failure_handler` LexikJWT |
| `symfony/security-http` | **élevée** | Usurpation d'identité par regex de DN non ancrée dans `X509Authenticator` | non — aucune authentification par certificat X.509 |
| `symfony/security-http` | moyenne | `Cas2Handler` dérive l'URL de service de l'en-tête `Host` | non — aucun CAS |
| `symfony/dom-crawler` | faible | XXE dans `addXmlContent()` | non — dépendance transitive de `symfony/browser-kit`, en `require-dev` : absente de l'image de production |
| `symfony/yaml` ×3 | faible | ReDoS, épuisement de pile, allocation exponentielle à l'analyse | non — seuls les fichiers de configuration du projet sont analysés, jamais une saisie utilisateur |

### 3.2. Conclusion de l'analyse

Sur 14 avis, **deux sont réellement pertinents** pour ce projet : le contournement
d'autorisation via `PATH_INFO` et la modification d'`APP_ENV` par requête web. Les douze
autres visent des fonctionnalités que l'application n'utilise pas, ou des dépendances qui
ne partent pas en production.

Les deux avis pertinents ne deviennent exploitables qu'en production, sur une application
accessible publiquement — ce qui n'est pas le cas à ce jour.

### 3.3. Décision

**La montée en Symfony 7.4 LTS n'est pas effectuée avant la soutenance.** Une montée de
version mineure du framework touche l'ensemble de l'application ; la conduire à deux jours
d'un rendu figé, sans fenêtre pour en traiter les régressions, présente un risque supérieur
à celui des deux avis identifiés, qui supposent une exposition publique inexistante.

Elle constitue **le premier chantier à mener avant toute mise en ligne réelle**. La
démarche est balisée : passage de `7.2.*` à `7.4.*` dans `composer.json` et
`extra.symfony.require`, `composer update`, puis exécution des 82 tests existants — dont
c'est précisément le rôle de rendre une telle montée vérifiable.

---

## 4. Failles trouvées dans le code du projet

Deux défauts ont été mis au jour en auditant le code pour le dossier projet. Les deux sont
corrigés.

### 4.1. Inscription non validée

`AuthController::register` contrôlait la validité du JSON, la présence des champs,
l'appartenance du rôle à l'énumération et l'unicité de l'adresse — mais **ni le format de
l'adresse électronique, ni la longueur du mot de passe**. Une inscription avec
`email = "x"` et `password = "a"` était acceptée, en contradiction avec le cahier des
charges technique qui impose une politique de mot de passe avec longueur minimale.

**Corrigé** : `Assert\Email` sur `User::email`, champ non persisté `plainPassword`
contraint à 12 caractères minimum d'après la recommandation ANSSI. La contrainte porte sur
la saisie et non sur l'empreinte. Trois tests couvrent la correction.

### 4.2. Clé API Riot publiée sur un dépôt public

Le fichier `.env`, **versionné**, contenait une clé de développement Riot en clair. Le
dépôt étant public, la clé était lisible par quiconque.

L'impact réel est faible : une clé de développement Riot expire au bout de 24 heures, et
le commit concerné date de juin. Mais le dossier projet affirmait par ailleurs qu'« aucun
secret n'est versionné », ce qui était faux.

**Corrigé** : valeur retirée du `.env` versionné, qui porte désormais une consigne
explicite ; la clé réelle vit dans `.env.local`, ignoré par git. La clé exposée est à
révoquer sur le portail développeur Riot.

**L'historique git n'est pas réécrit.** Un `filter-repo` modifierait tous les hachages de
commits, invaliderait les références des PR déjà fusionnées et casserait les clones
existants. Pour une clé expirée depuis trois mois, le risque de l'opération dépasse celui
de la fuite.

---

## 5. Reproduire cet audit

```bash
# Back
docker compose exec php composer audit
docker compose exec php composer audit --format=summary

# Front
npm audit                 # toutes dépendances
npm audit --omit=dev      # dépendances de production uniquement
```

À relancer avant chaque livraison. Les deux commandes sont candidates à une intégration
dans la chaîne d'intégration continue, en avertissement plutôt qu'en échec bloquant : un
avis publié sur une dépendance transitive ne doit pas empêcher de livrer un correctif
urgent.
