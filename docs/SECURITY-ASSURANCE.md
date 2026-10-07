<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Security assurance

This document states what users of `nr_textdb` can and cannot expect in terms of security, the threat model and trust boundaries of the extension, and how it counters common weaknesses. Every statement refers to the code at the commit that contains this file. Vulnerabilities are reported as described in [SECURITY.md](../SECURITY.md).

Versions named below are the ones Composer resolved for `typo3/cms-core: ^14.3` on 2026-09-30 (the repository tracks no `composer.lock`): TYPO3 14.3.7 and Fluid 5.3.2. The parser behaviour was checked on PHP 8.5.10 with libxml2 2.9.14.

## What the extension does, security-wise

| Entry point | Who can reach it | Input | Code |
|-------------|------------------|-------|------|
| Backend module "Netresearch > TextDB" with the actions `list`, `translated`, `translateRecord`, `import`, `export` | Backend users whose group grants the module (`'access' => 'user'`) | Filter and pagination query parameters, the translation form (`new[]`, `update[]`), one uploaded XLIFF file | `Configuration/Backend/Modules.php`, `Classes/Controller/TranslationController.php` |
| Fluid ViewHelpers `textdb:textdb` and `textdb:translate` in frontend or backend templates | Anyone who can request a page rendered with such a template | ViewHelper arguments set by the template author | `Classes/ViewHelpers/TextdbViewHelper.php`, `Classes/ViewHelpers/TranslateViewHelper.php`, `Classes/Service/TranslationService.php` |
| Console command `nr_textdb:import` | Whoever can run the TYPO3 CLI on the host | Optional extension key, `--override`; reads `textdb*.xlf` and `*.textdb*.xlf` from `Resources/Private/Language/` of installed extensions | `Classes/Command/ImportCommand.php`, `Classes/Service/ImportService.php` |
| Backend module "Netresearch > Sync > TextDB", registered only when the extension `nr_sync` is loaded | Backend users whose group grants that module (`'access' => 'user'`) | Whatever nr_sync's controller reads | `Configuration/Backend/Modules.php`: the route targets nr_sync's `BaseSyncModuleController::indexAction` and hands it the four `tx_nrtextdb_domain_model_*` tables to dump and synchronise |

The extension stores its data in the four tables `tx_nrtextdb_domain_model_*` (`ext_tables.sql`). It ships no frontend plugin, no AJAX route and no middleware.

## Security expectations

What you can expect:

- Only backend users with access to the TextDB module can list, edit, import or export TextDB records through the module. TYPO3 checks the module access and the route token before the controller runs (`BackendModuleValidator` and `RouteDispatcher::assertRequestToken()` in `typo3/cms-backend`).
- Within the module, every action applies the backend user's record permissions as DataHandler would, because the module writes through Extbase persistence and not through DataHandler: read access (`tables_select`) to the four TextDB tables and "show page" on the storage page for listing, viewing and exporting; modify access (`tables_modify`) to the translation table, or to all four tables for an import, and "edit content" on the storage page for saving and importing; for a single record, the permission on the record's own page, and for a non-admin the list and the export show only the records on the storage page; the group's language list for every saved or imported language; and the live workspace for every change. Admins pass the table and page checks (`TranslationController::mayReadTextDb()`, `mayWriteTextDb()`, `hasPageAccess()`).
- Values stored in TextDB are output as text. The ViewHelpers leave escaping to Fluid, and the module templates escape every record field.
- Uploaded XLIFF files are parsed without resolving external entities and without network access, and they are not stored on the server.
- Export archives are built in a directory with a random name and mode 0700 under the system temp directory and are removed after the response is created.

What you cannot expect:

