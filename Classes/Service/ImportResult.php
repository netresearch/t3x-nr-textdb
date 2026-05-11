<?php

/*
 * This file is part of the package netresearch/nr-textdb.
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
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class ImportResult
{
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
}
