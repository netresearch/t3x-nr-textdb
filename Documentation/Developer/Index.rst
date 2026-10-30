.. SPDX-License-Identifier: GPL-3.0-or-later
.. SPDX-FileCopyrightText: Netresearch DTT GmbH

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

.. _dev-localization:

Localization Infrastructure
============================

The extension includes a robust localization infrastructure supporting **23 languages** for the backend interface.

Supported Languages
-------------------

**European (13):**
Afrikaans (af), Czech (cs), Danish (da), German (de), Spanish (es), Finnish (fi), French (fr), Italian (it), Dutch (nl), Norwegian (no), Polish (pl), Portuguese (pt), Swedish (sv)

**Asian & African (10):**
Arabic (ar), Hindi (hi), Indonesian (id), Japanese (ja), Korean (ko), Russian (ru), Swahili (sw), Thai (th), Vietnamese (vi), Chinese (zh)

Technical Implementation
------------------------

**XLIFF 1.2 Standard**

All translation files follow XLIFF 1.2 specification:

.. code-block:: xml

   <?xml version="1.0" encoding="utf-8"?>
   <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
       <file source-language="en" datatype="plaintext" original="EXT:nr_textdb/Resources/Private/Language/locallang.xlf" date="..." product-name="nr_textdb">
           <header/>
           <body>
               <trans-unit id="module.title" resname="module.title" translate="no">
                   <source>Netresearch</source>
               </trans-unit>
           </body>
       </file>
   </xliff>

**Proper Names Protection**

Brand names are marked as untranslatable using ``translate="no"`` attribute:

.. code-block:: xml

   <trans-unit id="module.title" resname="module.title" translate="no">
       <source>Netresearch</source>
   </trans-unit>

This ensures "Netresearch" and "TextDb" remain unchanged across all translations.

**UTF-8 Encoding**

All language files use UTF-8 encoding to support non-Latin scripts:

* **Right-to-left scripts**: Arabic (العربية)
* **Asian ideographs**: Chinese (中文), Japanese (日本語), Korean (한국어)
* **Indic scripts**: Hindi (हिन्दी), Thai (ไทย)

File Structure
--------------

Each language has 5 translation files:

.. code-block:: none

   Resources/Private/Language/
   ├── {lang}.locallang.xlf           # General interface labels
   ├── {lang}.locallang_db.xlf        # Database field labels
   ├── {lang}.locallang_mod.xlf       # Backend module labels
   ├── {lang}.locallang_mod_sync.xlf  # Sync module labels
   └── {lang}.locallang_mod_textdb.xlf # TextDB module labels

Total: 116 XLIFF files (23 languages × 5 files + 1 source file per type)

Community Translation Workflow
-------------------------------

The extension integrates with TYPO3's centralized Crowdin translation system:

**Configuration** (``crowdin.yml``):

.. code-block:: yaml

   files:
     - source: Resources/Private/Language/locallang.xlf
       translation: Resources/Private/Language/%two_letters_code%.locallang.xlf
     - source: Resources/Private/Language/locallang_db.xlf
       translation: Resources/Private/Language/%two_letters_code%.locallang_db.xlf
     # ... additional file types

**Translation Process:**

1. Translators contribute via https://crowdin.com/project/typo3-cms
2. TYPO3 translation coordinators review submissions
3. Approved translations automatically sync to repository
4. Changes included in next extension release

**Adding New Languages:**

To add a new language:

1. Create language files following naming convention: ``{lang}.locallang*.xlf``
2. Copy structure from English source files
3. Update ``crowdin.yml`` with new language patterns
4. Submit to Crowdin for community translation

See `CONTRIBUTING.md <https://github.com/netresearch/t3x-nr-textdb/blob/main/CONTRIBUTING.md#how-to-contribute-translations>`_ for detailed translation contribution guidelines.

.. _dev-viewhelpers:

ViewHelpers
===========

TextDB ViewHelper
-----------------

.. php:class:: TextdbViewHelper

   Main ViewHelper for displaying translations in Fluid templates.

   :Namespace: ``Netresearch\NrTextdb\ViewHelpers``
   :Extends: ``TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper``

   **Usage:**

   .. code-block:: html

      {namespace textdb=Netresearch\NrTextdb\ViewHelpers}

      <textdb:textdb
          component="website"
          type="label"
          placeholder="welcome.message"
      />

   **Parameters:**

   .. confval:: component
      :name: textdb-viewhelper-component
      :type: string
      :Required: true

      Component identifier for organizing translations (e.g., "website", "shop", "checkout").

   .. confval:: type
      :name: textdb-viewhelper-type
      :type: string
      :Required: true
      :Default: ``P``

      Type identifier categorizing the translation (e.g., "label", "message", "error", "button").

   .. confval:: placeholder
      :name: textdb-viewhelper-placeholder
      :type: string
      :Required: true

      Unique translation key within the component and type context.

   .. confval:: environment
      :name: textdb-viewhelper-environment
      :type: string
      :Required: false
      :Default: ``default``

      Environment name for contextual translations.

   **Output:**

