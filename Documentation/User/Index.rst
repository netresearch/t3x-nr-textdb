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
* **File Size**: Large files may timeout - split into smaller files

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
