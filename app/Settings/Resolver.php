<?php

namespace SimplifyAdminMenus\Settings;

use WP_User;

use function absint;
use function get_option;
use function get_role;
use function get_user_by;
use function get_user_meta;
use function get_users;
use function in_array;
use function is_array;
use function is_string;
use function update_option;
use function user_can;

/**
 * Resolves effective menu / admin-bar visibility for a user.
 */
if (!defined('ABSPATH')) {
    exit;
}

class Resolver
{
    public const TYPE_MENU = 'menu';
    public const TYPE_ADMINBAR = 'adminbar';

    public const OPTION_PROTECTED_USERS = 'simpad_protected_users';
    public const META_MENU = 'simpad_menu_settings';
    public const META_ADMINBAR = 'simpad_adminbar_settings';

    public const STATE_HIDE = 'hide';
    public const STATE_SHOW = 'show';

    /**
     * Whether the user is exempt from all visibility restrictions.
     * Only users in the protected administrators list are exempt.
     */
    public function isProtectedUser(WP_User $user): bool
    {
        if (!$user->ID) {
            return false;
        }

        $protected = $this->getProtectedUserIds();

        return in_array((int) $user->ID, $protected, true);
    }

    /**
     * @return int[]
     */
    public function getProtectedUserIds(): array
    {
        $option = get_option(self::OPTION_PROTECTED_USERS, null);

        if ($option === null) {
            return $this->seedProtectedUsers();
        }

        if (!is_array($option)) {
            return $this->seedProtectedUsers();
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $option))));

        if ($ids === []) {
            return $this->seedProtectedUsers();
        }

        return $ids;
    }

    /**
     * Seed protected users with the earliest administrator.
     *
     * @return int[]
     */
    public function seedProtectedUsers(): array
    {
        $admins = get_users([
            'role' => 'administrator',
            'orderby' => 'ID',
            'order' => 'ASC',
            'number' => 1,
            'fields' => 'ID',
        ]);

        $ids = [];
        if (!empty($admins)) {
            $ids[] = absint(is_array($admins) ? $admins[0] : $admins);
        }

        update_option(self::OPTION_PROTECTED_USERS, $ids, false);

        return $ids;
    }

    /**
     * Persist protected user IDs after validating they are administrators.
     *
     * @param int[] $userIds
     * @return int[] Sanitized IDs that were stored
     */
    public function saveProtectedUserIds(array $userIds): array
    {
        $sanitized = [];

        foreach ($userIds as $userId) {
            $userId = absint($userId);
            if ($userId <= 0) {
                continue;
            }

            $user = get_user_by('id', $userId);
            if (!$user || !user_can($user, 'manage_options')) {
                continue;
            }

            $sanitized[] = $userId;
        }

        $sanitized = array_values(array_unique($sanitized));

        if ($sanitized === []) {
            $sanitized = $this->seedProtectedUsers();
        } else {
            update_option(self::OPTION_PROTECTED_USERS, $sanitized, false);
        }

        return $sanitized;
    }

    /**
     * Role hide map: item id => true.
     *
     * @return array<string, true>
     */
    public function getRoleHideMap(string $role, string $type): array
    {
        if ($role === '') {
            return [];
        }

        $option = get_option($this->getRoleOptionKey($role, $type), []);

        if (!is_array($option)) {
            return [];
        }

        $map = [];
        foreach ($option as $itemId => $hidden) {
            if ($hidden) {
                $map[(string) $itemId] = true;
            }
        }

        return $map;
    }

    /**
     * Normalized user overrides: item id => hide|show.
     * Converts legacy full-replace maps on read when needed.
     *
     * @return array<string, string>
     */
    public function getUserOverrides(int $userId, string $type): array
    {
        $raw = get_user_meta($userId, $this->getUserMetaKey($type), true);

        if (!is_array($raw) || $raw === []) {
            return [];
        }

        if ($this->isLegacyHideMap($raw)) {
            $user = get_user_by('id', $userId);
            $role = ($user && !empty($user->roles[0])) ? $user->roles[0] : '';

            return $this->convertLegacyUserMap($raw, $this->getRoleHideMap($role, $type));
        }

        return $this->normalizeOverrides($raw);
    }

    /**
     * Whether a single item should be hidden for the user.
     */
    public function isItemHidden(WP_User $user, string $itemId, string $type): bool
    {
        if ($this->isProtectedUser($user)) {
            return false;
        }

        $overrides = $this->getUserOverrides((int) $user->ID, $type);

        if (isset($overrides[$itemId])) {
            return $overrides[$itemId] === self::STATE_HIDE;
        }

        $role = !empty($user->roles[0]) ? $user->roles[0] : '';
        $roleMap = $this->getRoleHideMap($role, $type);

        return isset($roleMap[$itemId]);
    }

    /**
     * Effective hide map for apply loops: item id => true.
     *
     * @return array<string, true>
     */
    public function getEffectiveHideMap(WP_User $user, string $type): array
    {
        if ($this->isProtectedUser($user)) {
            return [];
        }

        $role = !empty($user->roles[0]) ? $user->roles[0] : '';
        $roleMap = $this->getRoleHideMap($role, $type);
        $overrides = $this->getUserOverrides((int) $user->ID, $type);

        $effective = $roleMap;

        foreach ($overrides as $itemId => $state) {
            if ($state === self::STATE_HIDE) {
                $effective[$itemId] = true;
            } elseif ($state === self::STATE_SHOW) {
                unset($effective[$itemId]);
            }
        }

        return $effective;
    }

    /**
     * Detect legacy boolean hide maps vs new hide/show string maps.
     *
     * @param array<string, mixed> $map
     */
    public function isLegacyHideMap(array $map): bool
    {
        if ($map === []) {
            return false;
        }

        $hasLegacy = false;
        $hasNew = false;

        foreach ($map as $value) {
            if ($value === self::STATE_HIDE || $value === self::STATE_SHOW) {
                $hasNew = true;
            } elseif ($value === true || $value === 1 || $value === '1') {
                $hasLegacy = true;
            }
        }

        return $hasLegacy && !$hasNew;
    }

    /**
     * Convert a legacy full-replace hide map into exception overrides.
     *
     * @param array<string, mixed> $legacyUserHide
     * @param array<string, true>  $roleHide
     * @return array<string, string>
     */
    public function convertLegacyUserMap(array $legacyUserHide, array $roleHide): array
    {
        $overrides = [];

        foreach ($legacyUserHide as $itemId => $hidden) {
            if ($hidden) {
                $overrides[(string) $itemId] = self::STATE_HIDE;
            }
        }

        foreach ($roleHide as $itemId => $hidden) {
            if ($hidden && !isset($overrides[$itemId])) {
                $overrides[(string) $itemId] = self::STATE_SHOW;
            }
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, string>
     */
    public function normalizeOverrides(array $raw): array
    {
        $overrides = [];

        foreach ($raw as $itemId => $state) {
            $itemId = (string) $itemId;
            if ($itemId === '') {
                continue;
            }

            if ($state === self::STATE_HIDE || $state === self::STATE_SHOW) {
                $overrides[$itemId] = $state;
            }
        }

        return $overrides;
    }

    public function getRoleOptionKey(string $role, string $type): string
    {
        return $type === self::TYPE_ADMINBAR
            ? 'simpad_adminbar_settings_' . $role
            : 'simpad_menu_settings_' . $role;
    }

    public function getUserMetaKey(string $type): string
    {
        return $type === self::TYPE_ADMINBAR
            ? self::META_ADMINBAR
            : self::META_MENU;
    }

    /**
     * Capability names granted to a role or user.
     * Returns null when the actor cannot be resolved (caller should skip filtering).
     *
     * @return string[]|null
     */
    public function getActorCapabilities(?string $role = null, $user = null): ?array
    {
        if ($user instanceof WP_User && $user->ID) {
            $allcaps = is_array($user->allcaps) ? $user->allcaps : [];
            return array_values(array_keys(array_filter($allcaps)));
        }

        if (is_string($role) && $role !== '') {
            $roleObject = get_role($role);
            if (!$roleObject) {
                return null;
            }

            $capabilities = is_array($roleObject->capabilities) ? $roleObject->capabilities : [];
            return array_values(array_keys(array_filter($capabilities)));
        }

        return null;
    }

    /**
     * Resolve a real user ID to probe admin-bar visibility for a role.
     */
    public function getProbeUserIdForRole(string $role): int
    {
        if ($role === '') {
            return 0;
        }

        $users = get_users([
            'role' => $role,
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ID',
        ]);

        if (empty($users)) {
            return 0;
        }

        return absint(is_array($users) ? $users[0] : $users);
    }
}
