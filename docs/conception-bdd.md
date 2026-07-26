# Plan de conception — base de données des hadiths et des chaînes de transmission

Document de conception. Il décrit un modèle cible, les raisons de chaque choix, et un
phasage. Il ne décrit pas l'état actuel du code, sauf pour montrer où il casse.

---

## 1. Ce qui casse dans le modèle actuel

Le modèle actuel (`Period`, `Person`, `Hadith`, `HadithParticipant`, `Transmission`) a été
dérivé du wireframe. Il rend correctement une vue de graphe, mais il ne peut pas porter le
domaine. Voici les ruptures, avec leurs preuves.

### 1.1 Le matn est attaché au mauvais objet — rupture principale

En science du hadith, l'unité réelle n'est pas « le hadith » mais la **riwaya** : une
occurrence précise, c'est-à-dire *une chaîne + un texte + une citation dans un recueil*. Un
même enseignement circule par plusieurs voies (turuq), et **chaque voie a sa propre variante
de texte**.

C'est le fondement de l'analyse *isnad-cum-matn* : on corrèle les divergences de texte avec
les divergences de chaîne pour dater une tradition. Un schéma qui impose un seul matn par
hadith supprime purement et simplement l'objet d'étude.

Le modèle actuel a `Hadith.text_fr` NOT NULL et `Hadith.text_ar` — un texte unique par hadith.

**Preuve que la contrainte casse déjà, dans nos propres données :**

```
ukhuwwa   reference = "Sahîh al-Bukhârî, n°13 · Muslim, n°45"
ihsan     reference = "Sahîh Muslim, n°8 · Abû Dâwûd, n°4695"
```

`reference` est un `VARCHAR(255)` unique. Faute de pouvoir exprimer « ce hadith est dans
Bukhârî au n°13 *et* dans Muslim au n°45 », on a concaténé avec un séparateur. C'est une
liste déguisée en chaîne : non interrogeable, non joignable, non contraignable.

La base brute de référence confirme la forme réelle des données : `isnad_subset.db` contient
**75 lignes `hadith` qui sont des occurrences livre+numéro**, pas 75 matns distincts. Les
données sont déjà en forme de riwaya ; c'est notre schéma qui ne l'est pas.

### 1.2 L'arête est prisonnière du hadith

`Transmission.hadith_id` est `NOT NULL`, avec `UNIQUE (hadith_id, from_person_id, to_person_id)`.
Une transmission ne peut donc pas exister en dehors d'un hadith.

Deux conséquences mesurées :

