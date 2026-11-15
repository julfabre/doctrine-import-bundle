<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Analysis\Naming;

/**
 * Default table prefixes of widespread PHP applications, used to suggest a prefix configuration.
 */
final class KnownTablePrefixes
{
    public const PREFIXES = [
        'wp_',      // WordPress
        'ps_',      // PrestaShop
        'jos_',     // Joomla (legacy default)
        'mdl_',     // Moodle
        'llx_',     // Dolibarr
        'phpbb_',   // phpBB
        'matomo_',  // Matomo
        'piwik_',   // Piwik (former Matomo)
        'pma__',    // phpMyAdmin configuration storage
        'oc_',      // Nextcloud, ownCloud, OpenCart
        'tbl_',     // common naming convention
    ];

    /**
     * Returns the known prefix shared by every table, if any.
     *
     * @param list<string> $tableNames
     */
    public static function sharedBy(array $tableNames): ?string
    {
        if ([] === $tableNames) {
            return null;
        }

        foreach (self::PREFIXES as $prefix) {
            if ([] === array_filter($tableNames, static fn (string $table): bool => !str_starts_with($table, $prefix))) {
                return $prefix;
            }
        }

        return null;
    }
}
