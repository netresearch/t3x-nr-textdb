.. SPDX-License-Identifier: GPL-3.0-or-later
.. SPDX-FileCopyrightText: Netresearch DTT GmbH

.. include:: /Includes.rst.txt

.. _changelog:

=========
ChangeLog
=========

.. _version-4-1-0:

Version 4.1.0
=============

**Features:**

* ✨ ``textdb:textdb`` and ``textdb:translate`` fall back along the site
  language's fallback chain, then the default language, before they render
  the key (`#194 <https://github.com/netresearch/t3x-nr-textdb/pull/194>`__)
* ✨ The backend import and ``nr_textdb:import`` name the XLIFF units they
  leave out or shorten (`#193
  <https://github.com/netresearch/t3x-nr-textdb/pull/193>`__)

**Fixes:**

* 🐛 Lookups made by the backend module follow the ``createIfMissing``
  extension setting (`#172
  <https://github.com/netresearch/t3x-nr-textdb/pull/172>`__)
* 🐛 A hidden TextDB entry is not rendered in the frontend (`#206
  <https://github.com/netresearch/t3x-nr-textdb/pull/206>`__)
* 🐛 An import writes a file in one database transaction and rolls back the
  whole file on any error, on every supported database (`#189
  <https://github.com/netresearch/t3x-nr-textdb/pull/189>`__, `#211
  <https://github.com/netresearch/t3x-nr-textdb/pull/211>`__, `#215
  <https://github.com/netresearch/t3x-nr-textdb/pull/215>`__)
* 🐛 A unit marked unused imports under its own placeholder (`#191
  <https://github.com/netresearch/t3x-nr-textdb/pull/191>`__)
* 🐛 The backend upload reads files with ``XliffLoader`` and shows an error
  for a malformed file; multi-line values survive export and re-import
  (`#186 <https://github.com/netresearch/t3x-nr-textdb/pull/186>`__)
* 🐛 The module, the export and the imports use the languages of the site
  that holds the storage page (`#188
  <https://github.com/netresearch/t3x-nr-textdb/pull/188>`__)
* 🐛 The translation list and the default-language export contain
  default-language records only (`#187
  <https://github.com/netresearch/t3x-nr-textdb/pull/187>`__)
* 🐛 A malformed ``translateRecord`` request shows an error, and the dialog
  saves only the record it was opened for and its translations (`#182
  <https://github.com/netresearch/t3x-nr-textdb/pull/182>`__)
* 🐛 Saving from the list view shows the save messages, on every TYPO3 14.3
  release, and a double-click opens a translation row once (`#185 <https://github.com/netresearch/t3x-nr-textdb/pull/185>`__,
  `#202 <https://github.com/netresearch/t3x-nr-textdb/pull/202>`__)
* 🐛 The filter reset clears the stored filter (`#181
  <https://github.com/netresearch/t3x-nr-textdb/pull/181>`__)
* 🐛 An export that matches no record shows an error instead of HTTP 500 and
  leaves no working directory behind (`#180
  <https://github.com/netresearch/t3x-nr-textdb/pull/180>`__)

**Changes:**

* Import and export read and write 1,000 records at a time, and the import
  writes new entries in bulk, which lowers memory use and import time (`#204
  <https://github.com/netresearch/t3x-nr-textdb/pull/204>`__, `#216
  <https://github.com/netresearch/t3x-nr-textdb/pull/216>`__)
* One extension title in ``ext_emconf.php`` and ``composer.json`` (`#169
  <https://github.com/netresearch/t3x-nr-textdb/pull/169>`__)

.. _version-4-0-2:

Version 4.0.2
=============

**Fixes:**

* 🐛 Every action of the backend module checks the backend user's table
  rights and page permissions before it reads or writes TextDB records, and
  the write actions also check excluded fields, allowed languages and the
  live workspace; the list and the export show non-admins only the records of
  the configured storage page, and a record opened by its uid is checked
  against the permissions of the page it is stored on. See
  :ref:`backend-module-configuration` for the permissions each action needs
  (`#170 <https://github.com/netresearch/t3x-nr-textdb/pull/170>`__)
