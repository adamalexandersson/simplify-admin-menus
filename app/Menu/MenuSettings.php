<?php

namespace SimplifyAdminMenus\Menu;

use SimplifyAdminMenus\Settings\Resolver;

use function add_action;
use function current_user_can;
use function remove_menu_page;
use function remove_submenu_page;
use function sanitize_title;
use function wp_get_current_user;
use function wp_strip_all_tags;

/**
 * Admin Menu Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MenuSettings
{
    private array $originalMenu = [];
    private array $originalSubmenu = [];
    private Resolver $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;

        add_action('admin_menu', [$this, 'storeOriginalMenu'], 99);
        add_action('admin_menu', [$this, 'hideMenuItems'], 9999);
    }

    public function storeOriginalMenu(): void
    {
        global $menu, $submenu;
        // Store the menu with original keys preserved
        $this->originalMenu = is_array($menu) ? array_map(function ($item) {
            return is_array($item) ? $item : $item;
        }, $menu) : [];

        $this->originalSubmenu = [];
        if (is_array($submenu)) {
            foreach ($submenu as $key => $items) {
                $this->originalSubmenu[$key] = array_map(function ($item) {
                    return is_array($item) ? $item : $item;
                }, $items);
            }
        }
    }

    public function getMenuItems(): array
    {
        $items = [];

        if (empty($this->originalMenu)) {
            return $items;
        }

        // Sort menu items by their numeric keys
        $menuKeys = array_keys($this->originalMenu);
        sort($menuKeys, SORT_NUMERIC);

        foreach ($menuKeys as $key) {
            $menuItem = $this->originalMenu[$key];
            // Skip separators and empty items
            if (!is_array($menuItem) || empty($menuItem[2])) {
                continue;
            }

            $menuId = sanitize_title($menuItem[2]);
            $items[] = [
                'id' => $menuId,
                'title' => wp_strip_all_tags($menuItem[0]),
                'slug' => $menuItem[2],
                'capability' => isset($menuItem[1]) ? (string) $menuItem[1] : '',
                'submenu' => isset($this->originalSubmenu[$menuItem[2]]) ?
                    $this->getSubmenuItems($this->originalSubmenu[$menuItem[2]], $menuId) : []
            ];
        }

        return $items;
    }

    private function getSubmenuItems(array $submenuItems, string $parentId): array
    {
        $items = [];

        if (!is_array($submenuItems)) {
            return $items;
        }

        // Sort submenu items by their numeric keys
        $submenuKeys = array_keys($submenuItems);
        sort($submenuKeys, SORT_NUMERIC);

        foreach ($submenuKeys as $key) {
            $submenuItem = $submenuItems[$key];
            if (!is_array($submenuItem) || empty($submenuItem[2])) {
                continue;
            }

            $items[] = [
                'id' => $parentId . '-' . sanitize_title($submenuItem[2]),
                'title' => wp_strip_all_tags($submenuItem[0]),
                'slug' => $submenuItem[2],
                'capability' => isset($submenuItem[1]) ? (string) $submenuItem[1] : '',
            ];
        }
        return $items;
    }

    public function hideMenuItems(): void
    {
        $currentUser = wp_get_current_user();

        if (empty($currentUser->roles)) {
            return;
        }

        $settings = $this->resolver->getEffectiveHideMap($currentUser, Resolver::TYPE_MENU);

        if (empty($settings)) {
            return;
        }

        $keepPluginSettings = current_user_can('manage_options');

        foreach ($settings as $menuId => $hidden) {
            if (!$hidden) {
                continue;
            }

            if ($keepPluginSettings && $this->isPluginSettingsMenuId($menuId)) {
                continue;
            }

            $this->removeMenuItem($menuId);
        }
    }

    /**
     * Never hide this plugin's own settings screen for users who can manage options.
     */
    private function isPluginSettingsMenuId(string $menuId): bool
    {
        $pluginSlug = sanitize_title('simplify-admin-menus');
        $settingsParent = sanitize_title('options-general.php');

        return $menuId === $pluginSlug
            || $menuId === $settingsParent . '-' . $pluginSlug;
    }

    private function removeMenuItem(string $menuId): void
    {
        // Handle main menu items via core API
        if (is_array($this->originalMenu)) {
            foreach ($this->originalMenu as $menuItem) {
                if (!is_array($menuItem) || empty($menuItem[2])) {
                    continue;
                }

                if (sanitize_title($menuItem[2]) === $menuId) {
                    remove_menu_page($menuItem[2]);
                    return;
                }
            }
        }

        // Handle submenu items via core API
        if (is_array($this->originalSubmenu)) {
            foreach ($this->originalSubmenu as $parentMenu => $parentSubmenu) {
                if (!is_array($parentSubmenu)) {
                    continue;
                }

                foreach ($parentSubmenu as $submenuItem) {
                    if (!is_array($submenuItem) || empty($submenuItem[2])) {
                        continue;
                    }

                    $submenuId = sanitize_title($parentMenu) . '-' . sanitize_title($submenuItem[2]);
                    if ($submenuId === $menuId) {
                        remove_submenu_page($parentMenu, $submenuItem[2]);
                        return;
                    }
                }
            }
        }
    }
}
