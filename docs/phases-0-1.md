# Phases 0 et 1 — comptes, traçabilité, identité des personnes

Spécification d'implémentation. Suppose lu `conception-bdd.md`, qui porte les raisons.

---

# Phase 0 — Comptes et traçabilité

Objectif : plusieurs curateurs peuvent se connecter, chaque écriture est attribuée, et deux
curateurs ne peuvent plus s'écraser l'un l'autre en silence.

## 0.1 Entité `User`

| Champ | Type | Note |
|---|---|---|
| `id` | int | |
| `email` | string(180) unique | identifiant de connexion |
| `roles` | json | `ROLE_ADMIN`, éventuellement `ROLE_REVIEWER` |
| `password` | string | haché, jamais en clair |
| `displayName` | string(100) | affiché dans les attributions |
| `active` | bool | désactivation sans suppression |
| `createdAt` | datetime_immutable | |

**Un curateur ne se supprime jamais.** Ses attributions (`created_by`) pointent vers lui ; le
supprimer casserait la traçabilité ou forcerait des `ON DELETE SET NULL` qui effacent
l'information. On bascule `active = false` : il ne peut plus se connecter, son nom reste
attaché à ce qu'il a saisi.

Pas d'inscription libre. Création par commande console `app:curator:create`, réservée à
l'exploitant.

## 0.2 Traçabilité — trait `Curated`

Un trait PHP plutôt qu'une `MappedSuperclass` : les entités n'héritent de rien aujourd'hui, et
un trait se compose sans contraindre la hiérarchie.

```php
trait Curated
{
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $updatedAt = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private User $createdBy;
    #[ORM\ManyToOne] private ?User $updatedBy = null;
    #[ORM\Version] #[ORM\Column] private int $version = 1;
}
```

Appliqué à : `Person`, `PersonName`, `Hadith`, `HadithParticipant`, `Transmission` — et aux
entités des phases suivantes.

### Le piège du remplissage automatique

Remplir `updatedBy` depuis un callback `#[ORM\PreUpdate]` **ne fonctionne pas** de façon
fiable : dans `preUpdate`, Doctrine a déjà calculé le changeset, et une modification de champ
qui n'y figure pas est ignorée.

Il faut un `EventSubscriber` sur `onFlush` qui, pour chaque entité programmée en insertion ou
en mise à jour, renseigne les champs puis appelle
`recomputeSingleEntityChangeSet()` sur l'`UnitOfWork`. L'auteur vient de
`Security::getUser()`.

Alternative acceptable : `stof/doctrine-extensions` (Timestampable + Blameable), au prix d'une
dépendance supplémentaire. À trancher — le projet a jusqu'ici peu de dépendances.

## 0.3 Verrou optimiste — le détail qui décide de son efficacité

`#[ORM\Version]` protège nativement contre deux écritures concurrentes **dans une même
requête**. Or le conflit réel se produit entre deux requêtes HTTP : un curateur charge un
formulaire, part déjeuner, enregistre — pendant ce temps un autre a modifié la fiche.

Pour couvrir ce cas, la version doit **faire l'aller-retour par le formulaire** :

1. Le formulaire d'édition porte la version lue en champ caché.
2. Au traitement, avant de modifier :
   `$em->lock($entity, LockMode::OPTIMISTIC, $versionSoumise);`
3. Doctrine lève une `OptimisticLockException` si la ligne a bougé.
4. L'écran affiche « cette fiche a été modifiée entre-temps » et propose de recharger, plutôt
   que d'écraser.

Sans cet aller-retour, la colonne `version` existe mais ne protège rien d'utile.

## 0.4 Sécurité

- Un pare-feu sur `/admin`, `form_login`, hachage automatique.
- `IS_AUTHENTICATED_FULLY` + `ROLE_ADMIN` requis sur tout `/admin`.
- Le site public reste anonyme et ne voit que les riwāyāt publiées.

## 0.5 Livrables et tests

| Livrable | |
|---|---|
| `src/Entity/User.php`, `src/Repository/UserRepository.php` | |
| `src/Entity/Trait/Curated.php` | |
| `src/EventListener/CuratedEntitySubscriber.php` | remplissage attribution |
| `src/Command/CreateCuratorCommand.php` | `app:curator:create` |
| `config/packages/security.yaml` | pare-feu `/admin` |
| Migration Postgres | avec le garde de plateforme habituel |

Tests :

- `/admin` anonyme → redirection vers la connexion.
- Un curateur actif se connecte ; un curateur `active = false` ne peut pas.
- À la création d'une entité, `createdBy` et `createdAt` sont renseignés sans intervention.
- **Conflit d'édition** : charger une entité, la modifier ailleurs, soumettre l'ancienne
  version → `OptimisticLockException`. C'est le test qui prouve que le §0.3 fonctionne.

