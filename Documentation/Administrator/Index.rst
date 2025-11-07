.. include:: /Includes.rst.txt

.. _administrator:

===================
Administrator Manual
===================

.. _admin-overview:

Overview
========

This section covers administrative tasks for managing the TextDB extension,
including setup, maintenance, permissions, and advanced configuration.

.. _admin-installation:

Installation & Setup
====================

Initial Setup Checklist
-----------------------

1. ☐ Install extension via Composer
2. ☐ Update database schema
3. ☐ Create storage folder
4. ☐ Configure extension settings
5. ☐ Set up user permissions
6. ☐ Create component/type/environment records
7. ☐ Test with sample translations

Detailed Setup Steps
--------------------

**1. Create Storage Folder**

.. code-block:: none

   Page Tree:
   └── [Root]
       └── TextDB Translations (Folder)
           ├── [pid: 123]
           └── Language: Default + All Site Languages

**2. Extension Configuration**

Navigate to **Admin Tools > Settings > Extension Configuration > nr_textdb**

.. code-block:: none

   textDbPid = 123
   createIfMissing = 1

**3. Language Setup**

Ensure site languages are configured:

.. code-block:: yaml

   # config/sites/main/config.yaml
   languages:
     -
       languageId: 0
       title: English
       navigationTitle: English
       base: /
       locale: en_US.UTF-8
     -
       languageId: 1
       title: German
       navigationTitle: Deutsch
       base: /de/
       locale: de_DE.UTF-8

.. _admin-permissions:

User Permissions
================

Backend User Groups
-------------------

Create dedicated user groups for TextDB access:

TextDB Editors
~~~~~~~~~~~~~~

.. code-block:: none

   Module Access:
   ✓ Netresearch
   ✓ Netresearch TextDB

   Table Access (Modify):
   ✓ tx_nrtextdb_domain_model_translation

   Table Access (Read):
   ✓ tx_nrtextdb_domain_model_component
   ✓ tx_nrtextdb_domain_model_type
   ✓ tx_nrtextdb_domain_model_environment

   Page Access:
   ✓ TextDB Translations Folder (pid: 123)

TextDB Administrators
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: none

   Module Access:
   ✓ Netresearch
   ✓ Netresearch TextDB

   Table Access (Full):
   ✓ tx_nrtextdb_domain_model_translation
   ✓ tx_nrtextdb_domain_model_component
   ✓ tx_nrtextdb_domain_model_type
   ✓ tx_nrtextdb_domain_model_environment

   Page Access:
   ✓ TextDB Translations Folder (full access)

Setting Up Permissions
----------------------

1. Navigate to **System > Backend Users > Backend User Groups**
2. Create/Edit user group
3. **Access Lists** tab:
   
   * Select modules
   * Select table permissions

4. **Mounts and Workspaces** tab:
   
   * Add DB Mount to TextDB folder

5. Assign users to the group

.. _admin-data-management:

Data Management
===============

Components
----------

Components organize translations logically (e.g., "website", "shop", "blog").

**Create Component:**

1. Go to **List** module
2. Navigate to TextDB storage folder
3. Click **Create new record**
4. Select **Component**
5. Enter component details

Types
-----

Types categorize translations by usage (e.g., "label", "message", "error").

**Create Type:**

1. Navigate to TextDB storage folder
2. Create new **Type** record
3. Define type name and identifier

Environments
------------

Environments differentiate translations by context (e.g., "development", "production").

**Create Environment:**

1. Navigate to TextDB storage folder
2. Create new **Environment** record
3. Set environment identifier

.. _admin-cli-commands:

Command Line Interface
======================

Import Command
--------------

Import translations via CLI:

