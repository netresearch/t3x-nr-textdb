.. include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

.. _extension-configuration:

Extension Configuration
=======================

Configure the extension via **Admin Tools > Settings > Extension Configuration > nr_textdb**

Available Settings
------------------

.. confval:: textDbPid

   :type: string (integer)
   :Default: (empty)
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb']['textDbPid']

   The Page ID (PID) where TextDB translations should be stored.

   This should point to a dedicated storage folder in your page tree.

   .. important::
      This setting is required for the extension to function properly.

   Example
   ~~~~~~~

   Configure in **Admin Tools > Settings > Extension Configuration > nr_textdb**:

   .. code-block:: none

      textDbPid = 123

.. confval:: createIfMissing

   :type: boolean
   :Default: true
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb']['createIfMissing']

   Automatically create translation records if they don't exist when requested
   via ViewHelpers.

   When enabled, missing translations will be auto-created with placeholder text.
   When disabled, only existing translations will be displayed.

   .. tip::
      Enable this during development to quickly identify missing translations.
      Disable in production if you want strict translation management.

   Example
   ~~~~~~~

   .. code-block:: php
      :caption: config/system/additional.php

      // Development: auto-create missing translations
      $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb']['createIfMissing'] = true;

      // Production: strict mode
      $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb']['createIfMissing'] = false;

.. _typoscript-configuration:

TypoScript Configuration
========================

The extension includes default TypoScript configuration that is automatically loaded.

Constants
---------

The extension provides the following TypoScript constants:

.. code-block:: typoscript

   # File: Configuration/TypoScript/constants.typoscript
   plugin.tx_nrtextdb {
       settings {
           storagePid = {$plugin.tx_nrtextdb.settings.storagePid}
       }
   }

Setup
-----

The extension setup is automatically included:

.. code-block:: typoscript

   # File: Configuration/TypoScript/setup.typoscript
   plugin.tx_nrtextdb {
       persistence {
           storagePid = {$plugin.tx_nrtextdb.settings.storagePid}
       }
   }

.. _backend-module-configuration:

Backend Module Configuration
=============================

Access Control
--------------

The TextDB backend module requires appropriate permissions:

**Module Access:**

1. Navigate to **System > Backend Users > [User Group]**
2. Go to tab **Access Lists**
3. Under **Modules**, check:
   * Netresearch
   * Netresearch TextDB

**Record Permissions:**

Grant access to TextDB tables:

* `tx_nrtextdb_domain_model_translation`
* `tx_nrtextdb_domain_model_component`
* `tx_nrtextdb_domain_model_type`
* `tx_nrtextdb_domain_model_environment`

Module Customization
--------------------

The backend module is configured in `Configuration/Backend/Modules.php`:

.. code-block:: php

   // Parent module (Netresearch)
   'netresearch_module' => [
       'labels' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod.xlf',
       'iconIdentifier' => 'extension-netresearch-module',
       'position' => ['after' => 'web'],
   ],

   // TextDB submodule
   'netresearch_textdb' => [
       'parent' => 'netresearch_module',
       'access' => 'user',
       'iconIdentifier' => 'extension-netresearch-textdb',
       'path' => '/module/netresearch/textdb',
       'labels' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod_textdb.xlf',
       'extensionName' => 'NrTextdb',
       'controllerActions' => [
           TranslationController::class => [
               'list', 'translated', 'translateRecord',
               'import', 'export',
           ],
       ],
   ],

.. _service-configuration:

Service Configuration
=====================

Dependency Injection
--------------------

Services are configured in `Configuration/Services.yaml`:

.. code-block:: yaml

   services:
       _defaults:
           autowire: true
           autoconfigure: true
           public: false

       Netresearch\NrTextdb\:
           resource: '../Classes/*'
           exclude: '../Classes/Domain/Model/*'

       # Public services
       Netresearch\NrTextdb\Service\TranslationService:
           public: true

       # Console commands
       Netresearch\NrTextdb\Command\ImportCommand:
           tags:
               - name: 'console.command'
                 command: 'nr_textdb:import'
                 description: 'Imports textdb records from language files'
                 schedulable: false

.. _database-configuration:

Database Configuration
======================

Table Configuration (TCA)
--------------------------

The extension defines TCA for four tables:

Translation Records
~~~~~~~~~~~~~~~~~~~

.. code-block:: php

   // Configuration/TCA/tx_nrtextdb_domain_model_translation.php
   return [
       'ctrl' => [
           'title' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation',
           'label' => 'placeholder',
           'languageField' => 'sys_language_uid',
           'transOrigPointerField' => 'l10n_parent',
           'searchFields' => 'value,placeholder',
           // ... additional configuration
       ],
       'columns' => [
           'value' => [
               'label' => 'Translation Value',
               'config' => [
                   'type' => 'text',
                   'required' => true,
               ],
           ],
           // ... additional fields
       ],
   ];

Storage Configuration
---------------------

**Recommended Setup:**

1. Create a dedicated storage folder at root level
2. Set folder type to "Folder"
3. Configure folder PID in extension configuration
4. Create language overlays for this folder

.. code-block:: none

   Page Tree:
   └── [Root]
       └── TextDB Translations (Folder, PID: 123)
           ├── [Default Language]
           └── [Language Overlays]

.. _icon-configuration:

Icon Configuration
==================

Icons are registered in `Configuration/Icons.php`:

.. code-block:: php

   return [
       'extension-netresearch-module' => [
           'provider' => SvgIconProvider::class,
           'source' => 'EXT:nr_textdb/Resources/Public/Icons/Module.svg',
       ],
       'extension-netresearch-textdb' => [
           'provider' => SvgIconProvider::class,
           'source' => 'EXT:nr_textdb/Resources/Public/Icons/Extension.svg',
       ],
   ];

.. _language-configuration:

Language Files
==============

The extension uses XLIFF files for localization:

.. code-block:: none

   Resources/Private/Language/
   ├── locallang.xlf                  # General labels
   ├── locallang_db.xlf               # Database field labels
   ├── locallang_mod.xlf              # Main module labels
   ├── locallang_mod_textdb.xlf       # TextDB module labels
   ├── de.locallang.xlf               # German translations
   ├── de.locallang_db.xlf
   ├── de.locallang_mod.xlf
   └── de.locallang_mod_textdb.xlf

Adding Custom Translations
---------------------------

To add support for additional languages:

1. Copy `locallang.xlf` to `[lang-key].locallang.xlf`
2. Update the `target-language` attribute
3. Translate all `<target>` elements

.. _advanced-configuration:

Advanced Configuration
======================

Custom ViewHelper Configuration
--------------------------------

When using the TextDB ViewHelper in your templates:

.. code-block:: html

   {namespace textdb=Netresearch\NrTextdb\ViewHelpers}

   <textdb:textdb
       component="my-component"
       type="label"
       placeholder="welcome.message"
   />

JavaScript Module Configuration
--------------------------------

The extension provides JavaScript modules via `Configuration/JavaScriptModules.php`:

.. code-block:: php

   return [
       'dependencies' => ['core', 'backend'],
       'imports' => [
           '@netresearch/nr-textdb/' => 'EXT:nr_textdb/Resources/Public/JavaScript/',
       ],
   ];

.. _environment-specific:

Environment-Specific Configuration
===================================

Development Environment
-----------------------

.. code-block:: php

   // config/system/additional.php (Development)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb'] = [
       'textDbPid' => 123,
       'createIfMissing' => true,
   ];

Production Environment
----------------------

.. code-block:: php

   // config/system/additional.php (Production)
   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb'] = [
       'textDbPid' => 456,
       'createIfMissing' => false, // Strict mode
   ];
