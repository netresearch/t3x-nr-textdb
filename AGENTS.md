<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
<!-- Managed by agent: keep sections & order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md (root)

**Precedence:** The **closest AGENTS.md** to changed files wins. Root holds global defaults only.

## Global rules

- Keep PRs small (~≤300 net LOC)
- Conventional Commits: `type(scope): subject`
- Ask before: heavy deps, full e2e, repo rewrites, breaking changes
- Never commit secrets or PII
- Follow TYPO3 CGL (Coding Guidelines)
- PSR-4 autoloading: `Netresearch\NrTextdb` → `Classes/`
- Component map for agents: `docs/ARCHITECTURE.md`; execution plans: `docs/exec-plans/`

## Commands

- Lint: `composer ci:test:php:lint`
- Static Analysis: `composer ci:test:php:phpstan`
- Code Style: `composer ci:test:php:cgl`
- Rector: `composer ci:test:php:rector`
- Fractor: `composer ci:test:php:fractor`
- Unit Tests: `composer ci:test:php:unit`
- Functional Tests: `composer ci:test:php:functional`
- Mutation Tests: `composer ci:test:php:mutation`
- All checks: `composer ci:test`
- Changelog fragments: `composer ci:test:changelog`
- Make shortcuts: `make help` (cgl, cgl-fix, phpstan, rector, test, test-unit)

## Changelog

- Changes are recorded in [`CHANGELOG.md`](CHANGELOG.md), [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/) from the release after 4.2.0 on: `## [X.Y.Z] - YYYY-MM-DD` with `### Added`, `### Changed`, `### Deprecated`, `### Removed`, `### Fixed`, `### Security` as needed, newest first, below `## [Unreleased]`. Releases up to 4.2.0 keep their original format (`# X.Y.Z` with `## FEATURE`, `## BUGFIX` and the like) below.
- A feature or fix PR writes its entry as a fragment `changelog.d/<branch-slug>.<section>.md` ([format](changelog.d/README.md)) instead of editing `CHANGELOG.md`; `composer ci:test:changelog` checks it, and CI runs it through `composer ci:test:php:cgl`.
- The release PR `chore(release): X.Y.Z` runs `composer changelog:collect`, which moves the fragments into `## [Unreleased]`, and then renames that section to `## [X.Y.Z] - YYYY-MM-DD`.
- The release PR also writes the same changes in reStructuredText in `Documentation/ChangeLog/Index.rst`.

## Index of scoped AGENTS.md

- `./Classes/AGENTS.md` — PHP backend classes (Controllers, Domain, Services, ViewHelpers)
- `./Tests/AGENTS.md` — PHPUnit testing infrastructure

## When instructions conflict

Nearest AGENTS.md wins. User prompts override files.

## Commit Signing

Signed commits are required: `git commit -S --signoff`. The `require-signed-commits` ruleset on the default branch rejects unsigned commits at merge time, and the DCO check additionally requires the `Signed-off-by` trailer. Quickest setup is SSH signing — register your SSH key as a *signing key* on your GitHub account, then `git config --global gpg.format ssh && git config --global user.signingkey ~/.ssh/<key>.pub`.
