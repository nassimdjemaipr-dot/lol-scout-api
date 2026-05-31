# Politique de tests — LoL Scout (Jalon 5)

> Document remis dans le cadre du chapitre **X. Politique de tests** du dossier projet.
> État au **29/05/2026** (rendu Jalon 5).

---

## 1. Bilan honnête

À la livraison du Jalon 5, l'application LoL Scout est dans un état de **bêta fonctionnelle** : le cœur métier (auth, profils, offres, candidatures, intégration Riot) est implémenté et opérationnel, mais la **couverture de tests automatisés est volontairement reportée au Jalon 6**.

Le choix a été fait de **prioriser l'implémentation des fonctionnalités** (backend complet + frontend MVP) pendant le mois de mai, dans la limite du temps disponible pour un projet solo. Les tests automatisés, qui doivent atteindre **70 % de couverture** d'ici la livraison finale (Jalon 6, juin 2026), sont planifiés et leur conception est déjà documentée ci-dessous.

---

## 2. Tests réalisés (Jalon 5)

### 2.1 Smoke tests d'API (manuels, via curl)

Chaque endpoint critique a été testé manuellement avec `curl` :

| Test | Commande | Résultat attendu | Statut |
|---|---|---|---|
| Listing public | `curl http://localhost:8000/api/players` | HTTP 200, JSON liste | ✅ OK |
| Listing protégé sans token | `curl http://localhost:8000/api/players/me` | HTTP 401 | ✅ OK |
| Login JWT | `curl -X POST /api/login_check ...` | Token JWT renvoyé | ✅ OK |
| Profil avec token | `curl /api/players/me -H "Authorization: Bearer ..."` | Profil renvoyé | ✅ OK |
| Postuler à une offre | `curl -X POST /api/applications ...` | HTTP 201 | ✅ OK |
| Double candidature | Idem 2x sur même offre | HTTP 409 (contrainte UNIQUE) | ✅ OK |
| Accès offre d'un autre club | PATCH /api/offers/{id} d'un club B avec token A | HTTP 403 | ✅ OK |

### 2.2 Tests fonctionnels E2E (manuels, navigateur)

Le flow complet a été testé sur le front (`http://localhost:5173`) avec les comptes de démo :

| Scénario | Étapes | Statut |
|---|---|---|
| **Inscription joueur** | Visiteur → /register → choix Joueur → login auto | ✅ OK |
| **Browse public** | Visiteur sans login → /players, /offers → données visibles | ✅ OK |
| **Candidature complète** | joueur1 login → /offers → Postuler → message → ✅ envoyée → /dashboard/player → candidature visible | ✅ OK |
| **Réception candidature** | club1 login → /dashboard/club/applications → voit candidatures → Accepter → statut mis à jour | ✅ OK |
| **Garde-fous** | Tentative POST /offers en tant que joueur → 403 ; postuler à offre fermée → 409 | ✅ OK |

### 2.3 Tests d'intégration Doctrine

- ✅ Toutes les migrations passent à blanc sur une BDD vierge (vérifié via `docker compose exec php php bin/console doctrine:database:drop --force && create && migrate`)
- ✅ Les fixtures se chargent sans erreur (10 users, 6 players, 3 clubs, 5 offers, 5 applications, 18 champions joués vérifiés via `dbal:run-sql`)
- ✅ La contrainte `UNIQUE(player_id, offer_id)` sur la table `application` est bien créée (vérifiée dans la migration `Version20260527055707`)

---

## 3. Tests planifiés (Jalon 6 — juin 2026)

### 3.1 Tests unitaires PHPUnit (cible : ≥ 70 % de couverture)

| Module | Tests prévus |
|---|---|
| `AuthController` | register success / email déjà pris / role invalide / login success / mauvais password |
| `ApplicationController` | apply success / double candidature / offre inactive / accès non-club au PATCH |
| `RiotSyncService` | parsing region routing / cache hit/miss (mock) / gestion 401/429/5xx Riot |
| `RankComparator` (à créer) | logique de comparaison Iron < Bronze < ... < Challenger |
| `User`, `Player`, `Offer` | getters/setters + contraintes de validation |

**L'API Riot sera mockée** dans les tests unitaires via un `MockHttpClient` Symfony — aucun appel réel à l'API externe pendant les tests.

