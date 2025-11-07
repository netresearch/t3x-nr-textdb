.. include:: /Includes.rst.txt

.. _changelog:

=========
ChangeLog
=========

.. _version-3-0-1:

Version 3.0.1
=============

Released: 2024-XX-XX

**Changes:**

* Documentation improvements
* Minor bug fixes
* Updated GitHub Actions CI

.. _version-3-0-0:

Version 3.0.0
=============

Released: 2024-XX-XX

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
   ddev install-v13

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
