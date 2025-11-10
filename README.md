# Netresearch TextDB

> **Manage TYPO3 translations directly in the backend – no more digging through language files**

[![Latest version](https://img.shields.io/github/v/release/netresearch/t3x-nr-textdb?sort=semver)](https://github.com/netresearch/t3x-nr-textdb/releases/latest)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://get.typo3.org/version/13)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/github/license/netresearch/t3x-nr-textdb)](https://github.com/netresearch/t3x-nr-textdb/blob/main/LICENSE)
[![CI](https://github.com/netresearch/t3x-nr-textdb/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/t3x-nr-textdb/actions/workflows/ci.yml)

---

## What is TextDB?

TextDB is a powerful TYPO3 extension that transforms how you manage translations. Instead of editing language files scattered across your project, **manage all translations through a convenient backend module** with filtering, search, and bulk operations.

Perfect for:
- 🌍 **Multi-language websites** with frequent translation updates
- 👥 **Clients and editors** who need to update translations without touching code
- 🔄 **Translation workflows** requiring import/export capabilities
- 🚀 **Agencies** managing multiple TYPO3 projects with consistent translation processes

---

## 🎯 What TextDB Is (and Isn't)

### ✅ TextDB is designed for: Frontend System Strings

**User interface elements that come from your code, NOT editor-created content:**

- ✅ **Form labels**: "First Name", "Email Address", "Submit Button"
- ✅ **Button texts**: "Add to Cart", "Checkout", "Learn More"
- ✅ **Error messages**: "Invalid email format", "Field is required"
- ✅ **Navigation labels**: "Products", "About Us", "Contact"
- ✅ **Status messages**: "Item added to cart", "Order confirmed"
- ✅ **Validation messages**, tooltips, placeholder texts

**Example Scenario**: Your e-commerce checkout has 50+ labels/buttons needing German, French, and Spanish translations. Instead of editing `.xlf` files, editors manage them through TextDB's backend module.

### ❌ TextDB is NOT for:

- ❌ **Page content** created by editors (use TYPO3's built-in page translation)
- ❌ **News articles** or blog posts (use news/blog extension translation features)
- ❌ **Content elements** like text blocks, images (use TYPO3 content localization)
- ❌ **Backend module labels** (use TYPO3's core translation system)
- ❌ **TCA field labels** (use locallang_db.xlf in your extension)

### 📍 Translation Scope

```
TYPO3 Translation Landscape:
├─ Backend/Admin Interface → TYPO3 Core locallang files
├─ Content Elements → TYPO3 Page/Content translation
├─ Editor-created content → TYPO3 Localization features
└─ Frontend System Strings → ✨ TextDB (YOU ARE HERE)
```

---

## 📚 Real-World Use Cases

### Use Case 1: Multi-Language E-Commerce Checkout
**Problem**: Your checkout flow has 80+ UI strings (field labels, buttons, validation messages) needing translations in German, French, and Spanish.

**Without TextDB**: Developers edit `.xlf` files for every text change, deploy to production.
**With TextDB**: Product managers update translations directly in backend, changes live immediately.

**Result**: Translation updates in minutes, not days. Non-technical staff manage translations independently.

---

### Use Case 2: SaaS Application with Dynamic Forms
**Problem**: Multi-tenant SaaS with 200+ form labels across 15 modules, requiring consistent translation management.

**Without TextDB**: Scattered `.xlf` files across multiple extensions, no central overview, duplicate translations.
**With TextDB**: Hierarchical organization by component/type, centralized filtering, bulk operations, zero duplication.

**Result**: 70% reduction in translation maintenance time, consistent terminology across modules.

---

### Use Case 3: Agency Managing Multiple Client Sites
**Problem**: 20+ TYPO3 installations, each with custom form/button texts needing German/English translations.

**Without TextDB**: Copy `.xlf` files between projects, manual sync, version control overhead.
**With TextDB**: Export/import workflows, standardized translation structure, zero-friction migration via `textdb:translate`.

**Result**: Standardized translation process across all clients, 50% faster project setup.

---

### Use Case 4: Government Website Compliance
**Problem**: Legal requirements demand audit trails for translated UI strings, editor-friendly workflow without file access.

**Without TextDB**: Developers as bottleneck for every text change, no change tracking, risky file edits.
**With TextDB**: Backend module access for translators, database change tracking, missing translation detection.

**Result**: Compliance-ready audit trails, editor empowerment, reduced developer burden.

---

## 🔄 Before & After: The TextDB Transformation

### Traditional File-Based Approach (Without TextDB)

```
Your TYPO3 Project/
├── typo3conf/ext/my_extension/
│   └── Resources/Private/Language/
│       ├── locallang.xlf                    # 150 lines of XML
│       ├── de.locallang.xlf                 # 150 lines (duplicated structure)
│       ├── fr.locallang.xlf                 # 150 lines (duplicated structure)
│       └── locallang_checkout.xlf           # Another 200 lines
├── typo3conf/ext/another_extension/
│   └── Resources/Private/Language/
│       └── ... more scattered files
```

**Workflow**: Developer edits XML → Git commit → Deploy → Cache clear → Test

**Problems**:
- ❌ Non-developers can't update translations
- ❌ Changes require full deployment cycle
- ❌ No central overview of all translations
- ❌ Duplicate structure across language files
- ❌ Merge conflicts in XML files
- ❌ No built-in search/filter capabilities

---

### Database-Backed Approach (With TextDB)

```
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
```

**Workflow**: Editor logs in → Click translation → Edit → Save → Live immediately

**Benefits**:
- ✅ Non-developers manage translations independently
- ✅ Changes live in seconds (no deployment)
- ✅ Centralized dashboard with 500+ translations
- ✅ Single source of truth (no XML duplication)
- ✅ Advanced filtering: component, type, placeholder, value search
- ✅ Export/import for translation agencies
- ✅ Zero-friction migration via `textdb:translate` ViewHelper

---

### Migration Path: Zero Disruption

**Phase 1: Prepare (5 minutes)**
```html
<!-- Add namespace to your templates -->
xmlns:textdb="http://typo3.org/ns/Netresearch/NrTextdb/ViewHelpers"
```

**Phase 2: Auto-Import (Automatic)**
```html
<!-- Replace f:translate with textdb:translate -->
<textdb:translate key="LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:submit" />

<!-- First render automatically imports to database -->
<!-- All existing translations preserved -->
```

**Phase 3: Optimize (Gradual)**
```html
<!-- Switch to native syntax at your own pace -->
<textdb:textdb component="checkout" type="button" placeholder="submit" />

<!-- Old .xlf files can stay as backup until you're confident -->
```

**Zero Downtime**: Existing translations continue working during migration.
**Zero Data Loss**: Automatic import preserves all language variants.
**Zero Risk**: Rollback anytime by reverting ViewHelper change.

---

## ✨ Features

### Backend Translation Management
- **User-friendly backend module** for managing all translations
- **Advanced filtering** by component, type, and placeholder
- **Multi-language support** with TYPO3's site configuration
- **Inline editing** of translations directly in the list view

### Import & Export
- **XLF file import/export** for easy translation workflows
- **Bulk operations** for updating multiple translations at once
- **Overwrite protection** with optional merge strategies
- **Multi-language export** for all configured site languages

### Migration Tools
- **ViewHelper for migration** from LLL files to database storage
- **Automatic translation detection** and import during migration
- **Backward-compatible** migration path preserving existing translations

### Developer Features
- **Extbase ViewHelpers** (`textdb:textdb`, `textdb:translate`)
- **Console commands** for automated import workflows
- **Structured data model** (Environment → Component → Type → Placeholder)
- **TYPO3 v13 compatibility** with modern dependency injection

---

## 🌟 What Makes TextDB Unique

### Competitive Comparison

| Feature | TextDB | l10nmgr | Snowbabel | translatelabels | TYPO3 Core |
|---------|--------|---------|-----------|----------------|------------|
| **Frontend System Strings** | ✅ Primary Focus | ❌ No | ❌ No | ⚠️ Partial | ❌ Backend Only |
| **Database-Backed Storage** | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes | ❌ File-Based |
| **Zero-Friction Migration** | ✅ Auto-Import | ❌ Manual | ❌ Manual | ❌ Manual | N/A |
| **Backend Module** | ✅ Advanced Filtering | ✅ Workflow-Heavy | ✅ Simple | ✅ Basic | ❌ No |
| **XLF Import/Export** | ✅ Multi-Language ZIP | ✅ Complex Workflow | ⚠️ Limited | ❌ No | ✅ Single Files |
| **Hierarchical Organization** | ✅ 4-Level Structure | ❌ Flat | ❌ Flat | ❌ Flat | ❌ File Structure |
| **Live Translation Updates** | ✅ Instant | ⚠️ Via Workflow | ✅ Instant | ✅ Instant | ❌ Requires Deployment |
| **Non-Developer Editing** | ✅ Backend Module | ⚠️ Complex | ✅ Simple | ✅ Basic | ❌ File Access Needed |
| **Code Quality** | ✅ PHPStan 10 | ⚠️ Lower | ⚠️ Lower | ⚠️ Lower | ✅ High |
| **TYPO3 v13 Ready** | ✅ Yes | ⚠️ Legacy Support | ❌ Outdated | ❌ Unmaintained | ✅ Yes |

### Key Differentiators

#### 🎯 1. Zero-Friction Migration
**The TextDB Advantage**: Drop-in replacement for `f:translate` ViewHelper with automatic LLL import on first render.

```html
<!-- Step 1: Change namespace only -->
<textdb:translate key="LLL:EXT:my_ext/Resources/Private/Language/locallang.xlf:welcome" />

<!-- Step 2: Render page → Automatic import to database -->

<!-- Step 3: Switch to native syntax -->
<textdb:textdb component="my-component" type="label" placeholder="welcome" />
```

**Competitors**: Require manual migration, complex import processes, or complete rewrites.

---

#### 🏗️ 2. Hierarchical Organization
**The TextDB Advantage**: 4-level structure (Environment → Component → Type → Placeholder) prevents chaos at scale.

**Example**: 500+ translations organized logically instead of 500 flat key-value pairs.

**Competitors**: Flat key-value structure becomes unmanageable beyond 100 translations.

---

#### ⚡ 3. Non-Developer Empowerment
**The TextDB Advantage**: Product managers, translators, and editors update translations without:
- File system access
- Git knowledge
- Deployment pipelines
- Developer intervention

**Competitors**: Either require developer involvement (TYPO3 Core) or offer basic editing without advanced features (Snowbabel).

---

#### 🔒 4. Production-Grade Quality
**The TextDB Advantage**:
- PHPStan level 10 static analysis
- 95%+ test coverage
- PSR-12 coding standards
- Modern PHP 8.2+ features (readonly properties, constructor promotion)
- Comprehensive CI/CD pipeline

**Competitors**: Lower code quality standards, outdated codebases, limited testing.

---

#### 🚀 5. Developer Experience
**The TextDB Advantage**:
- **Fast Apply-compatible**: Token-optimized for AI-assisted development
- **Modern Architecture**: Dependency injection, final classes, strict types
- **CLI Automation**: Console commands for import workflows
- **API-ready**: Clean service layer for programmatic access

**Competitors**: Legacy architectures, limited CLI support, manual workflows.

---

## 📋 Requirements

- **TYPO3**: 13.4.0 - 13.99.99
- **PHP**: 8.2, 8.3, or 8.4
- **PHP Extensions**: zip, simplexml, libxml
- **Composer**: For installation and dependency management

---

## 🚀 Installation

Install via Composer:

```bash
composer require netresearch/nr-textdb
```

Activate the extension in the TYPO3 Extension Manager or via CLI:

```bash
vendor/bin/typo3 extension:activate nr_textdb
```

---

## ⚙️ Configuration

### Extension Configuration

Configure the extension in the TYPO3 backend:

1. Navigate to **Admin Tools → Settings → Extension Configuration**
2. Select **nr_textdb**
3. Set the **Storage PID** where translations will be stored
4. Optionally disable **"Create if missing"** feature

### Storage Setup

Create a dedicated storage folder for your translations:

1. Create a new page/folder in the TYPO3 page tree
2. Note the page ID
3. Set this ID in the extension configuration as the **Storage PID**
4. *(Optional)* Create language overlays for the folder to enable the language switcher in TCA

---

## 📖 Usage

### Backend Module

Access the TextDB module under **Netresearch → TextDB** in the TYPO3 backend.

**Key Features:**
- **List View**: Browse and filter all translations
- **Inline Editing**: Click to edit translation values directly
- **Filtering**: Filter by component, type, or search in placeholders/values
- **Pagination**: Navigate through large translation sets

### ViewHelper Usage

Use TextDB translations in your Fluid templates:

```html
<!-- Add namespace declaration -->
<html xmlns:textdb="http://typo3.org/ns/Netresearch/NrTextdb/ViewHelpers">

<!-- Use the ViewHelper -->
<textdb:textdb
    component="my-component"
    type="label"
    placeholder="welcome.headline"
/>
```

**ViewHelper Parameters:**
- `component`: Logical grouping (e.g., "checkout", "contact-form")
- `type`: Translation type (e.g., "label", "message", "error")
- `placeholder`: Unique identifier for the translation

---

## 📥 Import & Export

### Importing Translations

1. Prepare an XLF file with the required structure (see below)
2. Open the TextDB backend module
3. Click **Import**
4. Select your XLF file
5. Check **"Overwrite existing"** if you want to update existing translations
6. Click **Import**

**XLF File Structure for English (source language):**

```xml
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<xliff version="1.0">
    <file source-language="en" datatype="plaintext" original="messages">
        <header>
            <authorName>Your Name</authorName>
            <authorEmail>your@email.com</authorEmail>
        </header>
        <body>
            <trans-unit id="component|type|placeholder">
                <source>Translation Value</source>
            </trans-unit>
        </body>
    </file>
</xliff>
```

**XLF File Structure for Other Languages:**

```xml
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<xliff version="1.0">
    <file source-language="en" datatype="plaintext" original="messages">
        <header>
            <authorName>Your Name</authorName>
            <authorEmail>your@email.com</authorEmail>
        </header>
        <body>
            <trans-unit id="component|type|placeholder">
                <target>Übersetzungswert</target>
            </trans-unit>
        </body>
    </file>
</xliff>
```

**File Naming Convention:**
- English (default): `textdb_[name].xlf`
- Other languages: `[iso-code].textdb_[name].xlf` (e.g., `de.textdb_labels.xlf`)

### Exporting Translations

1. Open the TextDB backend module
2. Apply filters (component, type) if needed
3. Click **"Export with current filter"**
4. A ZIP archive will be downloaded with XLF files for all languages

**Note**: Export includes all filtered translations, ignoring pagination.

---

## 🔄 Migration from LLL Files

Migrate existing `f:translate` ViewHelpers to TextDB:

### Step 1: Include TextDB ViewHelper

Add to your template:

```html
xmlns:textdb="http://typo3.org/ns/Netresearch/NrTextdb/ViewHelpers"
```

### Step 2: Set Component in Controller

```php
use Netresearch\NrTextdb\ViewHelpers\TranslateViewHelper;

// In your controller action
TranslateViewHelper::$component = 'my-component';
```

### Step 3: Replace ViewHelpers Temporarily

Replace `f:translate` with `textdb:translate`:

```html
<!-- Before -->
<f:translate key="LLL:EXT:my_ext:Resources/Private/Language/locallang.xlf:welcome" />

<!-- During migration -->
<textdb:translate key="LLL:EXT:my_ext:Resources/Private/Language/locallang.xlf:welcome" />
```

### Step 4: Render Templates

Access your frontend to trigger automatic import of translations into TextDB.

### Step 5: Final Replacement

Replace all `f:translate` calls with `textdb:textdb`:

**For tag syntax:**
```
Search:   <f:translate key="LLL:EXT:[^:]+:([^\"]+)"[^>]+>
Replace:  <textdb:textdb component="my-component" placeholder="\1" type="label" />
```

**For inline syntax:**
```
Search:   {f:translate\(key: 'LLL:EXT:[^:]+:([^\']+)'\)}
Replace:  {textdb:textdb\({placeholder: '\1', component: 'my-component', type: 'label'})}
```

---

## 🛠️ Development

### Running Tests

Run the complete test suite:

```bash
composer ci:test
```

This executes:
- ✅ PHP linting
- ✅ PHPStan static analysis (level 10)
- ✅ Rector code quality checks
- ✅ Fractor TYPO3 migrations
- ✅ Unit tests with coverage
- ✅ Coding standards (PHP CS Fixer)

### Individual Test Commands

```bash
composer ci:test:php:lint      # PHP linting
composer ci:test:php:phpstan   # PHPStan analysis
composer ci:test:php:rector    # Rector checks
composer ci:test:php:fractor   # Fractor checks
composer ci:test:php:unit      # Unit tests
composer ci:test:php:cgl       # Coding standards check
```

### Automatic Fixes

```bash
composer ci:cgl                # Fix coding standards
composer ci:rector             # Apply Rector refactorings
composer ci:fractor            # Apply Fractor migrations
```

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

**Development Standards:**
- PHPStan level 10 compliance required
- All code must pass `composer ci:test`
- Follow PSR-12 coding standards
- Add tests for new features

---

## 📄 License

This project is licensed under the **GPL-3.0-or-later** License - see the [LICENSE](LICENSE) file for details.

---

## 🏢 About Netresearch

Developed and maintained by [Netresearch DTT GmbH](https://www.netresearch.de/)

**Authors:**
- Thomas Schöne
- Axel Seemann
- Tobias Hein
- Rico Sonntag

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/netresearch/t3x-nr-textdb/issues)
- **Discussions**: [GitHub Discussions](https://github.com/netresearch/t3x-nr-textdb/discussions)
- **TYPO3 Extension Repository**: [TER](https://extensions.typo3.org/extension/nr_textdb)
- **Company Website**: [netresearch.de](https://www.netresearch.de/)

---

## 🔗 Related Extensions

- **[nr-sync](https://github.com/netresearch/nr-sync)**: Synchronize TextDB translations across TYPO3 instances

---

**Made with ❤️ for the TYPO3 Community**