- No permission per component, type or environment. A user who may read or modify the TextDB tables on a page may do so for every component, type and environment stored there.
- No review of imported content. An import writes the values of the file as they are; a stored value can contain any text, including markup, which is escaped on output but not rejected on input.
- No protection against record creation from templates that pass request data to the ViewHelpers. With `createIfMissing` enabled (the default in `ext_conf_template.txt`), rendering a ViewHelper with an unknown placeholder, component or environment creates the missing records. Placeholders are meant to be constants in templates; a template that builds them from request parameters lets visitors create records.
- Database error messages are shown to the module user when saving a translation fails (`translateRecordAction()` adds the exception message to the flash message).
- A single permission for all TextDB data when `nr_sync` is loaded. The TextDB sync module is granted separately from the TextDB module and gives access to the four TextDB tables through nr_sync's controller, which this extension does not implement.
- Anything outside this extension: TYPO3 core, the backend login, the web server and the database are covered by their own projects.

## Threat model and trust boundaries

| Boundary | Untrusted input | Control |
|----------|-----------------|---------|
| Browser → backend module | Query parameters, form fields, uploaded file, the module's stored filter in `be_users.uc` | TYPO3 backend authentication, module access check and route token; table, page, language and workspace checks per action (`mayReadTextDb()`, `mayWriteTextDb()`, `hasPageAccess()`); per-field validation in `translateRecordAction()`; `normalizeRecordFilter()` and `normalizeTextFilter()` for filters from the request and from `be_users.uc`; filename pattern and `LIBXML_NONET` parsing in `importAction()`; `errorAction()` only redirects to routes of this module |
| Uploaded XLIFF → database | Trans-unit ids and values | Ids must have the form `component\|type\|placeholder`, otherwise the import aborts with an error; values are stored through Extbase persistence |
| Database → browser | Stored component, type, placeholder and value | Fluid output escaping in the ViewHelpers and templates |
| Database → export file | Stored names and values | `htmlspecialchars(..., ENT_XML1 \| ENT_QUOTES)` for the id parts, CDATA with the `]]>` sequence split for values (`writeTranslationExportFile()`) |
| Template → database | ViewHelper arguments | Trusted: set by the template author; see the expectation above |
| Installed extensions → CLI import | XLIFF files under `Resources/Private/Language/` | Trusted: installed code; parsed by TYPO3's `XliffLoader` |
| Integrator → extension | Extension configuration (`textDbPid`, `createIfMissing`), site languages | Trusted |

## Secure design principles applied

- Least privilege and complete mediation: the module uses TYPO3's module access check and route token for every action (`Configuration/Backend/Modules.php`), and every action checks the user's table, page, language and workspace permissions before it reads or writes a record (`TranslationController`); the extension registers no route outside its backend modules (the TextDB module, and the TextDB sync module when `nr_sync` is loaded).
- Fail-safe defaults: request values that do not have the expected type are rejected and counted, not coerced into a write (`translateRecordAction()`); an unusable stored filter falls back to "no filter", and an export without a filter is refused (`exportAction()`).
- Economy of mechanism: output escaping is Fluid's default, not a custom escaper; database access goes through Extbase queries only (`Classes/Domain/Repository/`), with no hand-written SQL.
- Input is data, never code: stored filters are JSON, not PHP-serialised (`getConfigFromBeUserData()`); the XLIFF parser is called without `LIBXML_NOENT`.

## Countering common weaknesses

