#!/bin/bash
#
# Simple performance test for single file
# Usage: ./run-simple-performance-test.sh <test_file>
#

set -e

TEST_FILE="${1:-test_1mb.textdb_import.xlf}"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

echo "============================================"
echo "Performance Test: ${TEST_FILE}"
echo "============================================"
echo ""

# Ensure test file exists
TEST_PATH="${PROJECT_ROOT}/Resources/Private/Language/Test/${TEST_FILE}"
if [ ! -f "${TEST_PATH}" ]; then
    echo "ERROR: Test file not found: ${TEST_PATH}"
    exit 1
fi

# Copy to vendor location
mkdir -p "${PROJECT_ROOT}/vendor/netresearch/nr-textdb/Resources/Private/Language"
cp "${TEST_PATH}" "${PROJECT_ROOT}/vendor/netresearch/nr-textdb/Resources/Private/Language/${TEST_FILE}"

# Clear database
echo "Clearing database..."
ddev exec "vendor/bin/typo3 database:updateschema '*' --force > /dev/null 2>&1 || true"

# Run import with timing
echo "Running import..."
echo ""
ddev exec "cd /var/www/html/v13 && time vendor/bin/typo3 nr_textdb:import 2>&1"

# Cleanup
rm -f "${PROJECT_ROOT}/vendor/netresearch/nr-textdb/Resources/Private/Language/${TEST_FILE}"

echo ""
echo "Test complete!"
