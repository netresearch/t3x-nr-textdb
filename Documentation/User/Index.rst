.. SPDX-License-Identifier: GPL-3.0-or-later
.. SPDX-FileCopyrightText: Netresearch DTT GmbH

.. include:: /Includes.rst.txt

.. _user-manual:

===========
User Manual
===========

.. _user-introduction:

Introduction for Editors
=========================

The TextDB extension provides a user-friendly interface for managing translations
directly in the TYPO3 backend. As an editor, you can:

* View and edit existing translations
* Filter translations by component, type, and environment
* Import and export translation files
* Search for specific translations
* Manage multilingual content without technical knowledge

.. _accessing-module:

Accessing the TextDB Module
============================

1. Log in to the TYPO3 backend
2. In the left menu, click on **Netresearch**
3. Select **Netresearch TextDB** from the submenu

The TextDB module is located under the **Netresearch** section in the backend menu.

.. _viewing-translations:

Viewing Translations
====================

List View
---------

The main list view displays all translation records:

* **Placeholder**: The translation key/identifier
* **Value**: The translated text
* **Component**: The component this translation belongs to
* **Type**: The translation type (label, message, etc.)
* **Environment**: Target environment (if applicable)
* **Language**: The language of the translation

Pagination
----------

Large translation sets are automatically paginated:

* Use the pagination controls at the bottom
* Adjust items per page if needed
* Navigate between pages efficiently

.. _filtering-translations:

Filtering Translations
======================

Use the filter options to narrow down your results:

Component Filter
----------------

Select a specific component to show only its translations:

1. Click the **Component** dropdown
2. Select the desired component
3. The list updates automatically

Type Filter
-----------

Filter by translation type:

1. Click the **Type** dropdown
2. Select label, message, or other types
3. View filtered results

Search
------

Search for specific translations:

1. Enter search term in the search box
2. Search applies to:
   * Placeholder (key)
   * Translation value
3. Press Enter or click Search

.. tip::

   Combine filters for precise results (e.g., Component + Type + Search)

.. _editing-translations:

Editing Translations
====================

Edit Single Translation
-----------------------

1. Click the **pencil icon** next to a translation
2. Modify the translation value
3. Update other fields if needed:
   * Component assignment
   * Type assignment
   * Environment
4. Click **Save**

Multi-Language Editing
----------------------

To edit translations in different languages:

1. Switch the language in the TYPO3 toolbar
2. Edit the translation for that language
3. Repeat for additional languages

.. attention::

   Ensure you're editing the correct language before making changes!

.. _importing-translations:

Importing Translations
======================

Import from XLIFF File
----------------------

1. Click the **Import** button in the toolbar
2. Select the XLIFF file to import
3. Choose import options:

   * **Overwrite Existing**: Check to replace existing translations
   * Leave unchecked to only import new translations

4. Click **Import**
5. Review the import summary

.. _import-skipped-units:

Units the Import Skips
----------------------

The import names the units it leaves out or shortens after the import, in
one warning per kind. Each warning gives the number of units and the keys of
the first ten.

**Not approved.** In a translation file (XLIFF 1.2 with ``target-language``,
XLIFF 2.0 with ``trgLang``), TYPO3 reads only approved units while
``$GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']`` is
enabled, which is the TYPO3 default. A unit is not approved when it is
marked ``approved="no"`` (XLIFF 1.2) or when its segment has
``state="initial"`` or ``state="translated"`` (XLIFF 2.0). The warning names
the setting. To import such units, approve them in the file, or ask the
administrator to disable the setting. The setting applies to every XLIFF
file TYPO3 reads, not only to the TextDB import.

**Plural forms not approved.** The same setting applies to each form of a
plural unit (a ``restype="x-gettext-plurals"`` group in XLIFF 1.2, a unit
with several segments in XLIFF 2.0). When only some of its first two forms
are approved, the unit is imported with the approved forms only, and the
warning names it. When no form is approved, the unit counts as not approved.
When the approved forms have no text, the unit is reported as having no text.

**No text.** A unit whose text is empty or only whitespace, for example an
untranslated unit without ``<target>`` in an XLIFF 1.2 translation file.
Importing it would overwrite the stored value with nothing.

Three cases are not reported, because TYPO3 drops the text while reading
the file and the import never sees it:

* A unit of an XLIFF 2.0 translation file without ``<target>``. It is not
  imported.
