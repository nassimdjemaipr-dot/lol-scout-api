# Analyse de sécurité — LoL Scout (Jalon 5)

> Document remis dans le cadre du chapitre **IX. Sécurité** du dossier projet.
> État au **29/05/2026** (rendu Jalon 5).

Ce document détaille les mesures de sécurité mises en place dans le backend Symfony et analyse les risques OWASP applicables au projet.

---

## 1. Injection SQL

**Risque** : un attaquant insère du SQL dans un paramètre utilisateur pour exécuter des requêtes non prévues (vol de données, élévation de privilèges).

**Mesures en place** :

- 100 % des accès à la base passent par **Doctrine ORM** (entités + repositories). Aucune requête SQL brute n'est concaténée avec des entrées utilisateur.
- Les recherches avec critères dynamiques utilisent `findBy()` ou `QueryBuilder` avec `setParameter()` — les paramètres sont systématiquement échappés par Doctrine.

**Exemple** (`ApplicationRepository::findByPlayer`) :

```php
return $this->createQueryBuilder('a')
    ->andWhere('a.player = :player')
    ->setParameter('player', $player)   // ← paramètre bindé, jamais concaténé
    ->orderBy('a.appliedAt', 'DESC')
    ->getQuery()
    ->getResult();
```

**Vérification** : tentative manuelle d'injection via une URL `/api/players/search?role=MID'+OR+1=1--` → l'enum `PlayerRole::tryFrom()` rejette la valeur invalide avec un HTTP 400.

---

## 2. XSS (Cross-Site Scripting)

**Risque** : l'API renvoie du contenu utilisateur (bio, message de candidature) qui pourrait contenir du JavaScript exécuté par le navigateur.

**Mesures en place** :

- L'API renvoie **uniquement du JSON** (`application/json`). Le contenu n'est jamais interprété comme du HTML côté serveur.
- Côté frontend (React), le rendu se fait via JSX. React **échappe par défaut** toutes les valeurs interpolées (`{variable}`), ce qui empêche l'exécution de scripts injectés.
- Le seul cas où XSS serait possible côté front serait `dangerouslySetInnerHTML` — **non utilisé** dans le projet.

**Validation des entrées utilisateur** :

- Tous les champs texte (bio, message de candidature, description d'offre) ont des **contraintes Symfony Validator** (`#[Assert\Length]`) qui bornent la longueur.
- La bio joueur est limitée à 2000 caractères, le message de candidature entre 10 et 2000.

---

## 3. CSRF (Cross-Site Request Forgery)

