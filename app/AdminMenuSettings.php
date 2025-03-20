<?php

namespace SimplifyAdmin;

use function add_action;
use function get_option;
use function sanitize_title;
use function strip_tags;
use function wp_get_current_user;

/**
 * Admin Menu Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AdminMenuSettings
{
    private array $originalMenu;
    private array $originalSubmenu;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'storeOriginalMenu'], 99);
        add_action('admin_head', [$this, 'hideMenuItems']);
    }

    public function storeOriginalMenu(): void
    {
        global $menu, $submenu;
        // Store the menu with original keys preserved
        $this->originalMenu = is_array($menu) ? array_map(function($item) {
            return is_array($item) ? $item : $item;
        }, $menu) : [];
        
        $this->originalSubmenu = [];
        if (is_array($submenu)) {
            foreach ($submenu as $key => $items) {
                $this->originalSubmenu[$key] = array_map(function($item) {
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
                'title' => $menuItem[0],
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
                'title' => strip_tags($submenuItem[0])
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

        $role = $currentUser->roles[0];
        $settings = get_option('sa_menu_settings_' . $role, []);

        if (empty($settings)) {
            return;
        }

        foreach ($settings as $menuId => $hidden) {
            if ($hidden) {
                $this->removeMenuItem($menuId);
            }
        }
    }

    private function removeMenuItem(string $menuId): void
    {
        global $menu, $submenu;

        // Handle main menu items
        if (is_array($menu)) {
            foreach ($menu as $menuKey => $menuItem) {
                if (sanitize_title($menuItem[2]) === $menuId) {
                    unset($menu[$menuKey]);
                    break;
                }
            }
        }

        // Handle submenu items
        if (is_array($submenu)) {
            foreach ($submenu as $parentMenu => &$parentSubmenu) {
                if (is_array($parentSubmenu)) {
                    foreach ($parentSubmenu as $submenuKey => $submenuItem) {
                        $submenuId = sanitize_title($parentMenu) . '-' . sanitize_title($submenuItem[2]);
                        if ($submenuId === $menuId) {
                            unset($parentSubmenu[$submenuKey]);
                            break;
                        }
                    }
                }
            }
        }
    }
} 