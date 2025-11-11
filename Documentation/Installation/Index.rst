.. include:: /Includes.rst.txt

.. _installation:

============
Installation
============

.. _requirements:

Requirements
============

Minimum Requirements
--------------------

* **TYPO3**: 13.4.0 or higher
* **PHP**: 8.2, 8.3, or 8.4
* **PHP Extensions**:
   * ext-zip
   * ext-simplexml
   * ext-libxml

Recommended
-----------

* **Database**: MariaDB 10.11+ or MySQL 8.0+
* **Development**: DDEV or Docker for local development

.. _composer-installation:

Installation via Composer
==========================

The recommended way to install this extension is via Composer:

.. code-block:: bash

   composer require netresearch/nr-textdb

After requiring the package, activate the extension in the Extension Manager
or via command line:

.. code-block:: bash

   # Via command line
   vendor/bin/typo3 extension:activate nr_textdb

.. _ter-installation:

Installation from TER
======================

Alternatively, you can install the extension from the TYPO3 Extension Repository (TER):

1. Navigate to **Admin Tools > Extensions**
2. Search for "nr_textdb"
3. Click the download icon
4. Activate the extension

.. _database-schema:

Database Setup
==============

After installation, update the database schema:

.. code-block:: bash

   # Via command line
   vendor/bin/typo3 database:updateschema

Or use the **Maintenance > Analyze Database Structure** module in the backend.

The extension will create the following database tables:

* `tx_nrtextdb_domain_model_translation` - Translation records
* `tx_nrtextdb_domain_model_component` - Component definitions
* `tx_nrtextdb_domain_model_type` - Translation type definitions
* `tx_nrtextdb_domain_model_environment` - Environment definitions

.. _post-installation:

Post-Installation Steps
========================

1. **Create Storage Folder**

   Create a dedicated page/folder in the page tree for TextDB records:

   * Page Type: Folder
   * Recommended location: Root level
   * Suggested name: "TextDB Translations"

2. **Configure Extension**

   Go to **Admin Tools > Settings > Extension Configuration > nr_textdb**

   Set the PID (Page ID) of your storage folder.

3. **Create Language Records**

   Create the necessary system language records (if not already present):

   * Navigate to your storage folder
   * Create records for Components, Types, and Environments as needed

4. **Set Permissions**

   Grant backend user groups access to:

   * TextDB Backend Module
   * Storage folder for TextDB records

.. _localization:

Localization
============

.. versionadded:: 3.1.0
   Backend interface available in 23 languages with Crowdin integration.

The extension backend interface is available in **23 languages** out of the box:

**European**: Afrikaans, Czech, Danish, German, Spanish, Finnish, French, Italian, Dutch, Norwegian, Polish, Portuguese, Swedish

**Asian & African**: Arabic, Hindi, Indonesian, Japanese, Korean, Russian, Swahili, Thai, Vietnamese, Chinese

The interface language follows your TYPO3 backend user settings. To change the backend language:

1. Navigate to **User Settings** (click your username in top bar)
2. Change **Interface Language** to your preferred language
3. Save and reload the backend

**Contribute Translations**: Help translate the extension into more languages or improve existing translations through the `TYPO3 Crowdin project <https://crowdin.com/project/typo3-cms>`_. No technical knowledge required! See the `Contributing Guide <https://github.com/netresearch/t3x-nr-textdb/blob/main/CONTRIBUTING.md#how-to-contribute-translations>`_ for details.

.. _upgrade:

Upgrade Instructions
====================

From Version 2.x to 3.x
-----------------------

.. versionchanged:: 3.0.0
   Added TYPO3 13.4 LTS compatibility with breaking changes requiring PHP 8.2+.

Version 3.0 brings TYPO3 13.4 LTS compatibility:

**Breaking Changes:**

* PHP 8.2 minimum requirement
* TYPO3 13.4 minimum requirement
* Database schema updates required

**Migration Steps:**

1. Ensure PHP 8.2+ is installed
2. Update composer dependencies:

   .. code-block:: bash

      composer update netresearch/nr-textdb

3. Update database schema:

   .. code-block:: bash

      vendor/bin/typo3 database:updateschema

4. Clear all caches:

   .. code-block:: bash

      vendor/bin/typo3 cache:flush

5. Test translation functionality in backend module

.. _troubleshooting:

Troubleshooting
===============

Extension not visible after installation
-----------------------------------------

* Clear all caches via **Admin Tools > Maintenance > Flush TYPO3 and PHP Cache**
* Verify extension is activated in Extension Manager
* Check that database tables were created

Missing translations
--------------------

* Verify storage PID is configured correctly in Extension Configuration
* Check that translations are stored in the correct page/folder
* Ensure "Create if missing" option is enabled (if desired)

Import fails
------------

* Verify XLIFF file format matches expected structure
* Check file permissions on upload
* Review logs in **Admin Tools > Log**
* Ensure PHP extensions (ext-zip, ext-simplexml, ext-libxml) are installed

.. _uninstallation:

Uninstallation
==============

To remove the extension:

1. **Backup Translation Data** (if needed)

   Export all translations before uninstalling.

2. **Deactivate Extension**

   .. code-block:: bash

      vendor/bin/typo3 extension:deactivate nr_textdb

3. **Remove via Composer**

   .. code-block:: bash

      composer remove netresearch/nr-textdb

4. **Clean Database** (optional)

   Remove TextDB tables manually if desired:

   .. code-block:: sql

      DROP TABLE tx_nrtextdb_domain_model_translation;
      DROP TABLE tx_nrtextdb_domain_model_component;
      DROP TABLE tx_nrtextdb_domain_model_type;
      DROP TABLE tx_nrtextdb_domain_model_environment;

.. attention::

   Removing the database tables will permanently delete all translation data!
