<?php

namespace SimplifyAdmin;

use function add_action;
use function add_options_page;
use function admin_url;
use function add_query_arg;
use function check_admin_referer;
use function check_ajax_referer;
use function current_user_can;
use function esc_html__;
use function get_current_screen;
use function get_option;
use function plugin_basename;
use function register_setting;
use function sanitize_text_field;
use function update_option;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_current_user;
use function wp_localize_script;
use function wp_redirect;
use function wp_roles;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_add_inline_style;
use function get_user_option;
use function __;
use function translate_user_role;

/**
 * Admin Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AdminSettings
{
    private string $pluginPath;
    private string $pluginUrl;
    private AdminMenuSettings $menuSettings;
    private AdminBarSettings $adminBarSettings;
    private ViteManifest $viteManifest;

    public function __construct(
        string $pluginPath,
        string $pluginUrl,
        AdminMenuSettings $menuSettings,
        AdminBarSettings $adminBarSettings
    ) {
        $this->pluginPath = $pluginPath;
        $this->pluginUrl = $pluginUrl;
        $this->menuSettings = $menuSettings;
        $this->adminBarSettings = $adminBarSettings;
        $this->viteManifest = new ViteManifest($this->pluginPath . 'dist/.vite/manifest.json');

        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_enqueue_scripts', [$this, 'setAdminProfileColors']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_post_save_sa_settings', [$this, 'handleFormSubmission']);
        add_action('wp_ajax_load_role_settings', [$this, 'ajaxLoadRoleSettings']);
        add_action('admin_notices', [$this, 'displaySettingsUpdatedNotice']);
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('Simplify Admin', 'simplify-admin'),
            __('Simplify Admin', 'simplify-admin'),
            'manage_options',
            'simplify-admin',
            [$this, 'renderSettingsPage']
        );
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if ('settings_page_simplify-admin' !== $hook) {
            return;
        }

        // Get the main entry points from manifest
        $adminJs = $this->viteManifest->getAsset('resources/assets/js/admin.js');
        $adminCss = $this->viteManifest->getCss('resources/assets/js/admin.js');

        // Enqueue main JavaScript
        if ($adminJs) {
            wp_enqueue_script(
                'simplify-admin',
                $this->pluginUrl . 'dist/' . $adminJs,
                [],
                null,
                true
            );

            wp_localize_script('simplify-admin', 'simplifyAdmin', [
                'nonce' => wp_create_nonce('simplify-admin-nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'strings' => [
                    'editing' => __('Editing:', 'simplify-admin')
                ]
            ]);
        }

        // Enqueue any additional CSS from JS imports
        foreach ($adminCss as $index => $cssFile) {
            wp_enqueue_style(
                'simplify-admin-' . $index,
                $this->pluginUrl . 'dist/' . $cssFile,
                [],
                null
            );
        }
    }

    function setAdminProfileColors() {
        $admin_color = get_user_option('admin_color');
    
        global $_wp_admin_css_colors;
        
        if (!isset($_wp_admin_css_colors[$admin_color])) {
            return;
        }
    
        $scheme = $_wp_admin_css_colors[$admin_color];
        $colors = $scheme->colors;
        $color_count = count($colors);

        $primary_color = $color_count === 4 ? $colors[2] : $colors[1];
        $secondary_color = $color_count === 4 ? $colors[1] : $colors[2];
    
        $css_vars = ':root {';
        
        $css_vars .= "--wp-admin-color-primary: {$primary_color};";
        $css_vars .= "--wp-admin-color-secondary: {$secondary_color};";
        
        $css_vars .= "--wp-admin-color-primary-light: color-mix(in srgb, {$primary_color} 10%, transparent);";
        $css_vars .= "--wp-admin-color-primary-border: color-mix(in srgb, {$primary_color} 20%, transparent);";
        $css_vars .= "--wp-admin-color-secondary-light: color-mix(in srgb, {$secondary_color} 10%, transparent);";
        $css_vars .= "--wp-admin-color-secondary-border: color-mix(in srgb, {$secondary_color} 20%, transparent);";
        
        foreach ($colors as $index => $color) {
            $css_vars .= "--wp-admin-color-{$index}: {$color};";
        }
        
        $css_vars .= '}';
    
        wp_add_inline_style('wp-admin', $css_vars);
    }

    public function registerSettings(): void
    {
        register_setting('simplify-admin', 'sa_menu_settings');
        register_setting('simplify-admin', 'sa_adminbar_settings');
    }

    public function ajaxLoadRoleSettings(): void
    {
        check_ajax_referer('simplify-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $role = sanitize_text_field($_POST['role']);
        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'menu-items';
        
        if ($tab === 'menu-items') {
            $settings = get_option('sa_menu_settings_' . $role, []);
        } else {
            $settings = get_option('sa_adminbar_settings_' . $role, []);
        }

        wp_send_json_success($settings);
    }

    public function handleFormSubmission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        check_admin_referer('simplify-admin-options');

        $role = sanitize_text_field($_POST['selected_role']);

        if (empty($role)) {
            wp_die('Role is required');
        }

        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'menu-items';
        $settings = [];

        if (isset($_POST['sa_settings']) && is_array($_POST['sa_settings'])) {
            foreach ($_POST['sa_settings'] as $key => $value) {
                $cleanKey = sanitize_text_field($key);
                if ($tab === 'admin-bar') {
                    // Remove admin_bar_ prefix for storage
                    $cleanKey = str_replace('admin_bar_', '', $cleanKey);
                }
                $settings[$cleanKey] = true;
            }
        }

        if ($tab === 'menu-items') {
            update_option('sa_menu_settings_' . $role, $settings);
        } else {
            update_option('sa_adminbar_settings_' . $role, $settings);
        }

        // Redirect back to the settings page with a success message
        wp_redirect(add_query_arg(
            [
                'page' => 'simplify-admin',
                'tab' => $tab,
                'settings-updated' => 'true'
            ],
            admin_url('options-general.php')
        ));
        exit;
    }

    private function getCurrentRole(): string
    {
        $currentUser = wp_get_current_user();
        if (isset($currentUser->roles[0])) {
            return $currentUser->roles[0];
        }
        return 'administrator';
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $roles = array_map('translate_user_role', wp_roles()->get_names());
        $menuItems = $this->menuSettings->getMenuItems();
        $adminBarItems = $this->adminBarSettings->getAdminBarItems();
        $currentRole = isset($_POST['selected_role']) 
            ? sanitize_text_field($_POST['selected_role']) 
            : $this->getCurrentRole();
        $currentTab = isset($_GET['tab']) 
            ? sanitize_text_field($_GET['tab']) 
            : 'menu-items';
        
        // Get appropriate settings based on tab
        if ($currentTab === 'menu-items') {
            $settings = get_option('sa_menu_settings_' . $currentRole, []);
        } else {
            $settings = get_option('sa_adminbar_settings_' . $currentRole, []);
        }

        include $this->pluginPath . 'resources/views/settings-page.php';
    }

    public function displaySettingsUpdatedNotice(): void
    {
        $screen = get_current_screen();
        if ($screen->id === 'settings_page_simplify-admin' 
            && isset($_GET['settings-updated']) 
            && $_GET['settings-updated'] === 'true'
        ) {
            echo '<div class="notice notice-success is-dismissible"><p>' 
                . esc_html__('Settings saved successfully!', 'simplify-admin') 
                . '</p></div>';
        }
    }
} 
