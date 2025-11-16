#!/usr/bin/env python3
"""
Analyze Xdebug cachegrind profile to identify performance bottlenecks
"""

import sys
import gzip
import re
from collections import defaultdict

def parse_cachegrind(filepath):
    """Parse cachegrind file and aggregate function costs"""

    function_costs = defaultdict(int)
    current_function = None

    # Open file (handle both gzipped and plain)
    if filepath.endswith('.gz'):
        f = gzip.open(filepath, 'rt')
    else:
        f = open(filepath, 'r')

    try:
        for line in f:
            # Function definition: fn=(number) ClassName::methodName
            if line.startswith('fn='):
                # Extract function name
                match = re.search(r'fn=\(\d+\) (.+)$', line)
                if match:
                    current_function = match.group(1)

            # Cost line: starts with digit
            elif line and line[0].isdigit() and current_function:
                parts = line.strip().split()
                if len(parts) >= 2:
                    # First value after line number is Time_(10ns)
                    try:
                        time_cost = int(parts[1])
                        function_costs[current_function] += time_cost
                    except (ValueError, IndexError):
                        pass
    finally:
        f.close()

    return function_costs

def format_time(nanoseconds):
    """Convert 10ns units to readable format"""
    total_ns = nanoseconds * 10

    if total_ns < 1000:
        return f"{total_ns}ns"
    elif total_ns < 1_000_000:
        return f"{total_ns / 1000:.2f}μs"
    elif total_ns < 1_000_000_000:
        return f"{total_ns / 1_000_000:.2f}ms"
    else:
        return f"{total_ns / 1_000_000_000:.2f}s"

def main():
    if len(sys.argv) < 2:
        print("Usage: analyze-cachegrind.py <cachegrind.out.file>")
        sys.exit(1)

    filepath = sys.argv[1]

    print(f"Analyzing {filepath}...")
    print()

    function_costs = parse_cachegrind(filepath)

    # Calculate total
    total_cost = sum(function_costs.values())

    # Sort by cost (descending)
    sorted_functions = sorted(function_costs.items(), key=lambda x: x[1], reverse=True)

    print(f"Total Time: {format_time(total_cost)}")
    print()
    print("Top 50 Most Expensive Functions:")
    print("=" * 100)
    print(f"{'% Time':<8} {'Time':<15} {'Function':<75}")
    print("=" * 100)

    for func_name, cost in sorted_functions[:50]:
        percentage = (cost / total_cost) * 100
        print(f"{percentage:>6.2f}%  {format_time(cost):<15} {func_name[:75]}")

    print()
    print("=" * 100)

    # Aggregate by category
    print()
    print("Time Distribution by Category:")
    print("=" * 100)

    categories = {
        'Database': ['PDOStatement', 'Connection::execute', 'Query::execute', 'QueryBuilder', 'Repository::find', 'Repository::add', 'Repository::update', 'PersistenceManager'],
        'XML Parsing': ['XMLReader', 'SimpleXML', 'DOMDocument', 'parseXliff'],
        'Import Service': ['ImportService', 'importFile', 'importEntry'],
        'Extbase/ORM': ['DataMapper', 'Persistence', 'ReflectionService', 'ObjectManager'],
        'TYPO3 Core': ['Bootstrap', 'DependencyInjection', 'EventDispatcher'],
    }

    category_costs = defaultdict(int)
    uncat_cost = 0

    for func_name, cost in function_costs.items():
        categorized = False
        for cat_name, keywords in categories.items():
            if any(keyword in func_name for keyword in keywords):
                category_costs[cat_name] += cost
                categorized = True
                break
        if not categorized:
            uncat_cost += cost

    # Sort categories by cost
    sorted_cats = sorted(category_costs.items(), key=lambda x: x[1], reverse=True)

    for cat_name, cost in sorted_cats:
        percentage = (cost / total_cost) * 100
        print(f"{cat_name:<30} {percentage:>6.2f}%  {format_time(cost)}")

    if uncat_cost > 0:
        percentage = (uncat_cost / total_cost) * 100
        print(f"{'Other':<30} {percentage:>6.2f}%  {format_time(uncat_cost)}")

    print("=" * 100)

if __name__ == '__main__':
    main()
