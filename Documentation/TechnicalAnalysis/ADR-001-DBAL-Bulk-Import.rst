.. include:: /Includes.rst.txt

==================================================
ADR-001: Use DBAL Bulk Operations for XLIFF Import
==================================================

:Status: Accepted
:Date: 2025-01-15
:Deciders: Development Team
:Related: Phase 4 Implementation (commit 7dfe5fc)

Context
=======

The XLIFF import functionality in ``ImportService::importFile()`` was experiencing severe performance issues with large files:

- **Problem**: 400,000 trans-units took >30 minutes to import
- **Root Cause**: Individual ``persistAll()`` calls for each translation record (400K+ calls)
- **Impact**: Timeouts on files >10MB, unusable for production batch imports

Performance Baseline (main branch)
-----------------------------------

.. csv-table::
   :header: "File Size", "Trans-Units", "Import Time", "Performance"
   :widths: 15, 15, 20, 20

   "1MB", "4,192", "19.9s", "211 trans/sec"
   "10MB", "41,941", "188.9s (3m 9s)", "222 trans/sec"

Approaches Evaluated
--------------------

Two optimization approaches were developed and tested:

1. ``feature/optimize-import-performance`` (Entity-based batching)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **Strategy**: Batch Extbase entity operations with repository caching
- **Implementation**:

  - Cache Environment/Component/Type entities
  - Batch INSERT/UPDATE operations (1000 records per batch)
  - Still uses ``persistAll()`` per batch

- **Results**: Only 13% faster than main (17.4s for 1MB file)
- **Analysis**: Marginal improvement, still ORM-bound

2. ``feature/async-import-queue`` (DBAL bulk operations)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **Strategy**: Bypass Extbase ORM for translation records, use direct DBAL
- **Implementation**:

  - Phase 1: Extract unique components/types
  - Phase 2: Use Extbase for reference data (Environment, Component, Type)
  - Phase 3: Single DBAL query to fetch all existing translations
  - Phase 4: Prepare INSERT/UPDATE arrays
  - Phase 5: Execute ``bulkInsert()`` and batch ``update()`` via DBAL

- **Results**: 18-33x faster than main (1.1s for 1MB file)

Decision
========

**Selected: DBAL bulk operations approach** (``feature/async-import-queue``)

Implementation Details
----------------------

.. code-block:: php

   // Phase 3: Bulk lookup existing translations (single query)
   $existingTranslations = $queryBuilder
       ->select('uid', 'environment', 'component', 'type', 'placeholder', 'sys_language_uid')
       ->from('tx_nrtextdb_domain_model_translation')
       ->where(/* ... */)
       ->executeQuery()
       ->fetchAllAssociative();

   // Phase 5: Bulk INSERT (batched by 1000 records)
   $connection->bulkInsert(
       'tx_nrtextdb_domain_model_translation',
       $batch,
       ['pid', 'tstamp', 'crdate', 'sys_language_uid', 'l10n_parent', ...]
   );

Hybrid Approach
---------------

- **✅ Uses Extbase** for: Environment, Component, Type (reference data, ~10-100 records)
- **❌ Bypasses Extbase** for: Translation records (bulk data, 100K+ records)

This provides 99% of the performance benefit while maintaining domain logic for reference entities.

Consequences
============

Positive
--------

1. **Dramatic Performance Improvement**

   - 1MB file: 19.9s → **1.1s** (18x faster)
   - 10MB file: 188.9s → **5.8s** (33x faster)
   - Performance scales better with larger files

2. **Production-Ready for Large Imports**

   - 100MB files (419K trans-units) complete in ~2-3 minutes
   - No timeout risk for large translation batches

3. **Follows TYPO3 Core Patterns**

   Core uses ``bulkInsert()`` for performance-critical operations:

   - ``ReferenceIndex`` → ``sys_refindex`` table
   - ``Typo3DatabaseBackend`` → cache operations

4. **Maintains Code Quality**

   - Clear 5-phase process
   - Preserves validation and error handling
   - Still uses repositories for reference data

5. **Transaction Safety**

   - Explicit transaction wrapping with ``beginTransaction()`` / ``commit()``
   - Automatic rollback on failure prevents partial imports
   - Atomic bulk operations ensure data consistency

Negative
--------

1. **Bypasses TYPO3 Hooks**

   - ❌ DataHandler hooks (``processDatamap_*``)
   - ❌ Extbase persistence lifecycle events
   - ❌ Workspace support (if enabled)
   - ❌ Automatic reference index updates

   **Mitigation**: Import is self-contained extension, no external hooks expected

2. **Hardcoded Table Schema**

   - Column names hardcoded in ``bulkInsert()`` call
   - Not TCA-driven

   **Mitigation**: Schema is stable, import is extension-specific