**Risque** : un site malveillant fait exécuter une requête authentifiée à l'insu de l'utilisateur (transfert d'argent, modification de profil).

**Contexte** : LoL Scout est une **API REST stateless** (pas de session, pas de cookie). L'authentification utilise des **JWT envoyés dans le header `Authorization`**.

**Pourquoi CSRF n'est pas un risque ici** :

- Un site malveillant ne peut **pas lire le `localStorage`** d'un autre domaine (Same-Origin Policy).
- Sans token JWT, l'API renvoie 401 → l'attaquant ne peut pas forger une requête authentifiée.
- Les requêtes cross-origin sont contrôlées par le **header CORS** : seules les origines listées peuvent appeler l'API.

**Mesures complémentaires** :

- **CORS strict** configuré via `nelmio/cors-bundle` : seul `http://localhost:5173` (et la prod future) est autorisé en `CORS_ALLOW_ORIGIN`.
- Pas de mécanisme `credentials: include` qui exposerait les cookies.

---

## 4. Gestion des comptes & mots de passe

**Hachage** :

- Les mots de passe sont hashés en **bcrypt** via `UserPasswordHasherInterface` de Symfony (algorithme `auto`, factor de coût par défaut adapté à 2026).
- Le hash est stocké en VARCHAR(255) dans la colonne `user.password`.
- Le mot de passe en clair n'est **jamais** stocké, loggé ou renvoyé par l'API (aucun groupe de sérialisation `*:read` n'expose le champ `password`).

**Génération du JWT** :

- Algorithme **RS256** (asymétrique). Clé privée RSA stockée dans `config/jwt/private.pem` (protégée par passphrase `JWT_PASSPHRASE`).
- La clé publique est utilisée pour vérifier le token côté serveur.
- Les clés sont **dans le `.gitignore`** — elles ne sont jamais committées.

**Validation à l'inscription** :

- Email **unique** (contrainte au niveau BDD + applicative).
- Mot de passe : minimum 8 caractères (validation front + à étendre côté back en Jalon 6).
- Rôle limité à un enum `UserRole` (`ROLE_PLAYER` / `ROLE_CLUB` / `ROLE_ADMIN`) — pas d'élévation possible côté client.

---

## 5. Protection contre le brute force

**État actuel (Jalon 5)** :

- ⚠️ Pas de rate-limiting sur `/api/login_check` dans cette version.
- Le composant `symfony/rate-limiter` est **déjà installé** (pour les appels Riot Games) et opérationnel.

**Plan d'action Jalon 6** :

- Ajouter un `LoginThrottlingListener` qui limite à **5 tentatives par minute par IP** + **10 tentatives par minute par compte cible**.
- Mise en place via le composant `symfony/rate-limiter` déjà présent dans `composer.json`.
- Configuration prévue dans `config/packages/security.yaml` (firewall option `login_throttling`).

---

## 6. Données personnelles & RGPD

**Données collectées** :

| Donnée | Caractère | Conservation |
|---|---|---|
| Email | Identifiant de connexion | Tant que le compte existe |
| Mot de passe | Hashé (bcrypt) | Idem |
| Pseudo, prénom, nom (joueurs) | Profil public | Idem |
| Riot ID + PUUID | Statistiques de jeu | Idem |
| Statistiques classées | Calculées via Riot API | Mises à jour à chaque sync |
| Message de candidature | Texte libre | Tant que la candidature existe |

**Droits utilisateur** :

- **Droit à la suppression** : la cascade `ON DELETE CASCADE` est configurée sur les relations (User → Player, RiotAccount, etc.). La suppression d'un compte supprime toutes les données dépendantes.
- **Droit à la rectification** : endpoints `PATCH /api/players/{id}` permettent à l'utilisateur de modifier ses informations.
- **Politique de confidentialité** : à rédiger pour la production (Jalon 6) — page statique côté front prévue.

**Mesures techniques** :

- Communications HTTPS prévues en production (HTTP uniquement en dev).
- Pas de données bancaires, pas de données de santé → catégories spéciales non concernées.
- Le projet ne fait pas de profilage automatisé ni de décision algorithmique au sens RGPD art. 22.

---

## 7. Contrôle d'accès & ownership

**3 rôles** définis dans `UserRole` :

- `ROLE_PLAYER` : peut consulter, modifier son profil joueur, lier son compte Riot, postuler à des offres
- `ROLE_CLUB` : peut publier des offres, modifier ses offres, traiter les candidatures
- `ROLE_ADMIN` : tous les droits

**Contrôles inline** dans chaque controller :

```php
// OfferController.php
if ($offer->getClub()->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
    return $this->json(['error' => 'You can only edit offers from your own club'], 403);
}
```

**Access control par méthode HTTP** dans `security.yaml` :

- `GET /api/players`, `/api/offers`, `/api/clubs` → **public** (cohérent avec le sitemap Jalon 2)
- `GET /api/players/me`, `/api/clubs/me/*` → authentifié
- `POST/PATCH/DELETE` sur ces ressources → authentifié + check ownership

**Plan d'action Jalon 6** : refactoriser les checks inline en **Voters Symfony** (`PlayerVoter`, `OfferVoter`, `ApplicationVoter`) pour centraliser la logique.

---

## 8. Validation des entrées

Toutes les entités utilisent les **attributs Symfony Validator** :

```php
#[ORM\Column(length: 200)]
#[Assert\NotBlank]
#[Assert\Length(min: 3, max: 200)]
private ?string $title = null;

#[ORM\Column(enumType: PlayerRole::class)]
#[Assert\NotNull]
private ?PlayerRole $wantedRole = null;
```

Chaque controller appelle `$this->validator->validate($entity)` avant `persist()`. Si erreurs → HTTP 422 avec la liste des violations.

---

## 9. En-têtes HTTP & autres

- **CORS** configuré strictement (origine, méthodes, headers)
- **JWT stateless** → pas de session cookie → moins de surface d'attaque
- L'application n'expose pas le profiler Symfony en production (`APP_ENV=prod`)

**Plan d'action Jalon 6** :

- Ajouter `X-Content-Type-Options: nosniff` via Nginx
- Ajouter `X-Frame-Options: DENY` (clickjacking)
- Ajouter `Content-Security-Policy` basique
- Configurer Nginx pour servir uniquement en HTTPS en production

---

## 10. Récap des vulnérabilités OWASP Top 10 (2025)

| Vulnérabilité OWASP | Statut |
|---|---|
| A01 - Broken Access Control | ✅ Checks ownership + access_control par méthode |
| A02 - Cryptographic Failures | ✅ Bcrypt + JWT RS256 + clés en `.gitignore` |
| A03 - Injection | ✅ Doctrine ORM, pas de SQL brut |
| A04 - Insecure Design | ✅ Architecture en couches, separation of concerns |
| A05 - Security Misconfiguration | ✅ CORS strict, mode debug désactivé en prod |
| A06 - Vulnerable Components | ⚠️ Audit composer prévu (`composer audit`) en Jalon 6 |
| A07 - Identification & Auth Failures | ⚠️ Brute force non protégé encore (Jalon 6) |
| A08 - Software & Data Integrity | ✅ Composer lock, contrainte UNIQUE sur application |
| A09 - Logging & Monitoring | ⚠️ Symfony logger en place mais pas centralisé (Jalon 6) |
| A10 - Server-Side Request Forgery | ✅ Pas d'endpoint qui fetch une URL utilisateur arbitraire |

---

## Conclusion

Le backend respecte les bonnes pratiques de sécurité Symfony pour un MVP étudiant : authentification forte (JWT + bcrypt), validation systématique, contrôle d'accès par rôle et ownership, pas d'injection SQL, CORS strict.

Les **points en suspens** (brute force, en-têtes HTTP, voters, audit composer) sont identifiés et planifiés pour le Jalon 6 (juin).