---

# Phase 1 — Identité des personnes

Objectif : un rapporteur porte plusieurs noms, se retrouve par n'importe lequel, et les
doublons se détectent puis se fusionnent sans perte.

## 1.1 `Person` révisée

Retirer `name` et `name_ar` — les libellés descendent tous dans `PersonName`.

| Champ | Type | Note |
|---|---|---|
| `slug` | string(64) unique | identifiant d'URL, stable |
| `birthAhMin` / `birthAhMax` | int nullable | fourchette |
| `deathAhMin` / `deathAhMax` | int nullable | fourchette |
| `region` | string nullable | |
| `role` | string nullable | ex. « Qâdî », « Imam de Médine » |
| `isMudallis` | bool | |
| `tadlisType` / `tadlisRank` | nullable | type et rang Ibn Ḥajar (1 à 5) |
| `tabaqa` | FK nullable | génération |
| `notes` | text nullable | |

**Les dates sont des fourchettes, jamais un entier unique.** `min = max` signifie une date
certaine ; les deux nuls, une date inconnue. Une méthode `getDeathLabel()` rend
« d. 143 AH » ou « d. 141–150 AH » sans que la vue ait à connaître la représentation.

`Person::getDisplayName(string $script)` lit la forme marquée `isDisplay` — c'est ce qui
remplace l'ancien accès direct à `name`.

## 1.2 `PersonName`

| Champ | Type | Note |
|---|---|---|
| `person` | FK | |
| `form` | string(255) | la forme telle qu'elle est citée |
| `formNormalised` | string(255) | **calculée**, jamais saisie ; indexée |
| `script` | enum `ar` \| `latin` | |
| `kind` | enum `ism`, `kunya`, `nasab`, `nisba`, `laqab`, `shuhra`, `complet` | |
| `isDisplay` | bool | une seule par personne **et par script** |
| `source` | string nullable | d'où vient cette forme |

Contraintes :

- unique `(person_id, form, script)` — pas deux fois la même forme sur une personne ;
- index sur `form_normalised` — c'est lui qui porte la recherche ;
- unicité partielle de `is_display` par `(person_id, script)`. PostgreSQL sait le faire par
  index partiel (`WHERE is_display`) ; SQLite non. **Le faire respecter côté application**
  (validateur + service), pour que dev et prod se comportent pareil.

## 1.3 Normalisation — le service qui fait fonctionner la recherche

`PersonNameNormaliser`, fonction pure, sans dépendance : le meilleur candidat aux tests
unitaires du projet.

**Arabe**
- retirer le tashkīl : U+064B–U+0652, U+0670 ;
- retirer le tatwīl U+0640 (ـ) ;
- أ إ آ ٱ → ا ; ة → ه ; ى → ي ; ؤ → و ; ئ → ي ;
- réduire les espaces multiples.

**Latin**
- décomposer en NFD, retirer les marques combinantes (â → a) ;
- supprimer ʿ ʾ ' ` ;
- minuscules, espaces réduits.

Sans cela, « يحيى » et « يحيي » sont deux chaînes distinctes, et « Yahyâ » ne trouve pas
« Yahya ». C'est-à-dire que le curateur ne retrouve pas ce qu'il a saisi la semaine
précédente, et crée un doublon.

### Recherche : rester en `LIKE`, ne pas prendre `pg_trgm`

`pg_trgm` serait le bon outil pour de la recherche floue — contrairement à `ltree` ou Apache
AGE, écartés pour le graphe, il répond ici à un vrai besoin.

**Mais dev et test tournent sur SQLite** (`config/packages/{dev,test}/doctrine.yaml`, choix
délibéré et documenté « sans dépendance à un serveur Postgres/docker »). Un index `pg_trgm`
n'existerait qu'en production : la recherche ne serait ni testable, ni reproductible en
développement.

Décision : **`LIKE` sur `form_normalised`**, qui fonctionne partout. Sur un corpus saisi à la
main, quelques milliers de noms au plus, c'est largement suffisant. Revoir seulement si le
volume l'impose — et alors la vraie question sera de basculer dev et test sur Postgres, pas
d'ajouter un index invisible en test.

## 1.4 Recherche de personne

`PersonRepository::search(string $terme, int $limite = 10): array`

Normalise le terme, cherche en `LIKE '%…%'` sur `form_normalised`, regroupe par personne, et
renvoie pour chacune : la forme d'affichage, **la forme qui a produit la correspondance**, et
le contexte discriminant — dates, génération, région.

Ce contexte n'est pas décoratif : c'est ce qui permet de distinguer Sufyân ibn ʿUyayna de
Sufyân al-Thawrî dans la liste déroulante. Sans lui, le curateur choisit au hasard.

