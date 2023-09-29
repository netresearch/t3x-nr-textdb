<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Hooks;

use Netresearch\Sync\Module\BaseModule;
use Netresearch\Sync\ModuleInterface;

/**
 * Provides hooks that integrate into nr_sync and allows the user to synchronize the database tables.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Sync extends BaseModule
{
    /**
     * The name of the sync module to be displayed in the sync module selection menu.
     *
     * @var string
     */
    protected string $name = 'TextDB';

    /**
     * A list of table names to synchronize.
     *
     * @var string[]
     */
    protected array $tables = [
        'tx_nrtextdb_domain_model_environment',
        'tx_nrtextdb_domain_model_component',
        'tx_nrtextdb_domain_model_type',
        'tx_nrtextdb_domain_model_translation',
    ];

    /**
     * The access level of the module (value between 0 and 100). 100 requires admin access to typo3 backend.
     *
     * @var int
     */
    protected int $accessLevel = 50;

    /**
     * The type of tables to sync, e.g. "sync_tables", "sync_fe_groups", "sync_be_groups" or "backsync_tables".
     *
     * @var string
     *
     * @deprecated Seems deprecated. Not used anywhere?
     */
    protected string $type = ModuleInterface::SYNC_TYPE_TABLES;

    /**
     * The name of the synchronisation file containing the SQL statements to update the database records.
     *
     * @var string
     */
    protected string $dumpFileName = 'nr-textdb.sql';
}