Returns the translated text for the current page language. When that
language has no usable entry, the ViewHelper falls back as described in
:ref:`dev-viewhelpers-fallback`.

**Example:**

.. code-block:: html

   <!-- Simple usage -->
   <h1><textdb:textdb component="website" type="label" placeholder="page.title" /></h1>

   <!-- With explicit environment -->
   <p>
       <textdb:textdb
           component="website"
           type="message"
           placeholder="welcome.text"
           environment="production"
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

.. _dev-viewhelpers-fallback:

Lookup Order and Language Fallback
----------------------------------

Both ViewHelpers look for a value in this order and render the first one
they find:

1. The TextDB entry in the current language. An entry with an empty value,
   or one that was auto-created and never edited, does not count. A value
   that equals its key (``Login`` → ``Login``) does count.
2. ``textdb:translate`` only: the label from the language file (XLIFF).
   This is the label in the current language or, when the file has no
   translation for the key, the file's source label. Step 3 therefore runs
   for ``textdb:translate`` only when the key is in no language file, or
   its label is empty.
3. The TextDB entries in the languages of the site language's fallback
   chain (``fallbackLanguageIds`` in the site configuration), in their
   order, then the entry in the default language (``0``). The same rules as
   in step 1 apply to each entry.
4. The key itself.

TYPO3 passes the ``fallbackLanguageIds`` on for every ``fallbackType``,
so step 3 follows them for ``strict``, ``fallback`` and ``free`` alike. A
site language without fallback languages still falls back to the default
language in step 3.

When ``createIfMissing`` is enabled, step 1 creates the missing entry in
the current language, as before. Steps 3 and 4 never create translation
records.

For example, a German page (language ``1``) whose site language falls back
to ``0`` renders the English TextDB entry when no German entry exists. With
``textdb:translate``, a label in the language file wins over that English
entry, whether it is the German label or the file's source label.

.. _dev-services:

Services API
============

TranslationService
------------------

.. php:class:: TranslationService

   Core service for translation management and retrieval.

   :Namespace: ``Netresearch\NrTextdb\Service``

   **Dependency Injection:**

   .. code-block:: php

      use Netresearch\NrTextdb\Service\TranslationService;

      class MyClass
      {
          public function __construct(
              private readonly TranslationService $translationService
          ) {}
      }

   **Methods:**

   translate()
   ~~~~~~~~~~~

   .. code-block:: php

      public function translate(
          string $placeholder,
          string $typeName,
          string $componentName,
          string $environmentName,
      ): string

   Retrieves a translated string from the database. If ``createIfMissing`` is enabled
   and the translation doesn't exist, it will be auto-created with a placeholder value.

   :param string $placeholder: Translation key
   :param string $typeName: Type name (e.g., "label", "button")
   :param string $componentName: Component name (e.g., "website", "checkout")
   :param string $environmentName: Environment name (e.g., "default")
   :returns: The translated value, or the placeholder if not found

   **Example:**

   .. code-block:: php

      $value = $this->translationService->translate(
          placeholder: 'welcome.message',
          typeName: 'label',
          componentName: 'website',
          environmentName: 'default',
      );

   ``translate()`` looks up the current language only. It returns the key
   both when there is no usable entry and when the value equals the key; use
   ``findTranslation()`` to tell the two apart.

   findTranslation()
   ~~~~~~~~~~~~~~~~~

   .. code-block:: php

      public function findTranslation(
          string $placeholder,
          string $typeName,
          string $componentName,
          string $environmentName,
      ): ?string

   Same lookup as ``translate()``, including the auto-creation with
   ``createIfMissing``, but returns ``null`` when the current language has
   no usable entry: none, an empty value, or an auto-created one. The
   ViewHelpers use it for step 1 of :ref:`dev-viewhelpers-fallback`.

   :returns: The value, or ``null`` if the current language has no usable entry

   translateFromFallbackLanguages()
   ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

   .. code-block:: php

      public function translateFromFallbackLanguages(
          string $placeholder,
          string $typeName,
          string $componentName,
          string $environmentName,
      ): ?string

   Returns the value of the first usable entry along the fallback chain of
   the current language, then in the default language, as in step 3 of
   :ref:`dev-viewhelpers-fallback`. The current language itself is not
   searched. The method never creates translation records.

   :param string $placeholder: Translation key
   :param string $typeName: Type name (e.g., "label", "button")
   :param string $componentName: Component name (e.g., "website", "checkout")
   :param string $environmentName: Environment name (e.g., "default")
   :returns: The value, or ``null`` if no fallback language has one

   **Example:**

   .. code-block:: php

      $value = $this->translationService->findTranslation('welcome.message', 'label', 'website', 'default')
          ?? $this->translationService->translateFromFallbackLanguages('welcome.message', 'label', 'website', 'default')
          ?? 'welcome.message';

   createTranslation()
   ~~~~~~~~~~~~~~~~~~~~

   .. code-block:: php

      public function createTranslation(
          Environment $environment,
          Component $component,
          Type $type,
          string $placeholder,
          int $sysLanguageUid = 0,
          string $value = '',
      ): Translation

   Creates a new translation record in the database.

   **Example:**

   .. code-block:: php

      $translation = $this->translationService->createTranslation(
          environment: $environment,
          component: $component,
          type: $type,
          placeholder: 'cart.add',
          sysLanguageUid: 0,
          value: 'Add to cart',
      );

   getAllLanguages()
   ~~~~~~~~~~~~~~~~~

   .. code-block:: php

      public function getAllLanguages(): array

   Returns all configured site languages from the first available site.

   :returns: Array of ``SiteLanguage`` objects

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

