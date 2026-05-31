# Bilan d'avancement — Jalon 5 (29/05/2026)

> Document remis dans le cadre du Jalon 5 du projet fil-rouge CDA — LoL Scout.

---

## 1. Vue d'ensemble

| Jalon | Échéance | Statut |
|---|---|---|
| Jalon 1 — CDCF | 31/01/2026 | ✅ Livré |
| Jalon 2 — Méthodo + UI/UX | 28/02/2026 | ✅ Livré |
| Jalon 3 — MERISE BDD | 31/03/2026 | ✅ Livré |
| Jalon 4 — UML + Architecture | 30/04/2026 | ✅ Livré |
| **Jalon 5 — Dev + Sécurité + Tests** | **29/05/2026** | **🟡 En cours de livraison (ce document)** |
| Jalon 6 — Déploiement + Soutenance | 30/06/2026 | À venir |

**Statut global** : projet **en avance** sur certains aspects (architecture, conception, intégration Riot), **en retard** sur les tests automatisés, **conforme** sur le périmètre fonctionnel MVP.

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
| **Intégration Riot Games** | ✅ | RiotSyncService complet (link via Riot ID + sync stats + cache 1h + rate limit) |
| Fixtures de démo | ✅ | 1 admin + 6 joueurs + 3 clubs + 5 offres + 5 candidatures + 18 champions |
| Docker Compose | ✅ | PHP-FPM 8.2 + Nginx + MySQL 8 |
| Migrations versionnées | ✅ | 3 migrations passent sur BDD vierge |
| Access control par méthode | ✅ | Listings GET publics, écriture protégée |
| CORS strict | ✅ | nelmio/cors-bundle configuré |

### Frontend (React 19 + TypeScript + Vite)

| Module | Statut | Détail |
|---|---|---|
| Setup projet | ✅ | Vite + TypeScript + React Router v7 + React Query + Axios |
| Charte graphique LoL | ✅ | Design tokens CSS (couleurs `#0A1428` / `#C89B3C` / `#0AC8B9` + polices Cinzel/Outfit/JetBrains Mono) |
| Layout Header + Footer | ✅ | Navigation responsive, menu adaptatif selon rôle |
| AuthContext + intercepteur JWT | ✅ | Login/logout, persistance localStorage, redirection 401 |
| Page Accueil | ✅ | Hero + stats + "Comment ça marche" |
| Login / Register | ✅ | Formulaires react-hook-form avec validation |
| Liste joueurs (publique) | ✅ | Filtres par rôle + disponibilité, cartes responsives |
| Liste offres (publique) | ✅ | Filtres par rôle, cartes responsives |
| Profil joueur (publique) | ✅ | Stats + champions affichés |
| Détail offre | ✅ | Affichage complet |
| Postuler à une offre | ✅ | Formulaire avec validation, states loading/error/success |
| Mes candidatures (joueur) | ✅ | Liste avec badge de statut |
| Candidatures reçues (club) | ✅ | Liste avec messages + boutons Accepter/Refuser |
| Dashboards joueur / club | ✅ | Vue d'ensemble + accès rapides |
| Mobile First | ✅ | Breakpoints 375/768/1024 |

### Documentation

| Document | Statut |
|---|---|
| Dossier de conception Jalon 4 | ✅ Livré 30/04 |
| README backend | ✅ Livré 29/05 |
| README frontend | ✅ Livré 29/05 |
| Analyse de sécurité (`docs/SECURITY.md`) | ✅ Livré 29/05 |
| Politique de tests (`docs/TESTS.md`) | ✅ Livré 29/05 |
| Bilan d'avancement (ce doc) | ✅ Livré 29/05 |

---

## 3. Ce qui RESTE à finaliser en Juin (Jalon 6)

### 🔴 Bloquants pour le rendu final

| Item | Priorité | Estim. |
|---|---|---|
| **Tests automatisés PHPUnit ≥ 70 % couverture** | Très haute | 8-10h |
| **Sérialisation imbriquée Player → Stats** (afficher les rangs partout) | Haute | 1h |
| **Test live de la sync Riot** (avec dev key réelle) | Haute | 1h |
| **Déploiement production** (VPS + Docker + HTTPS) | Haute | 4-6h |

### 🟠 Fonctionnalités MVP encore en placeholder

| Page front | Priorité | Estim. |
|---|---|---|
| Créer une offre (club) | Moyenne | 1h |
| Mes offres + modifier/désactiver (club) | Moyenne | 2h |
| Modifier mon profil joueur | Moyenne | 1h |
| Lier compte Riot (formulaire) | Moyenne | 1h |
| Bouton synchroniser stats Riot (UI) | Moyenne | 30 min |

### 🟡 Améliorations sécurité Jalon 6

- Brute force : rate-limiting sur `/api/login_check` (`symfony/rate-limiter` déjà installé)
- Voters Symfony (refactoring des checks ownership inline)
- En-têtes HTTP : `X-Frame-Options`, `X-Content-Type-Options`, CSP
- Audit composer (`composer audit`)

### 🟢 Nice to have

- Recherche avancée par rang (filtre minimum)
- Page profil club
- Page comparaison joueurs
- Tests de charge JMeter

---

## 4. Risques identifiés & plan de mitigation

| Risque | Probabilité | Impact | Mitigation |
|---|---|---|---|
| Tests pas terminés en juin | Moyenne | Élevé | Plan détaillé dans `TESTS.md`, démarrer dès le 1er juin |
| Dev key Riot expirée le jour de la démo | Élevée (24h) | Faible | Régénérer juste avant + clé Personal Riot demandée |
| Bug en démo | Moyenne | Moyen | Comptes démo prêts + scénarios répétés |
| Déploiement prod tardif | Moyenne | Élevé | Cible mi-juin, fallback démo en local |

---

## 5. Métriques projet

| Métrique | Valeur |
|---|---|
| Issues GitHub fermées | 14 |
| Pull Requests mergées | 11+ |
| Commits cumulés | ~50 |
| Lignes de code (back, hors vendor) | ~3 500 |
| Lignes de code (front, hors node_modules) | ~3 200 |
| Endpoints REST documentés | 20 |
| Entités Doctrine | 10 |
| Pages React | 11 |

---

## 6. Auto-évaluation

### Ce qui s'est bien passé
- **Conception solide** au Jalon 4 → développement fluide en mai (pas de refonte BDD/API)
- **Workflow Git rigoureux** : 1 feature = 1 issue + 1 branche + 1 PR
- **Setup Docker** dès le début → portable, fiable sur les 3 machines utilisées (laptop cassé + 2 PC empruntés)
- **Intégration API Riot** maîtrisée techniquement (cache, rate-limiter, gestion erreurs)

### Ce qui aurait pu être mieux
- **Tests automatisés** non commencés en mai → dette technique pour juin
- **Frontend en avance temporellement** mais 5 pages restent en placeholder
- **Pas encore de déploiement production** → tout en local pour l'instant

### Leçon apprise
Avoir une **conception architecturale complète au Jalon 4** (UML + MERISE) a permis de développer rapidement et sans refactoring majeur en mai. Cet investissement de conception s'est largement amorti.

---

## 7. Engagement Jalon 6

D'ici le 30/06/2026, je m'engage à livrer :

1. ✅ Application déployée et accessible publiquement (HTTPS)
2. ✅ Tests automatisés PHPUnit ≥ 70 % de couverture
3. ✅ Toutes les pages front en placeholder finalisées
4. ✅ Tag `v1.0` sur le commit final
5. ✅ Présentation de soutenance préparée

---

**Nassim Djemai — 29 mai 2026**