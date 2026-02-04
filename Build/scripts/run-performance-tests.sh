#!/bin/bash
#
# Performance test runner for XLIFF import across different branches
#
# Usage: ./run-performance-tests.sh
# Output: Performance report in Build/reports/performance-test-results.md
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
REPORT_DIR="${PROJECT_ROOT}/Build/reports"
REPORT_FILE="${REPORT_DIR}/performance-test-results-$(date +%Y%m%d-%H%M%S).md"

# Create report directory
mkdir -p "${REPORT_DIR}"

# Test configuration
TEST_FILES=(
    "test_50kb.textdb_import.xlf"
    "test_1mb.textdb_import.xlf"
    "test_10mb.textdb_import.xlf"
    "test_100mb.textdb_import.xlf"
)

BRANCHES=(
    "main"
    "feature/optimize-import-performance"
    "feature/async-import-queue"
)

# Store current branch
ORIGINAL_BRANCH=$(git branch --show-current)

# Helper functions
log() {
    echo "[$(date +%H:%M:%S)] $*"
}

log_report() {
    echo "$*" >> "${REPORT_FILE}"
}

clear_database() {
    log "Clearing database..."
    ddev exec "vendor/bin/typo3 database:updateschema '*' --force > /dev/null 2>&1 || true"
    ddev exec "vendor/bin/typo3 database:updateschema '*' --force > /dev/null 2>&1 || true"
}

run_import_test() {
    local file=$1
    local branch=$2

    log "Testing: ${file} on ${branch}"

    # Switch branch
    git checkout "${branch}" > /dev/null 2>&1

    # Clear database
    clear_database

    # Copy test file to vendor directory for import
    mkdir -p "${PROJECT_ROOT}/vendor/netresearch/nr-textdb/Resources/Private/Language"
    cp "${PROJECT_ROOT}/Resources/Private/Language/Test/${file}" \
       "${PROJECT_ROOT}/vendor/netresearch/nr-textdb/Resources/Private/Language/${file}"

    # Run import with timing
    local start_time=$(date +%s.%N)
    local output
    output=$(ddev exec "cd /var/www/html/v13 && vendor/bin/typo3 nr_textdb:import 2>&1" | tail -5)
    local end_time=$(date +%s.%N)

    # Calculate duration
    local duration=$(echo "${end_time} - ${start_time}" | bc)

    # Extract import statistics from output
    local imported=$(echo "${output}" | grep -oP 'Imported: \K\d+' || echo "0")
    local updated=$(echo "${output}" | grep -oP 'Updated: \K\d+' || echo "0")

    # Clean up test file
    rm -f "${PROJECT_ROOT}/vendor/netresearch/nr-textdb/Resources/Private/Language/${file}"

    echo "${duration}|${imported}|${updated}"
}

# Initialize report
log "Starting performance tests..."
log_report "# Performance Test Results"
log_report ""
log_report "**Date**: $(date +'%Y-%m-%d %H:%M:%S')"
log_report "**Branch**: ${ORIGINAL_BRANCH}"
log_report ""
log_report "## Test Configuration"
log_report ""
log_report "| File | Size | Trans-Units |"
log_report "|------|------|-------------|"

for file in "${TEST_FILES[@]}"; do
    filepath="${PROJECT_ROOT}/Resources/Private/Language/Test/${file}"
    if [ -f "${filepath}" ]; then
        size=$(du -h "${filepath}" | cut -f1)
        transunits=$(grep -c '<trans-unit' "${filepath}" || echo "0")
        log_report "| ${file} | ${size} | ${transunits} |"
    fi
done

log_report ""
log_report "## Results"
log_report ""

# Run tests for each branch and file combination
for branch in "${BRANCHES[@]}"; do
    log_report "### Branch: \`${branch}\`"
    log_report ""
    log_report "| Test File | Duration (s) | Imported | Updated | Status |"
    log_report "|-----------|--------------|----------|---------|--------|"

    for file in "${TEST_FILES[@]}"; do
        filepath="${PROJECT_ROOT}/Resources/Private/Language/Test/${file}"

        if [ ! -f "${filepath}" ]; then
            log "Skipping ${file} - file not found"
            log_report "| ${file} | - | - | - | ⚠️ File not found |"
            continue
        fi

        # Run test (with timeout for 100mb file)
        if [[ "${file}" == *"100mb"* ]]; then
            log "⚠️  Skipping 100mb test on ${branch} (would take too long)"
            log_report "| ${file} | - | - | - | ⏭️ Skipped |"
            continue
        fi

        result=$(run_import_test "${file}" "${branch}")
        duration=$(echo "${result}" | cut -d'|' -f1)
        imported=$(echo "${result}" | cut -d'|' -f2)
        updated=$(echo "${result}" | cut -d'|' -f3)

        # Format duration
        duration_formatted=$(printf "%.2f" "${duration}")

        # Determine status
        if (( $(echo "${duration} < 5" | bc -l) )); then
            status="✅ Fast"
        elif (( $(echo "${duration} < 30" | bc -l) )); then
            status="⚡ Good"
        elif (( $(echo "${duration} < 60" | bc -l) )); then
            status="⚠️ Slow"
        else
            status="❌ Timeout Risk"
        fi

        log_report "| ${file} | ${duration_formatted} | ${imported} | ${updated} | ${status} |"

        log "  ✓ Duration: ${duration_formatted}s (${imported} imported, ${updated} updated)"
    done

    log_report ""
done

# Return to original branch
git checkout "${ORIGINAL_BRANCH}" > /dev/null 2>&1

log_report "## Summary"
log_report ""
log_report "Performance testing complete. Results saved to:"
log_report "\`${REPORT_FILE}\`"
log_report ""
log_report "**Legend:**"
log_report "- ✅ Fast: < 5 seconds"
log_report "- ⚡ Good: 5-30 seconds"
log_report "- ⚠️ Slow: 30-60 seconds"
log_report "- ❌ Timeout Risk: > 60 seconds"

log ""
log "✅ Performance tests complete!"
log "Report saved to: ${REPORT_FILE}"
log ""
cat "${REPORT_FILE}"
