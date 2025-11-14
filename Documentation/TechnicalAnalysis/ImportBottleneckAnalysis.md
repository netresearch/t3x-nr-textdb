# Import Bottleneck Analysis - Real Performance Data

## Executive Summary

**Problem:** Issue #30 reports "timeout during import" for large XLIFF files (>10MB)

**Hypothesis (Wrong):** SimpleXML parsing is slow and causes timeouts

**Reality:** XML parsing is **NOT** the bottleneck. Database operations are 90-95x slower.

## Performance Measurements (Real Data)

### Test: 100,000 trans-units (39 MB XLIFF file)

| Operation | Time | Percentage | Speed |
|-----------|------|------------|-------|
| **XML Parsing (SimpleXML)** | 463 ms | 1.0% | 215,975 units/sec |
| **Database Operations** | 44.01 sec | 99.0% | 2,272 units/sec |
| **Total** | 44.47 sec | 100% | |

**Bottleneck Factor:** Database is **95x slower** than XML parsing

### Test: 3,000 trans-units (1.2 MB XLIFF file)

| Operation | Time | Percentage | Speed |
|-----------|------|------------|-------|
| **XML Parsing (SimpleXML)** | 14 ms | 1.1% | 210,026 units/sec |
| **Database Operations** | 1.28 sec | 98.9% | 2,338 units/sec |
| **Total** | 1.30 sec | 100% | |

**Bottleneck Factor:** Database is **90x slower** than XML parsing

## Root Cause Analysis

### Current Implementation (ImportService.php:160-220)

For **EVERY trans-unit**, the code performs:

```php
// 1. Find environment (1 query)
$environment = $this->environmentRepository->findByName('default');

// 2. Find component (1 query)
$component = $this->componentRepository->findByName($componentName);

// 3. Find type (1 query)
$type = $this->typeRepository->findByName($typeName);

// 4. Find existing translation (1 query with joins)
$translation = $this->translationRepository
    ->findByEnvironmentComponentTypePlaceholderAndLanguage(...);

// 5. Persist (1 query - INSERT or UPDATE)
$this->translationRepository->add($translation);
// OR
$this->translationRepository->update($translation);
```

**Total:** 5 database queries per trans-unit

### Database Load

| File Size | Trans-Units | Database Queries | Estimated Time |
|-----------|-------------|------------------|----------------|
| 1.2 MB | 3,000 | 15,000 | 1.3 seconds |
| 39 MB | 100,000 | 500,000 | 44 seconds |
| 130 MB | 330,000 | 1,650,000 | ~2.4 minutes |

**With network latency (remote database + 50ms per query):**
- 100,000 trans-units: ~3.7 minutes
- 330,000 trans-units: ~12 minutes

This easily causes timeouts with default PHP `max_execution_time=30`.

## Why SimpleXML Is NOT The Problem

### Performance Comparison: SimpleXML vs Streaming Parser

| File Size | Method | Time | Winner |
|-----------|--------|------|--------|
| 130 MB | SimpleXML | 1.23 sec | ✅ 5.8x FASTER |
| 130 MB | Streaming (XMLReader) | 7.13 sec | ❌ 5.8x SLOWER |

SimpleXML is:
- **5-6x faster** than streaming parser
- **Already optimized** in PHP core (written in C)
- **Memory efficient** for iteration (1x file size)

### Time Breakdown

For 100,000 trans-units:
- XML parsing: 0.46 seconds **(1% of time)**
- Database operations: 44 seconds **(99% of time)**

**Optimizing XML parsing saves <1% - wrong target!**

## The Right Solution

### Problem Areas

1. **No caching** - Repository lookups query database every time
2. **No batching** - Individual INSERT/UPDATE per trans-unit
3. **No transactions** - Each operation commits separately
4. **Repeated lookups** - Same environment/component/type queried thousands of times

### Recommended Fixes

#### 1. Cache Repository Lookups (High Impact)

```php
// Cache these lookups - they don't change during import
private array $environmentCache = [];
private array $componentCache = [];
private array $typeCache = [];

public function importEntry(...) {
    // Cache environment (queried for EVERY trans-unit)
    if (!isset($this->environmentCache['default'])) {
        $this->environmentCache['default'] = $this->environmentRepository->findByName('default');
    }
    $environment = $this->environmentCache['default'];

    // Cache component
    if (!isset($this->componentCache[$componentName])) {
        $this->componentCache[$componentName] = $this->componentRepository->findByName($componentName);
    }
    $component = $this->componentCache[$componentName];

    // Cache type
    if (!isset($this->typeCache[$typeName])) {
        $this->typeCache[$typeName] = $this->typeRepository->findByName($typeName);
    }
    $type = $this->typeCache[$typeName];

    // ... rest of the code
}
```