* A segment without ``<target>`` in a plural unit of an XLIFF 2.0
  translation file. The unit is imported without that form.
* The third and every later form of a plural unit, as languages such as
  Russian or Polish have. TYPO3 keeps the first two forms only, whether the
  later ones are approved or not, so these forms are never imported, and a
  plural unit whose only unapproved form is the third or a later one is not
  reported.

A unit of a default-language file (no ``target-language`` or ``trgLang``) is
never skipped for approval.

Expected File Format
--------------------

English Source File (en):

.. code-block:: xml

   <?xml version="1.0" encoding="utf-8" standalone="yes" ?>
   <xliff version="1.0">
       <file source-language="en" datatype="plaintext" original="messages">
           <header>
               <authorName>Your Name</authorName>
               <authorEmail>you@example.com</authorEmail>
           </header>
           <body>
               <trans-unit id="component|type|placeholder">
                   <source>Translation value</source>
               </trans-unit>
           </body>
       </file>
   </xliff>

Translated File (e.g., German):

.. code-block:: xml

   <?xml version="1.0" encoding="utf-8" standalone="yes" ?>
   <xliff version="1.0">
       <file source-language="en" target-language="de" datatype="plaintext" original="messages">
           <header>
               <authorName>Your Name</authorName>
               <authorEmail>you@example.com</authorEmail>
           </header>
           <body>
               <trans-unit id="component|type|placeholder">
                   <target>Übersetzungswert</target>
               </trans-unit>
           </body>
       </file>
   </xliff>

.. important::

   File naming convention:
   
   * English: `textdb_[name].xlf`
   * Other languages: `[lang].textdb_[name].xlf` (e.g., `de.textdb_website.xlf`)

.. _exporting-translations:

Exporting Translations
======================

Export Filtered Translations
-----------------------------

1. Apply desired filters (component, type, search)
2. Click **Export with current filter**
3. The system exports:
   * All translations matching current filters
   * All languages
   * Ignores pagination

4. Save the XLIFF file to your computer

Use Cases for Export
--------------------

* **External Translation**: Send to translation agency
* **Backup**: Create snapshots of translations
* **Migration**: Move translations between TYPO3 instances
* **Review**: Share with stakeholders for review

.. _translation-workflow:

Translation Workflow
====================

Typical Workflow
----------------

1. **Developer** creates placeholder translations
2. **Editor** reviews and refines translations
3. **Translator** (if needed):
   
   a. Editor exports current translations
   b. Translator works on XLIFF file
   c. Editor imports translated file

4. **Editor** verifies imported translations
5. Translations are immediately available on frontend

Best Practices
--------------

* **Consistent Naming**: Use clear, descriptive placeholders
* **Component Organization**: Group related translations
* **Regular Backups**: Export translations periodically
* **Language Review**: Have native speakers review translations
* **Testing**: Verify translations on frontend after changes

.. _troubleshooting-user:

Troubleshooting
===============

Translation Not Showing
-----------------------

* **Check Language**: Ensure correct language is selected
* **Verify Filters**: Clear all filters to see all translations
* **Refresh Cache**: Ask administrator to clear frontend cache

Import Failed
-------------

* **File Format**: Verify XLIFF file format is correct
* **File Naming**: Check file name follows convention
* **Permissions**: Contact administrator if upload fails
* **File Size**: A file with tens of thousands of entries can take longer
  than the time limit of the backend. Split it into smaller files, or ask
  the administrator to import it with the CLI command

Cannot Edit Translation
-----------------------

* **Permissions**: Contact administrator for access rights
* **Lock Status**: Another user may be editing - wait and retry
* **Language Access**: Verify you have access to that language

.. _keyboard-shortcuts:

Keyboard Shortcuts
==================

The TextDB module supports standard TYPO3 keyboard shortcuts:

* **Ctrl+S**: Save current record (when editing)
* **Ctrl+Shift+S**: Save and close
* **Escape**: Close edit form without saving

.. _tips-tricks:

Tips & Tricks
=============

Quick Search
------------

Use the search box to quickly locate translations by partial matches.

Bulk Operations
---------------

For bulk changes, consider exporting, editing in a text editor, and re-importing.

Favorites
---------

Bookmark frequently used filter combinations in your browser.

Translation Preview
-------------------

Keep a frontend tab open to immediately verify translation changes.
