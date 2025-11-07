<!-- Managed by agent: keep sections & order; edit content, not structure. Last updated: 2025-11-07 -->

# AGENTS.md (root)

**Precedence:** The **closest AGENTS.md** to changed files wins. Root holds global defaults only.

## Global rules

- Keep PRs small (~≤300 net LOC)
- Conventional Commits: `type(scope): subject`
- Ask before: heavy deps, full e2e, repo rewrites, breaking changes
- Never commit secrets or PII
- Follow TYPO3 CGL (Coding Guidelines)
- PSR-4 autoloading: `Netresearch\NrTextdb` → `Classes/`

## Minimal pre-commit checks

- Lint: `composer ci:test:php:lint`
- Static Analysis: `composer ci:test:php:phpstan`
- Code Style: `composer ci:test:php:cgl`
- Rector: `composer ci:test:php:rector`
- Fractor: `composer ci:test:php:fractor`
- Unit Tests: `composer ci:test:php:unit`
- All checks: `composer ci:test`

## Index of scoped AGENTS.md

- `./Classes/AGENTS.md` — PHP backend classes (Controllers, Domain, Services, ViewHelpers)
- `./Tests/AGENTS.md` — PHPUnit testing infrastructure

## When instructions conflict

Nearest AGENTS.md wins. User prompts override files.
