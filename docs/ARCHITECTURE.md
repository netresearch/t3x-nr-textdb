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
| Translation service | Lookup with per-request cache; auto-creates missing translation records (incl. default-language parent) when `createIfMissing` is enabled | `Classes/Service/TranslationService.php` |
| Import service | Parses XLIFF files and creates/updates translation records; returns an `ImportResult` | `Classes/Service/ImportService.php`, `Classes/Service/ImportResult.php` |
| Backend module | Extbase controller: list/filter, translate records, XLIFF import/export | `Classes/Controller/TranslationController.php`, `Configuration/Backend/Modules.php` |
| CLI import | Symfony Console command wrapping the import service | `Classes/Command/ImportCommand.php` |
| Domain models | `Translation` plus its lookup dimensions `Environment`, `Component`, `Type` | `Classes/Domain/Model/` |
| Repositories | Extbase persistence; `AbstractRepository` holds shared query settings | `Classes/Domain/Repository/` (`TranslationRepository`, `EnvironmentRepository`, `ComponentRepository`, `TypeRepository`) |
| Persistence schema | Tables `tx_nrtextdb_domain_model_*` with TCA definitions | `ext_tables.sql`, `Configuration/TCA/` |
| Backend UI assets | Backend module JavaScript and templates | `Resources/Public/JavaScript/TextDbModule.js`, `Resources/Private/` |

## Data Flow

- **Frontend lookup**: Fluid template → `TranslateViewHelper`/`TextdbViewHelper` → `TranslationService::translate()` → repositories (environment/component/type/translation) → value returned; if no record exists and auto-create is enabled, the service persists a new `Translation` (and a default-language parent for localized requests) before returning the placeholder.
- **Import**: Backend module upload (`TranslationController::importAction()`) or CLI (`ImportCommand`) → `ImportService::importFile()` → `importEntry()` per XLIFF unit → repositories → `ImportResult` summary.
- **Export**: `TranslationController::exportAction()` collects translations and streams an XLIFF archive (uses `ext-zip`).

## Key Decisions

There are no ADR documents in this repository. Rendered user/administrator documentation lives in `Documentation/` (published at https://docs.typo3.org/p/netresearch/nr-textdb/main/en-us/); the extension configuration surface is defined in `ext_conf_template.txt`.