.. code-block:: bash

   # Import single file
   vendor/bin/typo3 nr_textdb:import /path/to/translations.xlf

   # Import multiple files
   vendor/bin/typo3 nr_textdb:import /path/to/translations/*.xlf

**Command Options:**

.. code-block:: bash

   vendor/bin/typo3 nr_textdb:import --help

Automated Imports
-----------------

Schedule imports via TYPO3 Scheduler:

1. Navigate to **Scheduler** module
2. Create new task
3. Select **Execute console commands**
4. Choose `nr_textdb:import`
5. Configure file path and frequency

.. _admin-maintenance:

Maintenance
===========

Database Cleanup
----------------

Remove orphaned translations:

.. code-block:: sql

   -- Find translations without component
   SELECT * FROM tx_nrtextdb_domain_model_translation
   WHERE component = 0 OR component NOT IN (
       SELECT uid FROM tx_nrtextdb_domain_model_component
   );

   -- Delete after verification
   DELETE FROM tx_nrtextdb_domain_model_translation
   WHERE component = 0 OR component NOT IN (
       SELECT uid FROM tx_nrtextdb_domain_model_component
   );

Performance Optimization
------------------------

**Database Indexes:**

The extension creates appropriate indexes automatically. Verify with:

.. code-block:: sql

   SHOW INDEXES FROM tx_nrtextdb_domain_model_translation;

**Cache Configuration:**

Ensure proper cache configuration:

.. code-block:: php

   // config/system/additional.php
   $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_nrtextdb'] = [
       'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
       'options' => [
           'defaultLifetime' => 86400, // 24 hours
       ],
   ];

Backup Strategy
---------------

**Regular Backups:**

1. **Database Backup:**

   .. code-block:: bash

      # Export TextDB tables
      mysqldump -u user -p database \
          tx_nrtextdb_domain_model_translation \
          tx_nrtextdb_domain_model_component \
          tx_nrtextdb_domain_model_type \
          tx_nrtextdb_domain_model_environment \
          > textdb_backup.sql

2. **XLIFF Export:**
   
   * Use backend module to export all translations
   * Store XLIFF files in version control

3. **Automated Backups:**
   
   * Schedule via cron or TYPO3 Scheduler
   * Store backups externally

.. _admin-monitoring:

Monitoring & Logging
====================

Access Logs
-----------

Monitor TextDB module usage via TYPO3 backend logs:

1. Navigate to **Admin Tools > Log**
2. Filter by:
   * User actions in TextDB module
   * Translation record changes
   * Import/export activities

Error Monitoring
----------------

Check for errors:

.. code-block:: bash

   # Review TYPO3 logs
   tail -f var/log/typo3_*.log | grep nr_textdb

Common Log Entries
------------------

.. code-block:: none

   # Successful import
   [INFO] TextDB: Imported 150 translations from website.xlf

   # Failed import
   [ERROR] TextDB: Import failed - Invalid XLIFF format

   # Auto-creation (if enabled)
   [NOTICE] TextDB: Created missing translation: component|type|key

.. _admin-troubleshooting:

Troubleshooting
===============

Module Not Accessible
---------------------

**Symptoms:** Users cannot see TextDB module

**Solutions:**

1. Verify module permissions in user group
2. Clear backend user cache:

   .. code-block:: bash

      vendor/bin/typo3 cache:flush

3. Check module registration:

   .. code-block:: bash

      vendor/bin/typo3 backend:listmodules

Translations Not Appearing
--------------------------

**Symptoms:** Frontend shows no translations

**Solutions:**

1. Verify storage PID configuration
2. Check translation records exist in correct folder
3. Flush frontend cache:

   .. code-block:: bash

      vendor/bin/typo3 cache:flush

4. Verify site language configuration

Import Failures
---------------

**Symptoms:** XLIFF import fails or creates errors

**Solutions:**

1. Validate XLIFF file format
2. Check PHP memory limit:

   .. code-block:: ini

      ; php.ini
      memory_limit = 256M
      upload_max_filesize = 64M
      post_max_size = 64M

3. Review error logs for specific issues
4. Test with minimal XLIFF file first

Performance Issues
------------------

**Symptoms:** Slow module loading or search

**Solutions:**

1. Add database indexes (if missing):

   .. code-block:: sql

      CREATE INDEX idx_component ON tx_nrtextdb_domain_model_translation (component);
      CREATE INDEX idx_type ON tx_nrtextdb_domain_model_translation (type);
      CREATE INDEX idx_placeholder ON tx_nrtextdb_domain_model_translation (placeholder);

2. Optimize database tables:

   .. code-block:: sql

      OPTIMIZE TABLE tx_nrtextdb_domain_model_translation;

3. Increase PHP memory for large datasets

.. _admin-migration:

Migration & Upgrades
====================

Migrating from Other Translation Systems
-----------------------------------------

**From XLIFF Files:**

1. Export existing XLIFF files
2. Convert to TextDB format (adjust `trans-unit` IDs)
3. Import via backend module

**From Database:**

Create migration script:

.. code-block:: php

   // Migration example
   $translations = $oldRepository->findAll();
   foreach ($translations as $old) {
       $new = new Translation();
       $new->setComponent($componentMapping[$old->getComponent()]);
       $new->setPlaceholder($old->getKey());
       $new->setValue($old->getTranslation());
       $translationRepository->add($new);
   }
   $persistenceManager->persistAll();

Version Updates
---------------

**Pre-Update Checklist:**

1. ☐ Backup database
2. ☐ Export all translations
3. ☐ Review CHANGELOG.md
4. ☐ Test in development first
5. ☐ Schedule maintenance window

**Update Process:**

.. code-block:: bash

   # 1. Update package
   composer update netresearch/nr-textdb

   # 2. Update database
   vendor/bin/typo3 database:updateschema

   # 3. Run upgrade wizards (if any)
   vendor/bin/typo3 upgrade:run

   # 4. Clear all caches
   vendor/bin/typo3 cache:flush

   # 5. Verify functionality
   # Test import/export and translation display

.. _admin-integration:

Integration with Other Extensions
==================================

nr-sync Integration
-------------------

If `netresearch/nr-sync` is installed, TextDB includes a sync module:

**Configuration:**

.. code-block:: php

   // Automatically registered in Configuration/Backend/Modules.php
   'netresearch_sync_textdb' => [
       'parent' => 'netresearch_sync',
       'moduleData' => [
           'dumpFile' => 'nr-textdb.sql',
           'tables' => [
               'tx_nrtextdb_domain_model_component',
               'tx_nrtextdb_domain_model_environment',
               'tx_nrtextdb_domain_model_translation',
               'tx_nrtextdb_domain_model_type',
           ],
       ],
   ];

**Usage:**

Sync TextDB data between environments using the nr-sync module interface.

.. _admin-security:

Security Considerations
=======================

Access Control
--------------

* Restrict TextDB module access to trusted users
* Use separate user groups for editors vs administrators
* Limit storage folder access via page permissions

File Upload Security
--------------------

* Validate XLIFF file format before processing
* Implement file size limits
* Scan uploaded files for malicious content
* Store uploads in protected directory

Data Integrity
--------------

* Regular database backups
* Version control for XLIFF exports
* Audit trail via TYPO3 logging
* Implement approval workflow for sensitive translations

SQL Injection Prevention
------------------------

The extension uses Extbase query API, which provides:

* Prepared statements
* Parameter binding
* SQL injection protection

.. important::

   Never use raw SQL queries when working with TextDB data!

.. _admin-performance:

Performance Optimization
========================

Database Optimization
---------------------

.. code-block:: sql

   -- Analyze table statistics
   ANALYZE TABLE tx_nrtextdb_domain_model_translation;

   -- Optimize table storage
   OPTIMIZE TABLE tx_nrtextdb_domain_model_translation;

Query Optimization
------------------

Monitor slow queries:

.. code-block:: ini

   ; php.ini or my.cnf
   slow_query_log = 1
   long_query_time = 2

Caching Strategy
----------------

Configure appropriate cache lifetime:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_nrtextdb'] = [
       'backend' => \TYPO3\CMS\Core\Cache\Backend\RedisBackend::class,
       'options' => [
           'hostname' => 'localhost',
           'database' => 3,
           'defaultLifetime' => 86400,
       ],
   ];

.. _admin-best-practices:

Best Practices
==============

Organizational Structure
------------------------

* **Separate Folders**: Use dedicated folder per environment if needed
* **Consistent Naming**: Establish naming conventions for components
* **Documentation**: Maintain documentation of component/type structure

Workflow Management
-------------------

* **Change Control**: Implement approval process for production translations
* **Testing**: Test translations in staging before production
* **Rollback Plan**: Keep XLIFF exports for quick rollback

Monitoring
----------

* **Regular Audits**: Review translation usage and orphaned records
* **Performance Metrics**: Monitor module response times
* **User Training**: Provide training for editors

Scalability
-----------

* **Pagination**: Adjust pagination limits for large datasets
* **Archiving**: Archive old/unused translations
* **Distribution**: Consider database replication for high-traffic sites