Test emblématique : **chercher « Abū Ḥanīfa » et « al-Nuʿmān ibn Thābit » doit renvoyer la même
personne.** Deux formes sans aucun caractère commun.

## 1.5 Fusion — `PersonMerger`

Signature : `merge(Person $absorbée, Person $conservée, User $auteur): void`, dans une
transaction unique.

1. Transférer les `PersonName` de l'absorbée vers la conservée, en écartant les formes déjà
   présentes (même `form` + `script`).
2. Repointer `HadithParticipant.person`.
3. Repointer `Transmission.from` et `Transmission.to`.
4. Repointer `Hadith.pivot`.
5. Supprimer l'absorbée.
6. Écrire le journal.

### Le piège : la fusion crée des doublons d'arêtes

Si `A → C` et `B → C` existent tous deux et qu'on fusionne `B` dans `A`, le repointage produit
deux fois `A → C` — violation de la contrainte d'unicité de `Transmission`.

Il faut donc, avant de repointer, **détecter les collisions et fusionner les arêtes** :
conserver une seule ligne, en propageant `spine = true` si l'une des deux le portait. Même
raisonnement pour `HadithParticipant` : deux participations de A et B au même hadith
deviennent une seule, et il faut décider quel `level` conserver (le plus faible, en signalant
la divergence au curateur).

C'est la partie la plus délicate de la phase. Elle mérite ses propres tests.

### Journal `PersonMergeLog`

`absorbedId`, `absorbedLabel` (forme d'affichage, la fiche n'existant plus), `keptId`,
`transferredNames` (json), `mergedBy`, `mergedAt`.

Permet de défaire une fusion erronée et de savoir à qui en parler.

## 1.6 Détection de doublons

`PersonDuplicateFinder`, deux signaux en phase 1 :

- **fort** — deux personnes partageant une `form_normalised` identique ;
- **faible** — fourchettes de décès qui se recoupent *et* formes proches.

Livrer le signal fort seulement ; le flou viendra si le besoin se confirme. Un écran
`/admin/personnes/doublons` liste les candidats et propose la fusion.

## 1.7 Migration des données existantes

52 personnes portent aujourd'hui `name` + `name_ar`. La migration doit :

1. créer deux `PersonName` par personne — `latin`/`isDisplay` et `ar`/`isDisplay`, `kind` =
   `complet` ;
2. remplir `form_normalised` via le normaliseur ;
3. convertir les dates : aucune n'est structurée aujourd'hui, `meta` reste en `notes` ;
4. supprimer `name` et `name_ar`.

**Refactorisation induite, à ne pas sous-estimer** — trois points lisent `Person::getName()` :

- `src/Domain/IsnadGraph.php` (champs `name` et `ar` du payload, et `shortName()`) ;
- `templates/search/index.html.twig` (pastilles de l'épine) ;
- `assets/controllers/network_controller.js` indirectement, via le payload.

Le test de fidélité `IsnadGraphControllerTest` compare la sortie de l'API au jeu de données
versionné : **il détectera toute régression** sur ce point. C'est exactement le filet pour
lequel il a été écrit.

## 1.8 Écrans d'administration

| Écran | |
|---|---|
| `/admin/personnes` | liste + recherche |
| `/admin/personnes/nouveau` | création, **affichant les correspondances proches avant validation** |
| `/admin/personnes/{slug}` | édition, gestion des noms en collection imbriquée |
| `/admin/personnes/fusion` | choix des deux fiches, aperçu de l'impact, confirmation |
| `/admin/personnes/doublons` | revue des candidats |

L'aperçu d'impact avant fusion (« 3 chaînes, 12 participations seront repointées ») est ce qui
rend l'opération sûre.

## 1.9 Tests

Unitaires :

- normaliseur — cas arabes (tashkīl, hamzas, ة, ى) et latins (diacritiques, ʿ) ;
- fusion — noms transférés, dédoublonnage des arêtes en collision, journal écrit.

Fonctionnels :

- recherche par kunya **et** par ism → même personne ;
- création d'une personne dont un nom existe déjà → avertissement affiché ;
- fusion depuis l'écran → chaînes repointées, aucune arête en double.

Non-régression : `IsnadGraphControllerTest` doit rester vert après la bascule vers
`PersonName`, sans modification de `data/isnad/wireframe.json`.

---

## Ordonnancement

La phase 0 précède la phase 1 : le trait `Curated` référence `User`, et `PersonMergeLog` a
besoin d'un auteur.

Dans la phase 1, l'ordre le moins risqué est : normaliseur (isolé, testable seul) → entité
`PersonName` + migration de données → recherche → écrans → fusion → détection de doublons. La
fusion arrive tard car elle suppose les écrans pour être utilisable, et c'est l'opération la
plus destructrice.