**Impact:** Reduces queries from 1.65M to ~330K (80% reduction)

#### 2. Batch Database Operations (High Impact)

```php
private array $batchInserts = [];
private array $batchUpdates = [];
private const BATCH_SIZE = 1000;

public function importEntry(...) {
    // ... prepare translation object ...

    if ($isNew) {
        $this->batchInserts[] = $translation;
    } else {
        $this->batchUpdates[] = $translation;
    }

    // Flush batch when size reached
    if (count($this->batchInserts) >= self::BATCH_SIZE) {
        $this->flushInserts();
    }
    if (count($this->batchUpdates) >= self::BATCH_SIZE) {
        $this->flushUpdates();
    }
}

private function flushInserts(): void {
    if (empty($this->batchInserts)) {
        return;
    }

    // Bulk INSERT using prepared statements
    // INSERT INTO translations VALUES (...), (...), (...)
    $this->translationRepository->bulkInsert($this->batchInserts);
    $this->batchInserts = [];
}
```

**Impact:** Reduces INSERTs from 330K to ~330 (99.9% reduction)

#### 3. Use Transactions (Medium Impact)

```php
public function importFile(...) {
    $this->persistenceManager->beginTransaction();

    try {
        foreach ($entries as $key => $data) {
            $this->importEntry(...);
        }

        $this->flushAllBatches(); // Flush remaining
        $this->persistenceManager->commit();
    } catch (\Exception $e) {
        $this->persistenceManager->rollback();
        throw $e;
    }
}
```

**Impact:** Reduces transaction overhead by 99%

#### 4. Progress Indicators (User Experience)

```php
// After every 1000 trans-units
if ($count % 1000 === 0) {
    $this->addFlashMessage(
        sprintf('Processed %d/%d trans-units...', $count, $total),
        'Import Progress',
        AbstractMessage::INFO
    );
}
```

**Impact:** User sees progress, no perceived "hang"

### Expected Improvement

| Optimization | Query Reduction | Time Saved |
|--------------|----------------|------------|
| Cache lookups | 80% | ~35 seconds |
| Batch operations | 99% | ~40 seconds |
| Transactions | 50% | ~2 seconds |
| **Total** | **~99%** | **~42 seconds (95% faster)** |

**Result:** 100,000 trans-units in ~2 seconds instead of 44 seconds

## What We Learned (Painful Lessons)

### Mistakes Made

1. ❌ **Assumed without measuring** - Guessed SimpleXML was slow
2. ❌ **Invented numbers** - Claimed "90 minutes" without data
3. ❌ **Wrong target** - Optimized XML parsing (1% of time)
4. ❌ **Made it worse** - Streaming parser was 5x slower
5. ❌ **Added complexity** - New extension for no benefit

### Correct Approach

1. ✅ **Measure first** - Profile before optimizing
2. ✅ **Use real data** - Actual measurements, not guesses
3. ✅ **Find bottleneck** - 99% of time in database
4. ✅ **Right solution** - Fix the actual problem
5. ✅ **Validate** - Test improvements with real data

## Engineering Principles

**"Premature optimization is the root of all evil"** - Donald Knuth

Always:
1. Profile to find the bottleneck
2. Measure current performance
3. Optimize the bottleneck
4. Measure improvement
5. Validate with real-world data

Never:
1. Assume you know the problem
2. Invent performance numbers
3. Optimize without profiling
4. Skip validation

## Action Items

- [ ] Implement repository caching in ImportService
- [ ] Add batch INSERT/UPDATE operations
- [ ] Wrap imports in transactions
- [ ] Add progress indicators for long imports
- [ ] Close Issue #30 with proper fix
- [ ] Update Issue #50 (XliffParser migration) - unrelated to performance

## Test Data Reproduction

```bash
# Generate test files
cd t3x-nr-xliff-streaming
ddev exec php Build/scripts/generate-xliff-samples.php

# Profile import bottleneck
cd t3x-nr-textdb
php Build/scripts/profile-import-bottleneck.php
```

## Conclusion

**The timeout issue is NOT caused by XML parsing.**

The real problem:
- **1.65 million database queries** for 330,000 trans-units
- **99% of time** spent in database operations
- **1% of time** spent in XML parsing

Proper solution:
- Cache repository lookups (80% query reduction)
- Batch database operations (99% INSERT reduction)
- Use transactions (better atomicity)
- Add progress indicators (better UX)

**Expected result:** 95% faster imports, no timeouts
