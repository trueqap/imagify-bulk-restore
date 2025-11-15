# Imagify Bulk Restore

**Contributors:** trueqap
**Tags:** imagify, bulk restore, image optimization, wordpress
**Requires at least:** 5.3
**Tested up to:** 6.4
**Requires PHP:** 7.4
**Stable tag:** 1.0.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Bulk restore all optimized images from Imagify in one click. Restore your entire Media Library and Custom Folders to their original state.

## Description

**Imagify Bulk Restore** is a powerful companion plugin for Imagify that allows you to restore ALL your optimized images back to their original state with a single click. While Imagify allows you to restore images one by one, this plugin enables batch restoration of your entire Media Library and Custom Folders.

### Features

✅ **Bulk Restore Media Library** - Restore all optimized images in your WordPress Media Library at once
✅ **Bulk Restore Custom Folders** - Restore all optimized files in your custom folders
✅ **Restore Everything** - One-click restore of both Media Library and Custom Folders
✅ **Real-time Progress Tracking** - Monitor restoration progress with live updates
✅ **Detailed Statistics** - View optimization statistics before restoring
✅ **Safe & Reliable** - Uses WordPress Action Scheduler for reliable background processing
✅ **Imagify-style Design** - Seamlessly integrated UI matching Imagify's design patterns
✅ **Network Multisite Support** - Works with WordPress Multisite installations
✅ **WP-CLI Support** - Full command-line interface for automation and server management

### How It Works

1. **Install & Activate** - Requires Imagify plugin to be installed and active
2. **Navigate to Admin Page** - Go to Media → Bulk Restore
3. **View Statistics** - See how many images can be restored and space savings
4. **Choose Restore Option** - Select Media Library, Custom Folders, or Everything
5. **Confirm & Restore** - Confirm the action and watch the progress in real-time
6. **Complete** - All images are restored to their original, unoptimized state

### Requirements

- WordPress 5.3 or higher
- PHP 7.4 or higher
- **Imagify plugin (required)** - Must be installed and activated
- Backup option enabled in Imagify settings
- At least one optimized image with available backup

### Technical Details

This plugin follows Imagify's architectural patterns:

- **Action Scheduler Integration** - Uses WordPress Action Scheduler for reliable async processing
- **Context System** - Supports both WP Media Library and Custom Folders contexts
- **Progress Tracking** - Real-time progress monitoring via AJAX
- **Transient-based State** - Uses WordPress transients for tracking restore operations
- **Hook Integration** - Integrates with Imagify's hooks and filters
- **Singleton Pattern** - Follows Imagify's instance getter pattern

### Important Notes

⚠️ **Warning:** Restoring images will remove all Imagify optimizations and restore original files. Your images will return to their pre-optimization file sizes. This action cannot be automatically undone.

💡 **Tip:** Before restoring, make sure you really need to restore all images. Consider if you could re-optimize with different settings instead.

## Installation

### From WordPress Admin

1. Go to **Plugins → Add New**
2. Click **Upload Plugin**
3. Choose the `imagify-bulk-restore.zip` file
4. Click **Install Now**
5. Click **Activate Plugin**

### Manual Installation

1. Upload the `imagify-bulk-restore` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **Media → Bulk Restore**

### Requirements Check

After activation, the plugin will check if Imagify is installed and active. If not, you'll see an error notice and the plugin will be deactivated automatically.

## WP-CLI Usage

The plugin provides full WP-CLI support for server-side operations and automation.

### Available Commands

**Show Statistics**
```bash
# Display restoration statistics
wp imagify-restore stats

# Output as JSON
wp imagify-restore stats --format=json

# Output as CSV
wp imagify-restore stats --format=csv
```

**Restore Media Library**
```bash
# Restore all Media Library images (with confirmation)
wp imagify-restore media

# Skip confirmation prompt
wp imagify-restore media --yes

# Preview what would be restored (dry run)
wp imagify-restore media --dry-run
```

