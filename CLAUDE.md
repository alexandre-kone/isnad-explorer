# Isnad Explorer v2 — Conventions projet

Application Symfony 8.1 (PHP 8.4) : Domain injecté dans les contrôleurs, rendu Twig SSR,
îlots Stimulus via AssetMapper.

## Flow de développement

Toute feature suit ces étapes, dans l'ordre :

1. **Carte Trello** — créer la carte si elle n'existe pas (outils MCP `trello`, ex.
   `getMyBoards`/`getLists`/`addCard`). Une feature = une carte.
2. **Branche** — créer une branche au format Angular `type/description` (voir § Branches),
   idéalement préfixée du numéro d'issue/carte (ex. `feat/39-recherche-isnad`).
3. **Développement** — implémenter la feature (Domain → SSR Twig → îlot Stimulus si besoin),
   avec ses tests.
4. **Commit + push** — commits Conventional Commits en français, suite `vendor/bin/phpunit`
   verte, puis `git push`.
5. **Ouverture PR + liens croisés** — ouvrir la PR (`gh pr create`), puis :
   - coller le **lien de la PR dans la carte Trello** (`addComment` sur la carte) ;
   - coller le **lien de la carte Trello dans la PR** (corps ou commentaire de PR).
6. **Review code** — faire relire la PR (reviewer GitHub / Copilot / revue IA) et traiter
   les retours.
7. **Merge** — merger la PR une fois la review validée et la CI verte, puis déplacer la
   carte Trello dans la liste « Done ».

## Conventions Git

### Commits
- **Format Conventional Commits** : `type: description` (`feat`, `fix`, `test`, `chore`, `refactor`, `docs`…).
- Message de commit rédigé **en anglais**, à l'impératif présent, concis.
- **Aucune attribution Claude** : pas de trailer `Co-Authored-By`, pas de mention Anthropic/Claude.
  *(Appliqué automatiquement : `attribution` vide + hook `.claude/hooks/no-claude-attribution.sh` qui bloque le commit sinon.)*
- Lancer `vendor/bin/phpunit` (suite verte) **avant** de committer du code applicatif.

### Branches — convention Angular
- Format `type/description-en-kebab`, où `type` ∈ `build`, `ci`, `docs`, `feat`, `fix`,
  `perf`, `refactor`, `style`, `test`, `chore`, `revert` (mêmes types que les commits).
- Numéro d'issue optionnel dans le slug : ex. `feat/39-recherche-isnad`, `fix/login-redirect`.
  *(Appliqué automatiquement : hook `.claude/hooks/branch-naming.sh` qui refuse la création
  d'une branche non conforme.)*

### Pull Requests
- Titre au format Conventional Commit (comme le commit principal).
- Corps **en français**, structuré : `## Contexte`, `## Changements`, `## Vérification`.
- **Aucune ligne** « 🤖 Generated with Claude Code » ni attribution Claude.

## Tests
- `vendor/bin/phpunit` — WebTestCase (SSR) + Panther (hydratation JS réelle).
- Panther boote son serveur sur l'env `test` (`PANTHER_APP_ENV=test` dans `phpunit.dist.xml`),
  car `App\Kernel::getAllowedEnvs()` n'autorise pas l'env `panther`.
- chromedriver est installé dans `./drivers` (gitignoré, auto-détecté par Panther).
