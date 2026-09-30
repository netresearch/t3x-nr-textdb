<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Contributing to Netresearch TextDB

Thank you for your interest in contributing to the TYPO3 TextDB extension! We welcome contributions of all kinds.

## Table of Contents

- [How to Contribute Translations](#how-to-contribute-translations)
- [Reporting Issues](#reporting-issues)
- [Contributing Code](#contributing-code)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Governance and policies](#governance-and-policies)

---

## How to Contribute Translations

We use **Crowdin** for managing translations as part of the TYPO3 community translation project. Contributing translations is easy and requires no technical knowledge!

### 🌍 Translation Workflow

#### 1. Join the TYPO3 Crowdin Project

Visit the TYPO3 Crowdin project:
- **URL:** https://crowdin.com/project/typo3-cms
- Create a free Crowdin account if you don't have one
- Join the TYPO3 project

#### 2. Find the TextDB Extension

Once you're in the TYPO3 Crowdin project:
1. Navigate to the extension files
2. Look for `nr_textdb` in the extensions list
3. Select your target language

#### 3. Start Translating

The extension has **5 translation files** to work on:

| File | Description | Translation Units |
|------|-------------|-------------------|
| `locallang.xlf` | General interface labels | ~48 strings |
| `locallang_db.xlf` | Database field labels | ~12 strings |
| `locallang_mod.xlf` | Backend module labels | ~3 strings |
| `locallang_mod_sync.xlf` | Sync module labels | ~1 string |
| `locallang_mod_textdb.xlf` | TextDB module labels | ~3 strings |

**Total:** ~67 strings per language

#### 4. Translation Guidelines

**✅ DO:**
- Translate user-facing text naturally in your language
- Maintain the same tone and style as the source text
- Keep placeholders like `%s` unchanged in translations
- Ask questions in Crowdin comments if context is unclear

**❌ DON'T:**
- Translate proper names: **"Netresearch"** and **"TextDb"** must remain unchanged
- Change HTML tags or placeholders
- Add or remove punctuation that changes the meaning
- Translate technical terms that are commonly used in English (e.g., "TYPO3", "backend")

**Special Markers:**
- Strings marked with `translate="no"` in the source are proper names - they will show as "locked" in Crowdin

#### 5. Translation Review Process

1. Submit your translations in Crowdin
2. TYPO3 translation coordinators review submissions
3. Approved translations are synchronized to the extension repository
4. Translations appear in the next extension release

### Currently Supported Languages

The extension already supports **23 languages**:

🇿🇦 Afrikaans (af) • 🇸🇦 Arabic (ar) • 🇨🇿 Czech (cs) • 🇩🇰 Danish (da) • 🇩🇪 German (de) • 🇪🇸 Spanish (es) • 🇫🇮 Finnish (fi) • 🇫🇷 French (fr) • 🇮🇳 Hindi (hi) • 🇮🇩 Indonesian (id) • 🇮🇹 Italian (it) • 🇯🇵 Japanese (ja) • 🇰🇷 Korean (ko) • 🇳🇱 Dutch (nl) • 🇳🇴 Norwegian (no) • 🇵🇱 Polish (pl) • 🇵🇹 Portuguese (pt) • 🇷🇺 Russian (ru) • 🇸🇪 Swedish (sv) • 🇹🇿 Swahili (sw) • 🇹🇭 Thai (th) • 🇻🇳 Vietnamese (vi) • 🇨🇳 Chinese (zh)

**Want to add a new language?** Create an issue requesting the language, or start translating it in Crowdin and we'll add it!

### Questions About Translations?

- **Crowdin Support:** Use the comments feature in Crowdin to ask questions about specific strings
- **TYPO3 Slack:** Join #typo3-translations channel on https://typo3.slack.com
- **GitHub Issues:** Create an issue for translation-related bugs or suggestions

---

## Reporting Issues

Found a bug or have a feature request? Please create an issue on GitHub:

**Before submitting:**
1. Search existing issues to avoid duplicates
2. Use the issue templates (bug report or feature request)
3. Provide as much context as possible:
   - TYPO3 version
   - PHP version
   - Extension version
   - Steps to reproduce (for bugs)

**Create an issue:** https://github.com/netresearch/t3x-nr-textdb/issues/new/choose

---

## Contributing Code

We welcome code contributions! Here's how to get started:

### 1. Fork and Clone

```bash
# Fork the repository on GitHub, then:
git clone https://github.com/YOUR-USERNAME/t3x-nr-textdb.git
cd t3x-nr-textdb
```

### 2. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

**Branch naming conventions:**
- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation updates
- `refactor/` - Code refactoring

### 3. Make Your Changes

Follow our [coding standards](#coding-standards) and ensure:
- All PHP files have `declare(strict_types=1)`
- Type declarations on all methods and properties
- PHPDoc comments on public methods
- Code style as enforced by PHP-CS-Fixer (`Build/.php-cs-fixer.dist.php`)

### 4. Test Your Changes

```bash
# Run quality checks
composer ci:test:php:lint
composer ci:test:php:phpstan
composer ci:test:php:cgl

# Run unit tests
composer ci:test:php:unit
```

### 5. Commit and Push

```bash
git add <changed files>
git commit -S --signoff -m "feat: add your feature description"
git push origin feature/your-feature-name
```

**Commit message format:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `refactor:` - Code refactoring
- `test:` - Test additions/changes
- `chore:` - Maintenance tasks

### 6. Create a Pull Request

1. Go to the original repository on GitHub
2. Click "New Pull Request"
3. Select your feature branch
4. Fill out the PR template with:
   - Description of changes
   - Related issues
   - Test coverage
   - Breaking changes (if any)

---

## Development Setup

### Prerequisites

- **TYPO3:** 14.3+
- **PHP:** 8.2, 8.3, 8.4 or 8.5
- **Composer:** 2.x
- **DDEV:** Recommended for local development

### Using DDEV (Recommended)

```bash
# Start the development environment
ddev start

# Install dependencies
ddev composer install

# Install TYPO3 v14.3
ddev install-v14

# Access the site
ddev launch
```

### Manual Setup

```bash
# Install dependencies
composer install

# Run TYPO3 in development mode
php -S localhost:8000 -t .Build/public
```

### Running Tests

```bash
# All tests
composer ci:test

# Specific tests
composer ci:test:php:unit        # Unit tests
composer ci:test:php:functional  # Functional tests (SQLite)
composer ci:test:php:phpstan     # Static analysis
composer ci:test:php:rector      # Code modernization checks
composer ci:test:php:cgl         # Code style
```

### Building Documentation

```bash
# Render Documentation/ with the TYPO3 render-guides image into
# Documentation-GENERATED-temp/ (.ddev/commands/host/docs)
ddev docs
```

---

## Coding Standards

This extension follows strict TYPO3 and PHP coding standards:

### PHP Standards

- **Code style:** PHP-CS-Fixer with the `@PER-CS3x0` and `@Symfony` rule sets (`Build/.php-cs-fixer.dist.php`)
- **Strict Types:** `declare(strict_types=1)` in all PHP files
- **Type Declarations:** All properties, parameters, and return types
- **PHPStan Level 10:** Maximum static analysis strictness

### TYPO3 Standards

- **Dependency Injection:** Use constructor injection, not `GeneralUtility::makeInstance()`
- **PSR-14 Events:** Use event dispatcher instead of hooks
- **Extbase Patterns:** Follow TYPO3 MVC conventions
- **XLIFF 1.2:** Translation files with proper namespace

### Quality Tools

The following tools enforce code quality:

- **php-cs-fixer** - PER-CS 3.0 and Symfony style enforcement
- **PHPStan** - Static analysis at level 10
- **Rector** - Code modernization to TYPO3 v14
- **Fractor** - TYPO3-specific code improvements

PHP-CS-Fixer, PHPStan and Rector run in CI on every pull request. Fractor is not part of the CI run; run `composer ci:test:php:fractor` locally.

### Testing Standards

- **Unit Tests:** Test classes in `Tests/Unit/` mirroring `Classes/`
- **Functional Tests:** Integration tests in `Tests/Functional/`
- **PHPUnit:** test attributes (`#[Test]`, `#[CoversClass]`); PHPUnit comes with `netresearch/typo3-ci-workflows`, and its version depends on the PHP version
- **Coverage:** Aim for 60%+ code coverage

---

## Code of Conduct

Be respectful, inclusive, and constructive. We follow the [TYPO3 Code of Conduct](https://typo3.org/community/code-of-conduct).

---

## Questions?

- **GitHub Discussions:** https://github.com/netresearch/t3x-nr-textdb/discussions
- **TYPO3 Slack:** #typo3-cms-textdb (if available)
- **Email:** Open an issue for contact information

---

## License

By contributing to this project, you agree that your contributions will be licensed under the GPL-3.0-or-later license.

---

**Thank you for contributing to Netresearch TextDB!** 🎉

## Governance and policies

This extension follows the organisation-wide Netresearch policies:

- [Governance](https://github.com/netresearch/.github/blob/main/GOVERNANCE.md): ownership, roles, how decisions are made and conflicts resolved.
- [Roadmap](https://github.com/netresearch/.github/blob/main/ROADMAP.md): planned and excluded work for the next twelve months.
- [Handling of dependency and code analysis findings](https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings): which vulnerability, licence and static-analysis findings must be fixed, by when, and how exceptions are recorded.
- [Secret management](https://github.com/netresearch/.github/blob/main/SECURITY.md#secret-management): where CI and release credentials are stored, who may use them, how committed secrets are detected, and when secrets are rotated.
- [Access roster](https://github.com/netresearch/.github/blob/main/docs/access-roster.md): the people and teams with administrative or write access to this repository.

The security expectations, threat model and trust boundaries of this extension are in [docs/SECURITY-ASSURANCE.md](docs/SECURITY-ASSURANCE.md).

Checks that run on every pull request in this repository:

- `.github/workflows/checks.yml`: Composer Audit (fails on any advisory for an installed package that is not listed under `config.audit.ignore` in `composer.json`) and Opengrep SAST (fails a pull request as the [organisation rule](https://github.com/netresearch/.github/blob/main/SECURITY.md#static-analysis-sast) sets out), both through `typo3-ci-workflows`' `security.yml`; Dependency Review (fails on newly added dependencies with a vulnerability of severity high or higher); PHP License Audit (`license-check.yml`, fails on an SSPL or BSL licensed Composer dependency); CodeQL with language auto-detection (JavaScript and the workflow files; CodeQL has no PHP analysis, PHPStan and Opengrep cover the PHP code); Betterleaks secret scanning; zizmor for the workflow files; `pr-quality` (the pull request size check, and the automatic approval of pull requests that maintainers open); the aggregate gate `All security checks`, which fails when any of these jobs fails. The fuzz job finds no `Fuzz` test suite in `Build/phpunit.xml` and is skipped. The OpenSSF Scorecard job runs only on pushes to `main` and on the weekly schedule.
- `.github/workflows/ci.yml`: PHP lint, code style (PHP-CS-Fixer, `Build/.php-cs-fixer.dist.php`), PHPStan (level 10, `Build/phpstan.neon`, and, advisory by default, once more against the newest PHPUnit as `PHPStan (unpinned PHPUnit)`), Rector, unit tests and functional tests (SQLite) on PHP 8.2 to 8.5 with TYPO3 ^14.3, and the documentation rendering of `Documentation/`, summarised by the aggregate gate `ci / All CI checks`. Fractor is not part of the CI run.
- `.github/workflows/harness-verify.yml`: `Build/Scripts/verify-harness.sh`.
- `.github/workflows/check-template-drift.yml`: drift of the managed files from the `typo3-extension` template in netresearch/.github.

## Commit Signing

All commits must be cryptographically signed and carry a DCO sign-off: `git commit -S --signoff`. The `require-signed-commits` ruleset on the default branch enforces the signature (the "Verified" badge on GitHub); the DCO check enforces the `Signed-off-by` trailer — these are two different things and both are required. Quickest setup is SSH signing: register your SSH key as a *signing key* on your GitHub account, then `git config --global gpg.format ssh && git config --global user.signingkey ~/.ssh/<key>.pub`.
