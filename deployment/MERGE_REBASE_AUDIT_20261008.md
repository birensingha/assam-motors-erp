# Merge / Rebase Audit — ERP Workshop Release

Audit date: 08-Oct-2026

Release PR: #9  
Head: `release/workshop-stack-20261008`  
Base: `main`

## Current compare result

- Base SHA: `714f891d7c9bc61b789057525a253d4599a0a6c4`
- Release head SHA at audit: `b794c82bb508c35d113a873f912ad0546e4e34f6`
- Ahead of main: **127 commits**
- Behind main: **0 commits**
- Compare status: **ahead**
- GitHub PR mergeability: **true** at final audit check

## Rebase decision

**No rebase required now.**

Reason:
- release branch is not behind current main;
- merge base equals current main base SHA;
- GitHub reports PR #9 mergeable;
- rebasing 127 additive/reference commits with no base divergence would add unnecessary rewrite risk.

## Open stacked ERP PRs

The following feature PRs remain open/draft:

- #1 Phase 5 ROT backend
- #2 Phase 3 Job Card Edit
- #3 Phase 4 OSL Purchase
- #4 Customer native auth
- #5 Phase 8 Staff alerts
- #6 Phase 6 ROT Performance
- #7 Phase 9 Admin alert review/export
- #8 Phase 10 FCM
- #9 consolidated Workshop release stack

## Merge rule

For the current release line:

> Treat PR #9 as the integration PR.

Do not merge #1–#8 individually into `main` before #9 unless the release plan is deliberately changed and the consolidated branch is refreshed afterward.

Why:
- some feature PRs are stacked on other feature branches;
- PR #9 already consolidates their prepared packages plus later phases;
- merging both the stack and the consolidated PR independently can create duplicate-history/review confusion even where Git can technically resolve it.

## Safe merge sequence

1. Re-check `main` immediately before merge.
2. Run compare:
   `main...release/workshop-stack-20261008`.
3. Confirm `behind_by = 0`.
4. Confirm PR #9 reports mergeable.
5. Review changed files and deployment docs.
6. Merge PR #9 only when the staging/deployment decision is approved.
7. After PR #9 is merged, close/supersede the old draft feature PRs as appropriate.

## If main changes before merge

If `behind_by > 0` later:

1. inspect files changed on main since merge base;
2. compare them with PR #9 modified paths;
3. update the release branch from main using the repository's normal strategy;
4. resolve conflicts in the release branch;
5. rerun status/preflight checks;
6. do not force-push/rewrite without preserving review traceability.

## Conflict-sensitive file

Current PR #9 modifies an existing file:
- `docs/MASTER_BACKLOG.md`

Most other PR #9 files are newly added patch/deployment documents.

Therefore future conflict risk is presently concentrated mainly in files that main may later edit, especially:
- `docs/MASTER_BACKLOG.md`
- any release/deployment file that may later be added to main under the same path

## Deployment note

Mergeability does not mean the reference packages are already wired into the live Laravel/PHP staging source.

Deployment still requires the live source mapping/integration steps documented under `deployment/`.