- **Duplication.** Sur nos 4 hadiths seulement, 5 paires (maître, élève) se répètent.
  `Qutayba → Tirmidhî` existe en **3 lignes physiques** pour une seule transmission
  historique. Toute mesure de degré (« combien d'élèves distincts ? ») est faussée sans
  `DISTINCT` explicite.
- **Un graphe entier inexprimable.** La base de référence contient 352 relations, dont
  **213 de type `enseignement`** (maître→élève, hors de tout hadith précis) contre 139 de type
  `narration_hadith`. Soit **60 % du graphe de référence n'a aucune représentation possible**
  dans le modèle actuel.

### 1.3 La science des transmetteurs (ʿilm al-rijāl) est absente

`Person` n'a ni date de naissance, ni date de décès, ni grade de fiabilité.

- Les données réelles ont pourtant `death_ah` rempli à **74/91 (81 %)** et
  `reliability_grade` à **70/91 (77 %)**. On jette de l'information disponible.
- Sans date structurée, la génération ne peut pas être dérivée. Le script de référence le fait
  (`mort ≤150 AH → tâbiʿîn`, `≤220 → atbâʿ`, sinon collecteur) en qualifiant la date de décès
  de « signal le plus fiable du dataset ». Chez nous, la génération est saisie à la main.
- Le seul `grade` du modèle porte sur le *hadith*, pas sur la *personne* — alors que le
  jarḥ wa taʿdīl est un jugement sur le transmetteur.

**Piège à ne pas reproduire :** un narrateur ne reçoit pas *un* grade. Il reçoit des jugements
**multiples et parfois contradictoires** selon le critique (Ibn Ḥajar, al-Dhahabī, al-Nasāʾī…),
sur une échelle dont les termes n'ont pas le même sens d'un critique à l'autre. Il n'existe pas
de barème canonique. Un champ `reliability_grade` unique serait déjà une erreur de
modélisation, moins grave que l'absence actuelle, mais une erreur quand même.

### 1.4 La formule de transmission est perdue

Chaque maillon d'un isnad porte un verbe précis : `ḥaddathanā`, `akhbaranā`, `samiʿtu`,
`ʿan`, `anbaʾanā`, `ijāzatan`… Ce verbe encode le **mode de transmission** (samāʿ, qirāʾa,
ijāza, munāwala, kitāba, iʿlām, waṣiyya, wijāda) et une hiérarchie de fiabilité :
`samiʿtu`/`ḥaddathanā` > `akhbaranā` > `ʿan`.

`ʿan` est le plus faible car il n'affirme pas le contact direct — et il devient franchement
suspect si le narrateur est un **mudallis** (quelqu'un qui dissimule une rupture de chaîne).
Autrement dit : la fiabilité d'un maillon est fonction du couple *(formule, réputation du
narrateur)*.

Le modèle actuel ne stocke aucune formule. `Transmission` n'a que `spine`. C'est la perte
d'information la plus lourde après le matn — et c'est précisément ce que les corpus plats
existants (LK Hadith Corpus, Sanadset) perdent aussi en stockant l'isnad comme texte brut.

### 1.5 Le recueil n'est pas une entité

`Person.work`, `HadithParticipant.work` et `Hadith.reference` sont trois chaînes libres qui
décrivent la même réalité : un ouvrage. Aucune table, aucune clé étrangère, aucun compilateur
rattaché. Impossible de demander « tous les hadiths de Tirmidhî ».

### 1.6 Les noms ne permettent pas d'identifier une personne

Un nom arabe se compose d'*ism*, *kunya* (Abū…), *nasab* (chaîne des ibn/bint), *nisba*
(origine géographique, tribale, professionnelle) et *laqab*. Une même personne est citée
tantôt par sa kunya, tantôt par son ism — et les deux formes peuvent n'avoir **aucun
chevauchement lexical** : Abū Ḥanīfa et al-Nuʿmān ibn Thābit sont le même homme.

Le modèle actuel a `name` + `name_ar`, deux chaînes plates. La discipline elle-même a produit
un genre littéraire entier (*al-Mushtabih*) pour cataloguer les noms confondables.

**À savoir avant de sur-investir :** la désambiguïsation des narrateurs est un problème
ouvert, pas un manque d'ingénierie. Une étude de liaison entre bases biographiques arabes
n'atteint que 51 à 95 % de rapprochement selon le sens, et **47,7 % des noms de Sanadset
n'ont aucun candidat**, souvent des formes kunya-seule. Aucun identifiant unifié n'existe
entre les bases publiques.

### 1.7 Les classifications sont traitées comme des faits

`gharīb` / `ʿazīz` / `mashhūr` / `mutawātir` classent un hadith selon le **nombre de voies à
chaque génération**, le niveau le plus faible déterminant le classement global. Ce ne sont pas
des attributs saisis : ce sont des **propriétés dérivées du graphe**. Et elles sont
contestées — le seuil du mutawātir varie (3 ou 5) selon les auteurs.

Idem pour le **pivot** (مدار) : c'est une propriété relationnelle calculée sur l'ensemble des
turuq, et son identification relève d'un jugement d'école (Schacht, Juynboll et la tradition
ne s'accordent pas). Le modèle actuel le stocke comme un simple `pivot_id`.

### 1.8 Ce que le modèle ne peut pas dire du tout

- **Chaînes lacunaires** : *mursal* (Compagnon manquant), *munqaṭiʿ* (rupture ailleurs),
  *muʿallaq* (manquant dès le début), *muʿḍal* (deux maillons consécutifs). Une lacune doit
  être déclarée, pas déduite d'une chaîne courte.
- **Transmission collective** : « ḥaddathanā X wa Y qālā… » — un maillon à plusieurs sources.
  L'isnad n'est donc pas une liste ordonnée mais un DAG, y compris localement.
- **Dates incertaines** : les années hégiriennes sont souvent des fourchettes, et même des
  figures majeures n'ont pas de dates consensuelles. Un entier unique force une fausse
  précision.

---

## 2. Contrainte directrice : la base est saisie à la main

Le schéma sert une **application d'administration** : un administrateur ajoute des hadiths et
leurs chaînes de transmission, à la main, dans la durée. Ce n'est pas un pipeline d'import de
masse.

Cela tranche la question d'échelle et réoriente toute la conception :

- On optimise pour l'**ergonomie de saisie** et l'**intégrité**, pas pour la désambiguïsation
  automatique d'un corpus de 650 000 hadiths.
- Le risque principal n'est plus l'appariement algorithmique mais le **doublon créé par
  inadvertance**. Il se corrige par de l'outillage de saisie, pas par un algorithme.
- La provenance devient centrale : qui a saisi quoi, quand, d'après quelle source.

### 2.1 Le doublon de personne est l'ennemi principal

Un rapporteur porte plusieurs noms — c'est une exigence, pas un détail. Un même homme est cité
tantôt par sa *kunya*, tantôt par son *ism*, et les deux formes peuvent n'avoir aucun
caractère commun : Abū Ḥanīfa et al-Nuʿmān ibn Thābit. La discipline a produit un genre
littéraire entier (*al-Mushtabih*) pour cataloguer ces confusions.

En saisie manuelle, cela se manifeste très concrètement. L'administrateur tape « Sufyân ».
S'agit-il de Sufyân ibn ʿUyayna ou de Sufyân al-Thawrî ? Deux hommes distincts, même
génération, souvent les mêmes maîtres. Sans outillage, il créera tôt ou tard deux fiches pour
un seul homme — ou pire, fusionnera deux hommes dans une seule fiche.

Quatre garde-fous, tous portés par le schéma :

1. **La recherche porte sur toutes les formes du nom**, pas seulement sur la forme d'affichage.
2. **Le sélecteur montre le contexte discriminant** : dates, génération, région, maîtres déjà
   connus. On ne choisit jamais un homonyme sur son seul nom.
3. **Créer une personne est un acte explicite**, jamais un effet de bord de la saisie d'une
   chaîne, et il affiche les correspondances proches avant de valider.
4. **Une opération de fusion existe**, parce que des doublons passeront malgré tout.

### 2.2 Modèle d'identité

| Champ de `PersonName` | Rôle |
|---|---|
| `person_id` | la personne canonique |
| `form` | la forme telle qu'elle est citée |
| `form_normalised` | calculée, sert la recherche |
| `script` | `ar` ou `translit` |
| `kind` | `ism`, `kunya`, `nasab`, `nisba`, `laqab`, `shuhra`, `complet` |
| `is_display` | une seule forme d'affichage par personne et par script |
| `source` | d'où vient cette forme |

`Person` garde l'identité canonique et les faits (dates, région, tadlīs) ; **tous les libellés
descendent dans `PersonName`**. Une personne a donc 1..N noms, et la recherche indexe
`form_normalised` sur l'ensemble.

**La normalisation est ce qui fait fonctionner la recherche.** Sans elle, l'administrateur ne
retrouve pas ce qu'il a saisi la semaine précédente. Règles minimales :

- *Arabe* — retirer le tashkīl (ً ٌ ٍ َ ُ ِ ّ ْ) ; unifier les hamzas (أ إ آ → ا) ;
  ة → ه ; ى → ي ; retirer le tatwīl (ـ).
- *Translittération* — retirer les diacritiques (â → a, ʿ et ʾ supprimés), passer en
  minuscules.

Sans cette normalisation, « يحيى » et « يحيي » sont deux chaînes différentes, et
« Yahyâ » ne trouve pas « Yahya ».

### 2.3 Saisie d'une chaîne

L'unité de saisie est la **riwāya** : « ce hadith, tel qu'il figure dans Bukhārī n°1, avec
cette chaîne et ce texte ». C'est exactement le découpage du §3 — ce qui confirme que la
scission `Hadith` → `HadithCluster` + `Riwaya` n'est pas une abstraction théorique mais la
forme du geste de saisie.

Pour chaque maillon, l'administrateur choisit deux personnes et **la formule employée**
(`ḥaddathanā`, `akhbaranā`, `ʿan`…). Le système rattache une `Transmission` globale
existante ou la crée, puis y attache un `RiwayaLink` portant la formule et la position.

Deux cas que la saisie doit permettre dès le départ :

- **Transmission collective** — « ḥaddathanā X wa Y qālā… » : plusieurs maillons à la même
  position. La chaîne n'est donc pas une liste, même localement.
- **Lacune déclarée** — mursal, munqaṭiʿ, muʿallaq, muʿḍal : une absence se saisit
  explicitement (`RiwayaGap` à une position), elle ne se déduit pas d'une chaîne courte.

### 2.4 Contrôles de cohérence : avertir, ne pas bloquer

Le schéma permet des contrôles utiles à la saisie, à condition qu'ils restent des
**avertissements**. Les données historiques sont incertaines et les exceptions réelles :

- un élève mort avant son maître ;
- une inversion de génération (un Compagnon recevant d'un Successeur) ;
- une chaîne non connexe, ou un maillon orphelin ;
- une même forme de nom pointant vers deux personnes distinctes.

Bloquer sur ces règles rendrait la saisie impossible sur les cas limites, qui sont précisément
les plus intéressants.

### 2.5 Traçabilité et travail à plusieurs

**Plusieurs administrateurs.** Le schéma doit donc porter *qui* a fait quoi, et se prémunir
contre deux curateurs qui travaillent en même temps sur la même fiche.

| Élément | Rôle |
|---|---|
| `User` | compte curateur, `ROLE_ADMIN` (+ `ROLE_REVIEWER` si la relecture est activée) |
| `created_by` / `created_at` / `updated_by` / `updated_at` | sur chaque entité curatée |
| `Riwaya.status` | `brouillon` → `en_relecture` → `publié` |
| `reviewed_by` / `reviewed_at` | qui a validé la publication |
| `version` | verrou optimiste (voir ci-dessous) |

Le statut `brouillon` répond d'abord à un besoin qui existait déjà seul : **la saisie d'une
chaîne complète ne tient pas en une session**, et le site public ne doit pas exposer un isnad à
moitié saisi. `en_relecture` s'y ajoute pour le travail à plusieurs.

L'état `en_relecture` ne coûte qu'une valeur d'énumération et deux colonnes : le schéma le
porte, **l'imposer ou non reste un réglage de politique**, pas une modification de structure.
Si vos curateurs se font mutuellement confiance, passez directement de `brouillon` à `publié`.

#### Le conflit d'édition concurrente

C'est le problème que l'administrateur unique n'avait pas. Deux curateurs ouvrent la même
`Riwaya`, l'un ajoute un maillon, l'autre corrige le matn : le second enregistrement écrase
silencieusement le premier.

Doctrine fournit la parade native : un champ `#[ORM\Version]` sur les entités curatées.
L'écriture échoue avec une `OptimisticLockException` si la ligne a changé depuis la lecture,
et l'écran de saisie peut alors proposer de recharger plutôt que d'écraser. **Le verrou
optimiste est préférable au verrou pessimiste ici** : les collisions sont rares (deux curateurs
travaillent rarement sur la même chaîne au même moment) et un verrou pessimiste bloquerait une
fiche qu'un curateur aurait simplement oublié de fermer.

#### Le doublon devient plus probable

Le risque du §2.1 s'aggrave nettement à plusieurs : deux curateurs qui ne se voient pas
créeront chacun leur fiche « Sufyân ibn ʿUyayna » le même mois, sans jamais s'en apercevoir.
Deux conséquences :

- La recherche sur toutes les formes de noms n'est plus seulement un confort de saisie, c'est
  le seul mécanisme qui empêche la divergence des fiches. **La phase 1 devient d'autant plus
  prioritaire.**
- Prévoir un écran de **détection de doublons probables** (mêmes formes normalisées, ou dates
  de décès proches avec noms voisins), à passer en revue périodiquement — plutôt que d'espérer
  que chaque curateur cherche bien avant de créer.

#### La fusion de personnes

C'est l'opération la plus destructrice de l'outil : elle repointe des chaînes entières vers une
autre fiche. À plusieurs, elle devient aussi la plus dangereuse, car un curateur peut fusionner
une fiche qu'un autre est en train d'éditer.

- Exécution **transactionnelle**, avec verrou sur les deux fiches concernées.
- Journal conservant l'auteur, la date, la fiche absorbée, la fiche conservée et les formes de
  noms transférées — de quoi défaire une fusion erronée et savoir à qui en parler.

---

## 3. Modèle cible

Quatre couches, de la plus stable à la plus volatile. Chaque couche ne dépend que des
précédentes.

### Couche 1 — Référentiel des personnes (rijāl)

| Entité | Rôle | Champs clés |
|---|---|---|
| `Person` | identité canonique d'un transmetteur | `slug`, ism, kunya, nasab, nisba[], laqab, `birth_ah`, `death_ah` (fourchettes), `region`, `is_mudallis` (+type, +rang) |
| `PersonName` | forme observée d'un nom → personne canonique | `person_id`, `form` (brut, tel que cité), `script` (ar/translit), `kind` (kunya, ism, shuhra…), `source` |
| `PersonGrade` | un jugement de fiabilité **par critique** | `person_id`, `critic_id → Person`, `term` (thiqa, ṣadūq, ḍaʿīf…), `verbatim`, `source` |
| `Tabaqa` | génération | remplace `Period`, avec `sort_order`, couleur, libellés |
| `PersonTabaqa` | affectation d'une personne à une génération **selon une source** | `person_id`, `tabaqa_id`, `source` |

Deux principes portent cette couche :

- **Jamais un jugement dans une colonne.** Grade et génération sont des affirmations attribuées
  à quelqu'un, pas des propriétés intrinsèques. D'où `PersonGrade` et `PersonTabaqa` avec
  `source`, plutôt que `person.reliability_grade` et `person.period_id`.
- **Les dates sont des fourchettes nullables** (`death_ah_min` / `death_ah_max` + indicateur de
  précision), jamais un entier obligatoire.

### Couche 2 — Bibliographie

| Entité | Rôle |
|---|---|
| `Collection` | un recueil (Ṣaḥīḥ al-Bukhārī…), avec `compiler_id → Person` |
| `Edition` | une édition imprimée d'un recueil — la numérotation des hadiths **diffère entre éditions**, y compris pour Bukhārī |
| `HadithCluster` | l'enseignement au sens large : ce qui regroupe plusieurs riwāyāt jugées « le même hadith » |

`HadithCluster` porte le jugement d'équivalence (*takhrīj*), qui est éditorial et non
dérivable automatiquement. Il doit donc porter sa **provenance**.

### Couche 3 — Occurrences

| Entité | Rôle |
|---|---|
| `Riwaya` | **l'unité réelle** : un isnad + un matn + une citation. Porte `text_ar`, `text_fr`, `cluster_id` |
| `RiwayaReference` | `riwaya_id`, `edition_id`, `number`, `is_primary` — M:N vers la bibliographie |
| `RiwayaGap` | lacune déclarée : `type` (mursal, munqaṭiʿ, muʿallaq, muʿḍal), position |
| `HadithClassification` | `cluster_id`, `type` (gharīb, ʿazīz…), `assigned_by`, `source` |

C'est ici que se joue la correction principale : **le texte descend du hadith vers la riwaya**.
`Hadith` tel qu'il existe aujourd'hui se scinde en `HadithCluster` (l'enseignement) et
`Riwaya` (l'occurrence).

### Couche 4 — Graphe de transmission

| Entité | Rôle |
|---|---|
| `Transmission` | arête **globale et typée** : `from_person_id`, `to_person_id`, `type` (`narration`, `enseignement`), UNIQUE sur le triplet |
| `RiwayaLink` | usage de l'arête dans une riwāya : `riwaya_id`, `transmission_id`, `position`, `formula` (verbatim), `mode` (samāʿ, ijāza…), `is_spine` |
| `RiwayaParticipant` | `riwaya_id`, `person_id`, `level` — la strate verticale, comme aujourd'hui |

**La décision structurante : l'arête devient globale, l'usage devient contextuel.**

Mes agents divergeaient ici. L'analyse technique recommandait de conserver l'arête contextuelle
actuelle — mais sous condition explicite : *« tant que `spine` reste la seule variation par
contexte et qu'aucune métadonnée n'est portée par la relation elle-même »*.

Cette condition est brisée par le domaine. La **formule de transmission** est par occurrence
(un même couple maître-élève peut être cité avec `ḥaddathanā` dans une voie et `ʿan` dans une
autre), tandis que la **relation elle-même** porte un fait historique indépendant de tout
hadith : cette personne a-t-elle réellement entendu de cette autre ? C'est exactement la
question du tadlīs, et c'est un attribut de la relation, pas de l'occurrence.

Dès lors, la séparation `Transmission` / `RiwayaLink` n'est plus un raffinement prématuré :
c'est ce qui permet à la fois de dédupliquer les 213 arêtes `enseignement` et de porter la
formule au bon niveau. C'est d'ailleurs ce que le `schema.sql` de référence avait déjà tranché
ainsi.

---

## 4. Décisions techniques

### 4.1 Interrogation du graphe

**Rester sur une liste d'adjacence + `WITH RECURSIVE`.** Le modèle actuel est déjà le bon
pattern ; il ne faut pas en changer.

```sql
WITH RECURSIVE ancetres(person_id, depth) AS (
    SELECT from_person_id, 1 FROM transmission WHERE to_person_id = :cible
  UNION ALL
    SELECT t.from_person_id, a.depth + 1
    FROM transmission t JOIN ancetres a ON t.to_person_id = a.person_id
)
CYCLE person_id SET is_cycle USING path
SELECT DISTINCT person_id, MIN(depth) FROM ancetres GROUP BY person_id;
```

La clause `CYCLE` (SQL standard, PostgreSQL 14+) coupe la récursion sur un nœud déjà visité.
Le graphe est censé être acyclique, mais les données sont saisies à la main : c'est un
garde-fou à coût quasi nul.

**Ne pas énumérer tous les chemins en SQL.** Le nombre de chemins simples est exponentiel dès
qu'un graphe diverge puis reconverge — ce qui est exactement la forme d'un isnad après le
pivot. Borner toute récursion à une riwāya ou à une profondeur.

### 4.2 Ce qu'il ne faut pas adopter

| Piste | Verdict |
|---|---|
| `ltree` / materialized path | **Non.** Suppose un chemin unique par nœud. Un narrateur a plusieurs maîtres : l'hypothèse est violée par construction. Les tentatives d'adaptation au DAG font un produit cartésien des chemins à chaque insertion. |
| Closure table | **Pas maintenant.** Optimisation de lecture au prix d'écritures en cascade par triggers. À garder en repli, mesures à l'appui, si les CTE deviennent lents. |
| `pgRouting` | **Non.** Conçu pour du routage pondéré. Notre problème est de l'accessibilité et de la dominance. |
| Apache AGE | **Non.** Cypher serait plus expressif, mais c'est une extension native (indisponible sur la plupart des PaaS gérés) et un second langage à maintenir, pour un gain nul à notre échelle. |

PostgreSQL nu avec des index corrects tient des dizaines de milliers de nœuds sans difficulté.

### 4.3 Le pivot reste curaté, et devient vérifiable

Le pivot est formellement le **dominateur** du sous-graphe d'une riwāya : le nœud par lequel
toute voie doit passer. L'algorithme de référence (Lengauer-Tarjan) est quasi linéaire, mais
il s'exprime mal en SQL déclaratif — c'est un calcul à point fixe sur tout le graphe, pas un
parcours.

Décision : **garder le pivot comme donnée saisie** — c'est un fait historiographique établi par
les savants, pas seulement une propriété structurelle — et ajouter une **commande de
validation** qui recalcule le dominateur en PHP (intersection des ancêtres des nœuds
terminaux, trivial sur quelques dizaines de nœuds) et signale les divergences. Vérification,
pas source de vérité.

### 4.4 Accès aux données

- Un `TransmissionRepository` dédié, avec les parcours récursifs en **DBAL brut**
  (`executeQuery`), retournant des identifiants scalaires ré-hydratés ensuite. DQL ne supporte
  pas les CTE, et le `QueryBuilder` DBAL ne couvre pas `WITH RECURSIVE`.
- Sortir de l'ORM dès qu'une requête est récursive, purement analytique, ou traverse plusieurs
  riwāyāt. Garder l'ORM pour le CRUD et l'affichage.
- Ne jamais hydrater d'entités complètes pour un calcul de graphe.
- Index composite `(from_person_id, to_person_id)` pour les parcours inter-riwāyāt.

---

## 5. Phasage

Chaque phase laisse l'application fonctionnelle et testée.

L'ordre est commandé par le geste de saisie : l'administrateur doit pouvoir, le plus tôt
possible, désigner une personne sans risque de doublon, puis saisir une riwāya complète.

| Phase | Contenu | Risque |
|---|---|---|
| **0. Comptes** | `User`, `ROLE_ADMIN`, authentification. Colonnes d'attribution et `version` (verrou optimiste) sur les entités curatées. | faible |
| **1. Identité** | `PersonName` (formes multiples, normalisées) + recherche sur toutes les formes + fusion transactionnelle journalisée + écran de doublons probables. Dates en fourchettes. | faible — additif, mais c'est le socle de la saisie |
| **2. Bibliographie** | `Collection`, `Edition`, références structurées. Découpe de la chaîne composite `Hadith.reference`. | faible — additif |
| **3. Riwāya** | Scission `Hadith` → `HadithCluster` + `Riwaya`. Le matn descend au niveau de l'occurrence. | **élevé** — touche la recherche par matn, l'API et la vue Réseau |
| **4. Saisie de la chaîne** | `Transmission` globale typée + `RiwayaLink` (formule, mode, position), transmission collective, `RiwayaGap`. Dédoublonnage des arêtes. | moyen — migration de données |
| **5. Rijāl approfondi** | `PersonGrade` par critique, `PersonTabaqa` sourcée. Reprise des 81 % de `death_ah` et 77 % de grades disponibles. | faible |
| **6. Dérivations** | Classifications calculées, validation du pivot, parcours récursifs. | faible |

La phase 1 passe en tête : sans recherche fiable sur les formes de noms, chaque séance de
saisie fabrique des doublons qu'il faudra fusionner ensuite — et à plusieurs curateurs qui ne
se voient pas travailler, ces doublons se multiplient d'autant plus vite. La phase 3 reste la plus coûteuse,
mais elle est incontournable — c'est l'unité que l'administrateur manipule.

Le CRUD d'administration (`src/Admin`, aujourd'hui vide) se construit au fil de ces phases,
pas après : chaque phase livre l'écran de saisie correspondant.

---

## 6. Décisions qui restent à prendre

*Déjà tranché : la base est saisie à la main (§2), par **plusieurs administrateurs** (§2.5).
L'import de masse est hors périmètre ; `PersonName` est le socle de la saisie ; le schéma porte
l'attribution par contributeur et le verrou optimiste.*

1. **La relecture est-elle imposée ?** Le schéma porte l'état `en_relecture` sans surcoût, mais
   l'exiger avant publication est un choix de fonctionnement : utile si les curateurs ont des
   niveaux d'expertise différents, pure friction s'ils se valent. Décidable plus tard, sans
   migration.

2. **Référentiel d'identité des narrateurs.** S'aligner sur un identifiant externe
   (muslimscholars.info sert d'ID de facto, réutilisé par d'autres projets) ou rester
   autonome ? S'aligner facilite les imports futurs mais impose un schéma tiers.

3. **Traductions françaises.** Le jeu curaté les a (4/4), la base brute ne les a pas (0/75).
   Si le corpus grandit, le français devient une couche de traduction à modéliser
   (`RiwayaTranslation` : langue, texte, traducteur), pas une colonne.

4. **Profondeur du jarḥ wa taʿdīl.** Stocker les jugements verbatim par critique est fidèle
   mais lourd à saisir. Un niveau intermédiaire (un grade consolidé + sa source) est plus
   réaliste à court terme — à condition d'assumer que c'est une simplification.
