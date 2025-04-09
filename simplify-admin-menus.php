<?php
/**
 * Plugin Name: Simplify Admin Menus
 * Plugin URI: 
 * Description: WordPress plugin that simplifies the admin panel menus and admin bar for user roles or specific users.
 * Version: 1.3.0
 * Author: Adam Alexandersson
 * Author URI: 
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: simplify-admin-menus
 * Domain Path: /resources/languages
 */

namespace SimplifyAdminMenus;

use function add_action;
use function load_plugin_textdomain;
use function plugin_basename;
use function plugin_dir_path;
use function plugin_dir_url;
use function wp_die;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Make sure we have access to WordPress functions
if (!function_exists('add_action')) {
    exit;
}

// Load Composer's autoloader
$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    wp_die('Please run composer install to install the necessary dependencies.');
}

class SimplifyAdminMenus {
    private static ?SimplifyAdminMenus $instance = null;
    private string $pluginPath;
    private string $pluginUrl;
    private AdminMenuSettings $menuSettings;
    private AdminBarSettings $adminBarSettings;
    private AdminSettings $adminSettings;

    public static function getInstance(): SimplifyAdminMenus {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->pluginPath = plugin_dir_path(__FILE__);
        $this->pluginUrl = plugin_dir_url(__FILE__);

        // Load text domain for translations
        add_action('init', [$this, 'loadPluginTextdomain']);

        // Initialize components
        $this->menuSettings = new AdminMenuSettings();
        $this->adminBarSettings = new AdminBarSettings();
        $this->adminSettings = new AdminSettings(
            $this->pluginPath,
            $this->pluginUrl,
            $this->menuSettings,
            $this->adminBarSettings
        );
    }

    /**
     * Load plugin translations
     */
    public function loadPluginTextdomain(): void {
        load_plugin_textdomain(
            'simplify-admin-menus',
            false,
            dirname(plugin_basename(__FILE__)) . '/resources/languages'
        );
    }
}

// Initialize the plugin
SimplifyAdminMenus::getInstance(); 