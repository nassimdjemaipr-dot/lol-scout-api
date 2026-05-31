# Bilan d'avancement — Jalon 5 (31/05/2026)

> Document remis dans le cadre du Jalon 5 du projet fil-rouge CDA — LoL Scout.

---

## 1. Vue d'ensemble

| Jalon | Échéance | Statut |
|---|---|---|
| Jalon 1 — CDCF | 31/01/2026 | ✅ Livré |
| Jalon 2 — Méthodo + UI/UX | 28/02/2026 | ✅ Livré |
| Jalon 3 — MERISE BDD | 31/03/2026 | ✅ Livré |
| Jalon 4 — UML + Architecture | 30/04/2026 | ✅ Livré |
| **Jalon 5 — Dev + Sécurité + Tests** | **31/05/2026** | **✅ Livré (v1.0-beta)** |
| Jalon 6 — Déploiement + Soutenance | 30/06/2026 | À venir |

**Statut global** : projet **en avance** sur l'intégration Riot live, **conforme** au périmètre MVP, **en retard** sur les tests automatisés (planifiés Jalon 6).

---

## 2. Fonctionnalités livrées (✅ FAIT)

### Backend (Symfony 7.2)

| Module | Statut | Détail |
|---|---|---|
| Inscription / Login JWT | ✅ | `/api/register`, `/api/login_check`, RS256, bcrypt |
| Modèle Doctrine | ✅ | 10 entités : User, Player, Club, Offer, Application, RiotAccount, PlayerStats, PlayedChampion + enums |
| CRUD Player | ✅ | List, get, search, me, create, update, delete |
| CRUD Club / Offer | ✅ | Création, modification, suppression avec check ownership |
| Système de candidature | ✅ | Postuler, lister (joueur/club), accepter/refuser |
| **Intégration Riot Games live** | ✅ | RiotSyncService : link Riot ID, sync rang Solo/Duo, top 3 champions, **mapping noms via Data Dragon** (cache 24h) |
| **Sérialisation imbriquée** | ✅ | `player.riotAccount.stats.playedChampions[]` exposé en JSON via groupes Doctrine |
| Fixtures de démo | ✅ | 1 admin + 6 joueurs + 3 clubs + 5 offres + 5 candidatures + 18 champions |
| **Documentation Swagger interactive** | ✅ | `/api/doc` (UI) + `/api/doc.json`, 18 endpoints auto-détectés, auth Bearer testable |
| Docker Compose | ✅ | PHP-FPM 8.2 + Nginx + MySQL 8 |
| Migrations versionnées | ✅ | 4 migrations passent sur BDD vierge |
| Access control par méthode | ✅ | Listings GET publics, écriture protégée |
| CORS strict | ✅ | nelmio/cors-bundle configuré |
| CI GitHub Actions | ✅ | Install + lint container à chaque PR |

### Frontend (React 19 + TypeScript + Vite)

| Module | Statut | Détail |
|---|---|---|
| Setup projet | ✅ | Vite + TypeScript + React Router v7 + React Query + Axios |
| Charte graphique LoL | ✅ | Design tokens CSS (couleurs `#0A1428` / `#C89B3C` / `#0AC8B9` + polices Cinzel/Outfit/JetBrains Mono) |
| Layout Header + Footer | ✅ | Navigation responsive, menu adaptatif selon rôle, **badge rôle + bouton Logout** |
| AuthContext + intercepteur JWT | ✅ | Login/logout, persistance localStorage, redirection 401 |
| **Système de toasts (notifications)** | ✅ | `react-hot-toast` stylisé charte LoL, branché sur 7 mutations clés |
| Page Accueil | ✅ | Hero + stats + "Comment ça marche" |
| Login / Register | ✅ | Formulaires react-hook-form avec validation + toasts |
| Liste joueurs (publique) | ✅ | Filtres par rôle + disponibilité, cartes responsives |
| Liste offres (publique) | ✅ | Filtres par rôle, cartes responsives |
| Profil joueur (publique) | ✅ | Stats + champions affichés avec **noms réels** |
| Détail offre + Postuler | ✅ | Formulaire candidature avec toast confirmation |
| Mes candidatures (joueur) | ✅ | Liste avec badge de statut |
| Candidatures reçues (club) | ✅ | Liste avec messages + boutons Accepter/Refuser |
| Dashboards joueur / club | ✅ | Vue d'ensemble + accès rapides |
| **Page Lier compte Riot** | ✅ | Formulaire inline (Riot ID + région), 11 régions supportées |
| **Page Créer une offre (club)** | ✅ | Formulaire avec rôle, rang min, date expiration |
| **Page Modifier mon profil joueur** | ✅ | Pré-rempli, validation, redirect dashboard |
| Mobile First | ✅ | Breakpoints 375/768/1024 |

