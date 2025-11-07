.. include:: /Includes.rst.txt

.. _developer:

==================
Developer Manual
==================

.. _dev-overview:

Overview
========

This section covers integration of the TextDB extension into your TYPO3
project, including ViewHelpers, APIs, services, and extension points.

.. _dev-architecture:

Architecture
============

Domain Model
------------

The extension uses Extbase domain-driven design:

.. code-block:: none

   Domain Models:
   ├── Translation      # Main translation record
   ├── Component        # Logical grouping (website, shop, etc.)
   ├── Type             # Category (label, message, error)
   └── Environment      # Context (dev, staging, production)

   Repositories:
   ├── TranslationRepository
   ├── ComponentRepository
   ├── TypeRepository
   └── EnvironmentRepository

   Services:
   ├── TranslationService  # Core translation logic
   └── ImportService       # XLIFF import handling

   Controllers:
   └── TranslationController  # Backend module

Dependency Injection
--------------------

All services use constructor injection via `Configuration/Services.yaml`:

.. code-block:: yaml

   services:
       _defaults:
           autowire: true
           autoconfigure: true
           public: false

       Netresearch\NrTextdb\:
           resource: '../Classes/*'
           exclude: '../Classes/Domain/Model/*'

.. _dev-viewhelpers:

ViewHelpers
===========

TextDB ViewHelper
-----------------

Main ViewHelper for displaying translations in Fluid templates.

**Namespace:**

.. code-block:: html

   {namespace textdb=Netresearch\NrTextdb\ViewHelpers}

**Usage:**

.. code-block:: html

   <textdb:textdb
       component="website"
       type="label"
       placeholder="welcome.message"
       default="Welcome!"
   />

**Parameters:**

:`component`: (required) Component identifier
:`type`: (required) Type identifier (label, message, etc.)
:`placeholder`: (required) Translation key
:`default`: (optional) Fallback text if translation not found

**Output:**

Returns the translated text for the current page language.

**Example:**

.. code-block:: html

   <!-- Simple usage -->
   <h1><textdb:textdb component="website" type="label" placeholder="page.title" /></h1>

   <!-- With default value -->
   <p>
       <textdb:textdb
           component="website"
           type="message"
           placeholder="welcome.text"
           default="Welcome to our website!"
       />
   </p>

   <!-- Inline syntax -->
   {textdb:textdb(component: 'website', type: 'label', placeholder: 'button.submit')}

Translate ViewHelper
--------------------

Alternative ViewHelper compatible with f:translate interface.

**Usage:**

.. code-block:: html

   {namespace textdb=Netresearch\NrTextdb\ViewHelpers}

   <textdb:translate key="LLL:EXT:my_ext:path.to.key" />

**Component Configuration:**

Set component in controller:

.. code-block:: php

   use Netresearch\NrTextdb\ViewHelpers\TranslateViewHelper;

   class MyController extends ActionController
   {
       public function initializeAction(): void
       {
           TranslateViewHelper::$component = 'my-component';
       }
   }

**Auto-Import Feature:**

When enabled (`createIfMissing = 1`), this ViewHelper will:

1. Load translation from XLIFF file on first request
2. Create TextDB record automatically
3. Use TextDB record on subsequent requests

.. _dev-services:

Services API
============

TranslationService
------------------

Core service for translation management.

**Injection:**

.. code-block:: php

   use Netresearch\NrTextdb\Service\TranslationService;

   class MyClass
   {
       public function __construct(
           private readonly TranslationService $translationService
       ) {}
   }

**Methods:**

getTranslation()
~~~~~~~~~~~~~~~~

.. code-block:: php

   public function getTranslation(
       string $component,
       string $type,
       string $placeholder,
       int $languageUid = 0
   ): ?Translation

Get translation record.

**Example:**

.. code-block:: php

   $translation = $this->translationService->getTranslation(
       component: 'website',
       type: 'label',
       placeholder: 'welcome.message',
       languageUid: 1
   );

   if ($translation) {
       echo $translation->getValue();
   }

createTranslation()
~~~~~~~~~~~~~~~~~~~

.. code-block:: php

   public function createTranslation(
       string $component,
       string $type,
       string $placeholder,
       string $value,
       int $languageUid = 0
   ): Translation

Create new translation record.

**Example:**