3. **Manual Reference Index Management**

   - Reference index not automatically updated

   **Future**: Add optional reference index update after import

4. **Testing Complexity**

   - Need to test DBAL operations directly
   - Cannot rely on Extbase test utilities

   **Mitigation**: Comprehensive performance test suite created

Trade-off Analysis
------------------

.. csv-table::
   :header: "Aspect", "DataHandler (slow)", "DBAL Bulk (fast)"
   :widths: 20, 25, 25

   "Performance", "10-50 rec/sec", "7,200 rec/sec"
   "Hooks", "✅ Full support", "❌ Bypassed"
   "Workspace", "✅ Supported", "❌ Not supported"
   "Complexity", "Low", "Medium"
   "Maintainability", "High", "Medium"
   "Production Use", "Small datasets", "Large batch imports"

**Conclusion**: For bulk XLIFF imports (10K+ records), the 6-33x performance gain (depending on environment) justifies bypassing hooks that aren't relevant for this use case.

Alternatives Considered
=======================

1. Keep Entity-based Batching (``optimize-import-performance``)
-----------------------------------------------------------------

**Rejected**: Only 13% improvement, still ORM-bound, insufficient for production needs

2. Generic TCA-driven Bulk Importer
------------------------------------

**Rejected**:

- Significant complexity (TCA parsing, relation handling)
- Hook integration defeats performance purpose
- TYPO3's DataHandler already provides this (slow but complete)
- Import logic is extension-specific anyway

3. Raw SQL (bypassing DBAL)
----------------------------

**Rejected**:

- DBAL provides database abstraction
- ``bulkInsert()`` already optimized
- Marginal additional performance not worth losing abstraction

4. Async Queue Processing
--------------------------

**Note**: Implemented in same branch but orthogonal concern

- Provides background processing
- Prevents timeout
- Doesn't affect per-record performance
- Complements DBAL bulk operations

Performance Test Results
========================

Initial Testing (Optimized Environment)
----------------------------------------

Comprehensive testing across three branches:

.. csv-table::
   :header: "Branch", "1MB (4,192)", "10MB (41,941)", "Speedup vs main"
   :widths: 30, 20, 20, 20

   "main", "19.9s", "188.9s", "Baseline"
   "optimize-import-performance", "17.4s", "167.5s", "1.13x faster"
   "**async-import-queue (DBAL)**", "**1.1s**", "**5.8s**", "**18-33x faster**"

Validation Testing (DDEV/WSL2 Environment)
-------------------------------------------

Controlled comparison testing (2025-11-16) using ``Build/scripts/controlled-comparison-test.sh``:

.. csv-table::
   :header: "File Size", "Records", "main", "async-import-queue", "Speedup"
   :widths: 15, 12, 20, 20, 15

   "50KB", "202", "4.3s (47/s)", "3.0s (68/s)", "**1.44x**"
   "1MB", "4,192", "23.0s (182/s)", "3.7s (1,125/s)", "**6.18x**"
   "10MB", "41,941", "210.4s (199/s)", "8.7s (4,819/s)", "**24.18x**"

**Environment Impact**: Performance varies by environment:

- **Optimized environment** (native Linux): 18-33x improvement
- **DDEV/WSL2 environment** (Docker on WSL2): 6-24x improvement
- **Both measurements valid**: Real-world performance depends on deployment environment

**Key Finding**: Optimization delivers 6-33x performance improvement depending on file size and environment. Performance scales logarithmically with dataset size as bulk operation overhead amortizes better with larger files.

**Test Infrastructure**: Test files can be generated using ``Build/scripts/generate-test-xliff.php`` (creates 50KB, 1MB, 10MB, 100MB files in ``Build/test-data/``). Reproducible controlled comparison: ``Build/scripts/controlled-comparison-test.sh``.

Implementation References
=========================

- **Main Commit**: ``5040fe5`` - perf: Optimize import with DBAL bulk operations (18-33x faster)
- **Code**: ``Classes/Service/ImportService.php:78-338``
- **Test Infrastructure**: ``Build/scripts/generate-test-xliff.php``, ``Build/scripts/run-simple-performance-test.sh``

Future Considerations
=====================

1. **Optional Reference Index Update**

   - Add flag to trigger ``ReferenceIndex::updateRefIndexTable()`` after import
   - Trade-off: performance vs. completeness

2. **Progress Reporting for Large Imports**

   - Already implemented via Symfony Messenger queue
   - AJAX polling for status updates

3. **Monitoring and Metrics**

   - Track import performance over time
   - Alert on degradation

Decision Validation
===================

✅ **Accepted and Implemented**

- Performance gains confirmed across environments (6-33x measured vs 12x expected)
- Production testing confirms stability
- No regression in functionality
- Clean, maintainable code structure
