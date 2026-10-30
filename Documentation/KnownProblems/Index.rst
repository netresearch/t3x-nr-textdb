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
* **Earlier versions:** Not supported by this version. Use version 3.x
  for TYPO3 13.4 and version 2.x for TYPO3 12.4.

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

**Issue:** When the current language had no entry, the ViewHelpers
rendered the bare key instead of a value from another language.

**Status:** Resolved. The ViewHelpers now follow the fallback chain of the
site language and then the default language. See
:ref:`dev-viewhelpers-fallback` for the lookup order.

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

The TextDB backend module runs in the TYPO3 backend and has no browser
requirements of its own. Its tests do not run in a browser. TYPO3 14.3
lists the latest versions of Chrome, Edge, Firefox and Safari as supported
browsers, see the
`system requirements of TYPO3 14 <https://get.typo3.org/version/14#system-requirements>`__.

.. _workarounds:

Workarounds & Solutions
========================

Problem: Module Not Visible
---------------------------

**Symptoms:**
  * TextDB module doesn't appear in backend menu
  * User has correct permissions

**Solution:**

1. Check that the extension is active and flush the caches, which hold the
   module registration:

   .. code-block:: bash

      vendor/bin/typo3 extension:list | grep nr_textdb
      vendor/bin/typo3 cache:flush

2. For an editor who is no administrator, check that the backend user or
   one of the user's groups lists the module :guilabel:`Netresearch > TextDb`
   in :guilabel:`Allowed modules` on the tab
   :guilabel:`Module Permissions`.

Problem: Translations Not Updating
-----------------------------------

**Symptoms:**
  * Changes in backend don't reflect on frontend
  * Old translations still showing

**Cause:**
  The ViewHelpers render the value into the page, and TYPO3 keeps the
  rendered page in the page cache. With TYPO3's default configuration,
  saving an entry in the module does not clear the cache of the pages that
  show the value.

**Solution:**

1. Flush the page cache, in the backend with
   :guilabel:`Flush frontend caches` in the cache menu of the toolbar, or
   on the command line:

   .. code-block:: bash

      vendor/bin/typo3 cache:flush --group pages

2. If the value is still old, check that the entry is stored in the folder
   that ``textDbPid`` in the extension configuration names. The ViewHelpers
   read entries from that folder only.

Problem: Import Fails Silently
-------------------------------

**Symptoms:**
  * Import appears to complete but no records created
  * No error messages shown

**Solution:**

1. Check the size of the file. A file larger than PHP's
   ``upload_max_filesize`` or ``post_max_size`` does not reach the module,
   which then shows the empty import form again without a message.

2. Verify the XLIFF format. The module imports the ``<trans-unit>``
   elements of ``<file><body>``, and each ``id`` names component, type and
   placeholder. A file exported by the module has this structure:

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

3. Check the language prefix of the file name. ``de.textdb_import.xlf``
   imports into the site language whose language code is ``de``, and a
   file without prefix into the language with the code ``en``. The
   languages come from the site of the storage folder. A prefix that no
   language of this site has imports nothing and shows no message.

4. Select :guilabel:`Override existing` to change entries that exist
   already. Without it the import leaves them unchanged and does not count
   them. Entries the extension created automatically are overwritten in
   both cases.

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

3. Use filters to reduce the export dataset to manageable sizes

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
      - TYPO3: 14.3.10
      - PHP: 8.4.14
      - Extension: 4.0.2
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

Open feature requests and their status are tracked in the
`issue tracker <https://github.com/netresearch/t3x-nr-textdb/issues>`__.
