# Gitflow

## Branches
1. Branches follow the semantic versioning format (MAJOR.MINOR.PATCH).
2. Stable branches are named with the suffix `.x`. For example, `1.x`, `2.x` etc.
3. Unstable branches are named with the suffix `-rc`. For example, `1.5-rc`, `1.6-rc`, `2.0-rc` etc. Always use a unreleased version number and always delete when merged.
4. Development branches are named with the prefixes `feature`, `bugfix` or `hotfix`. For example, `feature/<DESCRIPTION-SLUG>`, `bugfix/<DESCRIPTION-SLUG>`, `hotfix/<DESCRIPTION-SLUG>`. Always delete when merged.

### Example
- `1.x`: The stable branch for version 1.x.
- `2.x`: The stable branch for version 2.x.
- `3.0-rc`: The unstable branch for version 3.0. Unreleased version. Delete when merged.
- `feature/<DESCRIPTION-SLUG>`: The development feature branch for a new feature. Delete when merged.
- `fix/<DESCRIPTION-SLUG>`: The development fix branch for a bug fix. Delete when merged.
- `hotfix/<DESCRIPTION-SLUG>`: The development hotfix branch for a critical bug fix. Delete when merged.

## Tags
Tags follow the semantic versioning format (MAJOR.MINOR.PATCH).

- `v1.0.0`: The stable tag for version 1.0.0. Released version.
- `v1.0.1`: The stable tag for version 1.0.1. Released version.
- `v1.1.0`: The stable tag for version 1.1.0. Released version.
- `v2.0.0`: The stable tag for version 2.0.0. Released version.
- `v3.0-rc.1`: The unstable tag for version 3.0.0 candidate 1. Unreleased version.
- `v3.0-rc.2`: The unstable tag for version 3.0.0 candidate 2. Unreleased version.

### When fixing a bug in a released version
Create a new tag increasing the patch version of the released version and create a new release.

### When fixing a bug in a unreleased version
Use an existing unreleased tag or create a new tag increasing the patch version of the latest released tag.

### When developing a new feature
Create a new tag increasing the minor version of the latest released tag.

## Workflow
1. **Target Branch Selection**:
    - **Bug Fixes**: Target the oldest supported branch where the bug exists (e.g., `1.x` or `2.x`).
    - **Features / Improvements**: Target the current development branch (e.g., `3.0-rc`).
2. **Branch Creation**:
    - Fetch changes: `git fetch origin`
    - Checkout target branch: `git checkout <TARGET-BRANCH>`.
    - Sync with remote: `git reset --hard origin/<TARGET-BRANCH>`.
    - Create work branch: `git checkout -b <TYPE>/<DESCRIPTION-SLUG>`
3. **Contribution**:
    - Follow PSR-12 and Object Calisthenics.
    - Commit using the conventional commits model.
4. **Submission**:
    - Push branch: `git push -u origin <TYPE>/<DESCRIPTION-SLUG>`
    - Create PR: `gh pr create --base <TARGET-BRANCH> --fill`
5. **Maintenance**:
    - Once merged, the maintainers will merge the lower branch into the higher branches (e.g., `1.x` into `2.x`, and `2.x` into `master`) to ensure all versions receive the fixes.
6. **Release**:
    - Tag the release on the appropriate branch following semantic versioning.
    - Create the release: `gh release create <TAG> --generate-notes`.
