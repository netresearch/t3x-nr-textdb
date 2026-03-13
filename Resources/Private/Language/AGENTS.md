# Translation Files - AI Agent Instructions

## Overview

This directory contains XLIFF translation files for the nr_textdb TYPO3 extension. All translation files must follow XLIFF 1.2 specification and maintain complete coverage across all supported languages.

## Critical Rules

### 1. XLIFF Version Requirement

**MANDATORY**: All translation files MUST use XLIFF 1.2, not XLIFF 1.0.

✅ **Correct XLIFF 1.2 format**:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
  <file source-language="en" datatype="plaintext" original="messages" date="2024-01-15T12:00:00Z" product-name="nr_textdb" target-language="de">
    <header/>
    <body>
      <trans-unit id="example.key" resname="example.key">
        <source>English text</source>
        <target state="translated">German text</target>
      </trans-unit>
    </body>
  </file>
</xliff>
```

❌ **WRONG - XLIFF 1.0 format** (never use this):
```xml
<xliff version="1.0">
```

### 2. Complete Language Coverage

**MANDATORY**: When adding new translation strings, you MUST create translation files for ALL supported languages.

**Supported languages** (31 languages total):
- `af` - Afrikaans
- `ar` - Arabic
- `ca` - Catalan
- `cs` - Czech
- `da` - Danish
- `de` - German
- `el` - Greek
- `es` - Spanish
- `fi` - Finnish
- `fr` - French
- `he` - Hebrew
- `hi` - Hindi
- `hu` - Hungarian
- `id` - Indonesian
- `it` - Italian
- `ja` - Japanese
- `ko` - Korean
- `nl` - Dutch
- `no` - Norwegian
- `pl` - Polish
- `pt` - Portuguese
- `ro` - Romanian
- `ru` - Russian
- `sr` - Serbian
- `sv` - Swedish
- `sw` - Swahili
- `th` - Thai
- `tr` - Turkish
- `uk` - Ukrainian
- `vi` - Vietnamese
- `zh` - Chinese

**English (`en`)** is the source language and goes in base files (e.g., `locallang.xlf`, `locallang_scheduler.xlf`).

### 3. File Naming Convention

**Source files** (English):
- `locallang.xlf` - Main backend labels
- `locallang_mod.xlf` - Module labels
- `locallang_scheduler.xlf` - Scheduler task labels
- `locallang_db.xlf` - Database field labels (if present)

**Translation files** (all other languages):
- `{lang}.locallang.xlf` - Main backend labels
- `{lang}.locallang_mod.xlf` - Module labels
- `{lang}.locallang_scheduler.xlf` - Scheduler task labels
- `{lang}.locallang_db.xlf` - Database field labels (if present)

Examples:
- `de.locallang.xlf` (German main labels)
- `fr.locallang_scheduler.xlf` (French scheduler labels)
- `es.locallang_mod.xlf` (Spanish module labels)

### 4. Translation Quality Standards

**Professional translations required**:
- Use `state="translated"` for completed, professional translations
- Use `state="needs-translation"` ONLY when professional translation is pending
- Never leave English text in target elements (no lazy copy-paste)
- Maintain consistent terminology across all files
- Respect cultural and linguistic nuances

### 5. Required XLIFF Structure Elements

Every translation file MUST include:

1. **XML Declaration**:
   ```xml
   <?xml version="1.0" encoding="UTF-8"?>
   ```

2. **XLIFF Root with Namespace**:
   ```xml
   <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
   ```

3. **File Element with Attributes**:
   ```xml
   <file source-language="en" datatype="plaintext" original="messages"
         date="2024-01-15T12:00:00Z" product-name="nr_textdb" target-language="{lang}">
   ```

4. **Header** (required but can be empty):
   ```xml
   <header/>
   ```

5. **Body with trans-units**:
   ```xml
   <body>
     <trans-unit id="key.name" resname="key.name">
       <source>English text</source>
       <target state="translated">Translated text</target>
     </trans-unit>
   </body>
   ```

### 6. Workflow for Adding New Strings

When adding new translation keys:

1. **Add to English source file first** (e.g., `locallang_scheduler.xlf`):
   ```xml
   <trans-unit id="new.key" resname="new.key">
     <source>New English text</source>
   </trans-unit>
   ```

2. **Create/update ALL 30 language files** with professional translations:
   ```xml
   <trans-unit id="new.key" resname="new.key">
     <source>New English text</source>
     <target state="translated">Neue deutsche Text</target>
   </trans-unit>
   ```

3. **Verify completeness**: Ensure every language file contains the new key with proper translation

4. **Test in TYPO3**: Verify translations display correctly in backend

### 7. Common Mistakes to Avoid

❌ **NEVER use XLIFF 1.0**:
```xml
<xliff version="1.0">  <!-- WRONG -->
```

❌ **NEVER skip languages**:
```
Only created de.locallang_scheduler.xlf but forgot other 29 languages
```

❌ **NEVER use English text in target**:
```xml
<target state="translated">English text</target>  <!-- WRONG for non-English files -->
```

❌ **NEVER use lazy state for production**:
```xml
<target state="needs-translation">...</target>  <!-- Only acceptable temporarily -->
```

❌ **NEVER forget target-language attribute**:
```xml
<file source-language="en" ... >  <!-- Missing target-language="de" -->
```

### 8. Special Considerations

**Technical terms**: Some technical terms may remain untranslated if they're industry-standard:
- "Messenger" (often kept as-is)
- "Scheduler" (sometimes translated, sometimes not - check existing patterns)
- "Transport" (check existing usage in that language)

**Placeholders**: Preserve placeholder syntax:
```xml
<source>Welcome %s to %s</source>
<target>Bienvenue %s à %s</target>
```

**Line breaks**: Preserve `\n` and other escape sequences:
```xml
<source>Line 1\nLine 2</source>
<target>Zeile 1\nZeile 2</target>
```

### 9. Validation Checklist

Before committing translation changes:

- [ ] All files use XLIFF 1.2 (not 1.0)
- [ ] All 30 language files created/updated
- [ ] Each file has correct `target-language` attribute
- [ ] All translations are professional quality (not English copy-paste)
- [ ] All trans-units have matching IDs across all files
- [ ] Files are UTF-8 encoded
- [ ] XML is well-formed (validate with TYPO3 or XML tools)
- [ ] Tested in TYPO3 backend (if possible)

### 10. Integration with Crowdin

This project uses Crowdin for translation management. The workflow is:

1. **Update English source files** in Git repository
2. **Crowdin sync** pulls new English strings
3. **Community translates** via Crowdin platform
4. **Crowdin sync** pushes completed translations back to repository

**AI Agent Note**: When manually creating translation files (as done here), ensure they align with Crowdin expectations. Check `crowdin.yml` in project root for file mapping configuration.

## Examples

### Example 1: Adding a new scheduler task

**File: `Resources/Private/Language/locallang_scheduler.xlf`** (English source):
```xml
<trans-unit id="task.newTask.title" resname="task.newTask.title">
  <source>My New Task</source>