* 🐛 The import view shows its error list again instead of failing (`#170
  <https://github.com/netresearch/t3x-nr-textdb/pull/170>`__)

.. _version-4-0-1:

Version 4.0.1
=============

**Fixes:**

* 🐛 ``<nrtextdb:translate>`` resolves translation domain keys such as
  ``nr_textdb.messages:some.label`` through LocalizationUtility when TextDB
  holds no value, instead of rendering the raw key (`#161
  <https://github.com/netresearch/t3x-nr-textdb/pull/161>`__)
* 🐛 The Netresearch group icon and the record icons draw their glyph in the
  backend text colour and stay legible in the light and the dark scheme; the
  backend module views use core markup with accessible names and headings
  (`#157 <https://github.com/netresearch/t3x-nr-textdb/pull/157>`__)
* 🐛 The group and module icons are registered against the right SVG files
  again (`#148 <https://github.com/netresearch/t3x-nr-textdb/pull/148>`__)
* 🐛 Saving translations in the backend module rejects malformed input and
  unconfigured languages instead of crashing or persisting an unreachable
  record (`#129 <https://github.com/netresearch/t3x-nr-textdb/issues/129>`__)
* 🐛 The backend module works for users without a stored module config, and
  the export no longer runs unfiltered when no filter is selected (`#130
  <https://github.com/netresearch/t3x-nr-textdb/pull/130>`__)
* 🐛 ``nr_textdb:import <extension>`` imports only the requested extension
  (`#119 <https://github.com/netresearch/t3x-nr-textdb/pull/119>`__)

.. _version-4-0-0:

Version 4.0.0
=============

**Breaking Changes:**

* ⚠️  TYPO3 14.3 minimum requirement, support for TYPO3 13 dropped
* ⚠️  The default-language XLIFF export no longer emits a ``target-language``
  attribute, so the file is recognised as a source-only template

**Features:**

* ✨ TYPO3 14.3 LTS compatibility
* ✨ PHP 8.2, 8.3, 8.4, 8.5 support

**Fixes:**

* 🐛 Saving a translation in the backend module updates the existing record
  instead of inserting a duplicate, skips empty values and reports a
  persistence error as a flash message (`#100
  <https://github.com/netresearch/t3x-nr-textdb/issues/100>`__)

.. _version-3-0-3:

Version 3.0.3
=============

Released: 2024-11-27

**Changes:**

* Use ``$_EXTKEY`` in ext_emconf.php and sanitize TER comment
* Documentation improvements with GitHub integration for docs.typo3.org
* Changed license to Creative Commons BY 4.0 for documentation
* Fixed guides.xml schemaLocation and added theme attribute

.. _version-3-0-1:

Version 3.0.1
=============

Released: 2024-11-07

**Changes:**

* Documentation improvements
* Minor bug fixes
* Updated GitHub Actions CI

.. _version-3-0-0:

Version 3.0.0
=============

Released: 2024-11-07

**Breaking Changes:**

* ⚠️  PHP 8.2 minimum requirement
* ⚠️  TYPO3 13.4 minimum requirement
* ⚠️  Removed support for TYPO3 12 and earlier

**Features:**

* ✨ TYPO3 13.4 LTS compatibility
* ✨ PHP 8.2, 8.3, 8.4 support
* ✨ Modern dependency injection patterns
* ✨ Updated to PHPUnit 10+
* ✨ Symfony Console 7.0 support
* ✨ Improved DDEV development environment

**Improvements:**

* 🔧 Modernized codebase architecture
* 🔧 Enhanced code quality tooling
* 🔧 Updated testing framework
* 🔧 Improved CI/CD pipeline
* 🔧 Better error handling and logging

**Migration Guide:**

See :ref:`upgrade` in the Installation section.

.. _version-2-x:

Version 2.x
===========

TYPO3 12 LTS Support
--------------------

Version 2.x series supports TYPO3 12 LTS.

For details, see the `2.x branch <https://github.com/netresearch/t3x-nr-textdb/tree/2.x>`_.

.. _version-1-x:

Version 1.x
===========

Legacy Versions
---------------

Version 1.x series supports TYPO3 10-11.

**Note:** No longer maintained. Upgrade to 3.x recommended.

.. _detailed-changelog:

Detailed Change History
========================

For complete commit history and detailed changes:

https://github.com/netresearch/t3x-nr-textdb/commits/main

.. _release-notes:

Release Notes
=============

3.0.x Series
------------

**Focus:** TYPO3 13 LTS support and modernization

**Key Improvements:**

* Modern PHP 8.2+ features
* Enhanced type safety with strict types
* Improved dependency injection
* Better test coverage
* Updated quality tooling (PHPStan, Rector, Fractor)
* DDEV-based development workflow

**Deprecations:**

None in 3.0.x series.

**Removed Features:**

* TYPO3 12 and earlier support
* PHP 8.1 and earlier support

2.0.x Series
------------

**Focus:** TYPO3 12 LTS support

**Key Features:**

* TYPO3 12 LTS compatibility
* PHP 8.1+ support
* Modern Extbase patterns
* Backend module improvements

1.0.x Series
------------

**Focus:** Initial release and TYPO3 10-11 support

**Key Features:**

* Translation database management
* Import/Export functionality
* Backend module
* ViewHelper integration

.. _upgrade-path:

Upgrade Path
============

From 2.x to 3.x
---------------

**Prerequisites:**

* PHP 8.2 or higher installed
* TYPO3 13.4 or higher

**Steps:**

1. Backup your database and translations
2. Update composer.json:

   .. code-block:: bash

      composer require netresearch/nr-textdb:^3.0

3. Update database schema:

   .. code-block:: bash

      vendor/bin/typo3 database:updateschema

4. Clear all caches:

   .. code-block:: bash

      vendor/bin/typo3 cache:flush

5. Test functionality thoroughly

**Breaking Changes:**

* PHP 8.2 minimum (check your PHP version)
* TYPO3 13.4 minimum (upgrade TYPO3 first if needed)
* Some internal APIs may have changed (check custom code)

From 1.x to 3.x
---------------

**Not Directly Supported**

Upgrade path:
1. Upgrade to 2.x first
2. Test thoroughly
3. Then upgrade to 3.x

.. _security-updates:

Security Updates
================

Version 3.0.1
-------------

* No security issues

Version 3.0.0
-------------

* Updated dependencies to address known vulnerabilities
* Enhanced input validation
* Improved file upload security

.. note::

   Always keep your TYPO3 installation and extensions up to date
   to benefit from the latest security fixes.

.. _backwards-compatibility:

Backwards Compatibility
=======================

3.0.x Series
------------

**BC Breaks:**

* PHP 8.2 minimum
* TYPO3 13.4 minimum

**API Stability:**

Public APIs (ViewHelpers, Services) maintain backwards compatibility
within the 3.x series.

**Database Schema:**

Database schema is compatible between 2.x and 3.x (with updates).

.. _contribution-guidelines:

Contributing
============

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add/update tests
5. Ensure CI passes
6. Submit pull request

**Development Setup:**

.. code-block:: bash

   # Clone repository
   git clone https://github.com/netresearch/t3x-nr-textdb.git
   cd t3x-nr-textdb

   # Install dependencies
   composer install

   # Start DDEV
   ddev start
   ddev install-v14

   # Run quality checks
   composer ci:test

**Code Standards:**

* PSR-12 code style
* PHPStan level 7+
* 100% test coverage for new features
* Rector/Fractor compliance

.. _acknowledgments:

Acknowledgments
===============

Thanks to all contributors who have helped improve this extension!

**Major Contributors:**

* Thomas Schöne
* Axel Seemann
* Tobias Hein
* Rico Sonntag

**Community:**

Thanks to the TYPO3 community for feedback and bug reports.

**Netresearch DTT GmbH:**

For continued development and maintenance support.

.. _license:

License
=======

This extension is licensed under GPL-3.0-or-later.

See `LICENSE <https://github.com/netresearch/t3x-nr-textdb/blob/main/LICENSE>`_
file for details.
