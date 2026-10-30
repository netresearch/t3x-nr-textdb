<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

/**
 * Mutable result accumulator for {@see ImportService} operations.
 *
 * Replaces the previous by-reference parameter pattern (`int &$imported`,
 * `int &$updated`, `array &$errors`) on {@see ImportService::importFile()}
 * and {@see ImportService::importEntry()}. Callers create an instance,
 * pass it through nested import calls, then read the totals once import
 * completes.
 *
 * @author  Netresearch DTT GmbH <typo3.dev@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class ImportResult
{
    /**
     * Number of skipped keys a message about an import lists.
     */
    public const LISTED_KEYS = 10;

    /**
     * Number of newly imported translation records.
     */
    private int $imported = 0;

    /**
     * Number of updated existing translation records.
     */
    private int $updated = 0;

    /**
     * Accumulated error messages encountered during import.
     *
     * @var list<string>
     */
    private array $errors = [];

    /**
     * Keys of units the loader left out because they are not approved, as
     * the keys of the array so a key counts once.
     *
     * @var array<string, true>
     */
    private array $skippedUnapproved = [];

    /**
     * Keys of units without text, as the keys of the array so a key counts
     * once.
     *
     * @var array<string, true>
     */
    private array $skippedEmpty = [];

    /**
     * Increment the imported counter by one.
     */
    public function recordImported(): void
    {
        ++$this->imported;
    }

    /**
     * Increment the updated counter by one.
     */
    public function recordUpdated(): void
    {
        ++$this->updated;
    }

    /**
     * Append an error message to the result.
     */
    public function recordError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Records a unit that was not imported because it is not approved.
     */
    public function recordSkippedUnapproved(string $key): void
    {
        $this->skippedUnapproved[$key] = true;
    }

    /**
     * Records a unit that was not imported because it has no text.
     */
    public function recordSkippedEmpty(string $key): void
    {
        $this->skippedEmpty[$key] = true;
    }

    /**
     * Adds the counts, skipped keys and error messages of another result to
     * this one.
     */
    public function add(self $other): void
    {
        $this->imported += $other->imported;
        $this->updated += $other->updated;
        $this->errors = [...$this->errors, ...$other->errors];
        $this->skippedUnapproved += $other->skippedUnapproved;
        $this->skippedEmpty += $other->skippedEmpty;
    }

    public function getImported(): int
    {
        return $this->imported;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Keys of the units not imported because they are not approved, each
     * once, in the order they were recorded.
     *
     * @return list<string>
     */
    public function getSkippedUnapproved(): array
    {
        return array_map(strval(...), array_keys($this->skippedUnapproved));
    }

    /**
     * Keys of the units not imported because they have no text, each once,
     * in the order they were recorded.
     *
     * @return list<string>
     */
    public function getSkippedEmpty(): array
    {
        return array_map(strval(...), array_keys($this->skippedEmpty));
    }
}
