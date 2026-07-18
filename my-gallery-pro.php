<?php
/**
 * Plugin Name:       MY Gallery PRO
 * Plugin URI:        https://github.com/AminudinMurad/my-gallery-pro
 * Description:       Build fast, responsive, and accessible photo galleries in WordPress.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Aminudin Murad
 * Author URI:        https://github.com/AminudinMurad
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       my-gallery-pro
 * Update URI:        https://github.com/AminudinMurad/my-gallery-pro
 *
 * @package MyGalleryPro
 */

defined( 'ABSPATH' ) || exit;

define( 'MY_GALLERY_PRO_VERSION', '1.0.0' );
define( 'MY_GALLERY_PRO_NAME', 'MY Gallery PRO' );
define( 'MY_GALLERY_PRO_FILE', __FILE__ );
define( 'MY_GALLERY_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_GALLERY_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'MY_GALLERY_PRO_BASENAME', plugin_basename( __FILE__ ) );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'MY Gallery PRO requires PHP 7.4 or newer.', 'my-gallery-pro' )
			);
		}
	);

	return;
}

require_once MY_GALLERY_PRO_PATH . 'src/Autoloader.php';

MyGalleryPro\Autoloader::register();

add_action( 'plugins_loaded', array( MyGalleryPro\Plugin::class, 'boot' ) );
