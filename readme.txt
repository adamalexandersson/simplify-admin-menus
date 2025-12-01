=== Simplify Admin Menus ===
Contributors: adamalexandersson
Tags: admin, adminbar, simplify, clean, hide
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.3.2
Requires PHP: 7.4
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Simplify your WordPress admin interface by customizing menu items and admin bar elements per user role.

== Description ==

Simplify Admin Menus allows you to customize and streamline your WordPress admin interface by controlling which menu items and admin bar elements are visible to different user roles. This helps create a cleaner, more focused admin experience for your users.

Key Features:

* Role-based menu item visibility control
* Customizable admin bar elements per role
* Simple and intuitive interface
* Improves admin workflow efficiency

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/simplify-admin-menus` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Simplify Admin Menus screen to configure the plugin
4. Configure visibility settings for each user role as needed

= Development =

The source code for this plugin is available on GitHub: https://github.com/adamalexandersson/simplify-admin-menus

== Build Tools ==

This plugin uses modern build tools to compile and optimize assets. To set up the development environment:

1. Clone the repository:
   `git clone https://github.com/adamalexandersson/simplify-admin-menus.git`

2. Install dependencies:
   `npm install`

3. Available build commands:
   * `npm run build` - Build production assets
   * `npm run dev` - Start development server with hot reloading
   * `composer install` - Install PHP dependencies

The plugin uses:
* Vite for asset bundling and development server
* SCSS for styling
* Composer for PHP dependency management and PSR-4 autoloading

== Frequently Asked Questions ==

= Can I control menu items for specific user roles? =

Yes, you can customize which menu items are visible for each user role independently.

= Will this affect the front-end of my site? =

No, this plugin only modifies the admin interface and admin bar. It does not affect your website's front-end appearance.

== Screenshots ==

1. Main settings interface
2. Role-specific menu configuration
3. Admin bar customization options

== Changelog ==

= 1.3.2 =
* Tested with Wordpress 6.9

= 1.3.1 =
* General improvements 

= 1.3.0 =
* Added support for user specific settings 
* Improved security and code quality for WordPress plugin repository
* Renamed plugin to Simplify Admin Menus

= 1.2.2 =
* Improved security and code quality for WordPress plugin repository

= 1.2.1 =
* Minor changes and improvements
* Translations updates

= 1.2.0 =
* Renamed the plugin to Simplify Admin
* Added support for wordpress color schemes

= 1.1.0 =
* Added support for all admin bar items
* Added composer and rewrite of the code with PSR-12 and PSR-4 autoloading
* Added translations support
* Rewrited the javascript from jQuery to Vanilla javacript and updated the CSS to SCSS
* Added Vite build system for asset management

= 1.0.0 =
* Initial release
* Basic functionality for hiding admin menu items
* Role-based access control
* Admin interface for managing settings