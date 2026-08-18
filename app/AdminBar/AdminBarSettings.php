<?php

namespace SimplifyAdminMenus\AdminBar;

use SimplifyAdminMenus\Settings\Resolver;
use WP_Admin_Bar;
use WP_User;

use function add_action;
use function class_exists;
use function do_action_ref_array;
use function function_exists;
use function get_current_user_id;
use function is_array;
use function is_object;
use function is_string;
use function wp_enqueue_script;
use function wp_get_current_user;
use function wp_scripts;
use function wp_set_current_user;
use function wp_strip_all_tags;
use function __;

/**
 * Admin Bar Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AdminBarSettings
{
    private array $originalAdminBar = [];

    /**
     * Map of node IDs to custom titles
     */
    private array $titleMap = [];

    private Resolver $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;

        add_action('init', [$this, 'setTitleMap']);
        add_action('wp_before_admin_bar_render', [$this, 'storeOriginalAdminBar'], 9999);
        add_action('wp_before_admin_bar_render', [$this, 'hideAdminBarItems'], 99999);
    }

    public function setTitleMap(): void
    {
        $this->titleMap = [
            'updates' => __('Updates', 'simplify-admin-menus'),
            'comments' => __('Comments', 'simplify-admin-menus'),
            'my-account' => __('My account', 'simplify-admin-menus'),
            'command-palette' => __('Command Palette', 'simplify-admin-menus'),
            'litespeed-menu' => __('Litespeed Menu', 'simplify-admin-menus')
        ];
    }

    /**
     * Get mapped title for a node ID
     */
    private function getMappedTitle(string $nodeId, string $originalTitle): string
    {
        if (isset($this->titleMap[$nodeId])) {
            return $this->titleMap[$nodeId];
        }
        return $originalTitle;
    }

    /**
     * Recursively build the menu structure for a node and its children
     */
    private function buildNodeStructure($nodes, $parentId = false): array
    {
        $structure = [];

        foreach ($nodes as $node) {
            if ($node->id === 'menu-toggle') {
                continue;
            }

            if ($node->parent === $parentId) {
                // Get children before deciding to skip the node
                $children = $this->buildNodeStructure($nodes, $node->id);

                // Skip nodes without title but process their children
                if (empty($node->title)) {
                    // Reassign children to current parent
                    foreach ($children as $childId => $child) {
                        $child['parent'] = $parentId;
                        $structure[$childId] = $child;
                    }
                    continue;
                }

                $structure[$node->id] = [
                    'id' => $node->id,
                    'title' => $this->getMappedTitle($node->id, wp_strip_all_tags($node->title)),
                    'parent' => $node->parent,
                    'children' => $children
                ];
            }
        }

        return $structure;
    }

    public function storeOriginalAdminBar(): void
    {
        global $wp_admin_bar;

        if (!is_object($wp_admin_bar)) {
            return;
        }

        $nodes = $wp_admin_bar->get_nodes();

        if ($nodes) {
            // Sort nodes to ensure parent nodes are processed first
            uasort($nodes, function ($a, $b) {
                $aDepth = 0;
                $bDepth = 0;

                $parent = $a->parent;
                while ($parent) {
                    $aDepth++;
                    $parent = isset($nodes[$parent]) ? $nodes[$parent]->parent : null;
                }

                $parent = $b->parent;
                while ($parent) {
                    $bDepth++;
                    $parent = isset($nodes[$parent]) ? $nodes[$parent]->parent : null;
                }

                return $aDepth <=> $bDepth;
            });

            $this->originalAdminBar = $this->buildNodeStructure($nodes);
        } else {
            $this->originalAdminBar = [];
        }
    }

    public function getAdminBarItems(): array
    {
        return $this->originalAdminBar;
    }

    /**
     * Admin bar node IDs visible to a role or user.
     * Returns null when probing is not possible (UI should show all items).
     *
     * @return string[]|null
     */
    public function getAccessibleNodeIds(?string $role = null, $user = null): ?array
    {
        $probeUserId = 0;

        if ($user instanceof WP_User && $user->ID) {
            $probeUserId = (int) $user->ID;
        } elseif (is_string($role) && $role !== '') {
            $probeUserId = $this->resolver->getProbeUserIdForRole($role);
        }

        if ($probeUserId <= 0) {
            return null;
        }

        $ids = $this->collectNodeIdsForUser($probeUserId);

        // Probe failed — don't hide the entire list.
        if ($ids === []) {
            return null;
        }

        return $ids;
    }

    /**
     * Build a fresh admin bar as the probe user and collect node IDs.
     *
     * @return string[]
     */
    private function collectNodeIdsForUser(int $userId): array
    {
        if (!class_exists(WP_Admin_Bar::class)) {
            require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
        }

        if (!function_exists('wp_admin_bar_my_account_menu')) {
            require_once ABSPATH . 'wp-includes/admin-bar.php';
        }

        $previousUserId = get_current_user_id();
        wp_set_current_user($userId);

        try {
            return $this->withCommandPaletteScriptQueued(function (): array {
                $adminBar = new WP_Admin_Bar();
                $adminBar->initialize();
                // Registers core admin_bar_menu callbacks (not run during AJAX by default).
                $adminBar->add_menus();
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WordPress core hook to rebuild the admin bar for capability probing.
                do_action_ref_array('admin_bar_menu', [&$adminBar]);

                $nodes = $adminBar->get_nodes();
                $ids = [];

                if (is_array($nodes)) {
                    foreach ($nodes as $nodeId => $node) {
                        if ($nodeId === 'menu-toggle') {
                            continue;
                        }
                        $ids[] = (string) $nodeId;
                    }
                }

                return $ids;
            });
        } finally {
            wp_set_current_user($previousUserId);
        }
    }

    /**
     * Core only adds the command palette node when wp-core-commands is enqueued.
     * AJAX probes never load that script, so enqueue it for the rebuild and
     * restore the previous script queue afterwards.
     *
     * @param callable(): array $callback
     * @return string[]
     */
    private function withCommandPaletteScriptQueued(callable $callback): array
    {
        $scripts = wp_scripts();
        $queueBefore = $scripts->queue;

        wp_enqueue_script('wp-core-commands');

        try {
            return $callback();
        } finally {
            $scripts->queue = $queueBefore;
        }
    }

    /**
     * Find a node and its children in the structure recursively
     */
    private function findNodeInStructure(string $nodeId, ?array $structure = null): ?array
    {
        $structure = $structure ?? $this->originalAdminBar;

        // Check if node exists at current level
        if (isset($structure[$nodeId])) {
            return $structure[$nodeId];
        }

        // Search in children
        foreach ($structure as $node) {
            if (!empty($node['children'])) {
                $result = $this->findNodeInStructure($nodeId, $node['children']);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Recursively hide admin bar items
     */
    private function hideNodeAndChildren($wp_admin_bar, $nodeId, array $settings): void
    {
        // Hide the current node if it's in settings
        if (isset($settings[$nodeId])) {
            $wp_admin_bar->remove_node($nodeId);
            return; // No need to process children if parent is hidden
        }

        // Find the node in our structure
        $node = $this->findNodeInStructure($nodeId);

        // Process children if found
        if ($node && !empty($node['children'])) {
            foreach ($node['children'] as $childId => $child) {
                $this->hideNodeAndChildren($wp_admin_bar, $childId, $settings);
            }
        }
    }

    public function hideAdminBarItems(): void
    {
        global $wp_admin_bar;

        if (!is_object($wp_admin_bar)) {
            return;
        }

        $currentUser = wp_get_current_user();
        if (!$currentUser || !$currentUser->roles) {
            return;
        }

        $settings = $this->resolver->getEffectiveHideMap($currentUser, Resolver::TYPE_ADMINBAR);

        if (empty($settings)) {
            return;
        }

        // Process all top-level items
        foreach ($this->originalAdminBar as $nodeId => $node) {
            $this->hideNodeAndChildren($wp_admin_bar, $nodeId, $settings);
        }
    }
}
