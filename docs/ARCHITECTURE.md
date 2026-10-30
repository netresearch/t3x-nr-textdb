<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Architecture

<!-- Keep this document current. It's referenced from AGENTS.md and read by agents
     when they need to understand the system before making changes.
     Human-facing documentation lives in Documentation/ (rendered on docs.typo3.org). -->

## System Overview

TYPO3 extension (`nr_textdb`) providing an auto-creating translation database: Fluid templates use ViewHelpers to look up translations by environment/component/type/placeholder; missing entries are created automatically on first use, and editors maintain the values in a backend module. Translations can be imported from and exported to XLIFF files via the backend module or a CLI command.

## Component Map

| Component | Responsibility | Key Files |
|---|---|---|
| ViewHelpers | Fluid entry points for translation lookup (`textdb:translate`, `textdb:textdb`) | `Classes/ViewHelpers/TranslateViewHelper.php`, `Classes/ViewHelpers/TextdbViewHelper.php` |
| Translation service | Lookup with per-request cache; auto-creates missing translation records (incl. default-language parent) when `createIfMissing` is enabled; read-only lookup along the language fallback chain (`translateFromFallbackLanguages()`) | `Classes/Service/TranslationService.php` |
| Import service | Parses XLIFF files and creates/updates translation records; returns an `ImportResult` | `Classes/Service/ImportService.php`, `Classes/Service/ImportResult.php` |
| Backend module | Extbase controller: list/filter, translate records, XLIFF import/export | `Classes/Controller/TranslationController.php`, `Configuration/Backend/Modules.php` |
| CLI import | Symfony Console command wrapping the import service | `Classes/Command/ImportCommand.php` |
| Domain models | `Translation` plus its lookup dimensions `Environment`, `Component`, `Type` | `Classes/Domain/Model/` |
| Repositories | Extbase persistence; `AbstractRepository` holds shared query settings | `Classes/Domain/Repository/` (`TranslationRepository`, `EnvironmentRepository`, `ComponentRepository`, `TypeRepository`) |
| Persistence schema | Tables `tx_nrtextdb_domain_model_*` with TCA definitions | `ext_tables.sql`, `Configuration/TCA/` |
| Backend UI assets | Backend module JavaScript and templates | `Resources/Public/JavaScript/TextDbModule.js`, `Resources/Private/` |

## Data Flow

- **Frontend lookup**: Fluid template → `TranslateViewHelper`/`TextdbViewHelper` → `TranslationService::findTranslation()` → repositories (environment/component/type/translation) → the value, or null when the current language has no usable entry; if no record exists and auto-create is enabled, the service persists a new `Translation` (and a default-language parent for localized requests) before returning null. On null, `TranslateViewHelper` asks the language file first; then both ViewHelpers try `TranslationService::translateFromFallbackLanguages()` (the fallback chain of the current language, then language 0) before rendering the key. `TranslationService::translate()` wraps `findTranslation()` and returns the key for null.
- **Import**: Backend module upload (`TranslationController::importAction()`) → `ImportService::loadFile()` → `ImportService::importEntries()`; CLI (`ImportCommand`) → `ImportService::importFile()` → `loadFile()` → `importEntries()`. `loadFile()` reads the file with TYPO3's `XliffLoader`, and a second time with `requireApprovedLocalizations` disabled to name the units left out as not approved and the plural units kept without their unapproved forms. `importEntries()` checks every key, skips units without text, then writes the units in chunks of 1,000: environment, component and type through their repositories, the translations through `TranslationTableWriter` without Extbase (one lookup query per chunk, bulk `INSERT` for new records, one `UPDATE` per changed record, `ReferenceIndex::updateRefIndexTable()` per written record) → `ImportResult` summary, which carries the keys of the skipped units of both kinds. The writes of a file run in one transaction; a database error, and any exception raised while the persistence session is written, is thrown on instead of recorded, so a failed statement or write rolls back the whole file on every database. `importEntry()` imports a single unit through Extbase with the same error rule and is not used by `importEntries()`.
- **Export**: `TranslationController::exportAction()` collects translations and streams an XLIFF archive (uses `ext-zip`).

## Key Decisions

There are no ADR documents in this repository. Rendered user/administrator documentation lives in `Documentation/` (published at https://docs.typo3.org/p/netresearch/nr-textdb/main/en-us/); the extension configuration surface is defined in `ext_conf_template.txt`.
