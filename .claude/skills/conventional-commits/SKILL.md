---
name: conventional-commits
description: "Mandatory commit-message format AND commit cadence for this repository — apply this skill EVERY time you are about to run `git commit`, write a commit message, amend a commit, squash/reword during a rebase, draft a PR title, or finish a phase/step of a multi-step task. Commit phase by phase: at every completed phase boundary, run the QA gate, stage only that phase's paths, and make one Conventional Commit before starting the next phase (standing authorization — do not re-ask; never push unless the user asks). Every commit in this repo MUST follow the Conventional Commits 1.0.0 spec: `type(scope)?: description`, optional body, optional footer, with `!`/`BREAKING CHANGE:` for breaking changes. A local `commit-msg` git hook (in `.githooks/`) rejects any non-conforming message, so getting this right is not optional. Covers the allowed types (feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert), the scope conventions for this Laravel app, subject-line rules (imperative mood, no trailing period, ≤72 chars), when to add a body/footer, how breaking changes are signalled, and the merge/revert/fixup exceptions the hook allows. Also covers grouping unrelated work into separate commits and never committing/pushing unless the user asked."
---

# Conventional Commits

Every commit message in this repository follows the [Conventional Commits 1.0.0](https://www.conventionalcommits.org/) specification. This is enforced mechanically: the `commit-msg` git hook in `.githooks/commit-msg` rejects any commit whose subject line does not match the grammar below. A commit that does not follow this format cannot be created — write the message correctly the first time.

Conventional commits exist so that history reads like a changelog: `type` tells you the *kind* of change at a glance, `scope` tells you *where*, and the description tells you *what*. Consistent history is greppable, and it lets tooling derive semantic version bumps and release notes automatically.

## The Grammar

```
<type>(<scope>)?<!>?: <description>

[optional body]

[optional footer(s)]
```

- **`type`** — required. One of the allowed types below.
- **`scope`** — optional. A noun in parentheses naming the area touched, e.g. `(auth)`, `(inventory)`. Lowercase.
- **`!`** — optional. Placed right before the colon to flag a breaking change: `feat(api)!: ...`.
- **`description`** — required. Short summary of the change in the imperative mood.

## Allowed Types

| Type | Use for | Version impact |
|---|---|---|
| `feat` | A new feature or user-facing capability | MINOR |
| `fix` | A bug fix | PATCH |
| `docs` | Documentation only (README, PHPDoc, comments, this skill) | — |
| `style` | Formatting, whitespace, Pint fixes — no logic change | — |
| `refactor` | Code change that neither fixes a bug nor adds a feature | — |
| `perf` | A change that improves performance | PATCH |
| `test` | Adding or correcting tests only | — |
| `build` | Build system and Composer dependencies | — |
| `ci` | CI configuration and scripts | — |
| `chore` | Maintenance that doesn't touch `src`/tests (e.g. `.gitignore`) | — |
| `revert` | Reverting a previous commit | — |

Do not invent new types — the hook only accepts this list.

## Subject-Line Rules

1. **Imperative mood**, as if completing "This commit will…": `add`, `fix`, `remove` — not `added`, `fixes`, `removing`.
2. **Lowercase description**, no leading capital, **no trailing period**.
3. **Keep it ≤ 72 characters.** The hook warns past 72 and hard-rejects past 100. Put detail in the body, not a run-on subject.
4. **One logical change per commit.** If the subject needs "and", it's probably two commits.

## Scopes for This Application

Scope is optional but encouraged when the change is localised. Prefer the domain or subsystem over a file path:

- Domain areas: `(inventory)`, `(auth)`, `(team)`, `(user)`
- Layers: `(api)`, `(migration)`, `(factory)`, `(seeder)`, `(policy)`, `(request)`, `(action)`, `(data)`
- Tooling: `(deps)`, `(pint)`, `(rector)`, `(larastan)`, `(pest)`

Omit the scope when a change is genuinely cross-cutting rather than forcing a vague one.

## Body and Footer

Add a **body** (separated from the subject by one blank line) when the *why* isn't obvious from the subject — motivation, trade-offs, context. Wrap at ~72 columns. Use it to explain the reasoning, not to restate the diff.

Use **footers** for metadata:

- Issue references: `Refs: #123`, `Closes: #123`.
- Breaking changes (see below).

## Breaking Changes

Signal a breaking change **either** with a `!` before the colon **or** with a `BREAKING CHANGE:` footer (or both). The `!` gives an at-a-glance signal; the footer explains the migration path.

```
feat(api)!: return 422 instead of 200 for invalid inventory payloads

BREAKING CHANGE: clients that relied on a 200 with an error body must now
handle a 422 response. Update error handling before deploying.
```

## Exceptions the Hook Allows

The `commit-msg` hook skips validation for messages git generates itself, so normal workflows aren't blocked:

- Merge commits (`Merge branch '...'`)
- Revert commits (`Revert "..."`)
- `fixup!` / `squash!` autosquash commits
- Amend/rebase reword lines that keep a valid conventional subject

Everything else must conform.

## Good and Bad Examples

```
✅ feat(inventory): add low-stock threshold alerts
✅ fix(auth): reject expired tokens on refresh
✅ refactor(action): extract stock adjustment into UpdateStockAction
✅ test(inventory): cover negative quantity rejection
✅ chore(deps): bump laravel/framework to 13.2
✅ docs: document the conventional-commits hook setup

❌ Fixed a bug                    (no type, past tense, capitalised)
❌ feat: Added new feature.       (past tense, capitalised, trailing period)
❌ update stuff                   (no type, vague)
❌ feat(inventory): add alerts and fix pagination and tidy imports  (three changes in one)
❌ wip                            (no type, meaningless)
```

## Workflow Rules

- **Commit at the end of every completed phase** (see below). Beyond that, do not commit as a side effect of finishing a single edit, and **never `git push` unless the user explicitly asks.**
- **Branch only when on `main`.** If the current branch is the default branch (`main`), create a feature branch before the first commit of new work. If you are **already on any other branch, commit straight into it** — do not create a new branch.
- **Group unrelated changes into separate commits**, each with its own conventional message, rather than one mixed commit.
- **Run the QA gate first.** Before committing PHP changes, `vendor/bin/pint --dirty --format agent` (and the project's rector/larastan/test gate where relevant) should be green — otherwise your first commit is immediately followed by a `style:`/`fix:` cleanup commit.

## Commit After Every Completed Phase

Multi-step work is committed **phase by phase**, not all at once at the end. This keeps history reviewable, makes each step revertible, and means a failed later step never drags a working earlier one down with it. Setting up this workflow is standing authorization for these phase commits — you do **not** re-ask the user before each one (pushing still requires an explicit request).

**What counts as a phase.** A phase is a self-contained unit of work that leaves the repo in a consistent, working state — typically one checked-off todo/plan step, one migration + its factory/seeder, one Action plus its tests, or one bug fix plus its regression test. If reverting it alone would leave the tree sensible, it's a phase.

**At each phase boundary, before starting the next phase:**

1. **Confirm the phase is actually done** — its intended change is complete and the tree is consistent (not mid-refactor).
2. **Run the QA gate** for the files you touched: `vendor/bin/pint --dirty --format agent`, plus rector/larastan/`php artisan test` where the change warrants it. Fix failures *within the same phase* — don't commit red.
3. **Stage only this phase's changes** — `git add` the specific paths you worked on. Never blindly `git add -A`/`git add .`; the tree may hold unrelated pre-existing edits that don't belong in this commit.
4. **Commit with one Conventional Commit message** describing that phase (the `commit-msg` hook enforces the format).
5. **Branch only when on `main`** — if the current branch is `main`, create a feature branch before the first phase commit; if you are already on another branch, commit the phase into that existing branch without creating a new one.

**Don't** bundle several phases into one commit, commit work in progress, or commit a phase whose tests are failing. One phase → one green, conventional commit.