| Weakness (CWE / OWASP) | Counter | Evidence |
|------------------------|---------|----------|
| Missing authorisation (CWE-862, A01:2021) | TYPO3 module access check on the TextDB module and, when `nr_sync` is loaded, on the TextDB sync module; no route outside these modules; inside the TextDB module, the table, page, language and workspace checks of every action | `Configuration/Backend/Modules.php`; `Tests/Functional/Controller/TranslationControllerTest.php` (`translateRecordRefusesAnEditorWhoMayOnlyReadTheTranslationTable`, `translateRecordRejectsARecordOnAPageTheEditorMayNotEdit`, `translateRecordSavesOnlyTheLanguagesTheEditorMayEdit`, `importRefusesAnEditorWhoMayNotCreateComponents`, `listRefusesAnEditorWhoMayNotReadTheTranslationTable`) |
| Cross-site request forgery (CWE-352) | TYPO3 route token on every backend module request; backend session cookie `SameSite=strict` by default (`typo3/cms-core` `Configuration/DefaultConfiguration.php`, `BE.cookieSameSite`) | `Configuration/Backend/Modules.php` (the actions are module routes; the only other route the extension registers is the TextDB sync module's, when `nr_sync` is loaded) |
| Cross-site scripting (CWE-79, A03:2021) | Fluid escapes ViewHelper output (`AbstractViewHelper::$escapeOutput = true` in Fluid 5.3.2, not overridden by either ViewHelper); the module templates pass record fields through `f:format.htmlspecialchars` and use no `f:format.raw`; the module JavaScript inserts only HTML from the module's own escaped responses | `Tests/Functional/ViewHelpers/TextdbViewHelperTest.php` (`rendersAStoredValueWithMarkupEscaped`), `Resources/Private/Partials/Administration/TranslationItem.html`, `Resources/Public/JavaScript/TextDbModule.js` |
| XML external entities (CWE-611, A05:2021) | `simplexml_load_string()` with `LIBXML_NONET` and without `LIBXML_NOENT` in `importAction()`; libxml2 2.9 and later do not load external entities without `LIBXML_NOENT`; the CLI import uses TYPO3's `XliffLoader`, which also does not pass `LIBXML_NOENT` | `Tests/Unit/Controller/TranslationControllerXXETest.php` parses a payload with a `file://` entity using the flags of `importAction()`; it does not call the controller |
| Unrestricted upload (CWE-434), path traversal (CWE-22) | The upload is read from PHP's temporary upload file and never written elsewhere; the client filename is only matched against `^([a-z]{2}\.)?(textdb_(.*)\.xlf)$` to derive the language | `TranslationController::importAction()` |
| Insecure temporary file, arbitrary delete (CWE-377, CWE-73) | Export directory named with `random_bytes(16)`, created with mode 0700; removal only below `sys_get_temp_dir()` via Symfony Filesystem | `TranslationController::exportAction()`, `removeDirectory()` |
| Injection into the export file (CWE-91) | Id parts escaped with `ENT_XML1 \| ENT_QUOTES`, CDATA terminator split | `TranslationController::writeTranslationExportFile()` |
| SQL injection (CWE-89) | Extbase query objects bind values as parameters; no QueryBuilder or raw SQL in `Classes/` | `Classes/Domain/Repository/TranslationRepository.php` |
| Deserialisation of untrusted data (CWE-502) | Module filters in `be_users.uc` are JSON; legacy serialised values are discarded; every field is normalised to its declared type | `getConfigFromBeUserData()`; `Tests/Functional/Controller/TranslationControllerTest.php` (`exportWithAnUnusableStoredFilterIsRefused`, `listingTheModuleDiscardsASubmittedFilterOfTheWrongType`) |
| Improper input validation (CWE-20) | Keys and values of `new[]` and `update[]` are type-checked, languages must belong to the first site | `Tests/Functional/Controller/TranslationControllerTest.php` (`translateRecordSkipsAnUpdateKeyThatIsNotAnInteger`, `translateRecordSkipsANewEntryForALanguageThatIsNotConfiguredOnTheFirstSite`, `translateRecordSavesAMixOfValidAndRejectedEntries`) |
| Open redirect (CWE-601) | `errorAction()` builds the redirect with this module's URI builder and rejects referrers from other extensions or unroutable actions | `Tests/Functional/Controller/TranslationControllerTest.php` (`translateRecordThroughTheRouteRejectsAReferrerFromAForeignExtension`, `translateRecordThroughTheRouteRejectsAReferrerTargetingAnUnroutableAction`) |
| Hard-coded credentials (CWE-798) | None in the code; Betterleaks scans every pull request | `.github/workflows/checks.yml` |
| Vulnerable and outdated components (A06:2021) | Composer Audit and Dependency Review on every pull request, Renovate update pull requests | `.github/workflows/checks.yml`, `renovate.json` |

## Verification

The checks listed in [CONTRIBUTING.md](../CONTRIBUTING.md#governance-and-policies) run on every pull request: PHPStan at level 10, Opengrep, CodeQL for the JavaScript and the workflows, Composer Audit, Dependency Review, Betterleaks, and the unit and functional test suites. Findings are handled as described in the organisation's [policy for dependency and code analysis findings](https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings).
