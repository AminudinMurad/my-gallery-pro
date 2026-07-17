<?php
/**
 * Main plugin coordinator.
 *
 * @package MyGalleryPro
 */

namespace MyGalleryPro;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin services after WordPress has loaded all plugins.
 */
final class Plugin {

	/**
	 * Boot the plugin.
	 *
	 * Feature services should register their hooks from this method. Keeping the
	 * bootstrap centralized makes load order explicit and straightforward to test.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( GalleryPostType::class, 'register' ) );
		AdminPage::boot();
		GalleryShortcode::boot();

		/**
		 * Fires after MY Gallery PRO has loaded.
		 *
		 * @since 0.1.0
		 */
		do_action( 'my_gallery_pro_loaded' );
	}
}
