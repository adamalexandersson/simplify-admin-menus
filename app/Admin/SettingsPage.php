<?php

namespace SimplifyAdminMenus\Admin;

use SimplifyAdminMenus\AdminBar\AdminBarSettings;
use SimplifyAdminMenus\Core\ViteManifest;
use SimplifyAdminMenus\Menu\MenuSettings;
use SimplifyAdminMenus\Settings\Resolver;

use function absint;
use function add_action;
use function add_options_page;
use function add_query_arg;
use function admin_url;
use function check_ajax_referer;
use function current_user_can;
use function delete_option;
use function delete_user_meta;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function file_exists;
use function filemtime;
use function get_current_screen;
use function get_user_by;
use function get_user_option;
use function get_users;
use function is_array;
use function map_deep;
use function sanitize_text_field;
use function update_option;
use function update_user_meta;
use function wp_add_inline_style;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_current_user;
use function wp_localize_script;
use function wp_roles;
use function wp_safe_redirect;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_verify_nonce;
use function __;

/**
 * Admin Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    private string $pluginPath;
    private string $pluginUrl;
    private MenuSettings $menuSettings;
    private AdminBarSettings $adminBarSettings;
    private Resolver $resolver;
    private ViteManifest $viteManifest;

    public function __construct(
        string $pluginPath,
        string $pluginUrl,
        MenuSettings $menuSettings,
        AdminBarSettings $adminBarSettings,
        Resolver $resolver
    ) {
        $this->pluginPath = $pluginPath;
        $this->pluginUrl = $pluginUrl;
        $this->menuSettings = $menuSettings;
        $this->adminBarSettings = $adminBarSettings;
        $this->resolver = $resolver;
        $this->viteManifest = new ViteManifest($this->pluginPath . 'dist/.vite/manifest.json');

        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_enqueue_scripts', [$this, 'setAdminProfileColors']);
        add_action('admin_post_save_simpad_settings', [$this, 'handleFormSubmission']);
        add_action('admin_post_save_simpad_protected_users', [$this, 'handleProtectedUsersSubmission']);
        add_action('wp_ajax_load_settings', [$this, 'ajaxLoadSettings']);
        add_action('admin_notices', [$this, 'displaySettingsUpdatedNotice']);
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('Simplify Admin Menus', 'simplify-admin-menus'),
            __('Simplify Admin Menus', 'simplify-admin-menus'),
            'manage_options',
            'simplify-admin-menus',
            [$this, 'renderSettingsPage']
        );
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if ('settings_page_simplify-admin-menus' !== $hook) {
            return;
        }

        // Get the main entry points from manifest
        $adminJs = $this->viteManifest->getAsset('resources/assets/js/admin.js');
        $adminCss = $this->viteManifest->getCss('resources/assets/js/admin.js');

        // Enqueue main JavaScript
        if ($adminJs) {
            $jsPath = $this->pluginPath . 'dist/' . $adminJs;
            wp_enqueue_script(
                'simplify-admin-menus',
                $this->pluginUrl . 'dist/' . $adminJs,
                [],
                file_exists($jsPath) ? (string) filemtime($jsPath) : false,
                true
            );

            wp_localize_script('simplify-admin-menus', 'simplifyAdminMenus', [
                'nonce' => wp_create_nonce('simplify-admin-menus'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'strings' => [
                    'editing' => __('Editing:', 'simplify-admin-menus'),
                    'usingRoleDefaults' => __('Using role defaults', 'simplify-admin-menus'),
                    /* translators: %d: number of custom overrides */
                    'customOverrides' => __('Custom overrides (%d)', 'simplify-admin-menus'),
                    'resetToRole' => __('Reset to role defaults', 'simplify-admin-menus'),
                    'protectedNotice' => __('This user is a protected administrator. Menu restrictions do not apply.', 'simplify-admin-menus'),
                    'inherit' => __('Inherit', 'simplify-admin-menus'),
                    'hide' => __('Hide', 'simplify-admin-menus'),
                    'show' => __('Show', 'simplify-admin-menus'),
                    'hiddenByRole' => __('Hidden by role', 'simplify-admin-menus'),
                    'chooseHide' => __('Choose which items to hide', 'simplify-admin-menus'),
                    'chooseUserOverrides' => __('Set Inherit, Hide, or Show per item. Show bypasses role hiding, but cannot grant WordPress capabilities the user does not have.', 'simplify-admin-menus'),
                ],
            ]);
        }

        // Enqueue any additional CSS from JS imports
        foreach ($adminCss as $index => $cssFile) {
            $cssPath = $this->pluginPath . 'dist/' . $cssFile;
            wp_enqueue_style(
                'simplify-admin-menus-' . $index,
                $this->pluginUrl . 'dist/' . $cssFile,
                [],
                file_exists($cssPath) ? (string) filemtime($cssPath) : false
            );
        }
    }

    public function setAdminProfileColors(): void
    {
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

        $css_vars = [];

        $css_vars[] = sprintf(
            '--wp-admin-color-primary: %s',
            esc_attr($primary_color)
        );
        $css_vars[] = sprintf(
            '--wp-admin-color-secondary: %s',
            esc_attr($secondary_color)
        );

        $css_vars[] = sprintf(
            '--wp-admin-color-primary-light: color-mix(in srgb, %s 10%%, transparent)',
            esc_attr($primary_color)
        );
        $css_vars[] = sprintf(
            '--wp-admin-color-primary-border: color-mix(in srgb, %s 20%%, transparent)',
            esc_attr($primary_color)
        );
        $css_vars[] = sprintf(
            '--wp-admin-color-secondary-light: color-mix(in srgb, %s 10%%, transparent)',
            esc_attr($secondary_color)
        );
        $css_vars[] = sprintf(
            '--wp-admin-color-secondary-border: color-mix(in srgb, %s 20%%, transparent)',
            esc_attr($secondary_color)
        );

        foreach ($colors as $index => $color) {
            $css_vars[] = sprintf(
                '--wp-admin-color-%d: %s',
                (int) $index,
                esc_attr($color)
            );
        }

        $css_output = ':root {' . implode(';', array_map('esc_html', $css_vars)) . '}';

        wp_add_inline_style('wp-admin', $css_output);
    }

    public function ajaxLoadSettings(): void
    {
        check_ajax_referer('simplify-admin-menus', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'simplify-admin-menus'));
        }

        $role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '';
        $userId = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if ($role === '' && $userId === 0) {
            wp_send_json_error(__('Role or User ID is required', 'simplify-admin-menus'));
        }

        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'menu-items';
        $type = $tab === 'admin-bar' ? Resolver::TYPE_ADMINBAR : Resolver::TYPE_MENU;

        if ($userId) {
            $user = get_user_by('id', $userId);
            if (!$user) {
                wp_send_json_error(__('User not found', 'simplify-admin-menus'));
            }

            $userRole = !empty($user->roles[0]) ? $user->roles[0] : '';
            $settings = $this->resolver->getUserOverrides($userId, $type);
            $roleSettings = $this->resolver->getRoleHideMap($userRole, $type);

            $response = [
                'settings' => $settings,
                'role_settings' => $roleSettings,
                'role' => $userRole,
                'is_protected' => $this->resolver->isProtectedUser($user),
                'format' => 'overrides',
            ];

            if ($tab === 'menu-items') {
                // Capabilities for client-side filtering via data-capability attributes.
                $response['capabilities'] = $this->resolver->getActorCapabilities($userRole, $user);
            } else {
                // Probe which admin-bar nodes WordPress actually builds for this user.
                $response['accessible_ids'] = $this->adminBarSettings->getAccessibleNodeIds($userRole, $user);
            }

            wp_send_json_success($response);
        }

        $settings = $this->resolver->getRoleHideMap($role, $type);

        $response = [
            'settings' => $settings,
            'format' => 'role',
        ];

        if ($tab === 'menu-items') {
            $response['capabilities'] = $this->resolver->getActorCapabilities($role, null);
        } else {
            $response['accessible_ids'] = $this->adminBarSettings->getAccessibleNodeIds($role, null);
        }

        wp_send_json_success($response);
    }

    public function handleFormSubmission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'simplify-admin-menus'));
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'simplify-admin-menus')) {
            wp_die(esc_html__('Invalid nonce', 'simplify-admin-menus'));
        }

        $role = isset($_POST['selected_role']) ? sanitize_text_field(wp_unslash($_POST['selected_role'])) : '';
        $userId = isset($_POST['selected_user']) ? absint($_POST['selected_user']) : 0;

        if ($role === '' && $userId === 0) {
            wp_die(esc_html__('Role or User ID is required', 'simplify-admin-menus'));
        }

        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'menu-items';
        $type = $tab === 'admin-bar' ? Resolver::TYPE_ADMINBAR : Resolver::TYPE_MENU;
        $settingsKey = $this->resolver->getUserMetaKey($type);

        if ($userId) {
            $rawOverrides = [];
            if (isset($_POST['simpad_overrides'])) {
                $rawOverrides = map_deep(wp_unslash($_POST['simpad_overrides']), 'sanitize_text_field');
            }
            $overrides = $this->sanitizeUserOverrides($rawOverrides);
            $this->handleUserSettings($userId, $settingsKey, $overrides);
        } else {
            $rawSettings = [];
            if (isset($_POST['simpad_settings'])) {
                $rawSettings = map_deep(wp_unslash($_POST['simpad_settings']), 'sanitize_text_field');
            }
            $settings = $this->sanitizeRoleSettings($rawSettings, $tab);
            $this->handleRoleSettings($role, $settingsKey, $settings);
        }

        $redirectArgs = [
            'page' => 'simplify-admin-menus',
            'tab' => $tab,
            'settings-updated' => 'true',
            '_wpnonce' => wp_create_nonce('simplify-admin-menus'),
        ];

        if ($userId) {
            $redirectArgs['selected_user'] = $userId;
        } else {
            $redirectArgs['selected_role'] = $role;
        }

        wp_safe_redirect(add_query_arg($redirectArgs, admin_url('options-general.php')));
        exit;
    }

    public function handleProtectedUsersSubmission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'simplify-admin-menus'));
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'simplify-admin-menus-protected')) {
            wp_die(esc_html__('Invalid nonce', 'simplify-admin-menus'));
        }

        $posted = [];
        if (isset($_POST['simpad_protected_users'])) {
            $posted = map_deep(wp_unslash($_POST['simpad_protected_users']), 'absint');
        }
        if (!is_array($posted)) {
            $posted = [];
        }

        $ids = array_map('absint', $posted);
        $currentUserId = (int) wp_get_current_user()->ID;
        $existing = $this->resolver->getProtectedUserIds();

        // Lockout guard: cannot remove yourself if you are the only protected admin.
        if (
            in_array($currentUserId, $existing, true)
            && !in_array($currentUserId, $ids, true)
            && count($existing) === 1
        ) {
            $ids[] = $currentUserId;
        }

        // Always keep at least the current admin if the list would otherwise be empty after filter.
        if ($ids === [] && $currentUserId > 0) {
            $ids[] = $currentUserId;
        }

        $this->resolver->saveProtectedUserIds($ids);

        wp_safe_redirect(add_query_arg([
            'page' => 'simplify-admin-menus',
            'settings-updated' => 'true',
            '_wpnonce' => wp_create_nonce('simplify-admin-menus'),
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * @param mixed $input
     * @return array<string, true>
     */
    private function sanitizeRoleSettings($input, string $tab): array
    {
        if (!is_array($input)) {
            return [];
        }

        $settings = [];
        foreach ($input as $key => $value) {
            $cleanKey = sanitize_text_field((string) $key);
            if ($tab === 'admin-bar') {
                $cleanKey = str_replace('admin_bar_', '', $cleanKey);
            }
            if ($cleanKey === '') {
                continue;
            }
            $settings[$cleanKey] = true;
        }

        return $settings;
    }

    /**
     * @param mixed $input
     * @return array<string, string>
     */
    private function sanitizeUserOverrides($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $overrides = [];
        foreach ($input as $key => $value) {
            $cleanKey = sanitize_text_field((string) $key);
            $state = sanitize_text_field((string) $value);

            if ($cleanKey === '') {
                continue;
            }

            if ($state !== Resolver::STATE_HIDE && $state !== Resolver::STATE_SHOW) {
                continue;
            }

            $overrides[$cleanKey] = $state;
        }

        return $overrides;
    }

    private function handleUserSettings(int $userId, string $key, array $settings): void
    {
        if ($settings === []) {
            delete_user_meta($userId, $key);
        } else {
            update_user_meta($userId, $key, $settings);
        }
    }

    private function handleRoleSettings(string $role, string $key, array $settings): void
    {
        $optionKey = $key . '_' . $role;
        if ($settings === []) {
            delete_option($optionKey);
        } else {
            update_option($optionKey, $settings);
        }
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $selectedRoleParam = null;
        $selectedUserParam = null;
        $currentTab = 'menu-items';

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if ($nonce !== '' && wp_verify_nonce($nonce, 'simplify-admin-menus')) {
            $selectedRoleParam = isset($_GET['selected_role'])
                ? sanitize_text_field(wp_unslash($_GET['selected_role']))
                : null;
            $selectedUserParam = isset($_GET['selected_user'])
                ? absint(wp_unslash($_GET['selected_user']))
                : null;
            $currentTab = isset($_GET['tab'])
                ? sanitize_text_field(wp_unslash($_GET['tab']))
                : 'menu-items';
        }

        $roles = array_map('translate_user_role', wp_roles()->get_names());
        $menuItems = $this->menuSettings->getMenuItems();
        $adminBarItems = $this->adminBarSettings->getAdminBarItems();

        $selectedRole = $this->getSelectedRole($selectedRoleParam);
        $selectedUser = $this->getSelectedUser($selectedUserParam);

        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $administrators = get_users([
            'role' => 'administrator',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $protectedUserIds = $this->resolver->getProtectedUserIds();
        $resolver = $this->resolver;

        include $this->pluginPath . 'resources/views/settings-page.php';
    }

    public function displaySettingsUpdatedNotice(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->id !== 'settings_page_simplify-admin-menus') {
            return;
        }

        if (isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (!wp_verify_nonce($nonce, 'simplify-admin-menus')) {
                return;
            }
        }

        if (
            isset($_GET['settings-updated'])
            && sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true'
        ) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('Settings saved successfully!', 'simplify-admin-menus')
                . '</p></div>';
        }
    }

    private function getSelectedRole(?string $selectedRole = null): string
    {
        if ($selectedRole && array_key_exists($selectedRole, wp_roles()->get_names())) {
            return $selectedRole;
        }

        return 'administrator';
    }

    private function getSelectedUser(?int $selectedUserId = null): ?object
    {
        if ($selectedUserId) {
            $user = get_user_by('id', $selectedUserId);
            return $user ?: null;
        }

        return null;
    }
}
