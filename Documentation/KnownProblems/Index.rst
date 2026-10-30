.. SPDX-License-Identifier: GPL-3.0-or-later
.. SPDX-FileCopyrightText: Netresearch DTT GmbH

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

* **Minimum TYPO3:** 14.3.0
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

**Issue:** A large XLIFF file can take longer than the PHP time limit of a
backend request.

An import writes every entry of a file in one database transaction and
keeps at most 1,000 records in memory. A file of 10 MB with 48,500 units
imported in these times:

* SQLite, CLI import with a memory limit of 256 MB: 1 minute 43 seconds,
  at most 160 MB
* MariaDB 10.11, the same entries through the import service:
  6 minutes 15 seconds, at most 190 MB

If an import fails, the file imports nothing.

**Workaround:**
  * Import large files with the CLI command ``nr_textdb:import``, which
    has no time limit
  * Split large files into smaller files for the backend module. A file
    uploaded there must also fit PHP's ``upload_max_filesize`` and
    ``post_max_size``, which default to 2 MB and 8 MB.
  * Increase PHP ``max_execution_time`` for the backend. Choose the value
    from the times above; a backend upload of the 10 MB file was not
    measured.

**Status:** Open. Each new entry costs Extbase several SQL statements,
which limits the speed.

**GitHub Issue:** `#30 <https://github.com/netresearch/t3x-nr-textdb/issues/30>`_

Language Fallback
-----------------

**Issue:** No automatic fallback to default language if translation missing

**Workaround:**
  * Enable `createIfMissing` to auto-create translations
  * Manually ensure all languages have translations
  * Use `default` parameter in ViewHelpers

**Status:** Feature request for 3.2.0

**GitHub Issue:** `#32 <https://github.com/netresearch/t3x-nr-textdb/issues/32>`_

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

The functional test suite runs in CI against these database servers:

* SQLite
* MariaDB 10.11
* MySQL 8.0
* PostgreSQL 16

Other versions of these servers are not tested.

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

5. Check the units the import skips without a message:

   * A unit without text, for example an untranslated unit without
     ``<target>`` in a translation file. Importing it would overwrite the
     stored value with nothing.
   * A unit marked ``approved="no"`` while
     ``$GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']``
     is enabled (the TYPO3 default).

Problem: Line Breaks Lost After Re-Import
----------------------------------------

**Symptoms:**
  * After importing an exported file, values that had line breaks or
    repeated spaces show them as single spaces

**Cause:**
  The import reads files with TYPO3's XLIFF loader, which collapses
  whitespace in a unit unless the unit declares ``xml:space="preserve"``.
  Files exported by version 4.0.2 and earlier do not declare it.

**Solution:**
  Export the translations again and import the new file instead of the old
  one. This helps only while the stored values still have their line breaks.
  A value that an import has already flattened is stored without them, so a
  new export carries the flattened value as well. Restore such a value from
  the original XLIFF file or a database backup, or edit it in the backend
  module.

Problem: Export Exceeds the Time Limit
--------------------------------------

**Symptoms:**
  * The export of a large data set ends with a timeout

**Cause:**
  The export reads the entries in batches of 1,000. Measured on SQLite
  with entries in the default language and one translation of each:

  * 10,000 entries: 9 seconds, 10 MB of memory beyond the request
  * 48,500 entries: 44 seconds, 28.5 MB of memory beyond the request

  Version 4.0.2 and earlier read all entries at once, so their memory use
  grows with the data set.

**Solution:**
  * Raise ``max_execution_time`` for backend requests. TYPO3 recommends
    240 seconds.
  * Narrow the export with the filters of the module, for example to one
    component and type.

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
      - TYPO3: 14.3.5
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

**GitHub Issue:** `#33 <https://github.com/netresearch/t3x-nr-textdb/issues/33>`_

Version 3.2.0
-------------

* PSR-14 events for extensibility
* Language fallback chain support
* Translation versioning and history
* REST API for external integrations

**GitHub Issue:** `#34 <https://github.com/netresearch/t3x-nr-textdb/issues/34>`_

Version 4.0.0
-------------

* TYPO3 14 LTS compatibility
* AI-assisted translation suggestions
* Translation memory integration
* Workflow management with approval process

**GitHub Issue:** `#35 <https://github.com/netresearch/t3x-nr-textdb/issues/35>`_

.. note::

   Planned features are subject to change. Check the
   `GitHub milestones <https://github.com/netresearch/t3x-nr-textdb/milestones>`_
   for current roadmap.
