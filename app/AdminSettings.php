<?php

namespace SimplifyAdmin;

use function add_action;
use function add_options_page;
use function admin_url;
use function add_query_arg;
use function check_ajax_referer;
use function current_user_can;
use function esc_html__;
use function get_current_screen;
use function get_option;
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
use function wp_unslash;
use function is_array;
use function get_users;
use function get_user_meta;
use function update_user_meta;
use function get_user_by;
use function delete_user_meta;
use function delete_option;
use function absint;

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
        add_action('admin_post_save_simpad_settings', [$this, 'handleFormSubmission']);
        add_action('wp_ajax_load_settings', [$this, 'ajaxLoadSettings']);
        add_action('admin_notices', [$this, 'displaySettingsUpdatedNotice']);
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('Simplify Admin', 'simplify-admin'),
            __('Simplify Admin', 'simplify-admin'),
            'manage_options',
            'simpad-simplify-admin',
            [$this, 'renderSettingsPage']
        );
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if ('settings_page_simpad-simplify-admin' !== $hook) {
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
    
        $css_vars = array();
        
        array_push($css_vars, sprintf(
            '--wp-admin-color-primary: %s',
            esc_attr($primary_color)
        ));
        array_push($css_vars, sprintf(
            '--wp-admin-color-secondary: %s',
            esc_attr($secondary_color)
        ));
        
        array_push($css_vars, sprintf(
            '--wp-admin-color-primary-light: color-mix(in srgb, %s 10%%, transparent)',
            esc_attr($primary_color)
        ));
        array_push($css_vars, sprintf(
            '--wp-admin-color-primary-border: color-mix(in srgb, %s 20%%, transparent)',
            esc_attr($primary_color)
        ));
        array_push($css_vars, sprintf(
            '--wp-admin-color-secondary-light: color-mix(in srgb, %s 10%%, transparent)',
            esc_attr($secondary_color)
        ));
        array_push($css_vars, sprintf(
            '--wp-admin-color-secondary-border: color-mix(in srgb, %s 20%%, transparent)',
            esc_attr($secondary_color)
        ));
        
        foreach ($colors as $index => $color) {
            array_push($css_vars, sprintf(
                '--wp-admin-color-%d: %s',
                (int) $index,
                esc_attr($color)
            ));
        }
        
        $css_output = ':root {' . implode(';', array_map('esc_html', $css_vars)) . '}';
    
        wp_add_inline_style('wp-admin', $css_output);
    }

    public function sanitizeMenuSettings($input)
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = array_map(function ($item) {
            return sanitize_text_field($item);
        }, $input);

        return $sanitized;
    }

    public function registerSettings(): void
    {
        register_setting(
            'simplify-admin',
            'simpad_menu_settings',
            [
                'sanitize_callback' => [$this, 'sanitizeMenuSettings'], 
                'default' => []
            ]
        );
        
        register_setting(
            'simplify-admin',
            'simpad_adminbar_settings',
            [
                'sanitize_callback' => [$this, 'sanitizeMenuSettings'],
                'default' => []
            ]
        );
    }

    public function ajaxLoadSettings(): void
    {
        check_ajax_referer('simplify-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '';
        $userId = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        
        if (empty($role) && empty($userId)) {
            wp_send_json_error('Role or User ID is required');
        }

        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'menu-items';
        
        if ($userId) {
            if ($tab === 'menu-items') {
                $settings = get_user_meta($userId, 'simpad_menu_settings', true) ?: [];
            } else {
                $settings = get_user_meta($userId, 'simpad_adminbar_settings', true) ?: [];
            }

            $user = get_user_by('id', $userId);
            $userRole = $user && !empty($user->roles) ? $user->roles[0] : '';

            wp_send_json_success([
                'settings' => $settings,
                'role' => $userRole
            ]);
        } else {
            if ($tab === 'menu-items') {
                $settings = get_option('simpad_menu_settings_' . $role, []);
            } else {
                $settings = get_option('simpad_adminbar_settings_' . $role, []);
            }

            wp_send_json_success([
                'settings' => $settings
            ]);
        }
    }

    public function handleFormSubmission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'simplify-admin-options')) {
            wp_die('Invalid nonce');
        }

        $role = isset($_POST['selected_role']) ? sanitize_text_field(wp_unslash($_POST['selected_role'])) : '';
        $userId = isset($_POST['selected_user']) ? absint($_POST['selected_user']) : 0;

        if (empty($role) && empty($userId)) {
            wp_die('Role or User ID is required');
        }

        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'menu-items';
        $settings = [];

        $postSettings = array_map('sanitize_text_field', isset($_POST['simpad_settings']) ? wp_unslash($_POST['simpad_settings']) : []);

        if (isset($postSettings) && is_array($postSettings)) {
            foreach ($postSettings as $key => $value) {
                $cleanKey = sanitize_text_field($key);
                if ($tab === 'admin-bar') {
                    $cleanKey = str_replace('admin_bar_', '', $cleanKey);
                }
                $settings[$cleanKey] = true;
            }
        }

        $settingsKey = $this->getSettingsKey($tab);

        if ($userId) {
            $this->handleUserSettings($userId, $settingsKey, $settings);
        } else {
            $this->handleRoleSettings($role, $settingsKey, $settings);
        }

        // Build redirect URL with all necessary parameters
        $redirectArgs = [
            'page' => 'simpad-simplify-admin',
            'tab' => $tab,
            'settings-updated' => 'true',
            '_wpnonce' => wp_create_nonce('simplify-admin-settings-updated')
        ];

        // Add either selected_role or selected_user parameter
        if ($userId) {
            $redirectArgs['selected_user'] = $userId;
        } else {
            $redirectArgs['selected_role'] = $role;
        }

        wp_redirect(add_query_arg($redirectArgs, admin_url('options-general.php')));
        exit;
    }

    private function getSettingsKey(string $tab): string
    {
        return $tab === 'menu-items' ? 'simpad_menu_settings' : 'simpad_adminbar_settings';
    }

    private function handleUserSettings(int $userId, string $key, array $settings): void
    {
        if (empty($settings)) {
            delete_user_meta($userId, $key);
        } else {
            update_user_meta($userId, $key, $settings);
        }
    }

    private function handleRoleSettings(string $role, string $key, array $settings): void
    {
        $optionKey = $key . '_' . $role;
        if (empty($settings)) {
            delete_option($optionKey);
        } else {
            update_option($optionKey, $settings);
        }
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

        // Verify nonce if POST request
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
            if (empty($nonce) || !wp_verify_nonce($nonce, 'simplify-admin-options')) {
                wp_die('Invalid nonce');
            }
        }

        $roles = array_map('translate_user_role', wp_roles()->get_names());
        $menuItems = $this->menuSettings->getMenuItems();
        $adminBarItems = $this->adminBarSettings->getAdminBarItems();
        $selectedRole = $this->getSelectedRole();
        $selectedUser = $this->getSelectedUser();
        
        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        $currentUser = null;

        if (isset($_REQUEST['selected_user'])) {
            $userId = absint($_REQUEST['selected_user']);
            foreach ($users as $user) {
                if ($user->ID === $userId) {
                    $currentUser = $user;
                    break;
                }
            }
        }

        $currentRole = isset($_POST['selected_role']) 
            ? sanitize_text_field(wp_unslash($_POST['selected_role'])) 
            : $this->getCurrentRole();
        $currentTab = isset($_GET['tab']) 
            ? sanitize_text_field(wp_unslash($_GET['tab'])) 
            : 'menu-items';
        
        if ($currentTab === 'menu-items') {
            if ($currentUser) {
                $settings = get_user_meta($currentUser->ID, 'simpad_menu_settings', true) ?: [];
            } else {
                $settings = get_option('simpad_menu_settings_' . $currentRole, []);
            }
        } else {
            if ($currentUser) {
                $settings = get_user_meta($currentUser->ID, 'simpad_adminbar_settings', true) ?: [];
            } else {
                $settings = get_option('simpad_adminbar_settings_' . $currentRole, []);
            }
        }

        include $this->pluginPath . 'resources/views/settings-page.php';
    }

    public function displaySettingsUpdatedNotice(): void
    {
        $screen = get_current_screen();
        
        if (isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (!wp_verify_nonce($nonce, 'simplify-admin-settings-updated')) {
                return;
            }
        }

        if ($screen->id === 'settings_page_simplify-admin' 
            && isset($_GET['settings-updated']) 
            && sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true'
        ) {
            echo '<div class="notice notice-success is-dismissible"><p>' 
                . esc_html__('Settings saved successfully!', 'simplify-admin') 
                . '</p></div>';
        }
    }

    private function getSelectedRole(): ?string
    {
        // Verify nonce for role selection
        $nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'simplify-admin-settings') : false;
        
        if (!$nonce && !wp_verify_nonce(wp_create_nonce('simplify-admin-settings'), 'simplify-admin-settings')) {
            return 'administrator';
        }

        $selectedRole = isset($_GET['selected_role']) 
            ? sanitize_text_field(wp_unslash($_GET['selected_role'])) 
            : null;

        if ($selectedRole && array_key_exists($selectedRole, wp_roles()->get_names())) {
            return $selectedRole;
        }

        return 'administrator';
    }

    private function getSelectedUser(): ?object
    {
        // Verify nonce for user selection
        $nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'simplify-admin-settings') : false;
        
        if (!$nonce && !wp_verify_nonce(wp_create_nonce('simplify-admin-settings'), 'simplify-admin-settings')) {
            return null;
        }

        $selectedUserId = isset($_GET['selected_user']) 
            ? absint($_GET['selected_user']) 
            : null;

        if ($selectedUserId) {
            $user = get_user_by('id', $selectedUserId);
            return $user ?: null;
        }

        return null;
    }
} 