### Documentation

| Document | Statut |
|---|---|
| Dossier de conception Jalon 4 | ✅ Livré 30/04 |
| README backend | ✅ Mis à jour 31/05 (avec section Swagger) |
| README frontend | ✅ Mis à jour 31/05 |
| Analyse de sécurité (`docs/SECURITY.md`) | ✅ Livré 29/05 |
| Politique de tests (`docs/TESTS.md`) | ✅ Livré 29/05 |
| Bilan d'avancement (ce doc) | ✅ Livré 31/05 |
| **Documentation API interactive (Swagger)** | ✅ Auto-générée sur `/api/doc` |

---

## 3. Ce qui RESTE pour Juin (Jalon 6)

### 🔴 Priorité haute

| Item | Estim. |
|---|---|
| **Tests automatisés PHPUnit ≥ 70 % couverture** | 8-10h |
| **Déploiement production** (VPS + Docker + HTTPS) | 4-6h |
| Tag `v1.0` final + soutenance préparée | 2h |

### 🟠 Améliorations sécurité Jalon 6

- Rate-limiting sur `/api/login_check` (bundle déjà installé)
- Voters Symfony (refactoring des checks ownership inline)
- En-têtes HTTP : `X-Frame-Options`, `X-Content-Type-Options`, CSP
- Audit composer (`composer audit`)

### 🟢 Nice to have

- Page profil club détaillé
- Page "Mes offres" (club) avec modifier/désactiver
- Recherche avancée par rang (filtre minimum)
- Comparaison joueurs
- Tests de charge JMeter

---

## 4. Risques identifiés & plan de mitigation

| Risque | Probabilité | Impact | Mitigation |
|---|---|---|---|
| Tests pas terminés en juin | Moyenne | Élevé | Plan détaillé dans `TESTS.md`, démarrer dès le 1er juin |
| Dev key Riot expirée le jour de la démo | Élevée (24h) | Faible | Régénérer juste avant + clé Personal Riot demandée |
| Bug en démo | Moyenne | Moyen | Comptes démo prêts + scénarios répétés + Swagger pour tester chaque endpoint |
| Déploiement prod tardif | Moyenne | Élevé | Cible mi-juin, fallback démo en local |

---

## 5. Métriques projet (au 31/05/2026)

| Métrique | Valeur |
|---|---|
| Issues GitHub fermées (back + front) | 28+ |
| Pull Requests mergées (back + front) | 22+ |
| Commits cumulés | ~80 |
| Lignes de code (back, hors vendor) | ~4 000 |
| Lignes de code (front, hors node_modules) | ~4 200 |
| Endpoints REST documentés (Swagger) | 18 |
| Entités Doctrine | 10 |
| Pages React | 14 |
| Mutations React Query avec feedback toast | 7 |

---

## 6. Auto-évaluation

### Ce qui s'est bien passé
- **Conception solide** au Jalon 4 → développement fluide en mai (pas de refonte BDD/API)
- **Workflow Git rigoureux** : 1 feature = 1 issue + 1 branche + 1 PR
- **Setup Docker** dès le début → portable, fiable sur les 3 machines utilisées (laptop cassé + 2 PC empruntés)
- **Intégration API Riot live** maîtrisée techniquement (cache, rate-limiter, gestion erreurs, mapping champions via Data Dragon)
- **Sprint final 31/05** : 5 features ajoutées en 1 journée (Swagger, toasts, Header polish, créer offre, modifier profil)

### Ce qui aurait pu être mieux
- **Tests automatisés** non commencés en mai → dette technique pour juin
- **Pas encore de déploiement production** → tout en local pour l'instant

### Leçon apprise
Avoir une **conception architecturale complète au Jalon 4** (UML + MERISE) a permis de développer rapidement et sans refactoring majeur en mai. Cet investissement de conception s'est largement amorti — l'intégration de l'API Riot live et la sérialisation imbriquée n'auraient pas été possibles en sprint final sans cette base.

---

## 7. Engagement Jalon 6

D'ici le 30/06/2026, je m'engage à livrer :

1. ✅ Application déployée et accessible publiquement (HTTPS)
2. ✅ Tests automatisés PHPUnit ≥ 70 % de couverture
3. ✅ Tag `v1.0` sur le commit final
4. ✅ Présentation de soutenance préparée

---

**Nassim Djemai — 31 mai 2026**