### 3.2 Tests fonctionnels (PHPUnit + `WebTestCase`)

```php
public function testPlayerCanApplyToOffer(): void
{
    $client = static::createClient();
    $token = $this->loginAsPlayer('joueur1@lolscout.gg');
    $client->request('POST', '/api/applications', [], [], [
        'HTTP_AUTHORIZATION' => "Bearer $token",
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['offerId' => 1, 'message' => 'Bonjour !']));

    $this->assertResponseStatusCodeSame(201);
}
```

### 3.3 Tests d'API end-to-end (collection Postman / Newman)

Une **collection Postman** sera exportée et exécutée via Newman en CI. Couverture prévue : 100 % des routes documentées dans le README.

### 3.4 Tests frontend (Jest + React Testing Library)

- Composants UI (Button, Card, RoleBadge, RankBadge) : snapshot + props
- Hooks custom (useAuth) : login/logout/persistence
- Pages clés (LoginPage, OfferDetailPage form) : interactions utilisateur

---

## 4. Outils utilisés (planifiés)

| Outil | Usage | Statut |
|---|---|---|
| **PHPUnit** | Tests unitaires + fonctionnels backend | À configurer en Jalon 6 |
| **Symfony WebTestCase** | Tests d'intégration HTTP | À configurer en Jalon 6 |
| **MockHttpClient** | Mocker l'API Riot | À configurer en Jalon 6 |
| **Newman** (Postman CLI) | E2E sur les endpoints | À configurer en Jalon 6 |
| **Jest + RTL** | Tests frontend | À configurer en Jalon 6 |
| **GitHub Actions** | CI à chaque push | En cours (cf. `.github/workflows/`) |

---

## 5. Intégration continue (CI)

Une pipeline **GitHub Actions** est en place sur `.github/workflows/ci.yml` et s'exécute à chaque push :

- ✅ Installation des dépendances Composer
- ✅ Vérification de la syntaxe PHP
- ⚠️ Tests PHPUnit (à brancher en Jalon 6 quand les tests seront écrits)

Voir le badge dans le README et les exécutions sur :
**https://github.com/nassimdjemaipr-dot/lol-scout-api/actions**

---

## 6. Tests de performance (informatif)

- L'API renvoie les listings (`GET /api/players`) en **< 100 ms** sur le poste de dev (Docker Desktop, 6 entrées en BDD).
- Le pipeline Riot (link + sync) prend **~2-3 secondes** à cause des 3 appels HTTP successifs à l'API Riot Games (résolution PUUID + rang + champions).
- Le cache 1h sur les PUUID descend ce temps à **~1 seconde** lors d'une re-synchronisation.

**Tests de charge** (JMeter) prévus pour Jalon 6 si le temps le permet.

---

## 7. Résultats actuels

| Catégorie | Couverture | Détail |
|---|---|---|
| Smoke tests manuels (curl) | 100 % des endpoints critiques | ✅ Tous verts |
| Tests fonctionnels manuels (navigateur) | 5 scénarios principaux | ✅ Tous verts |
| Migrations | 100 % | ✅ Applicables sur BDD vierge |
| **Tests unitaires automatisés** | **0 %** | **À implémenter en Jalon 6** |
| **Tests fonctionnels automatisés** | **0 %** | **À implémenter en Jalon 6** |

---

## 8. Plan d'action Jalon 6 (juin 2026)

| Semaine | Objectif |
|---|---|
| Semaine 1 juin | Setup PHPUnit + 1er test (login) → vérifier la CI passe au vert |
| Semaine 2 juin | Tests unitaires des services (AuthController, ApplicationController, RiotSyncService) → cible 50 % couverture |
| Semaine 3 juin | Tests fonctionnels WebTestCase + collection Postman → cible 70 % couverture |
| Semaine 4 juin | Tests frontend Jest + finalisation rapport tests pour soutenance |

---

## Conclusion

Le projet n'a actuellement pas de tests automatisés, mais **chaque fonctionnalité a été testée manuellement** (curl + navigateur) avant d'être considérée comme livrable. Le **plan d'action pour atteindre les 70 % de couverture** d'ici fin juin est défini et planifié semaine par semaine.

Cette approche, pragmatique pour un projet solo dans des contraintes de temps réelles, **assure que toutes les fonctionnalités sont vérifiées** tout en planifiant rigoureusement la dette technique de test.