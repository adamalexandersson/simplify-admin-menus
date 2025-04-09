# Simplify Admin Menus

A WordPress plugin that provides granular control over which admin menu items and admin bar items are visible to different user roles.

## Description

Simplify Admin Menus is a powerful WordPress plugin designed to help administrators customize the WordPress admin interface by controlling the visibility of menu items based on user roles. This enhances security and provides a cleaner, more focused admin experience for different types of users.

## Features

- Selectively hide/show admin menu items for specific user roles
- Control visibility of admin bar items
- User-friendly interface for managing menu visibility settings
- Role-based access control
- Clean and efficient code implementation
- Compatible with the latest WordPress version

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click on "Upload Plugin" and choose the downloaded zip file
4. Click "Install Now" and then "Activate"

## Development

### Build Process

The plugin uses Vite for asset compilation. Here's how to get started with development:

1. Install dependencies:
   ```bash
   npm install
   ```

2. Development mode with hot reload:
   ```bash
   npm run dev
   ```

3. Build for production:
   ```bash
   npm run build
   ```

The build process will:
- Compile and bundle JavaScript files
- Process SCSS files to CSS
- Generate a manifest file for asset versioning
- Output optimized files to the `dist` directory

### Build Output Structure

After building, the following files will be generated in the `dist` directory:
- `js/` - Contains compiled JavaScript files with hash-based versioning
- `css/` - Contains compiled CSS files with hash-based versioning
- `.vite/manifest.json` - Contains the mapping of source files to their hashed versions

## Usage

1. Navigate to Settings > Simplify Admin Menus in your WordPress admin panel
2. Select the user role you want to configure from the dropdown menu
3. Check/uncheck the menu items you want to hide/show for that role
4. Save your changes
5. Repeat for other user roles as needed

## Translations

The plugin comes with support for multiple languages. Here's how to work with translations:

### Updating Translation Template

To update the POT (template) file when new strings are added to the plugin:

```bash
wp i18n make-pot . resources/languages/simplify-admin-menus.pot
```

### Adding a New Translation

1. Copy the template file to create a new PO file for your language:
   ```bash
   cp resources/languages/simplify-admin-menus.pot resources/languages/simplify-admin-menus-{language_code}.po
   ```
   Replace {language_code} with your language code (e.g., sv_SE for Swedish)

2. Edit the PO file using a translation editor like Poedit
3. Save the file - this will automatically generate the required .mo file

### Available Translations

- Swedish (sv_SE)

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Node.js 16.0 or higher (for development)
- npm 8.0 or higher (for development)

## Support

For support questions, feature requests, or bug reports, please create an issue in the plugin's repository.

## License

This plugin is licensed under the GPL v2 or later.

## Author

Created by Adam Alexandersson

## Changelog

### 1.0.0
- Initial release
- Basic functionality for hiding admin menu items
- Role-based access control
- Admin interface for managing settings

### 1.1.0
- Added support for all admin bar items
- Added composer and rewrite of the code with PSR-12 and PSR-4 autoloading
- Added translations support
- Rewrited the javascript from jQuery to Vanilla javacript and updated the CSS to SCSS
- Added Vite build system for asset management 

### 1.2.0
- Renamed the plugin to Simplify Admin
- Added support for wordpress color schemes

### 1.2.1
- Minor changes and improvements
- Translations updates

### 1.2.2
- Improved security and code quality for WordPress plugin repository