The counts, the error messages and the keys of skipped units collect on an
``ImportResult`` that the caller creates and passes in. One result can go
through several calls; read the totals at the end.

importFile()
~~~~~~~~~~~~

.. code-block:: php

   public function importFile(
       string $file,
       bool $forceUpdate,
       ImportResult $result,
   ): void

Imports an XLIFF file. The language comes from the file name: ``de.textdb_x.xlf``
is German, a name without a language prefix is the default language.
``$forceUpdate`` overwrites existing records. The method reads the file with
``loadFile()`` and writes it with ``importEntries()``.

It throws when the file cannot be read, when a key lacks a component, type or
placeholder, and when a write fails. A file that fails imports nothing.

**Example:**

.. code-block:: php

   use Netresearch\NrTextdb\Service\ImportResult;

   $result = new ImportResult();

   $this->importService->importFile('/path/to/de.textdb_website.xlf', true, $result);

   echo sprintf('Imported: %d, Updated: %d', $result->getImported(), $result->getUpdated());

   foreach ($result->getErrors() as $error) {
       echo $error, PHP_EOL;
   }

loadFile()
~~~~~~~~~~

.. code-block:: php

   public function loadFile(string $file, string $locale): LoadedXliffFile

Reads an XLIFF file with TYPO3's ``XliffLoader`` and returns a
``LoadedXliffFile`` with three public properties:

* ``entries``: the messages, keyed ``component|type|placeholder``
* ``unapprovedKeys``: keys of the units left out because they are not approved
* ``partiallyApprovedKeys``: keys of the plural units kept without their forms
  that are not approved. TYPO3 keeps the first two forms of a plural unit
  only, so an unapproved third or later form does not show here.

While ``$GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']``
is enabled, the file is read a second time with the setting disabled to find
these keys; the setting is restored afterwards. While it is disabled, both
lists are empty. Throws ``InvalidXmlFileException`` or
``InvalidResourceException`` when the file cannot be read.

importEntries()
~~~~~~~~~~~~~~~

.. code-block:: php

   public function importEntries(
       array $entries,
       int $languageUid,
       bool $forceUpdate,
       ImportResult $result,
       array $unapprovedKeys = [],
       array $partiallyApprovedKeys = [],
   ): void

Imports loaded messages for one language in one database transaction. Every
key is checked before the first write. A value that is empty or only
whitespace is skipped. A key in ``$partiallyApprovedKeys`` is recorded only
when its entry is written, that is imported or updated; an entry without text
is recorded as such, and an existing entry that is kept is not recorded. The
counts and the skipped keys reach ``$result`` once
the transaction has committed; after a rollback only the error messages do,
and the exception is thrown on.

importEntry()
~~~~~~~~~~~~~

.. code-block:: php

   public function importEntry(
       int $languageUid,
       ?string $componentName,
       ?string $typeName,
       string $placeholder,
       string $value,
       bool $forceUpdate,
       ImportResult $result,
   ): bool

Imports a single translation entry and returns whether it imported or updated
it. It records an error on ``$result`` instead of throwing, and returns
``false`` then.

ImportResult
~~~~~~~~~~~~

Read the totals with ``getImported()``, ``getUpdated()`` and ``getErrors()``,
and the skipped units with ``getSkippedUnapproved()``, ``getSkippedEmpty()``
and ``getPartiallyApproved()``. Each returns the keys once. ``add()`` adds
another result to this one.

.. _dev-repositories:

Repositories
============