</trans-unit>
```

**File: `Resources/Private/Language/de.locallang_scheduler.xlf`** (German):
```xml
<trans-unit id="task.newTask.title" resname="task.newTask.title">
  <source>My New Task</source>
  <target state="translated">Meine neue Aufgabe</target>
</trans-unit>
```

**File: `Resources/Private/Language/fr.locallang_scheduler.xlf`** (French):
```xml
<trans-unit id="task.newTask.title" resname="task.newTask.title">
  <source>My New Task</source>
  <target state="translated">Ma nouvelle tâche</target>
</trans-unit>
```

...and so on for ALL 30 languages.

### Example 2: Complete minimal translation file structure

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
  <file source-language="en" datatype="plaintext" original="messages" date="2024-01-15T12:00:00Z" product-name="nr_textdb" target-language="de">
    <header/>
    <body>
      <trans-unit id="example.key" resname="example.key">
        <source>Example English text</source>
        <target state="translated">Beispiel deutscher Text</target>
      </trans-unit>
    </body>
  </file>
</xliff>
```

## Summary

- **XLIFF 1.2 ONLY** - never use 1.0
- **ALL 30 languages** - complete coverage required
- **Professional quality** - no lazy English copy-paste
- **Consistent structure** - follow the template exactly
- **Verify completeness** - check all files before committing

When in doubt, reference existing translation files in this directory as templates.
