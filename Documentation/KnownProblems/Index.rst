.. include:: /Includes.rst.txt

.. _known-problems:

==============
Known Problems
==============

.. _current-limitations:

Current Limitations
===================

TYPO3 Version Support
---------------------

* **Minimum TYPO3:** 13.4.0
* **Earlier versions:** Not supported (use version 2.x for TYPO3 12)

PHP Version Requirements
------------------------

* **Minimum PHP:** 8.2
* **Earlier PHP versions:** Not supported

.. _reported-issues:

Reported Issues
===============

Import Performance
------------------

**Issue:** Large XLIFF files (>10MB) may timeout during import

**Workaround:**
  * Split large files into smaller chunks
  * Increase PHP `max_execution_time`:

    .. code-block:: ini

       max_execution_time = 300

  * Use CLI import command instead of backend module

**Status:** Under investigation

Pagination with Large Datasets
-------------------------------

**Issue:** Initial page load slow with >10,000 translation records

**Workaround:**
  * Use filters to narrow results
  * Implement custom caching layer
  * Consider database indexing optimization

**Status:** Performance improvements planned for 3.1.0

Language Fallback
-----------------

**Issue:** No automatic fallback to default language if translation missing

**Workaround:**
  * Enable `createIfMissing` to auto-create translations
  * Manually ensure all languages have translations
  * Use `default` parameter in ViewHelpers

**Status:** Feature request for 3.2.0

.. _compatibility-issues:

Compatibility Issues
====================

Extension Conflicts
-------------------

No known conflicts with other TYPO3 extensions.

If you experience issues, please:

1. Disable other extensions temporarily
2. Test TextDB functionality
3. Re-enable extensions one by one
4. Report findings to: https://github.com/netresearch/t3x-nr-textdb/issues

Database Engines
----------------

**Tested:**
  * MariaDB 10.11+
  * MySQL 8.0+

**Not tested:**
  * PostgreSQL
  * SQLite

**Note:** PostgreSQL and SQLite may work but are not officially supported.

.. _browser-support:

Browser Support
===============

Backend Module
--------------

The TextDB backend module is tested with:

* ✅ Chrome/Chromium (latest)
* ✅ Firefox (latest)
* ✅ Safari (latest)
* ✅ Edge (Chromium-based)
* ⚠️  Internet Explorer (not supported)

.. _workarounds:

Workarounds & Solutions
========================

Problem: Module Not Visible
---------------------------

**Symptoms:**
  * TextDB module doesn't appear in backend menu
  * User has correct permissions

**Solution:**

.. code-block:: bash

   # Clear all caches
   vendor/bin/typo3 cache:flush

   # Rebuild backend modules
   vendor/bin/typo3 backend:listmodules

   # Check module registration
   ls -la typo3conf/ext/nr_textdb/Configuration/Backend/Modules.php

Problem: Translations Not Updating
-----------------------------------

**Symptoms:**
  * Changes in backend don't reflect on frontend
  * Old translations still showing

**Solution:**

.. code-block:: bash

   # Clear frontend page cache
   vendor/bin/typo3 cache:flush

   # Clear specific page cache via backend:
   # Page > Clear Cache > Clear cache for this page

   # Verify correct storage PID in extension configuration

Problem: Import Fails Silently
-------------------------------

**Symptoms:**
  * Import appears to complete but no records created
  * No error messages shown

**Solution:**

1. Check TYPO3 logs:

   .. code-block:: bash

      tail -f var/log/typo3_*.log

2. Verify XLIFF format:

   .. code-block:: xml

      <?xml version="1.0" encoding="utf-8"?>
      <xliff version="1.0">
          <file source-language="en" datatype="plaintext" original="messages">
              <body>
                  <trans-unit id="component|type|placeholder">
                      <source>Value</source>
                  </trans-unit>
              </body>
          </file>
      </xliff>

3. Check file permissions:

   .. code-block:: bash

      ls -la /path/to/upload/directory

4. Validate XML:

   .. code-block:: bash

      xmllint --noout yourfile.xlf

Problem: Memory Exhaustion on Export
-------------------------------------

**Symptoms:**
  * Export fails with memory limit error
  * Large datasets cause timeout

**Solution:**

1. Increase PHP memory limit temporarily:

   .. code-block:: ini

      ; php.ini
      memory_limit = 512M

2. Export smaller subsets using filters

3. Use CLI for large exports:

   .. code-block:: bash

      # Create custom export command
      vendor/bin/typo3 textdb:export --component=website

.. _reporting-bugs:

Reporting Bugs
==============

If you encounter issues not listed here:

1. **Check existing issues:**
   
   https://github.com/netresearch/t3x-nr-textdb/issues

2. **Gather information:**

   * TYPO3 version
   * PHP version
   * Extension version
   * Error messages / stack traces
   * Steps to reproduce

3. **Create detailed report:**

   .. code-block:: none

      Issue Title: Brief description

      Environment:
      - TYPO3: 13.4.5
      - PHP: 8.3.2
      - Extension: 3.0.1
      - Database: MariaDB 10.11

      Description:
      Detailed description of the problem

      Steps to Reproduce:
      1. Go to...
      2. Click on...
      3. See error...

      Expected Behavior:
      What should happen

      Actual Behavior:
      What actually happens

      Error Messages:
      [Paste error messages / logs]

      Additional Context:
      [Screenshots, configurations, etc.]

4. **Submit issue:**
   
   https://github.com/netresearch/t3x-nr-textdb/issues/new

.. _getting-help:

Getting Help
============

Community Support
-----------------

* **GitHub Discussions:** https://github.com/netresearch/t3x-nr-textdb/discussions
* **Issue Tracker:** https://github.com/netresearch/t3x-nr-textdb/issues

Professional Support
--------------------

For commercial support and custom development:

* **Company:** Netresearch DTT GmbH
* **Website:** https://www.netresearch.de/
* **Email:** Contact via GitHub issues

.. _future-improvements:

Planned Improvements
====================

Version 3.1.0
-------------

* Performance optimizations for large datasets
* Enhanced import/export UI with progress indicators
* Batch operations for bulk translation management
* Advanced filtering options

Version 3.2.0
-------------

* PSR-14 events for extensibility
* Language fallback chain support
* Translation versioning and history
* REST API for external integrations

Version 4.0.0
-------------

* TYPO3 14 LTS compatibility
* AI-assisted translation suggestions
* Translation memory integration
* Workflow management with approval process

.. note::

   Planned features are subject to change. Check the
   `GitHub milestones <https://github.com/netresearch/t3x-nr-textdb/milestones>`_
   for current roadmap.