TranslationRepository
---------------------

Repository for translation records.

**Custom Methods:**

findAllByComponentTypePlaceholderValueAndLanguage()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

   public function findAllByComponentTypePlaceholderValueAndLanguage(
       int $component = 0,
       int $type = 0,
       ?string $placeholder = null,
       ?string $value = null,
       int $languageId = 0,
   ): QueryResultInterface

Find all translations filtered by component UID, type UID, placeholder substring,
value substring, and/or language ID. All parameters are optional filters.

findByEnvironmentComponentTypePlaceholderAndLanguage()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

   public function findByEnvironmentComponentTypePlaceholderAndLanguage(
       Environment $environment,
       Component $component,
       Type $type,
       string $placeholder,
       int $languageUid,
   ): ?Translation

Find a single translation by exact environment, component, type, placeholder, and language.

findByEnvironmentComponentTypeAndPlaceholder()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php

   public function findByEnvironmentComponentTypeAndPlaceholder(
       Environment $environment,
       Component $component,
       Type $type,
       string $placeholder,
   ): ?Translation

Find the default language (``sys_language_uid = 0``) translation for the given criteria.

**Example:**

.. code-block:: php

   use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;

   public function __construct(
       private readonly TranslationRepository $repository
   ) {}

   public function myAction(): void
   {
       $translations = $this->repository
           ->findAllByComponentTypePlaceholderValueAndLanguage(
               component: $componentUid,
               type: $typeUid,
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

   class Component extends AbstractValueObject
   {
       protected string $name = '';
   }

Type Model
----------

.. code-block:: php

   class Type extends AbstractValueObject
   {
       protected string $name = '';
   }

Environment Model
-----------------

.. code-block:: php

   class Environment extends AbstractValueObject
   {
       protected string $name = '';
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

   vendor/bin/typo3 nr_textdb:import [extensionKey] [--override|-o]

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

   use Netresearch\NrTextdb\Service\ImportResult;
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
               $result = new ImportResult();

               try {
                   $this->importService->importFile($file, false, $result);
               } catch (\Throwable $exception) {
                   $output->writeln('<error>' . $exception->getMessage() . '</error>');
               }

               $output->writeln(sprintf(
                   'Imported: %d, Updated: %d from %s',
                   $result->getImported(),
                   $result->getUpdated(),
                   $file,
               ));
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
           $component = $this->componentRepository->findByName('website');
           $type = $this->typeRepository->findByName('label');

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

       public function exportToJson(int $componentUid): string
       {
           $translations = $this->repository->findAllByComponentTypePlaceholderValueAndLanguage(component: $componentUid);

           $data = [];
           foreach ($translations as $translation) {
               $data[$translation->getPlaceholder()] = $translation->getValue();
           }

           return json_encode($data, JSON_PRETTY_PRINT);
       }

       public function exportToCsv(int $componentUid): string
       {
           $translations = $this->repository->findAllByComponentTypePlaceholderValueAndLanguage(component: $componentUid);

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
               $item['title'] = $this->translationService->translate(
                   placeholder: $item['key'],
                   typeName: 'label',
                   componentName: 'menu',
                   environmentName: 'default',
               );
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
       public function translate(
           string $placeholder,
           string $typeName,
           string $componentName,
           string $environmentName,
       ): string {
           // Add custom caching
           $cacheKey = "{$componentName}_{$typeName}_{$placeholder}_{$environmentName}";
           if ($cached = $this->cache->get($cacheKey)) {
               return $cached;
           }

           $result = parent::translate(
               $placeholder,
               $typeName,
               $componentName,
               $environmentName,
           );

           $this->cache->set($cacheKey, $result);
           return $result;
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

           return $this->translationService->translate(
               placeholder: $placeholder,
               typeName: 'label',
               componentName: $component,
               environmentName: 'default',
           );
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
   $value = $this->translationService->translate(
       placeholder: 'test.key',
       typeName: 'label',
       componentName: 'website',
       environmentName: 'default',
   );

   // If $value equals the placeholder, the translation was not found.
   // Check:
   // - Component 'website' exists in the configured storage folder?
   // - Type 'label' exists?
   // - Environment 'default' exists?
   // - createIfMissing enabled in extension configuration?

.. _dev-resources:

Resources
=========

* **GitHub Repository:** https://github.com/netresearch/t3x-nr-textdb
* **TYPO3 Extension Repository:** https://extensions.typo3.org/extension/nr_textdb
* **TYPO3 Core API:** https://docs.typo3.org/m/typo3/reference-coreapi/
* **Extbase Documentation:** https://docs.typo3.org/m/typo3/book-extbasefluid/
* **Fluid ViewHelper Reference:** https://docs.typo3.org/other/typo3/view-helper-reference/
