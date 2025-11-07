<!-- Managed by agent: keep sections & order; edit content, not structure. Last updated: 2025-11-07 -->

# Classes/AGENTS.md

PHP backend classes for nr_textdb TYPO3 extension.

## 1. Overview

Backend implementation for editing TYPO3 translations. Architecture follows TYPO3 Extbase patterns:

- **Command/**: CLI commands (Symfony Console)
- **Controller/**: Backend module controllers (Extbase)
- **Domain/Model/**: Domain models
- **Domain/Repository/**: Data repositories
- **Service/**: Business logic services
- **ViewHelpers/**: Fluid template helpers

## 2. Setup & environment

**Prerequisites:**
- PHP 8.1+ (extension requirements: ext-zip, ext-simplexml, ext-libxml)
- TYPO3 13.4+
- Composer 2.x

**Installation:**
```bash
composer install
# Dependencies installed to .build/vendor
# Binaries in .build/bin
```

**TYPO3 Extension:**
- Extension key: `nr_textdb`
- Namespace: `Netresearch\NrTextdb`
- Web dir: `.build/public`

## 3. Build & tests

**Run from project root** (not from Classes/):

```bash
# Lint PHP files
composer ci:test:php:lint

# Static analysis with PHPStan
composer ci:test:php:phpstan

# Code style check
composer ci:test:php:cgl

# Apply code style fixes
composer ci:cgl

# Rector (PHP upgrade checks)
composer ci:test:php:rector
composer ci:rector  # apply

# Fractor (TYPO3-specific refactoring)
composer ci:test:php:fractor
composer ci:fractor  # apply

# Unit tests with coverage
composer ci:test:php:unit

# Run all checks
composer ci:test
```

## 4. Code style & conventions

**TYPO3 CGL compliance:**
- PSR-12 base + TYPO3 Coding Guidelines
- 4 spaces indentation
- Opening braces on same line for classes/methods
- Use `declare(strict_types=1);`
- Type hints everywhere (params, returns, properties)

**Extbase patterns:**
- Controllers extend `ActionController`
- Repositories extend `Repository`
- Models extend `AbstractEntity` or `AbstractValueObject`
- Use dependency injection (constructor injection)

**Naming:**
- Classes: `PascalCase`
- Methods: `camelCase`
- Properties: `camelCase`
- Constants: `UPPER_SNAKE_CASE`
- Database tables: `tx_nrtextdb_*`

**File structure:**
```php
<?php

declare(strict_types=1);

namespace Netresearch\NrTextdb\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Translation controller
 */
final class TranslationController extends ActionController
{
    public function __construct(
        private readonly TranslationRepository $repository
    ) {}

    public function listAction(): ResponseInterface
    {
        // Implementation
    }
}
```

## 5. Security & safety

- **Never commit credentials:** Check `.gitignore` for sensitive files
- **Validate user input:** Always sanitize in controllers before passing to services
- **XSS protection:** Use Fluid's escaping (`{variable}` auto-escapes, `{variable -> f:format.raw()}` for HTML)
- **SQL injection:** Use QueryBuilder or Repository methods, never raw SQL
- **Access control:** Use TYPO3 backend user permissions

## 6. PR/commit checklist

Before committing Classes/ changes:

- [ ] `composer ci:test:php:lint` passes
- [ ] `composer ci:test:php:phpstan` passes (level 9, strict rules)
- [ ] `composer ci:test:php:cgl` passes (or apply with `composer ci:cgl`)
- [ ] `composer ci:test:php:rector` passes
- [ ] `composer ci:test:php:fractor` passes
- [ ] Added/updated unit tests in `Tests/Unit/`
- [ ] Type hints on all methods/properties
- [ ] `declare(strict_types=1);` at top of file
- [ ] Constructor property promotion used
- [ ] No unused imports

## 7. Good vs. bad examples

### ✅ Good: Modern Extbase controller with DI

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrTextdb\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ExampleController extends ActionController
{
    public function __construct(
        private readonly ExampleRepository $repository,
        private readonly ExampleService $service
    ) {}

    public function listAction(): ResponseInterface
    {
        $items = $this->repository->findAll();
        $this->view->assign('items', $items);
        return $this->htmlResponse();
    }
}
```

### ❌ Bad: Missing types, inject annotation, no strict types

```php
<?php

namespace Netresearch\NrTextdb\Controller;

class ExampleController extends ActionController
{
    protected $repository;

    public function injectRepository($repository)
    {
        $this->repository = $repository;
    }

    public function listAction()
    {
        $items = $this->repository->findAll();
        $this->view->assign('items', $items);
    }
}
```

### ✅ Good: Repository with type safety

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Translation;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Translation>
 */
final class TranslationRepository extends Repository
{
    public function findByLanguage(string $language): array
    {
        $query = $this->createQuery();
        return $query->matching(
            $query->equals('language', $language)
        )->execute()->toArray();
    }
}
```

### ❌ Bad: No types, public properties on model

```php
<?php

class Translation extends AbstractEntity
{
    public $key;
    public $value;
    public $language;
}
```

### ✅ Good: Model with proper typing

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

final class Translation extends AbstractEntity
{
    public function __construct(
        private string $key = '',
        private string $value = '',
        private string $language = ''
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }
}
```

## 8. When stuck

**TYPO3 Documentation:**
- Main: https://docs.typo3.org/
- Extbase/Fluid: https://docs.typo3.org/m/typo3/book-extbasefluid/
- Core API: https://docs.typo3.org/m/typo3/reference-coreapi/

**Project-specific:**
- Check similar controllers in `Controller/` for patterns
- Review existing repositories in `Domain/Repository/`
- Read `README.md` for extension overview
- Check `Configuration/` for TCA/TypoScript examples

**Tools:**
- PHPStan baseline: `Build/phpstan-baseline.neon`
- PHP-CS-Fixer config: `Build/.php-cs-fixer.dist.php`
- Rector config: `Build/rector.php`
- Fractor config: `Build/fractor.php`

## 9. House Rules

**TYPO3-specific overrides:**
- Use TYPO3 v13 patterns (constructor injection, not `@inject`)
- Prefer `final` classes unless designed for extension
- Use `readonly` properties where possible (PHP 8.1+)
- Always return `ResponseInterface` from controller actions
- Use QueryBuilder or Repository methods, never plain SQL
- Database table names: `tx_nrtextdb_*` prefix
- TCA configuration in `Configuration/TCA/`