**Restore Custom Folders**
```bash
# Restore all Custom Folders files (with confirmation)
wp imagify-restore folders

# Skip confirmation prompt
wp imagify-restore folders --yes

# Preview what would be restored (dry run)
wp imagify-restore folders --dry-run
```

**Restore Everything**
```bash
# Restore both Media Library and Custom Folders (with confirmation)
wp imagify-restore all

# Skip confirmation prompt
wp imagify-restore all --yes

# Preview what would be restored (dry run)
wp imagify-restore all --dry-run
```

**Maintenance Commands**
```bash
# Clear pending restore queue
wp imagify-restore clear-queue

# Clear statistics cache
wp imagify-restore clear-cache
```

### WP-CLI Examples

```bash
# Check what would be restored before actually doing it
wp imagify-restore stats
wp imagify-restore media --dry-run

# Automated restore in a deployment script
wp imagify-restore media --yes

# Restore everything without prompts (useful for cron jobs)
wp imagify-restore all --yes

# Export statistics as JSON for external processing
wp imagify-restore stats --format=json > imagify-stats.json
```

## Frequently Asked Questions

### Does this plugin work without Imagify?

No, this plugin requires Imagify to be installed and activated. It's a companion plugin specifically designed to extend Imagify's functionality.

### Will this delete my optimized images?

Yes, the restore process will replace optimized images with their original backups. The optimized versions will be deleted, and your images will return to their original file sizes.

### Can I restore just a few images instead of all?

This plugin is designed for bulk restoration of all images. If you want to restore individual images, use Imagify's built-in restore button for each image.

### What happens if restore fails?

If a restore operation fails for a specific image, the process will continue with the next image. Failed restorations are logged to the PHP error log if WP_DEBUG is enabled.

### Does this work with WordPress Multisite?

Yes! The plugin fully supports WordPress Multisite installations and adds appropriate admin menus for both single sites and network admin.

### Will this affect my Imagify quota?

No, restoring images does not consume your Imagify API quota. Only optimization operations use quota.

### Can I re-optimize after restoring?

Yes! After restoring, you can use Imagify's Bulk Optimization page to re-optimize your images with different settings if needed.

### How long does bulk restore take?

The time depends on how many images you have. The plugin uses WordPress Action Scheduler to process images in the background, typically handling several images per second.

### What if I accidentally restore everything?

Unfortunately, the restore action cannot be automatically undone. You would need to run Imagify's bulk optimization again to re-optimize your images (which will consume your API quota).

## Screenshots

1. **Bulk Restore Admin Page** - Overview with statistics and restore options
2. **Statistics Display** - Detailed optimization statistics before restoring
3. **Progress Tracking** - Real-time progress bar during restoration
4. **Completion Notice** - Success message after restoration completes

## Changelog

### 1.0.0 - 2025-01-14

**Initial Release**

- ✨ Bulk restore for WordPress Media Library
- ✨ Bulk restore for Imagify Custom Folders
- ✨ "Restore Everything" option for all contexts
- ✨ Real-time progress tracking with AJAX
- ✨ Detailed statistics display
- ✨ Pause & Resume functionality
- ✨ Auto-save queue every 5 seconds
- ✨ WP-CLI support for command-line operations
- ✨ Batch processing (10 images per request)
- ✨ WordPress Multisite support
- ✨ Responsive design matching Imagify styles
- ✨ Comprehensive error handling
- ✨ Unlimited image restoration

## Upgrade Notice

### 1.0.0

Initial release of Imagify Bulk Restore. Adds powerful bulk restore capabilities to your Imagify installation.

## Support

For support, feature requests, or bug reports:

- Check the [GitHub Repository](https://github.com/trueqap/imagify-bulk-restore)

## Credits

Developed to complement the excellent [Imagify](https://imagify.io) image optimization plugin by WP Media.

**Trademark Notice:** Imagify® is a registered trademark of WP Media. This plugin is an independent third-party extension and is not officially endorsed, sponsored, or affiliated with WP Media or Imagify. All product names, trademarks, and registered trademarks are property of their respective owners.

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

**Made with ❤️ for the WordPress community**
