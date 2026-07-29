<?php
/**
 * Settings page template
 *
 * @package SimplifyAdminMenus
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template variables provided by SettingsPage::renderSettingsPage().
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="simpad-protected-form">
        <input type="hidden" name="action" value="save_simpad_protected_users">
        <?php wp_nonce_field('simplify-admin-menus-protected'); ?>
        <div class="simpad-protected-box">
            <h2><?php esc_html_e('Protected administrators', 'simplify-admin-menus'); ?></h2>
            <p class="description">
                <?php esc_html_e('Protected administrators always see the full admin menu and admin bar, even when the Administrator role has items hidden. Only users checked here are exempt — including network/super admins. The first administrator is protected by default.', 'simplify-admin-menus'); ?>
            </p>
            <ul class="simpad-protected-list">
                <?php foreach ($administrators as $adminUser) : ?>
                    <li>
                        <label>
                            <input
                                type="checkbox"
                                name="simpad_protected_users[]"
                                value="<?php echo esc_attr((string) $adminUser->ID); ?>"
                                <?php checked(in_array((int) $adminUser->ID, $protectedUserIds, true)); ?>
                            >
                            <span>
                                <?php echo esc_html($adminUser->display_name); ?>
                                <small>(<?php echo esc_html($adminUser->user_login); ?>)</small>
                            </span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php submit_button(esc_html__('Save protected administrators', 'simplify-admin-menus'), 'secondary', 'submit', false); ?>
        </div>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="simplify-admin-menus-form" class="simplify-admin-menus-form <?php echo $selectedUser ? 'is-user-mode' : 'is-role-mode'; ?>" data-current-tab="<?php echo esc_attr($currentTab); ?>" data-mode="<?php echo esc_attr($selectedUser ? 'user' : 'role'); ?>">
        <input type="hidden" name="action" value="save_simpad_settings">
        <input type="hidden" name="tab" value="<?php echo esc_attr($currentTab); ?>">
        <?php wp_nonce_field('simplify-admin-menus'); ?>
        <div class="simpad-container">
            <div class="simpad-roles-column">
                <h2><?php esc_html_e('User Roles', 'simplify-admin-menus'); ?></h2>
                <ul class="simpad-roles-list">
                    <?php foreach ($roles as $role_slug => $role_name) : ?>
                        <li>
                            <label>
                                <input type="radio" name="selected_role" value="<?php echo esc_attr($role_slug); ?>" <?php checked($role_slug === $selectedRole && !$selectedUser); ?>>
                                <span><?php echo esc_html($role_name); ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h2><?php esc_html_e('Users', 'simplify-admin-menus'); ?></h2>
                <div class="simpad-users-search">
                    <input type="text" id="simpad-user-search" placeholder="<?php esc_attr_e('Search users...', 'simplify-admin-menus'); ?>">
                </div>
                <ul class="simpad-users-list">
                    <?php foreach ($users as $user) : ?>
                        <?php
                        $isProtected = $resolver->isProtectedUser($user);
                        ?>
                        <li class="<?php echo $isProtected ? 'simpad-user-protected' : ''; ?>">
                            <label>
                                <input type="radio" name="selected_user" value="<?php echo esc_attr((string) $user->ID); ?>" <?php checked($selectedUser && (int) $selectedUser->ID === (int) $user->ID); ?>>
                                <span>
                                    <?php
                                    echo esc_html($user->display_name);
                                    $user_roles = array_map(function ($role) use ($roles) {
                                        return isset($roles[$role]) ? $roles[$role] : $role;
                                    }, $user->roles);
                                    echo ' <small>(' . esc_html(implode(', ', $user_roles)) . ')</small>';
                                    if ($isProtected) {
                                        echo ' <small class="simpad-protected-badge">' . esc_html__('Protected', 'simplify-admin-menus') . '</small>';
                                    }
                                    ?>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="simpad-content-wrapper">
                <div class="simpad-settings-column">
                    <nav class="nav-tab-wrapper">
                        <?php
                        $tabUrlArgs = [
                            'page' => 'simplify-admin-menus',
                            '_wpnonce' => wp_create_nonce('simplify-admin-menus'),
                        ];

                        if ($selectedUser) {
                            $tabUrlArgs['selected_user'] = $selectedUser->ID;
                        } elseif ($selectedRole) {
                            $tabUrlArgs['selected_role'] = $selectedRole;
                        }
                        ?>
                        <a href="<?php echo esc_url(add_query_arg(array_merge($tabUrlArgs, ['tab' => 'menu-items']), admin_url('options-general.php'))); ?>"
                           class="nav-tab <?php echo $currentTab === 'menu-items' ? 'nav-tab-active' : ''; ?>">
                            <span class="dashicons dashicons-menu-alt"></span>
                            <?php esc_html_e('Menu Items', 'simplify-admin-menus'); ?>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg(array_merge($tabUrlArgs, ['tab' => 'admin-bar']), admin_url('options-general.php'))); ?>"
                           class="nav-tab <?php echo $currentTab === 'admin-bar' ? 'nav-tab-active' : ''; ?>">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Admin Bar', 'simplify-admin-menus'); ?>
                        </a>
                    </nav>
                    <div class="simpad-settings-content">
                        <div class="simpad-content-header">
                            <div class="simpad-content-header-top">
                                <h2 class="simpad-content-header-title">
                                    <?php
                                    if ($currentTab === 'menu-items') {
                                        esc_html_e('Menu Items', 'simplify-admin-menus');
                                    } else {
                                        esc_html_e('Admin Bar', 'simplify-admin-menus');
                                    }
                                    ?>
                                </h2>
                                <span class="simpad-current-role">
                                    <?php
                                    if ($selectedUser) {
                                        /* translators: %s: User display name */
                                        printf(esc_html__('Editing user: %s', 'simplify-admin-menus'), esc_html($selectedUser->display_name));
                                    } else {
                                        /* translators: %s: User role name */
                                        printf(esc_html__('Editing role: %s', 'simplify-admin-menus'), esc_html($roles[$selectedRole]));
                                    }
                                    ?>
                                </span>
                            </div>

                            <p class="simpad-content-header-description" id="simpad-settings-description">
                                <?php
                                if ($selectedUser) {
                                    esc_html_e('Set Inherit, Hide, or Show per item. Show bypasses role hiding, but cannot grant WordPress capabilities the user does not have.', 'simplify-admin-menus');
                                } else {
                                    esc_html_e('Choose which items to hide', 'simplify-admin-menus');
                                }
                                ?>
                            </p>

                            <div class="simpad-user-status" id="simpad-user-status" hidden>
                                <span class="simpad-status-label" id="simpad-status-label"></span>
                                <button type="button" class="button-link" id="simpad-reset-overrides">
                                    <?php esc_html_e('Reset to role defaults', 'simplify-admin-menus'); ?>
                                </button>
                            </div>
                            <div class="simpad-protected-notice" id="simpad-protected-notice" hidden>
                                <?php esc_html_e('This user is a protected administrator. Menu restrictions do not apply.', 'simplify-admin-menus'); ?>
                            </div>
                        </div>

                        <div class="simpad-loading-overlay">
                            <div class="simpad-loading-spinner">
                                <div class="simpad-spinner-circle"></div>
                                <div class="simpad-spinner-text"><?php esc_html_e('Loading settings...', 'simplify-admin-menus'); ?></div>
                            </div>
                        </div>

                        <?php if ($currentTab === 'menu-items') : ?>
                            <div class="simpad-menu-items-list">
                                <?php foreach ($menuItems as $menu_item) : ?>
                                    <?php if (isset($menu_item['title']) && $menu_item['title']) : ?>
                                        <div class="simpad-menu-item" data-item-id="<?php echo esc_attr($menu_item['id']); ?>" data-capability="<?php echo esc_attr($menu_item['capability'] ?? ''); ?>">
                                            <label class="simpad-role-control">
                                                <input type="checkbox" name="simpad_settings[<?php echo esc_attr($menu_item['id']); ?>]" value="1">
                                                <?php echo esc_html($menu_item['title']); ?>
                                            </label>
                                            <div class="simpad-user-control" hidden>
                                                <span class="simpad-item-title"><?php echo esc_html($menu_item['title']); ?></span>
                                                <div class="simpad-state-toggle" role="group" aria-label="<?php echo esc_attr($menu_item['title']); ?>">
                                                    <button type="button" class="simpad-state-btn" data-state="inherit"><?php esc_html_e('Inherit', 'simplify-admin-menus'); ?></button>
                                                    <button type="button" class="simpad-state-btn" data-state="hide"><?php esc_html_e('Hide', 'simplify-admin-menus'); ?></button>
                                                    <button type="button" class="simpad-state-btn" data-state="show"><?php esc_html_e('Show', 'simplify-admin-menus'); ?></button>
                                                </div>
                                                <input type="hidden" class="simpad-override-input" data-item-id="<?php echo esc_attr($menu_item['id']); ?>" value="inherit" disabled>
                                                <span class="simpad-role-hint" hidden><?php esc_html_e('Hidden by role', 'simplify-admin-menus'); ?></span>
                                            </div>

                                            <?php if (!empty($menu_item['submenu'])) : ?>
                                                <div class="simpad-submenu-items">
                                                    <?php foreach ($menu_item['submenu'] as $submenu_item) : ?>
                                                        <div class="simpad-menu-item simpad-submenu-item" data-item-id="<?php echo esc_attr($submenu_item['id']); ?>" data-capability="<?php echo esc_attr($submenu_item['capability'] ?? ''); ?>">
                                                            <label class="simpad-role-control">
                                                                <input type="checkbox"
                                                                       name="simpad_settings[<?php echo esc_attr($submenu_item['id']); ?>]"
                                                                       value="1">
                                                                <?php echo esc_html($submenu_item['title']); ?>
                                                            </label>
                                                            <div class="simpad-user-control" hidden>
                                                                <span class="simpad-item-title"><?php echo esc_html($submenu_item['title']); ?></span>
                                                                <div class="simpad-state-toggle" role="group" aria-label="<?php echo esc_attr($submenu_item['title']); ?>">
                                                                    <button type="button" class="simpad-state-btn" data-state="inherit"><?php esc_html_e('Inherit', 'simplify-admin-menus'); ?></button>
                                                                    <button type="button" class="simpad-state-btn" data-state="hide"><?php esc_html_e('Hide', 'simplify-admin-menus'); ?></button>
                                                                    <button type="button" class="simpad-state-btn" data-state="show"><?php esc_html_e('Show', 'simplify-admin-menus'); ?></button>
                                                                </div>
                                                                <input type="hidden" class="simpad-override-input" data-item-id="<?php echo esc_attr($submenu_item['id']); ?>" value="inherit" disabled>
                                                                <span class="simpad-role-hint" hidden><?php esc_html_e('Hidden by role', 'simplify-admin-menus'); ?></span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="simpad-admin-bar-items-list">
                                <?php
                                $renderAdminBarItem = static function ($item) use (&$renderAdminBarItem) {
                                    ?>
                                    <div class="simpad-menu-item" data-item-id="<?php echo esc_attr($item['id']); ?>">
                                        <label class="simpad-role-control">
                                            <input type="checkbox"
                                                   name="simpad_settings[<?php echo esc_attr($item['id']); ?>]"
                                                   value="1">
                                            <?php echo wp_kses_post($item['title']); ?>
                                        </label>
                                        <div class="simpad-user-control" hidden>
                                            <span class="simpad-item-title"><?php echo wp_kses_post($item['title']); ?></span>
                                            <div class="simpad-state-toggle" role="group" aria-label="<?php echo esc_attr(wp_strip_all_tags($item['title'])); ?>">
                                                <button type="button" class="simpad-state-btn" data-state="inherit"><?php esc_html_e('Inherit', 'simplify-admin-menus'); ?></button>
                                                <button type="button" class="simpad-state-btn" data-state="hide"><?php esc_html_e('Hide', 'simplify-admin-menus'); ?></button>
                                                <button type="button" class="simpad-state-btn" data-state="show"><?php esc_html_e('Show', 'simplify-admin-menus'); ?></button>
                                            </div>
                                            <input type="hidden" class="simpad-override-input" data-item-id="<?php echo esc_attr($item['id']); ?>" value="inherit" disabled>
                                            <span class="simpad-role-hint" hidden><?php esc_html_e('Hidden by role', 'simplify-admin-menus'); ?></span>
                                        </div>
                                        <?php if (!empty($item['children'])) : ?>
                                            <div class="simpad-submenu-items">
                                                <?php foreach ($item['children'] as $child) : ?>
                                                    <?php $renderAdminBarItem($child); ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                };

                                if (empty($adminBarItems)) : ?>
                                    <p><?php esc_html_e('No admin bar items found.', 'simplify-admin-menus'); ?></p>
                                <?php else :
                                    foreach ($adminBarItems as $item) :
                                        $renderAdminBarItem($item);
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="simpad-save-box">
                    <?php submit_button(esc_html__('Save Settings', 'simplify-admin-menus'), 'primary', 'submit', false); ?>
                </div>
            </div>
        </div>
    </form>
</div>
