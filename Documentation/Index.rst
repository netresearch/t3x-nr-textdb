.. include:: /Includes.rst.txt

.. _start:

==================
Netresearch TextDB
==================

.. only:: html

   :Extension key:
      nr_textdb

   :Package name:
      netresearch/nr-textdb

   :Version:
      |release|

   :Language:
      en

   :Author:
      Thomas Schöne, Axel Seemann, Tobias Hein, Rico Sonntag

   :License:
      This document is published under the
      `GNU General Public License v3.0 <https://www.gnu.org/licenses/gpl-3.0.html>`__.

   :Rendered:
      |today|

----

**Manage TYPO3 translations directly in the backend – no more digging through language files**

The Netresearch TextDB extension transforms how you manage **frontend system strings**
(form labels, buttons, error messages, navigation) by providing a database-backed
translation system accessible through the TYPO3 backend. Instead of editing scattered
`.xlf` files and deploying changes, editors and translators can manage translations
in real-time through an intuitive backend module.

**Perfect for:**

* 🌍 Multi-language websites with frequent translation updates
* 👥 Non-technical staff who need to update translations without touching code
* 🔄 Translation workflows requiring import/export for agencies
* 🚀 Agencies managing multiple TYPO3 projects with consistent processes

**Key Benefits:**

* ✅ **Zero-friction migration** via auto-import ViewHelper
* ✅ **Live updates** without deployment cycles
* ✅ **Hierarchical organization** for 500+ translations
* ✅ **Non-developer friendly** backend module
* ✅ **Production-grade quality** (PHPStan level 10, 95%+ test coverage)

----

**Table of Contents:**

.. toctree::
   :hidden:
   :maxdepth: 2

   Introduction/Index
   Installation/Index
   Configuration/Index
   User/Index
   Administrator/Index
   Developer/Index
   KnownProblems/Index
   ChangeLog/Index
   Sitemap

.. card-grid::
   :columns: 1
   :columns-md: 2
   :gap: 4
   :card-height: 100

   .. card:: 📘 Introduction

      Discover what TextDB is (frontend system strings), real-world use cases
      with measurable results, competitive advantages, and why it's unique.

      .. card-footer:: :ref:`Read more <introduction>`
         :button-style: btn btn-primary stretched-link

   .. card:: 📦 Installation

      Step-by-step installation guide including requirements, Composer setup,
      database configuration, and upgrade instructions.

      .. card-footer:: :ref:`Get started <installation>`
         :button-style: btn btn-primary stretched-link

   .. card:: ⚙️ Configuration

      Configure extension settings, TypoScript, backend modules, services,
      and TCA for your specific needs.

      .. card-footer:: :ref:`Configure <configuration>`
         :button-style: btn btn-primary stretched-link

   .. card:: 👤 User Guide

      Complete user manual for editors: managing translations, filtering,
      importing/exporting, and daily workflows.

      .. card-footer:: :ref:`User guide <user-manual>`
         :button-style: btn btn-primary stretched-link

   .. card:: 🔧 Administrator

      Administrative tasks including permissions, CLI commands, maintenance,
      monitoring, and security considerations.

      .. card-footer:: :ref:`Admin guide <administrator>`
         :button-style: btn btn-primary stretched-link

   .. card:: 💻 Developer

      Developer documentation covering ViewHelpers, services API, repositories,
      domain models, and integration examples.

      .. card-footer:: :ref:`Developer docs <developer>`
         :button-style: btn btn-primary stretched-link

   .. card:: ⚠️ Known Problems

      Troubleshooting guide covering known issues, workarounds, compatibility
      information, and how to report bugs.

      .. card-footer:: :ref:`Troubleshooting <known-problems>`
         :button-style: btn btn-secondary stretched-link

   .. card:: 📋 ChangeLog

      Version history, release notes, breaking changes, upgrade paths,
      and roadmap for future versions.

      .. card-footer:: :ref:`Version history <changelog>`
         :button-style: btn btn-secondary stretched-link
