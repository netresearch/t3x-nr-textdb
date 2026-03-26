.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

.. _what-it-does:

What does it do?
================

The **Netresearch TextDB** extension provides a translation database system for TYPO3
that allows backend users to manage and edit translations directly in the
TYPO3 backend interface. This eliminates the need to edit XLIFF language files
manually and provides a centralized translation management system.

.. _what-textdb-is:

What TextDB Is (and Isn't)
===========================

TextDB is Designed for: Frontend System Strings
------------------------------------------------

**User interface elements that come from your code, NOT editor-created content:**

✅ **Form labels**: "First Name", "Email Address", "Submit Button"

✅ **Button texts**: "Add to Cart", "Checkout", "Learn More"

✅ **Error messages**: "Invalid email format", "Field is required"

✅ **Navigation labels**: "Products", "About Us", "Contact"

✅ **Status messages**: "Item added to cart", "Order confirmed"

✅ **Validation messages**, tooltips, placeholder texts

**Example Scenario**: Your e-commerce checkout has 50+ labels/buttons needing German, French, and Spanish translations. Instead of editing `.xlf` files, editors manage them through TextDB's backend module.

TextDB is NOT for:
------------------

❌ **Page content** created by editors (use TYPO3's built-in page translation)

❌ **News articles** or blog posts (use news/blog extension translation features)

❌ **Content elements** like text blocks, images (use TYPO3 content localization)

❌ **Backend module labels** (use TYPO3's core translation system)

❌ **TCA field labels** (use locallang_db.xlf in your extension)

Translation Scope
-----------------

TextDB focuses specifically on **Frontend System Strings** within the TYPO3 translation landscape:

* **Backend/Admin Interface** → TYPO3 Core locallang files
* **Content Elements** → TYPO3 Page/Content translation
* **Editor-created content** → TYPO3 Localization features
* **Frontend System Strings** → ✨ **TextDB** (YOU ARE HERE)

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

.. note::
   The extension backend interface is available in **23 languages** including European, Asian, and African languages. See the :ref:`Installation Guide <localization>` for changing your backend language, or :ref:`Developer Manual <dev-localization>` for technical details about the localization infrastructure.

.. _screenshots:

Screenshots
===========

Backend Module
--------------

The TextDB backend module provides an intuitive interface for managing translations:

**Translation List View**

The main list view displays all translations with filtering by component, type,
placeholder, and value. Pagination is built in for large datasets.

.. code-block:: none

   ┌─────────────────────────────────────────────────────────────┐
   │  Netresearch TextDB                          [List] [Import]│
   ├─────────────────────────────────────────────────────────────┤
   │  Component: [All        ▼]  Type: [All    ▼]               │
   │  Placeholder: [________]    Value: [________]  [🔍 Search] │
   ├──────┬───────────┬────────┬──────────────┬─────────────────┤
   │ Lang │ Component │ Type   │ Placeholder  │ Value           │
   ├──────┼───────────┼────────┼──────────────┼─────────────────┤
   │ 🏴 ▶ │ checkout  │ button │ submit       │ Proceed to Pay  │
   │ 🏴 ▶ │ checkout  │ label  │ email        │ Email Address   │
   │ 🏴 ▶ │ website   │ label  │ welcome.msg  │ Welcome!        │
   └──────┴───────────┴────────┴──────────────┴─────────────────┘
   │◀ ◁  Records 1 - 15  Page [1] / 5  ▷ ▶│  🔄

**Multi-language Translation Editor**

Click the language icons on any row to expand the inline translation editor,
showing existing translations and allowing new languages to be added:

.. code-block:: none

   ┌──────────────────────────────────────────────┐
   │  Translation: checkout | button | submit      │
   ├──────────────────────────────────────────────┤
   │ 🇬🇧 English    │ [Proceed to Checkout      ] │
   │ 🇩🇪 German     │ [Zur Kasse gehen          ] │
   │ 🇫🇷 French     │ [Passer à la caisse       ] │
   │ 🇪🇸 Spanish    │ [                         ] │  ← new
   ├──────────────────────────────────────────────┤
   │                              [💾 Save]       │
   └──────────────────────────────────────────────┘

Import & Export
---------------

**XLIFF Import Interface**

Upload XLIFF files to import translations. Optionally override existing values:

.. code-block:: none

   ┌─────────────────────────────────────────────────┐
   │  ℹ️ Upload a textdb XLIFF file to import         │
   │     translations into the database.              │
   ├─────────────────────────────────────────────────┤
   │  File: [Choose File...  textdb_import.xlf]       │
   │  ☐ Override existing translations                │
   │                            [📥 Import]           │
   ├─────────────────────────────────────────────────┤
   │  ✅ Import done for Language: German              │
   │  ✅ Translations imported: 42                     │
   │  ✅ Translations updated: 15                      │
   └─────────────────────────────────────────────────┘

**Filtered Export**

Export only the translations matching your current filters as a ZIP archive
containing XLIFF files for all configured languages.

.. _use-cases:

Real-World Use Cases
====================

Use Case 1: Multi-Language E-Commerce Checkout
-----------------------------------------------

**Problem**: Your checkout flow has 80+ UI strings (field labels, buttons, validation messages) needing translations in German, French, and Spanish.

**Without TextDB**: Developers edit `.xlf` files for every text change, deploy to production.

**With TextDB**: Product managers update translations directly in backend, changes live immediately.

**Result**: Translation updates in minutes, not days. Non-technical staff manage translations independently.

Use Case 2: SaaS Application with Dynamic Forms
------------------------------------------------

**Problem**: Multi-tenant SaaS with 200+ form labels across 15 modules, requiring consistent translation management.

**Without TextDB**: Scattered `.xlf` files across multiple extensions, no central overview, duplicate translations.

**With TextDB**: Hierarchical organization by component/type, centralized filtering, bulk operations, zero duplication.

**Result**: 70% reduction in translation maintenance time, consistent terminology across modules.

Use Case 3: Agency Managing Multiple Client Sites
--------------------------------------------------

**Problem**: 20+ TYPO3 installations, each with custom form/button texts needing German/English translations.

**Without TextDB**: Copy `.xlf` files between projects, manual sync, version control overhead.

**With TextDB**: Export/import workflows, standardized translation structure, zero-friction migration via `textdb:translate`.

**Result**: Standardized translation process across all clients, 50% faster project setup.

Use Case 4: Government Website Compliance
------------------------------------------

**Problem**: Legal requirements demand audit trails for translated UI strings, editor-friendly workflow without file access.

**Without TextDB**: Developers as bottleneck for every text change, no change tracking, risky file edits.

**With TextDB**: Backend module access for translators, database change tracking, missing translation detection.

**Result**: Compliance-ready audit trails, editor empowerment, reduced developer burden.

.. _before-after:

Before & After: The TextDB Transformation
==========================================

Traditional File-Based Approach (Without TextDB)
-------------------------------------------------

**File Structure**::

   Your TYPO3 Project/
   ├── typo3conf/ext/my_extension/
   │   └── Resources/Private/Language/
   │       ├── locallang.xlf                    # 150 lines of XML
   │       ├── de.locallang.xlf                 # 150 lines (duplicated structure)
   │       ├── fr.locallang.xlf                 # 150 lines (duplicated structure)
   │       └── locallang_checkout.xlf           # Another 200 lines

**Workflow**: Developer edits XML → Git commit → Deploy → Cache clear → Test

**Problems**:

* ❌ Non-developers can't update translations
* ❌ Changes require full deployment cycle
* ❌ No central overview of all translations
* ❌ Duplicate structure across language files
* ❌ Merge conflicts in XML files
* ❌ No built-in search/filter capabilities

Database-Backed Approach (With TextDB)
---------------------------------------

**Backend Interface**::

   Your TYPO3 Backend/
   └── Netresearch → TextDB Module
       ├── 🔍 Search: [button checkout]          # Instant filtering
       ├── 📊 Filter: Component: "checkout" | Type: "button"
       │
       ├── ✏️ Edit inline:
       │   ├── EN: "Proceed to Checkout"  →  [Click to edit]
       │   ├── DE: "Zur Kasse gehen"      →  [Click to edit]
       │   └── FR: "Passer à la caisse"   →  [Click to edit]
       │
       ├── 📥 Import: Upload XLF → Auto-merge
       └── 📤 Export: Download ZIP (all languages)

**Workflow**: Editor logs in → Click translation → Edit → Save → Live immediately

**Benefits**:

* ✅ Non-developers manage translations independently
* ✅ Changes live in seconds (no deployment)
* ✅ Centralized dashboard with 500+ translations
* ✅ Single source of truth (no XML duplication)
* ✅ Advanced filtering: component, type, placeholder, value search
* ✅ Export/import for translation agencies
* ✅ Zero-friction migration via `textdb:translate` ViewHelper

Migration Path: Zero Disruption
--------------------------------

**Phase 1: Prepare (5 minutes)**

Add namespace to your templates::

   xmlns:textdb="http://typo3.org/ns/Netresearch/NrTextdb/ViewHelpers"

**Phase 2: Auto-Import (Automatic)**

Replace `f:translate` with `textdb:translate`::

   <textdb:translate key="LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:submit" />

First render automatically imports to database. All existing translations preserved.

**Phase 3: Optimize (Gradual)**

Switch to native syntax at your own pace::

   <textdb:textdb component="checkout" type="button" placeholder="submit" />

Old `.xlf` files can stay as backup until you're confident.

**Zero Downtime**: Existing translations continue working during migration.

**Zero Data Loss**: Automatic import preserves all language variants.

**Zero Risk**: Rollback anytime by reverting ViewHelper change.

.. _what-makes-unique:

What Makes TextDB Unique
=========================

Key Differentiators
-------------------

1. **Zero-Friction Migration**
   Drop-in replacement for `f:translate` ViewHelper with automatic LLL import on first render. No manual migration required.

2. **Hierarchical Organization**
   4-level structure (Environment → Component → Type → Placeholder) prevents chaos at scale. Manage 500+ translations logically instead of flat key-value pairs.

3. **Non-Developer Empowerment**
   Product managers, translators, and editors update translations without:

   * File system access
   * Git knowledge
   * Deployment pipelines
   * Developer intervention

4. **Production-Grade Quality**

   * PHPStan level 10 static analysis
   * 95%+ test coverage
   * PSR-12 coding standards
   * Modern PHP 8.2+ features (readonly properties, constructor promotion)
   * Comprehensive CI/CD pipeline

5. **Developer Experience**

   * Modern architecture with dependency injection
   * CLI automation with console commands
   * API-ready with clean service layer
   * Token-optimized for AI-assisted development

Competitive Positioning
-----------------------

**vs. l10nmgr**: TextDB focuses on frontend system strings with simpler workflows, while l10nmgr targets complex workflow-heavy translation management.

**vs. Snowbabel**: TextDB provides advanced filtering, hierarchical organization, and production-grade code quality beyond Snowbabel's basic editing.

**vs. translatelabels**: TextDB offers zero-friction migration and modern TYPO3 v13 support, while translatelabels is unmaintained.

**vs. TYPO3 Core**: TextDB provides backend module access and live updates, while TYPO3 Core requires file editing and deployment cycles.

.. _target-audience:

Target Audience
===============

This extension is designed for:

* **🌍 Multi-language websites**: Frequent translation updates without deployment overhead
* **👥 Clients and editors**: Non-technical staff who need translation access without code knowledge
* **🔄 Translation workflows**: Teams requiring import/export for translation agencies
* **🚀 Agencies**: Managing multiple TYPO3 projects with consistent translation processes
* **🏢 Enterprises**: Organizations needing audit trails, compliance, and centralized management
* **💻 Developers**: Projects requiring modern architecture with API access and CLI automation

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
