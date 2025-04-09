<?php

namespace SimplifyAdmin;

use function add_action;
use function get_option;
use function wp_get_current_user;
use function wp_strip_all_tags;
use function __;
use function get_user_meta;

/**
 * Admin Bar Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AdminBarSettings
{
    private array $originalAdminBar;

    /**
     * Map of node IDs to custom titles
     */
    private array $titleMap = [];

    public function __construct()
    {
        add_action('init', [$this, 'setTitleMap']);
        add_action('wp_before_admin_bar_render', [$this, 'storeOriginalAdminBar'], 9999);
        add_action('wp_before_admin_bar_render', [$this, 'hideAdminBarItems'], 99999);
    }
    
    public function setTitleMap()
    {
        $this->titleMap = [
            'updates' => __('Updates', 'simplify-admin'),
            'comments' => __('Comments', 'simplify-admin'),
            'my-account' => __('My account', 'simplify-admin'),
            'litespeed-menu' => __('Litespeed Menu', 'simplify-admin')
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
            uasort($nodes, function($a, $b) {
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

        // First check for user-specific settings
        $settings = get_user_meta($currentUser->ID, 'simpad_adminbar_settings', true);
        
        // If no user settings, fall back to role settings
        if (empty($settings)) {
            $role = reset($currentUser->roles);
            $settings = get_option('simpad_adminbar_settings_' . $role, []);
        }

        if (empty($settings)) {
            return;
        }

        // Process all top-level items
        foreach ($this->originalAdminBar as $nodeId => $node) {
            $this->hideNodeAndChildren($wp_admin_bar, $nodeId, $settings);
        }
    }
} 
