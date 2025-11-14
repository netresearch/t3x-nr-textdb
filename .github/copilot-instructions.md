# Copilot Instructions for nr_textdb

This repository contains a TYPO3 extension for managing translations. When working on this codebase, follow these guidelines.

## Repository Overview

**nr_textdb** is a TYPO3 extension that allows editing translations in the backend.

- **Extension Key**: `nr_textdb`
- **Namespace**: `Netresearch\NrTextdb`
- **TYPO3 Version**: 13.4+
- **PHP Version**: 8.2+
- **Architecture**: TYPO3 Extbase/Fluid

## File Structure

```
Classes/           - PHP backend classes (Controllers, Domain, Services, ViewHelpers)
├── Command/       - CLI commands (Symfony Console)
├── Controller/    - Backend module controllers (Extbase)
├── Domain/        - Models and Repositories
├── Service/       - Business logic services
└── ViewHelpers/   - Fluid template helpers
Tests/             - PHPUnit tests
├── Unit/          - Unit tests (mirrors Classes/ structure)
Configuration/     - TCA, TypoScript configuration
Resources/         - Frontend assets, templates
Documentation/     - User documentation
Build/             - Build configuration (phpstan, rector, etc.)
```

## Development Workflow

### Installation

```bash
composer install
# Dependencies installed to .build/vendor
# Binaries in .build/bin
```

### Essential Commands

**Linting & Code Quality:**
```bash
composer ci:test:php:lint      # PHP syntax check
composer ci:test:php:phpstan   # Static analysis (PHPStan level 9)
composer ci:test:php:cgl       # Code style check
composer ci:cgl                # Apply code style fixes
composer ci:test:php:rector    # Rector checks
composer ci:rector             # Apply rector fixes
composer ci:test:php:fractor   # TYPO3 Fractor checks
composer ci:fractor            # Apply fractor fixes
```

**Testing:**
```bash
composer ci:test:php:unit      # Unit tests with coverage
composer ci:test               # Run all checks (lint, phpstan, rector, fractor, unit, cgl)
```

### Run Commands from Project Root

Always run composer commands from the **project root directory**, not from subdirectories like `Classes/` or `Tests/`.

## Coding Standards

### TYPO3 CGL (Coding Guidelines)

- Follow **PSR-12** + **TYPO3 Coding Guidelines**
- Use `declare(strict_types=1);` at the top of every PHP file
- 4 spaces indentation
- Type hints everywhere (parameters, returns, properties)
- Constructor property promotion for dependencies
- Prefer `final` classes unless designed for extension
- Use `readonly` properties where possible (PHP 8.1+)

### Naming Conventions

- **Classes**: `PascalCase`
- **Methods/Properties**: `camelCase`
- **Constants**: `UPPER_SNAKE_CASE`
- **Database tables**: `tx_nrtextdb_*` prefix

### Extbase Patterns

- Controllers extend `ActionController` and return `ResponseInterface`
- Repositories extend `Repository`
- Models extend `AbstractEntity` or `AbstractValueObject`
- Use **constructor injection** (not `@inject` annotations)
- Use QueryBuilder or Repository methods (never raw SQL)

### Example Controller

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrTextdb\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ExampleController extends ActionController
{
    public function __construct(
        private readonly ExampleRepository $repository
    ) {}

    public function listAction(): ResponseInterface
    {
        $items = $this->repository->findAll();
        $this->view->assign('items', $items);
        return $this->htmlResponse();
    }
}
```

## Testing Guidelines

- Unit tests in `Tests/Unit/` mirror `Classes/` structure
- Extend `TYPO3\TestingFramework\Core\Unit\UnitTestCase`
- Use `@test` annotation or `test` prefix for test methods
- Mock dependencies with `createMock()`
- Use specific assertions (`assertSame()` over `assertEquals()`)
- Aim for >80% coverage on critical paths

### Example Test

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

use Netresearch\NrTextdb\Domain\Model\Translation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TranslationTest extends UnitTestCase
{
    /**
     * @test
     */
    public function getKeyReturnsInitialValue(): void
    {
        $subject = new Translation();
        self::assertSame('', $subject->getKey());
    }
}
```

## Pre-Commit Checklist

Before committing changes:

- [ ] `composer ci:test:php:lint` passes
- [ ] `composer ci:test:php:phpstan` passes
- [ ] `composer ci:test:php:cgl` passes (or apply with `composer ci:cgl`)
- [ ] `composer ci:test:php:rector` passes
- [ ] `composer ci:test:php:fractor` passes
- [ ] `composer ci:test:php:unit` passes
- [ ] Added/updated unit tests for new functionality
- [ ] Type hints on all methods and properties
- [ ] `declare(strict_types=1);` at top of file
- [ ] No unused imports

## Pull Request Guidelines

- **Keep PRs small**: ~≤300 net lines of code
- **Conventional Commits**: `type(scope): subject`
  - Examples: `feat(controller): add translation export`, `fix(service): handle empty language code`
- **Ask before**: heavy dependencies, full e2e rewrites, breaking changes
- **Never commit**: secrets, credentials, PII

## Security

- **Validate user input** in controllers before passing to services
- **XSS protection**: Use Fluid's auto-escaping (`{variable}` is safe)
- **SQL injection**: Use QueryBuilder or Repository methods only
- **Access control**: Leverage TYPO3 backend user permissions

## Additional Resources

### TYPO3 Documentation
- Main: https://docs.typo3.org/
- Extbase/Fluid: https://docs.typo3.org/m/typo3/book-extbasefluid/
- Testing: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/

### Project-Specific
- See `AGENTS.md` for detailed global rules
- See `Classes/AGENTS.md` for PHP backend specifics
- See `Tests/AGENTS.md` for testing infrastructure details
- Review `README.md` for extension overview and use cases

## Configuration Files

- **PHPStan**: `Build/phpstan.neon` (baseline: `Build/phpstan-baseline.neon`)
- **PHP-CS-Fixer**: `Build/.php-cs-fixer.dist.php`
- **Rector**: `Build/rector.php`
- **Fractor**: `Build/fractor.php`
- **PHPUnit**: `Build/UnitTests.xml`
- **PHPLint**: `Build/.phplint.yml`

## Important Notes

1. **Run from project root**: All composer commands must be run from the repository root
2. **Check existing AGENTS.md files**: The closest `AGENTS.md` to changed files provides scoped instructions
3. **TYPO3 v13 patterns**: Use modern patterns (constructor injection, final classes, readonly properties)
4. **PSR-4 autoloading**: `Netresearch\NrTextdb` → `Classes/`

## When Stuck

- Check similar files in the codebase for patterns
- Review existing controllers in `Controller/` or repositories in `Domain/Repository/`
- Consult TYPO3 documentation for framework-specific questions
- Read configuration files in `Build/` for tool setup
- Refer to `AGENTS.md` files for more detailed guidance