.. code-block:: php

   $translation = $this->translationService->createTranslation(
       component: 'shop',
       type: 'label',
       placeholder: 'cart.add',
       value: 'Add to cart',
       languageUid: 0
   );

ImportService
-------------

Service for importing XLIFF files.

**Injection:**

.. code-block:: php

   use Netresearch\NrTextdb\Service\ImportService;

   public function __construct(
       private readonly ImportService $importService
   ) {}

**Methods:**

importXliffFile()
~~~~~~~~~~~~~~~~~

.. code-block:: php

   public function importXliffFile(
       string $filePath,
       bool $overwriteExisting = false
   ): array

Import translations from XLIFF file.

**Returns:** Array with import statistics

.. code-block:: php

   [
       'imported' => 150,
       'updated' => 25,
       'skipped' => 10,
       'errors' => []
   ]

**Example:**

.. code-block:: php

   $result = $this->importService->importXliffFile(
       filePath: '/path/to/translations.xlf',
       overwriteExisting: true
   );

   echo "Imported: {$result['imported']} translations";

.. _dev-repositories:

Repositories
============

TranslationRepository
---------------------

Repository for translation records.

**Custom Methods:**

findByComponentAndType()
~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

   public function findByComponentAndType(
       Component $component,
       Type $type
   ): QueryResultInterface

Find all translations for component and type.

findByPlaceholder()
~~~~~~~~~~~~~~~~~~~

.. code-block:: php

   public function findByPlaceholder(
       string $placeholder,
       int $languageUid = 0
   ): ?Translation

Find translation by placeholder key.

**Example:**

.. code-block:: php

   use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;

   public function __construct(
       private readonly TranslationRepository $repository
   ) {}

   public function myAction(): void
   {
       $translations = $this->repository->findByComponentAndType(
           $component,
           $type
       );

       foreach ($translations as $translation) {
           // Process translations
       }
   }

.. _dev-domain-models:

Domain Models
=============

Translation Model
-----------------

Main translation entity.

**Properties:**

.. code-block:: php

   class Translation extends AbstractEntity
   {
       protected string $placeholder = '';
       protected string $value = '';
       protected ?Component $component = null;
       protected ?Type $type = null;
       protected ?Environment $environment = null;
       protected int $sysLanguageUid = 0;
   }

**Getters/Setters:**

.. code-block:: php

   $translation = new Translation();
   $translation->setPlaceholder('welcome.message');
   $translation->setValue('Welcome!');
   $translation->setComponent($component);
   $translation->setType($type);

   echo $translation->getValue(); // "Welcome!"

Component Model
---------------

.. code-block:: php

   class Component extends AbstractEntity
   {
       protected string $name = '';
       protected string $identifier = '';
   }

Type Model
----------

.. code-block:: php

   class Type extends AbstractEntity
   {
       protected string $name = '';
       protected string $identifier = '';
   }

Environment Model
-----------------

.. code-block:: php

   class Environment extends AbstractEntity
   {
       protected string $name = '';
       protected string $identifier = '';
   }

.. _dev-commands:

Console Commands
================

ImportCommand
-------------

CLI command for importing translations.

**Location:** `Classes/Command/ImportCommand.php`

**Usage:**

.. code-block:: bash

   vendor/bin/typo3 nr_textdb:import [file-path]

**Configuration:**

.. code-block:: yaml

   # Configuration/Services.yaml
   Netresearch\NrTextdb\Command\ImportCommand:
       tags:
           - name: 'console.command'
             command: 'nr_textdb:import'
             description: 'Imports textdb records from language files'
             schedulable: false

**Creating Custom Commands:**

.. code-block:: php

   namespace MyVendor\MyExt\Command;

   use Netresearch\NrTextdb\Service\ImportService;
   use Symfony\Component\Console\Command\Command;
   use Symfony\Component\Console\Input\InputInterface;
   use Symfony\Component\Console\Output\OutputInterface;

   class CustomImportCommand extends Command
   {
       public function __construct(
           private readonly ImportService $importService
       ) {
           parent::__construct();
       }

       protected function execute(
           InputInterface $input,
           OutputInterface $output
       ): int {
           $files = glob('/path/to/translations/*.xlf');

           foreach ($files as $file) {
               $result = $this->importService->importXliffFile($file);
               $output->writeln("Imported {$result['imported']} from {$file}");
           }

           return Command::SUCCESS;
       }
   }

