.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

.. _what-it-does:

What does it do?
================

The **Nr TextDB** extension provides a translation database system for TYPO3
that allows backend users to manage and edit translations directly in the
TYPO3 backend interface. This eliminates the need to edit XLIFF language files
manually and provides a centralized translation management system.

Key Features
------------

* **Backend Translation Editor**: Edit translations directly in TYPO3 backend
* **Import/Export**: Batch import and export translations via XLIFF files
* **Multi-language Support**: Manage translations for all configured site languages
* **Component-Based Organization**: Organize translations by components and types
* **Environment Support**: Different translation sets for different environments
* **Command-Line Import**: CLI command for automated translation imports
* **ViewHelper Integration**: Custom ViewHelpers for easy frontend integration
* **Filter & Search**: Powerful filtering and search capabilities
* **Pagination**: Efficient handling of large translation sets

.. _screenshots:

Screenshots
===========

..  note::
    Screenshots will be added in a future update. The following features are available
    in the backend module.

Backend Module
--------------

The TextDB backend module provides an intuitive interface for managing translations:

* **Translation List View** - Browse and filter all translations with advanced search
* **Edit Forms** - Intuitive forms for editing translation records
* **Multi-language Support** - Switch between languages seamlessly

Import & Export
---------------

* **XLIFF Import** - Import translations from properly formatted XLIFF files
* **Filtered Export** - Export only the translations you need
* **Batch Operations** - Handle multiple translations efficiently

..  todo::
    Add screenshots showing:

    - Main translation list view with filtering options
    - Translation edit form interface
    - XLIFF import interface

.. _use-cases:

Use Cases
=========

Content Management
------------------

* Manage frontend translations without accessing language files
* Enable editors to maintain multilingual content
* Quick translation updates without deployment

Development Workflow
--------------------

* Export translations for external translation services
* Import translated content back into the system
* Maintain translation consistency across environments

Multi-Site Projects
-------------------

* Centralized translation management
* Component-based organization for modular projects
* Environment-specific translations (development, staging, production)

.. _target-audience:

Target Audience
===============

This extension is designed for:

* **Editors**: Who need to manage translations without technical knowledge
* **Administrators**: Who want centralized translation management
* **Developers**: Who need a robust translation system with API access
* **Agencies**: Managing multiple multilingual TYPO3 projects

.. _support:

Support
=======

For issues, feature requests, or contributions:

* **GitHub Repository**: https://github.com/netresearch/t3x-nr-textdb
* **Issue Tracker**: https://github.com/netresearch/t3x-nr-textdb/issues

.. _credits:

Credits
=======

This extension is developed and maintained by **Netresearch DTT GmbH**.

:Authors:
   Thomas Schöne, Axel Seemann, Tobias Hein, Rico Sonntag

:Company:
   Netresearch DTT GmbH

:Website:
   https://www.netresearch.de/
