# GITFLOW.md — Git workflow

> Guide to the Git flows of Ensemble: how to create branches, commit, open a
> PR, merge, version and publish. **Follow this document** when contributing.
>
> In case of conflict with the [PHILOSOPHY.md](PHILOSOPHY.md), the philosophy wins.

---

## 1. Overview

We adopt a **lean** flow based on short-lived branches and PRs into `main`. We do
not use classic gitflow (`develop` + `release/*` + `hotfix/*`): it adds ceremony
without value for our team size.

```
main ──●────────────────●───────────────●──────────►
        \              / \             /
         ●──●──●──────●   ●──●──●─────●
        feat/...          fix/...
```

- **`main`** is the only permanent, always-stable branch.
- All work starts from a **short branch** (`feat/*` or `fix/*`).
- Work reaches `main` **only via PR**.
- Each relevant change produces a **semver tag** and a **release**.

---

## 2. Step by step

### 2.1 Update `main`

```bash
git checkout main
git pull origin main
```

### 2.2 Create the working branch

From the **updated** `main`:

```bash
git checkout -b feat/short-description   # new feature
git checkout -b fix/short-description    # bug fix
```

Naming rules:

- `feat/<description>` — new feature
- `fix/<description>` — bug fix
- Use `kebab-case`, descriptive and short.

### 2.3 Implement and stage

```bash
git add <files>
```

> **Remember:** before implementing, read the philosophy and the state documents.
> When done, update STATE, ROADMAP, ARCHITECTURE and README.

### 2.4 Commit (Conventional Commits)

Format:

```
<type>(<ID>): <imperative description>
```

Examples:

```
feat(BOARD-123): add close endpoint
fix(BOARD-124): persist worker on update
docs(BOARD-125): document the REST API
```

Common types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`.

- `<ID>` is the task identifier (e.g. `BOARD-123`); omit it when there is none.
- The description is short, imperative, lowercased.

### 2.5 Push and PR

```bash
git push -u origin feat/short-description

gh pr create \
  --base main \
  --title "feat(BOARD-123): short description" \
  --body "## What changed
- ...

## How to test
- ...

## Checklist
- [ ] Tests green
- [ ] Documents updated"
```

### 2.6 Check conflicts and merge

```bash
gh pr view <number> --json mergeable,mergeStateStatus
```

If there is **no conflict**, merge and delete the branch:

```bash
gh pr merge <number> --merge --delete-branch
```

If there **is** a conflict, resolve locally:

```bash
git checkout feat/short-description
git fetch origin
git rebase origin/main
# resolve conflicts
git add <files>
git rebase --continue
git push --force-with-lease
```

### 2.7 Update local `main`

```bash
git checkout main
git pull origin main
```

---

## 3. Versioning (Semantic Versioning)

After merging, create a **tag** following [SemVer](https://semver.org/):

| Change | Bump | Example |
|---|---|---|
| `fix` | **patch** | `v1.0.0` → `v1.0.1` |
| `feat` | **minor** | `v1.0.0` → `v1.1.0` |
| *breaking change* | **major** | `v1.0.0` → `v2.0.0` |

> **Tag policy: always prefixed with `v`** (e.g. `v1.0.0`).

### 3.0 Development version (`0.y.z`)

While the API is not stable, we version in **`0.y.z`** (SemVer: "initial
development; anything may change"). **`v1.0.0`** is only declared when all roadmap
phases are done and the API is considered stable.

### 3.0.1 Tag immutability

From `v0.1.0`, **tags are immutable**: never delete or rewrite a published tag.

### 3.1 Release policy

**One release per tag.** Each published tag produces its own release with its own
notes (`gh release create <tag> --generate-notes`). A release never moves.

```bash
git describe --tags --abbrev=0
git tag v0.1.1
git push origin v0.1.1
```

---

## 4. Publication (GitHub + Docker Hub)

The Docker image is published **locally** via `docker build` / `docker push`
(the repository has no GitHub Actions workflow as of `v0.10.1` — this was
verified; the tag only feeds the semver release below).

```bash
# local image
docker build -t esdrasschonevald/ensemble:latest .

# tag + release
git tag v0.1.1 && git push origin v0.1.1
gh release create v0.1.1 --generate-notes
```

---

## 5. Golden rules

- **Never** commit directly to `main`.
- **Never** commit secrets (`.env`, keys, tokens).
- **Always** open a PR, even for small changes.
- **Always** delete the branch after merge (`--delete-branch`).
- **Always** update the state documents before opening the PR.
- **Always** run `vendor/bin/phpunit` and `vendor/bin/psalm` before pushing.