.. _dev-events:

Events & Hooks
==============

The extension currently uses standard Extbase/TYPO3 patterns. Future versions
may add PSR-14 events for extensibility.

**Potential Event Points:**

* Before/After translation import
* Before/After translation creation
* Translation retrieval (for caching)
* Export generation

.. _dev-api-examples:

API Examples
============

Example 1: Programmatic Translation Management
-----------------------------------------------

.. code-block:: php

   use Netresearch\NrTextdb\Domain\Model\Translation;
   use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
   use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
   use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
   use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

   class TranslationManager
   {
       public function __construct(
           private readonly TranslationRepository $translationRepository,
           private readonly ComponentRepository $componentRepository,
           private readonly TypeRepository $typeRepository,
           private readonly PersistenceManager $persistenceManager
       ) {}

       public function createBulkTranslations(array $data): void
       {
           $component = $this->componentRepository->findByIdentifier('website');
           $type = $this->typeRepository->findByIdentifier('label');

           foreach ($data as $key => $value) {
               $translation = new Translation();
               $translation->setPlaceholder($key);
               $translation->setValue($value);
               $translation->setComponent($component);
               $translation->setType($type);

               $this->translationRepository->add($translation);
           }

           $this->persistenceManager->persistAll();
       }
   }

Example 2: Custom Export Functionality
---------------------------------------

.. code-block:: php

   use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;

   class CustomExporter
   {
       public function __construct(
           private readonly TranslationRepository $repository
       ) {}

       public function exportToJson(string $component): string
       {
           $translations = $this->repository->findByComponent($component);

           $data = [];
           foreach ($translations as $translation) {
               $data[$translation->getPlaceholder()] = $translation->getValue();
           }

           return json_encode($data, JSON_PRETTY_PRINT);
       }

       public function exportToCsv(string $component): string
       {
           $translations = $this->repository->findByComponent($component);

           $csv = "Placeholder,Value,Language\n";
           foreach ($translations as $translation) {
               $csv .= sprintf(
                   "%s,%s,%d\n",
                   $translation->getPlaceholder(),
                   $translation->getValue(),
                   $translation->getSysLanguageUid()
               );
           }

           return $csv;
       }
   }

Example 3: Frontend Integration
--------------------------------

.. code-block:: php

   use Netresearch\NrTextdb\Service\TranslationService;
   use TYPO3\CMS\Core\Context\Context;

   class FrontendTranslations
   {
       public function __construct(
           private readonly TranslationService $translationService,
           private readonly Context $context
       ) {}

       public function getTranslatedMenu(array $menuItems): array
       {
           $languageUid = $this->context->getPropertyFromAspect(
               'language',
               'id'
           );

           foreach ($menuItems as &$item) {
               $translation = $this->translationService->getTranslation(
                   component: 'menu',
                   type: 'label',
                   placeholder: $item['key'],
                   languageUid: $languageUid
               );

               $item['title'] = $translation?->getValue() ?? $item['title'];
           }

           return $menuItems;
       }
   }

.. _dev-testing:

Testing
=======

Unit Testing
------------

Example unit test for Translation model:

.. code-block:: php

   namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

   use Netresearch\NrTextdb\Domain\Model\Translation;
   use PHPUnit\Framework\Attributes\CoversClass;
   use PHPUnit\Framework\Attributes\Test;
   use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

   #[CoversClass(Translation::class)]
   final class TranslationTest extends UnitTestCase
   {
       private Translation $subject;

       protected function setUp(): void
       {
           parent::setUp();
           $this->subject = new Translation();
       }

       #[Test]
       public function setValueSetsValue(): void
       {
           $value = 'Test translation';
           $this->subject->setValue($value);

           self::assertSame($value, $this->subject->getValue());
       }
   }

Functional Testing
------------------

Example functional test for repository:

.. code-block:: php

   namespace Netresearch\NrTextdb\Tests\Functional\Domain\Repository;

   use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
   use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

   final class TranslationRepositoryTest extends FunctionalTestCase
   {
       protected array $testExtensionsToLoad = [
           'typo3conf/ext/nr_textdb',
       ];

       private TranslationRepository $subject;

       protected function setUp(): void
       {
           parent::setUp();
           $this->subject = $this->get(TranslationRepository::class);
           $this->importCSVDataSet(__DIR__ . '/Fixtures/translations.csv');
       }

       public function testFindAllReturnsAllTranslations(): void
       {
           $result = $this->subject->findAll();
           self::assertCount(10, $result);
       }
   }

