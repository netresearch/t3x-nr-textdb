#!/bin/bash
#
# Controlled Performance Comparison: main vs feature/async-import-queue
# Tests: test_50kb.textdb_import.xlf (202 records)
#
set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REPORT_FILE="${PROJECT_ROOT}/claudedocs/Controlled-Comparison-Results.md"
TEST_FILE="test_10mb.textdb_import.xlf"
TEST_SOURCE="${PROJECT_ROOT}/Build/test-data/${TEST_FILE}"
# IMPORTANT: Copy to project root Resources, not vendor (vendor/netresearch/nr-textdb is symlink to /var/www/nr_textdb in container)
FILE_DEST="${PROJECT_ROOT}/Resources/Private/Language/${TEST_FILE}"

echo "========================================"
echo "Controlled Performance Comparison Test"
echo "========================================"
echo ""
echo "Test File: ${TEST_FILE}"
echo "Branches: main vs feature/async-import-queue"
echo ""

# Save current branch and stash uncommitted changes
ORIGINAL_BRANCH=$(git branch --show-current)
echo "Current branch: ${ORIGINAL_BRANCH}"
echo ""

# Stash any uncommitted changes
echo "Stashing uncommitted changes..."
git stash push -u -m "Controlled comparison test - temporary stash" > /dev/null 2>&1
STASHED=$?
echo ""

# Initialize report
cat > "${REPORT_FILE}" <<'EOF'
# Controlled Performance Comparison Results

**Date**: $(date +'%Y-%m-%d %H:%M:%S')
**Test File**: test_50kb.textdb_import.xlf (202 trans-units)
**Environment**: DDEV (WSL2), MySQL 8.0, TYPO3 v13.4

## Test Methodology

1. Clear database completely
2. Copy test file to vendor location
3. Run `vendor/bin/typo3 nr_textdb:import`
4. Record time and import statistics
5. Repeat for each branch

---

EOF

# Function to run single test
run_test() {
    local branch=$1
    echo "Testing branch: ${branch}"

    # Switch branch
    git checkout "${branch}" > /dev/null 2>&1

    # Sync code to vendor (important!)
    rsync -a --delete \
        --exclude vendor \
        --exclude .git \
        "${PROJECT_ROOT}/" \
        "${PROJECT_ROOT}/vendor/netresearch/nr-textdb/"

    # Clear TYPO3 cache
    ddev exec "rm -rf /var/www/html/v13/var/cache/*" > /dev/null 2>&1

    # Clear database
    echo "  Clearing database..."
    ddev exec "mysql -e 'TRUNCATE TABLE tx_nrtextdb_domain_model_translation; TRUNCATE TABLE tx_nrtextdb_domain_model_component; TRUNCATE TABLE tx_nrtextdb_domain_model_type; TRUNCATE TABLE tx_nrtextdb_domain_model_environment;'" > /dev/null 2>&1

    # Copy test file to project Resources (maps to /var/www/nr_textdb in container)
    cp "${TEST_SOURCE}" "${FILE_DEST}"

    # Run import with timing
    echo "  Running import..."
    local output
    local start=$(date +%s.%N)
    output=$(ddev exec "cd /var/www/html/v13 && vendor/bin/typo3 nr_textdb:import 2>&1")
    local end=$(date +%s.%N)

    # Calculate duration
    local duration=$(echo "${end} - ${start}" | bc)

    # Extract statistics
    local imported=$(echo "${output}" | grep -oP 'Imported: \K\d+' || echo "0")
    local updated=$(echo "${output}" | grep -oP 'Updated: \K\d+' || echo "0")

    # Clean up test file
    rm -f "${FILE_DEST}"

    # Report results
    echo "  Results: ${imported} imported in ${duration}s"
    echo ""

    # Append to report
    cat >> "${REPORT_FILE}" <<EOF
## Branch: ${branch}

\`\`\`
Imported: ${imported} records
Updated: ${updated} records
Duration: ${duration}s
Throughput: $(echo "scale=2; ${imported} / ${duration}" | bc) records/second
\`\`\`

EOF
}

# Run tests
echo "Starting tests..."
echo ""

run_test "main"
run_test "feature/async-import-queue"

# Restore original branch
git checkout "${ORIGINAL_BRANCH}" > /dev/null 2>&1

# Restore stashed changes if any were stashed
if [ $STASHED -eq 0 ]; then
    echo ""
    echo "Restoring uncommitted changes..."
    git stash pop > /dev/null 2>&1
fi

# Add comparison to report
cat >> "${REPORT_FILE}" <<'EOF'
## Analysis

[Analysis will be added after test completion]

---

**Test completed**: $(date +'%Y-%m-%d %H:%M:%S')
EOF

echo "========================================"
echo "Test Complete!"
echo "========================================"
echo ""
echo "Report saved to: ${REPORT_FILE}"
echo ""
cat "${REPORT_FILE}"
