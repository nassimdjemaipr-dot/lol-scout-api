# Jeu d'essai — collection Postman

Jeu d'essai du dossier projet CDA, sur la fonctionnalité la plus représentative de
l'application : **la candidature d'un joueur à une offre**.

Elle a été retenue parce qu'elle traverse en un seul parcours les quatre mécanismes que le
reste du projet met en œuvre séparément :

| Mécanisme | Où il intervient |
|---|---|
| Authentification par jeton | étapes 6, 8 |
| Validation des entrées | étapes 4, 9, 17 |
| Contrainte d'unicité en base | étape 11 |
| Contrôle d'autorisation par propriété | étape 15 |

**17 requêtes, 34 assertions.**

---

## Prérequis

```bash
docker compose up -d
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

L'API doit répondre sur `http://localhost:8000`.

## Exécution dans Postman

1. *Import* → glisser `LoL-Scout-Jeu-d-essai.postman_collection.json`.
2. Ouvrir le **Collection Runner** et lancer la collection **entière**.

> ⚠️ **L'ordre compte.** Chaque étape prépare la suivante : le jeton du joueur, l'identifiant
> de l'offre et celui de la candidature circulent par des variables de collection. Lancer
> une requête isolée échouera.

Les comptes proviennent du jeu de données de démonstration — `club1@lolscout.gg`,
`club2@lolscout.gg`, mot de passe `LolScout2026!`. Le joueur, lui, est **créé à chaque
exécution** avec une adresse horodatée : la collection est donc rejouable sans remise à zéro
de la base.

## Exécution en ligne de commande

```bash
npm install -g newman
newman run postman/LoL-Scout-Jeu-d-essai.postman_collection.json
```

## Résultats attendus

| # | Étape | Attendu |
|---:|---|---|
| 1 | Connexion du club propriétaire | 200 + jeton |
| 2 | Profil du club | 200 |
| 3 | Offre active de ce club | 200 |
| 4 | Inscription, mot de passe trop court | **422** `plainPassword` |
| 5 | Inscription valide | 201, aucune empreinte retournée |
| 6 | Connexion du joueur | 200 + jeton |
| 7 | Création du profil joueur | 201 |
| 8 | Candidature sans jeton | **401** |
| 9 | Message de moins de 10 caractères | **422** `message` |
| 10 | Candidature valide | **201**, statut `EN_ATTENTE` |
| 11 | Candidature en double | **409** |
| 12 | Le joueur consulte ses candidatures | 200, la candidature est présente |
| 13 | Le club reçoit la candidature | 200, la candidature est présente |
| 14 | Connexion d'un autre club | 200 + jeton |
| 15 | Un autre club tente de trancher | **403** |
| 16 | Le club propriétaire accepte | 200, statut `ACCEPTEE` |
| 17 | Statut inconnu | **400** + valeurs admises |

Les étapes intéressantes sont **4, 8, 9, 11, 15 et 17** : ce sont les cas d'erreur, et ce
sont eux qui démontrent que les règles tiennent. Un jeu d'essai qui ne contiendrait que le
cas nominal ne prouverait rien.

L'analyse des écarts entre attendu et obtenu figure au **chapitre 12 du dossier projet**.