.. _dev-extension-points:

Extension Points
================

Extending the TranslationService
---------------------------------

.. code-block:: php

   namespace MyVendor\MyExt\Service;

   use Netresearch\NrTextdb\Service\TranslationService;

   class ExtendedTranslationService extends TranslationService
   {
       public function getTranslation(
           string $component,
           string $type,
           string $placeholder,
           int $languageUid = 0
       ): ?Translation {
           // Add custom caching
           $cacheKey = "{$component}_{$type}_{$placeholder}_{$languageUid}";
           if ($cached = $this->cache->get($cacheKey)) {
               return $cached;
           }

           $translation = parent::getTranslation(
               $component,
               $type,
               $placeholder,
               $languageUid
           );

           $this->cache->set($cacheKey, $translation);
           return $translation;
       }
   }

Custom ViewHelper
-----------------

.. code-block:: php

   namespace MyVendor\MyExt\ViewHelpers;

   use Netresearch\NrTextdb\Service\TranslationService;
   use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

   class CustomTranslateViewHelper extends AbstractViewHelper
   {
       public function __construct(
           private readonly TranslationService $translationService
       ) {}

       public function initializeArguments(): void
       {
           $this->registerArgument('key', 'string', 'Translation key', true);
       }

       public function render(): string
       {
           $parts = explode('.', $this->arguments['key']);
           $component = $parts[0] ?? 'default';
           $placeholder = $parts[1] ?? $this->arguments['key'];

           $translation = $this->translationService->getTranslation(
               component: $component,
               type: 'label',
               placeholder: $placeholder
           );

           return $translation?->getValue() ?? $this->arguments['key'];
       }
   }

.. _dev-best-practices:

Best Practices
==============

Naming Conventions
------------------

**Components:**
  * Use lowercase with hyphens: `website`, `shop-cart`, `user-portal`
  * Be descriptive and consistent

**Types:**
  * Standard types: `label`, `message`, `error`, `notification`
  * Use singular form: `button` not `buttons`

**Placeholders:**
  * Use dot notation: `page.title`, `button.submit`, `error.validation.email`
  * Be hierarchical and descriptive

Performance
-----------

* Cache translation lookups in frontend
* Use repository methods instead of manual queries
* Batch import/export operations for large datasets
* Consider Redis/Memcached for high-traffic sites

Code Quality
------------

* Use strict types: `declare(strict_types=1)`
* Type-hint all parameters and return values
* Write unit tests for business logic
* Document public APIs with PHPDoc

Security
--------

* Escape translation output in templates: `{translation -> f:format.htmlspecialchars()}`
* Validate XLIFF files before import
* Use prepared statements (Extbase does this automatically)
* Restrict file upload permissions

.. _dev-troubleshooting:

Developer Troubleshooting
==========================

ViewHelper Not Working
-----------------------

.. code-block:: php

   // Check namespace registration
   {namespace textdb=Netresearch\NrTextdb\ViewHelpers}

   // Verify storage PID configuration
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb']['textDbPid']

Service Injection Failing
--------------------------

.. code-block:: yaml

   # Ensure Services.yaml is configured
   services:
       _defaults:
           autowire: true

       MyVendor\MyExt\MyClass:
           public: true  # If accessed via GeneralUtility::makeInstance

Translation Not Found
---------------------

.. code-block:: php

   // Debug translation lookup
   $translation = $this->translationService->getTranslation(
       component: 'website',
       type: 'label',
       placeholder: 'test.key',
       languageUid: 0
   );

   if (!$translation) {
       // Check: Component exists?
       // Check: Type exists?
       // Check: Record in correct storage folder?
       // Check: createIfMissing enabled?
   }

.. _dev-resources:

Resources
=========

* **GitHub Repository:** https://github.com/netresearch/t3x-nr-textdb
* **TYPO3 Extension Repository:** https://extensions.typo3.org/extension/nr_textdb
* **TYPO3 Core API:** https://docs.typo3.org/m/typo3/reference-coreapi/
* **Extbase Documentation:** https://docs.typo3.org/m/typo3/book-extbasefluid/
* **Fluid ViewHelper Reference:** https://docs.typo3.org/other/typo3/view-helper-reference/
