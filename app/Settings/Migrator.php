<?php

namespace SimplifyAdminMenus\Settings;

use function absint;
use function current_user_can;
use function get_option;
use function get_user_by;
use function get_user_meta;
use function is_array;
use function update_option;
use function update_user_meta;

/**
 * Handles database schema upgrades for Simplify Admin Menus.
 */
if (!defined('ABSPATH')) {
    exit;
}

class Migrator
{
    public const DB_VERSION_OPTION = 'simpad_db_version';

    /**
     * Internal settings schema version (independent of plugin release version).
     */
    public const DB_VERSION = '2';

    private Resolver $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Run pending upgrades when an admin loads wp-admin.
     */
    public function maybeUpgrade(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current = (string) get_option(self::DB_VERSION_OPTION, '0');

        // Normalize a brief development marker that used the plugin version string.
        if ($current === '1.4.0') {
            $current = '2';
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
        }

        if (version_compare($current, self::DB_VERSION, '>=')) {
            return;
        }

        // Schema 2: user override maps + protected admins option.
        // Also covers installs that briefly stored "1.4.0" as the schema marker.
        if (version_compare($current, '2', '<')) {
            $this->upgradeToSchema2();
        }

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    /**
     * Migrate legacy user hide maps to hide/show exception maps and seed protected users.
     */
    public function upgradeToSchema2(): void
    {
        $this->resolver->getProtectedUserIds();

        $this->migrateUserMetaType(Resolver::TYPE_MENU);
        $this->migrateUserMetaType(Resolver::TYPE_ADMINBAR);
    }

    private function migrateUserMetaType(string $type): void
    {
        global $wpdb;

        $metaKey = $this->resolver->getUserMetaKey($type);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade lookup of users with plugin meta.
        $userIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $metaKey
            )
        );

        if (!is_array($userIds)) {
            return;
        }

        foreach ($userIds as $userId) {
            $userId = absint($userId);
            if ($userId <= 0) {
                continue;
            }

            $raw = get_user_meta($userId, $metaKey, true);
            if (!is_array($raw) || $raw === []) {
                continue;
            }

            if (!$this->resolver->isLegacyHideMap($raw)) {
                continue;
            }

            $user = get_user_by('id', $userId);
            $role = ($user && !empty($user->roles[0])) ? $user->roles[0] : '';
            $roleHide = $this->resolver->getRoleHideMap($role, $type);
            $overrides = $this->resolver->convertLegacyUserMap($raw, $roleHide);

            update_user_meta($userId, $metaKey, $overrides);
        }
    }
}
