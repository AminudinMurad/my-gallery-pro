<?php
/**
 * Dependency-free MY Gallery PRO behavior tests.
 *
 * @package MyGalleryPro
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * Fail with an actionable message.
 *
 * @param string $message Failure message.
 * @return void
 */
function mygallery_test_fail( string $message ): void {
	fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Assert a condition.
 *
 * @param bool   $condition Condition under test.
 * @param string $message   Failure message.
 * @return void
 */
function mygallery_test_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		mygallery_test_fail( $message );
	}
}

/**
 * Assert text contains a substring.
 *
 * @param string $needle  Expected substring.
 * @param string $haystack Actual text.
 * @param string $message Failure message.
 * @return void
 */
function mygallery_test_contains( string $needle, string $haystack, string $message ): void {
	mygallery_test_assert( false !== strpos( $haystack, $needle ), $message );
}

/**
 * Run a mutating handler and capture its redirect termination.
 *
 * @param callable $callback Handler under test.
 * @param string   $message  Failure message when no redirect occurs.
 * @return string
 */
function mygallery_test_redirect( callable $callback, string $message ): string {
	try {
		call_user_func( $callback );
	} catch ( MyGalleryTestRedirect $exception ) {
		return $exception->getMessage();
	}

	mygallery_test_fail( $message );
	return '';
}

/**
 * Return the first captured hook callback.
 *
 * @param string $type Hook collection type.
 * @param string $hook Hook name.
 * @return callable
 */
function mygallery_test_hook( string $type, string $hook ): callable {
	global $mygallery_test_state;

	if ( empty( $mygallery_test_state[ $type ][ $hook ][0]['callback'] ) ) {
		mygallery_test_fail( 'Missing callback for ' . $hook . '.' );
	}

	return $mygallery_test_state[ $type ][ $hook ][0]['callback'];
}

require dirname( __DIR__ ) . '/my-gallery-pro.php';

global $mygallery_test_state;

mygallery_test_assert( defined( 'MY_GALLERY_PRO_VERSION' ), 'The version constant is missing.' );
mygallery_test_assert( '1.0.0' === MY_GALLERY_PRO_VERSION, 'The version constant is not 1.0.0.' );
mygallery_test_assert( 'MY Gallery PRO' === MY_GALLERY_PRO_NAME, 'The plugin name constant is incorrect.' );

$plugin_source  = (string) file_get_contents( dirname( __DIR__ ) . '/my-gallery-pro.php' );
$readme_source  = (string) file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
$license_source = (string) file_get_contents( dirname( __DIR__ ) . '/LICENSE' );

mygallery_test_contains( 'Version:           1.0.0', $plugin_source, 'The plugin header version is incorrect.' );
mygallery_test_contains( 'Stable tag: 1.0.0', $readme_source, 'The readme stable tag is incorrect.' );
mygallery_test_contains( 'License:           MIT', $plugin_source, 'The plugin header must declare the MIT license.' );
mygallery_test_contains( 'License: MIT', $readme_source, 'The readme must declare the MIT license.' );
mygallery_test_assert( 0 === strpos( $license_source, 'MIT License' ), 'The MIT license file is incorrect.' );

call_user_func( mygallery_test_hook( 'actions', 'plugins_loaded' ) );

mygallery_test_assert(
	array( 'my_gallery_pro_loaded' ) === $mygallery_test_state['fired_actions'],
	'The plugin loaded action should fire exactly once.'
);
mygallery_test_assert( isset( $mygallery_test_state['actions']['init'] ), 'Gallery registration is not hooked to init.' );
mygallery_test_assert( isset( $mygallery_test_state['shortcodes']['mygallery'] ), 'The gallery shortcode is not registered.' );
mygallery_test_assert( isset( $mygallery_test_state['actions']['admin_post_my_gallery_pro_save_gallery'] ), 'The secure save handler is missing.' );
mygallery_test_assert( isset( $mygallery_test_state['actions']['admin_post_my_gallery_pro_delete_gallery'] ), 'The secure delete handler is missing.' );
mygallery_test_assert(
	9 === $mygallery_test_state['actions']['admin_menu'][0]['priority'],
	'The parent menu must register early.'
);

call_user_func( mygallery_test_hook( 'actions', 'init' ) );

$post_type = $mygallery_test_state['post_types']['mgp_gallery'] ?? array();
mygallery_test_assert( false === ( $post_type['public'] ?? null ), 'Gallery posts must not be public query objects.' );
mygallery_test_assert( false === ( $post_type['publicly_queryable'] ?? null ), 'Gallery posts must not be directly queryable.' );
mygallery_test_assert( false === ( $post_type['show_in_rest'] ?? null ), 'The private gallery type must not be exposed through REST.' );
mygallery_test_assert( false === ( $post_type['rewrite'] ?? null ), 'The private gallery type must not add rewrite rules.' );
mygallery_test_assert( array( 'title', 'author' ) === ( $post_type['supports'] ?? array() ), 'Gallery post support must stay minimal.' );

$mygallery_test_state['images'] = array(
	101 => array(
		'url'     => 'https://example.test/uploads/one.jpg',
		'title'   => 'First image',
		'alt'     => 'Calm ocean',
		'caption' => 'Ocean caption',
	),
	102 => array(
		'url'     => 'https://example.test/uploads/two.jpg',
		'title'   => 'Second image',
		'alt'     => '',
		'caption' => 'Caption <script>alert(1)</script>',
	),
);

$sanitized_ids = MyGalleryPro\GalleryPostType::sanitize_image_ids( array( 102, '101', 102, 0, -999, array( 101 ) ) );
mygallery_test_assert( array( 102, 101 ) === $sanitized_ids, 'Image IDs must be positive, unique, valid, and order-preserving.' );
mygallery_test_assert( 200 === MyGalleryPro\GalleryPostType::MAX_IMAGES, 'The server and editor image limit must remain explicit.' );
mygallery_test_assert( array() === MyGalleryPro\GalleryPostType::sanitize_image_ids( array( -101 ) ), 'Negative attachment IDs must not be converted to positive IDs.' );
mygallery_test_assert( array() === MyGalleryPro\GalleryPostType::sanitize_image_ids( new stdClass() ), 'Non-array image input must be rejected.' );

$settings = MyGalleryPro\GalleryPostType::sanitize_settings( 'script', 9, 9, 99, 'script', true, false );
mygallery_test_assert(
	array( 'layout' => 'grid', 'columns' => 3, 'mobile_columns' => 1, 'gap' => 16, 'aspect' => 'square', 'lightbox' => true, 'captions' => false ) === $settings,
	'Invalid layout values must fall back to safe defaults.'
);

$mygallery_test_state['posts'][42] = (object) array(
	'ID'           => 42,
	'post_type'    => 'mgp_gallery',
	'post_status'  => 'publish',
	'post_title'   => 'Summer <script>alert(1)</script>',
	'post_content' => '',
	'post_author'  => 7,
);
MyGalleryPro\GalleryPostType::save_meta(
	42,
	array( 102, 101, 102, 999 ),
	array(
		'layout'         => 'grid',
		'columns'        => 4,
		'mobile_columns' => 2,
		'gap'            => 24,
		'aspect'         => 'landscape',
		'lightbox'       => true,
		'captions'       => true,
	)
);
mygallery_test_assert(
	array( 102, 101 ) === $mygallery_test_state['meta'][42][MyGalleryPro\GalleryPostType::META_IMAGE_IDS],
	'Saved image IDs must be validated and deduplicated.'
);
mygallery_test_assert(
	array( 'layout' => 'grid', 'columns' => 4, 'mobile_columns' => 2, 'gap' => 24, 'aspect' => 'landscape', 'lightbox' => true, 'captions' => true ) === MyGalleryPro\GalleryPostType::get_settings( 42 ),
	'Saved display settings are incorrect.'
);

call_user_func( mygallery_test_hook( 'actions', 'admin_menu' ) );

$menu_page = $mygallery_test_state['menu_pages']['my-gallery-pro'] ?? array();
mygallery_test_contains( 'Version v1.0.0', mygallery_test_admin_page_title( 'my-gallery-pro' ), 'The effective overview browser title must include version v1.0.0.' );
mygallery_test_assert( 'upload_files' === ( $menu_page['capability'] ?? '' ), 'The gallery menu capability is incorrect.' );
mygallery_test_assert( isset( $mygallery_test_state['submenu_pages']['my-gallery-pro-new'] ), 'The Add Gallery submenu is missing.' );
mygallery_test_contains( 'Add Gallery — MY Gallery PRO v1.0.0', mygallery_test_admin_page_title( 'my-gallery-pro-new' ), 'The add-gallery browser title is incorrect.' );

$mygallery_test_state['posts'][43] = (object) array(
	'ID'           => 43,
	'post_type'    => 'mgp_gallery',
	'post_status'  => 'private',
	'post_title'   => 'Another author private gallery',
	'post_content' => '',
	'post_author'  => 8,
);
$mygallery_test_state['denied_caps']['edit_others_posts'] = true;
ob_start();
call_user_func( $menu_page['callback'] );
$overview_output = (string) ob_get_clean();
mygallery_test_contains( '<h1 id="mgp-page-title">Galleries</h1>', $overview_output, 'The WordPress-style Galleries heading is missing.' );
mygallery_test_contains( 'mgp-version-inline', $overview_output, 'The overview must display the plugin version chip.' );
mygallery_test_contains( MY_GALLERY_PRO_NAME . ' v' . MY_GALLERY_PRO_VERSION, $overview_output, 'The overview version chip must show the product name and v-prefixed version.' );
mygallery_test_contains( 'Add New Gallery', $overview_output, 'The gallery list needs a clear add-new action.' );
mygallery_test_contains( 'widefat fixed striped mgp-gallery-table', $overview_output, 'The gallery overview table is missing.' );
mygallery_test_contains( '4 desktop / 2 mobile', $overview_output, 'The gallery row must summarize its basic layout settings.' );
mygallery_test_contains( '[mygallery id=&quot;42&quot;]', $overview_output, 'The gallery shortcode is missing from the overview.' );
mygallery_test_contains( 'class="mgp-shortcode-copy"', $overview_output, 'The overview shortcode must be a one-click copy control.' );
mygallery_test_contains( 'data-shortcode=', $overview_output, 'The overview copy control must expose the exact shortcode to the admin script.' );
mygallery_test_assert( false === strpos( $overview_output, '<script>alert(1)</script>' ), 'Stored gallery titles must be escaped in the overview.' );
mygallery_test_assert( false === strpos( $overview_output, 'Another author private gallery' ), 'Authors must not see another author\'s private galleries.' );
mygallery_test_assert( 7 === ( $mygallery_test_state['last_get_posts_args']['author'] ?? 0 ), 'The overview query must be scoped to the current author.' );
unset( $mygallery_test_state['denied_caps']['edit_others_posts'] );

$_GET['gallery_id'] = '42';
$mygallery_test_state['menu_pages']    = array();
$mygallery_test_state['submenu_pages'] = array();
MyGalleryPro\AdminPage::register();
$editor_page = $mygallery_test_state['submenu_pages']['my-gallery-pro-new'];
mygallery_test_contains( 'Edit Gallery — MY Gallery PRO v1.0.0', mygallery_test_admin_page_title( 'my-gallery-pro-new' ), 'The edit-gallery browser title is incorrect.' );
ob_start();
call_user_func( $editor_page['callback'] );
$editor_output = (string) ob_get_clean();
mygallery_test_contains( MY_GALLERY_PRO_NAME . ' v' . MY_GALLERY_PRO_VERSION, $editor_output, 'The editor version chip must show the product name and v-prefixed version.' );
mygallery_test_contains( 'Add from Media Library', $editor_output, 'The Media Library selector is missing.' );
mygallery_test_contains( 'placeholder="Gallery title"', $editor_output, 'The familiar gallery title field is missing.' );
mygallery_test_contains( 'Manage Images', $editor_output, 'The image-management tab is missing.' );
mygallery_test_contains( 'Gallery Preview', $editor_output, 'The gallery-preview tab is missing.' );
mygallery_test_contains( 'id="my-gallery-pro-preview"', $editor_output, 'The basic gallery preview surface is missing.' );
mygallery_test_contains( 'Gallery Settings', $editor_output, 'The basic gallery settings panel is missing.' );
mygallery_test_contains( 'Gallery Shortcode', $editor_output, 'The shortcode panel is missing from the editor.' );
mygallery_test_contains( 'name="my_gallery_pro_image_ids[]" value="102"', $editor_output, 'The first ordered image input is missing.' );
mygallery_test_assert(
	strpos( $editor_output, 'value="102"' ) < strpos( $editor_output, 'value="101"' ),
	'The editor must preserve saved image order.'
);
mygallery_test_contains( '[mygallery id=&quot;42&quot;]', $editor_output, 'The editor must expose the saved gallery shortcode.' );
mygallery_test_contains( 'class="mgp-shortcode-copy"', $editor_output, 'The editor shortcode must be a one-click copy control.' );
mygallery_test_contains( 'aria-live="polite"', $editor_output, 'Shortcode copy feedback must be announced accessibly.' );
mygallery_test_contains( 'name="gallery_layout"', $editor_output, 'The layout style control is missing from the editor.' );
mygallery_test_contains( 'value="masonry"', $editor_output, 'The masonry layout option is missing from the editor.' );
mygallery_test_contains( 'name="gallery_mobile_columns"', $editor_output, 'The mobile column control is missing from the editor.' );

$mygallery_test_state['enqueued_styles']  = array();
$mygallery_test_state['enqueued_scripts'] = array();
$mygallery_test_state['media_calls']      = array();
MyGalleryPro\AdminPage::enqueue_assets( 'posts_page_unrelated' );
mygallery_test_assert( empty( $mygallery_test_state['enqueued_styles'] ), 'Admin assets loaded on an unrelated screen.' );
MyGalleryPro\AdminPage::enqueue_assets( $editor_page['hook'] );
mygallery_test_assert( isset( $mygallery_test_state['enqueued_styles']['my-gallery-pro-admin'] ), 'Editor CSS was not loaded.' );
mygallery_test_assert( isset( $mygallery_test_state['enqueued_scripts']['my-gallery-pro-admin'] ), 'Editor JavaScript was not loaded.' );
mygallery_test_assert( array( 'post' => 42 ) === $mygallery_test_state['media_calls'][0], 'The Media Library was not scoped to the edited gallery.' );

$mygallery_test_state['media_calls'] = array();
$_GET['gallery_id']                         = '999999';
MyGalleryPro\AdminPage::enqueue_assets( $editor_page['hook'] );
mygallery_test_assert( array() === $mygallery_test_state['media_calls'][0], 'A nonexistent gallery ID must not be passed to wp_enqueue_media().' );

$mygallery_test_state['posts'][88] = (object) array(
	'ID'          => 88,
	'post_type'   => 'post',
	'post_status' => 'publish',
	'post_title'  => 'Not a gallery',
	'post_author' => 7,
);
$mygallery_test_state['media_calls'] = array();
$_GET['gallery_id']                         = '88';
MyGalleryPro\AdminPage::enqueue_assets( $editor_page['hook'] );
mygallery_test_assert( array() === $mygallery_test_state['media_calls'][0], 'A wrong-type post ID must not be passed to wp_enqueue_media().' );

$mygallery_test_state['media_calls']                          = array();
$mygallery_test_state['denied_objects']['edit_post'][42] = true;
$_GET['gallery_id']                                                  = '42';
MyGalleryPro\AdminPage::enqueue_assets( $editor_page['hook'] );
mygallery_test_assert( array() === $mygallery_test_state['media_calls'][0], 'An unauthorized gallery ID must not be passed to wp_enqueue_media().' );
unset( $mygallery_test_state['denied_objects']['edit_post'][42] );

$mygallery_test_state['enqueued_scripts'] = array();
$mygallery_test_state['media_calls']       = array();
MyGalleryPro\AdminPage::enqueue_assets( $menu_page['hook'] );
mygallery_test_assert( isset( $mygallery_test_state['enqueued_scripts']['my-gallery-pro-admin'] ), 'Overview JavaScript was not loaded for the trash confirmation.' );
mygallery_test_assert( empty( $mygallery_test_state['media_calls'] ), 'The Media Library must not load on the overview.' );

$action_hook = 'plugin_action_links_my-gallery-pro/my-gallery-pro.php';
$action_link = mygallery_test_hook( 'filters', $action_hook );
$links       = call_user_func( $action_link, array() );
mygallery_test_contains( 'Manage Galleries', $links[0] ?? '', 'The Plugins-screen management link is missing.' );
$mygallery_test_state['allowed'] = false;
mygallery_test_assert( array( 'existing' ) === call_user_func( $action_link, array( 'existing' ) ), 'Unauthorized users must not receive the management link.' );
$mygallery_test_state['allowed']                       = true;
$mygallery_test_state['denied_caps']['publish_posts'] = true;
mygallery_test_assert( array( 'existing' ) === call_user_func( $action_link, array( 'existing' ) ), 'Users who cannot publish galleries must not receive the management link.' );
unset( $mygallery_test_state['denied_caps']['publish_posts'] );

$mygallery_test_state['allowed'] = false;
try {
	call_user_func( $menu_page['callback'] );
	mygallery_test_fail( 'The admin page rendered without upload permission.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( 'permission', $exception->getMessage(), 'The authorization failure is not actionable.' );
}
$mygallery_test_state['allowed'] = true;

$_SERVER['REQUEST_METHOD'] = 'GET';
try {
	MyGalleryPro\AdminPage::handle_save();
	mygallery_test_fail( 'The mutation handler accepted a non-POST request.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( 'POST', $exception->getMessage(), 'The non-POST rejection is incorrect.' );
}
$_SERVER['REQUEST_METHOD'] = 'POST';
unset( $_GET['gallery_id'] );

$valid_save_post = array(
	'action'                             => 'my_gallery_pro_save_gallery',
	'gallery_id'                         => '0',
	'gallery_title'                      => 'Created Gallery',
	'my_gallery_pro_image_ids'      => array( '101', '102' ),
	'gallery_layout'                     => 'grid',
	'gallery_columns'                    => '3',
	'gallery_mobile_columns'             => '1',
	'gallery_gap'                        => '16',
	'gallery_aspect'                     => 'square',
	'gallery_lightbox'                   => '1',
	'gallery_captions'                   => '1',
	'my_gallery_pro_nonce'          => 'test-nonce',
);

$_POST                                      = $valid_save_post;
$mygallery_test_state['nonce_valid'] = false;
try {
	MyGalleryPro\AdminPage::handle_save();
	mygallery_test_fail( 'The save handler accepted an invalid nonce.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( 'nonce', strtolower( $exception->getMessage() ), 'The nonce failure is not actionable.' );
}
$mygallery_test_state['nonce_valid'] = true;

$_POST['gallery_id'] = '88';
try {
	MyGalleryPro\AdminPage::handle_save();
	mygallery_test_fail( 'The save handler updated a non-gallery post.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( 'permission', $exception->getMessage(), 'The wrong-type update rejection is incorrect.' );
}

$_POST                  = $valid_save_post;
$_POST['gallery_title'] = str_repeat( 'a', 161 );
try {
	MyGalleryPro\AdminPage::handle_save();
	mygallery_test_fail( 'The save handler accepted an overlong gallery title.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( '160', $exception->getMessage(), 'The title length rejection is incorrect.' );
}

$_POST                                              = $valid_save_post;
$_POST['my_gallery_pro_image_ids']             = array( '101' );
$mygallery_test_state['images'][101]['status'] = 'private';
$mygallery_test_state['redirect_throws']       = true;
$private_media_redirect = mygallery_test_redirect(
	array( MyGalleryPro\AdminPage::class, 'handle_save' ),
	'The save handler did not retain an authorized non-public attachment.'
);
$private_media_gallery_id = $mygallery_test_state['next_post_id'];
mygallery_test_contains( 'saved=1', $private_media_redirect, 'The retained-media save did not redirect successfully.' );
mygallery_test_assert(
	array( 101 ) === $mygallery_test_state['meta'][ $private_media_gallery_id ][MyGalleryPro\GalleryPostType::META_IMAGE_IDS],
	'Authorized draft/private-parent media should remain stored while a site is being built.'
);
$_GET['gallery_id'] = (string) $private_media_gallery_id;
ob_start();
call_user_func( $editor_page['callback'] );
$private_media_editor = (string) ob_get_clean();
mygallery_test_contains( 'Hidden until published', $private_media_editor, 'The editor must explain why retained non-public media is not on the public gallery.' );
unset( $_GET['gallery_id'] );
$mygallery_test_state['images'][101]['status'] = 'publish';
$mygallery_test_state['redirect_throws']       = false;

$_POST                                                      = $valid_save_post;
$_POST['my_gallery_pro_image_ids']                     = array( '101' );
$mygallery_test_state['denied_objects']['edit_post'][101] = true;
try {
	MyGalleryPro\AdminPage::handle_save();
	mygallery_test_fail( 'The save handler accepted an unauthorized attachment.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( 'valid Media Library image', $exception->getMessage(), 'The unauthorized attachment rejection is incorrect.' );
}
unset( $mygallery_test_state['denied_objects']['edit_post'][101] );

$_POST                                             = $valid_save_post;
$mygallery_test_state['redirect_throws']     = true;
$create_redirect = mygallery_test_redirect(
	array( MyGalleryPro\AdminPage::class, 'handle_save' ),
	'The successful create handler did not redirect.'
);
$created_id = $mygallery_test_state['next_post_id'];
mygallery_test_contains( 'saved=1', $create_redirect, 'The create redirect is missing its success flag.' );
mygallery_test_assert( 'mgp_gallery' === $mygallery_test_state['posts'][ $created_id ]->post_type, 'The create handler stored the wrong post type.' );
mygallery_test_assert( 'publish' === $mygallery_test_state['posts'][ $created_id ]->post_status, 'The create handler did not publish the gallery.' );
mygallery_test_assert( 7 === $mygallery_test_state['posts'][ $created_id ]->post_author, 'The create handler stored the wrong author.' );
mygallery_test_assert(
	array( 101, 102 ) === $mygallery_test_state['meta'][ $created_id ][MyGalleryPro\GalleryPostType::META_IMAGE_IDS],
	'The create handler did not persist the selected image order.'
);

$_POST                            = $valid_save_post;
$_POST['gallery_id']              = '42';
$_POST['gallery_title']           = 'Updated Gallery';
$_POST['my_gallery_pro_image_ids'] = array( '102', '101' );
$_POST['gallery_layout']          = 'masonry';
$_POST['gallery_columns']         = '4';
$_POST['gallery_mobile_columns']  = '2';
$_POST['gallery_gap']             = '24';
$_POST['gallery_aspect']          = 'landscape';
$update_redirect = mygallery_test_redirect(
	array( MyGalleryPro\AdminPage::class, 'handle_save' ),
	'The successful update handler did not redirect.'
);
mygallery_test_contains( 'saved=1', $update_redirect, 'The update redirect is missing its success flag.' );
mygallery_test_assert( 'Updated Gallery' === $mygallery_test_state['posts'][42]->post_title, 'The update handler did not save the title.' );

$_POST = array(
	'action'                    => 'my_gallery_pro_delete_gallery',
	'gallery_id'                => '42',
	'my_gallery_pro_nonce' => 'test-nonce',
);
$delete_redirect = mygallery_test_redirect(
	array( MyGalleryPro\AdminPage::class, 'handle_delete' ),
	'The successful delete handler did not redirect.'
);
mygallery_test_contains( 'deleted=1', $delete_redirect, 'The delete redirect is missing its success flag.' );
mygallery_test_assert( 'trash' === $mygallery_test_state['posts'][42]->post_status, 'The delete handler did not trash the gallery.' );

$_GET['gallery_id'] = '42';
try {
	call_user_func( $editor_page['callback'] );
	mygallery_test_fail( 'The editor rendered a trashed gallery.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( 'trash', strtolower( $exception->getMessage() ), 'The trashed-gallery editor refusal is not actionable.' );
}

$mygallery_test_state['media_calls'] = array();
MyGalleryPro\AdminPage::enqueue_assets( $editor_page['hook'] );
mygallery_test_assert( array() === $mygallery_test_state['media_calls'][0], 'A trashed gallery ID must not be passed to wp_enqueue_media().' );
unset( $_GET['gallery_id'] );

$_POST               = $valid_save_post;
$_POST['gallery_id'] = '42';
try {
	MyGalleryPro\AdminPage::handle_save();
	mygallery_test_fail( 'The save handler republished a trashed gallery.' );
} catch ( RuntimeException $exception ) {
	mygallery_test_contains( 'trash', strtolower( $exception->getMessage() ), 'The trashed-gallery save refusal is not actionable.' );
}
mygallery_test_assert( 'trash' === $mygallery_test_state['posts'][42]->post_status, 'A trashed gallery must stay in the trash after a rejected save.' );

$mygallery_test_state['posts'][42]->post_status = 'publish';
$mygallery_test_state['redirect_throws']        = false;
$_POST                                                = array();

$shortcode = $mygallery_test_state['shortcodes']['mygallery'];
$mygallery_test_state['enqueued_styles']  = array();
$mygallery_test_state['enqueued_scripts'] = array();
mygallery_test_assert( '' === call_user_func( $shortcode, array() ), 'A shortcode without a gallery ID must render nothing.' );
mygallery_test_assert( empty( $mygallery_test_state['enqueued_styles'] ), 'Invalid shortcodes must not enqueue assets.' );

$gallery_output = call_user_func( $shortcode, array( 'id' => '42' ) );
mygallery_test_contains( 'my-gallery-pro-grid is-masonry-layout is-landscape has-4-columns has-2-mobile-columns has-24-gap', $gallery_output, 'The allow-listed gallery layout classes are incorrect.' );

foreach ( array( 'grid', 'masonry' ) as $tested_layout ) {
	foreach ( range( 1, 6 ) as $tested_desktop_columns ) {
		foreach ( range( 1, 3 ) as $tested_mobile_columns ) {
			MyGalleryPro\GalleryPostType::save_meta(
				42,
				array( 102, 101 ),
				array(
					'layout'         => $tested_layout,
					'columns'        => $tested_desktop_columns,
					'mobile_columns' => $tested_mobile_columns,
					'gap'            => 24,
					'aspect'         => 'landscape',
					'lightbox'       => true,
					'captions'       => true,
				)
			);
			$tested_column_output = call_user_func( $shortcode, array( 'id' => 42 ) );
			mygallery_test_contains(
				sprintf( 'is-%1$s-layout is-landscape has-%2$d-columns has-%3$d-mobile-columns', $tested_layout, $tested_desktop_columns, $tested_mobile_columns ),
				$tested_column_output,
				sprintf( '%1$s must emit desktop %2$d and mobile %3$d column classes.', ucfirst( $tested_layout ), $tested_desktop_columns, $tested_mobile_columns )
			);
		}
	}
}

MyGalleryPro\GalleryPostType::save_meta(
	42,
	array( 102, 101 ),
	array(
		'layout'         => 'masonry',
		'columns'        => 4,
		'mobile_columns' => 2,
		'gap'            => 24,
		'aspect'         => 'landscape',
		'lightbox'       => true,
		'captions'       => true,
	)
);
mygallery_test_assert( false === strpos( $gallery_output, 'style=' ), 'The gallery must not emit an inline style attribute.' );
mygallery_test_contains( 'srcset=', $gallery_output, 'Responsive WordPress image markup is missing.' );
mygallery_test_contains( 'data-mgp-viewer', $gallery_output, 'The optional viewer trigger is missing.' );
mygallery_test_contains( 'alt="Calm ocean"', $gallery_output, 'Media Library alt text must remain in the linked image markup.' );
mygallery_test_contains( 'my-gallery-pro-screen-reader-text', $gallery_output, 'Linked images need hidden action and position context.' );
mygallery_test_assert( 0 === preg_match( '/class="my-gallery-pro-link"[^>]*aria-label=/', $gallery_output ), 'Link labels must not override Media Library alt text.' );
mygallery_test_assert(
	strpos( $gallery_output, 'data-image-id="102"' ) < strpos( $gallery_output, 'data-image-id="101"' ),
	'The frontend must preserve gallery image order.'
);
mygallery_test_assert( false === strpos( $gallery_output, '<script>alert(1)</script>' ), 'Stored frontend text must be escaped.' );
mygallery_test_contains( 'Caption &lt;script&gt;alert(1)&lt;/script&gt;', $gallery_output, 'Captions must be escaped.' );
mygallery_test_assert( isset( $mygallery_test_state['enqueued_styles']['my-gallery-pro-frontend'] ), 'Valid gallery output did not enqueue its CSS.' );
mygallery_test_assert( isset( $mygallery_test_state['enqueued_scripts']['my-gallery-pro-frontend'] ), 'Valid gallery output did not enqueue its viewer script.' );

$mygallery_test_state['images'][102]['status'] = 'private';
$public_only_output = call_user_func( $shortcode, array( 'id' => 42 ) );
mygallery_test_assert( false === strpos( $public_only_output, 'data-image-id="102"' ), 'A non-public attachment must not render in a public gallery.' );
mygallery_test_contains( 'data-image-id="101"', $public_only_output, 'Public attachments should remain renderable.' );
$mygallery_test_state['images'][102]['status'] = 'publish';

MyGalleryPro\GalleryPostType::save_meta(
	42,
	array( 102, 101 ),
	array(
		'layout'         => 'grid',
		'columns'        => 4,
		'mobile_columns' => 1,
		'gap'            => 24,
		'aspect'         => 'landscape',
		'lightbox'       => false,
		'captions'       => true,
	)
);
$mygallery_test_state['enqueued_styles']  = array();
$mygallery_test_state['enqueued_scripts'] = array();
$linked_output = call_user_func( $shortcode, array( 'id' => 42 ) );
mygallery_test_assert( false === strpos( $linked_output, 'data-mgp-viewer' ), 'A gallery with the viewer disabled must use plain full-image links.' );
mygallery_test_contains( 'Open full-size image', $linked_output, 'Plain full-image links need accurate screen-reader context.' );
mygallery_test_assert( empty( $mygallery_test_state['enqueued_scripts'] ), 'A gallery with the viewer disabled must not enqueue viewer JavaScript.' );

MyGalleryPro\GalleryPostType::save_meta(
	42,
	array( 102, 101 ),
	array(
		'layout'         => 'masonry',
		'columns'        => 4,
		'mobile_columns' => 2,
		'gap'            => 24,
		'aspect'         => 'landscape',
		'lightbox'       => false,
		'captions'       => true,
	)
);
$mygallery_test_state['enqueued_scripts'] = array();
$masonry_linked_output = call_user_func( $shortcode, array( 'id' => 42 ) );
mygallery_test_assert( false === strpos( $masonry_linked_output, 'data-mgp-viewer' ), 'Masonry must keep ordinary full-image links when the viewer is disabled.' );
mygallery_test_assert( isset( $mygallery_test_state['enqueued_scripts']['my-gallery-pro-frontend'] ), 'Masonry must enqueue its exact-column enhancement even when the viewer is disabled.' );

MyGalleryPro\GalleryPostType::save_meta(
	42,
	array( 102, 101 ),
	array(
		'layout'         => 'masonry',
		'columns'        => 4,
		'mobile_columns' => 2,
		'gap'            => 24,
		'aspect'         => 'landscape',
		'lightbox'       => true,
		'captions'       => true,
	)
);

$second_output = call_user_func( $shortcode, array( 'id' => 42 ) );
mygallery_test_assert( $gallery_output !== $second_output, 'Multiple gallery instances need unique DOM IDs.' );

$mygallery_test_state['posts'][42]->post_status = 'draft';
$mygallery_test_state['enqueued_styles']        = array();
$mygallery_test_state['enqueued_scripts']       = array();
mygallery_test_assert( '' === call_user_func( $shortcode, array( 'id' => 42 ) ), 'Unpublished galleries must not render publicly.' );
mygallery_test_assert( empty( $mygallery_test_state['enqueued_scripts'] ), 'Unpublished galleries must not enqueue assets.' );
$mygallery_test_state['posts'][42]->post_status = 'publish';

$mygallery_test_state['is_singular']  = true;
$mygallery_test_state['current_post'] = (object) array( 'post_content' => 'Before [mygallery id="42"] after' );
call_user_func( mygallery_test_hook( 'actions', 'wp_enqueue_scripts' ) );
MyGalleryPro\GalleryShortcode::maybe_enqueue_for_current_post();
mygallery_test_assert( isset( $mygallery_test_state['enqueued_styles']['my-gallery-pro-frontend'] ), 'Standard shortcode content should enqueue gallery CSS early.' );
mygallery_test_assert( empty( $mygallery_test_state['enqueued_scripts'] ), 'Early shortcode detection should not load the viewer before a gallery enables it.' );

$admin_js    = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
$admin_css   = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.css' );
$frontend_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/frontend.js' );
$frontend_css= (string) file_get_contents( dirname( __DIR__ ) . '/assets/frontend.css' );

mygallery_test_assert( false === strpos( $admin_js, 'innerHTML' ), 'The Media Library UI must not build untrusted innerHTML.' );
mygallery_test_assert( false === strpos( $frontend_js, 'innerHTML' ), 'The viewer must not build untrusted innerHTML.' );
mygallery_test_assert(
	strpos( $admin_js, '.mgp-delete-form' ) < strpos( $admin_js, 'if ( ! $list.length )' ),
	'The trash confirmation must bind before the editor-only early return.'
);
mygallery_test_contains( 'refreshControls', $admin_js, 'Image-order controls must refresh their accessible labels and boundaries.' );
mygallery_test_contains( 'announceMoved', $admin_js, 'Image reorder actions must announce their result.' );
mygallery_test_contains( "multiple: 'add'", $admin_js, 'The Media Library must keep each clicked gallery image selected.' );
mygallery_test_contains( 'selection.length > maxImages', $admin_js, 'The editor must report and enforce the server image limit.' );
mygallery_test_contains( 'refreshPreview', $admin_js, 'The editor must keep the basic gallery preview in sync.' );
mygallery_test_contains( 'layoutPreviewMasonry', $admin_js, 'The editor preview must enhance Masonry into exact responsive tracks.' );
mygallery_test_contains( 'shortestColumn', $admin_js, 'The editor preview must pack later Masonry items into the shortest track.' );
mygallery_test_contains( 'ResizeObserver', $admin_js, 'The editor Masonry preview must react to container width changes.' );
mygallery_test_contains( "var \$mobileColumns = $( '#my-gallery-pro-mobile-columns' );", $admin_js, 'The editor preview must read the selected mobile column count.' );
mygallery_test_contains( "has-' + mobileColumns + '-mobile-columns", $admin_js, 'The editor preview must expose the selected mobile column class.' );
mygallery_test_contains( "'ArrowLeft'", $admin_js, 'The editor tabs must support keyboard navigation.' );
mygallery_test_contains( 'navigator.clipboard.writeText', $admin_js, 'Shortcode controls must use the Clipboard API when available.' );
mygallery_test_contains( "document.execCommand( 'copy' )", $admin_js, 'Shortcode copying needs a fallback for browsers without Clipboard API support.' );
mygallery_test_contains( "$( document ).on( 'click', '.mgp-shortcode-copy'", $admin_js, 'Shortcode copy controls must work on both overview and editor screens.' );
mygallery_test_assert(
	1 === preg_match( '/\.mgp-settings-grid\s*\{[^}]*align-items:\s*start;/s', $admin_css ),
	'Gallery setting controls must align from the top when neighbouring fields include help text.'
);
mygallery_test_assert(
	1 === preg_match( '/\.mgp-save-actions\s*\{[^}]*margin-top:/s', $admin_css ),
	'The Publish Gallery actions must keep breathing room above the primary button.'
);
mygallery_test_contains( '.mgp-shortcode-copy:focus-visible', $admin_css, 'The shortcode copy control needs a visible keyboard focus style.' );
mygallery_test_contains( '.mgp-gallery-preview.has-3-mobile-columns', $admin_css, 'The editor preview must support every mobile column option.' );
mygallery_test_assert( false === strpos( $admin_css, '--mgp-preview-columns: 2 !important' ), 'The editor preview must not hard-code two mobile columns.' );
mygallery_test_contains( 'showModal', $frontend_js, 'The progressive native dialog viewer is missing.' );
mygallery_test_contains( 'Escape', $frontend_js, 'The viewer must close explicitly when Escape is pressed.' );
mygallery_test_contains( 'ArrowLeft', $frontend_js, 'Viewer keyboard navigation is missing.' );
mygallery_test_contains( 'aria-labelledby', $frontend_js, 'The viewer must be labelled by its visible gallery title.' );
mygallery_test_contains( 'announceCurrent', $frontend_js, 'Viewer navigation must announce the current image context.' );
mygallery_test_contains( 'layoutMasonry', $frontend_js, 'Public Masonry must be progressively enhanced into exact responsive tracks.' );
mygallery_test_contains( 'shortestColumn', $frontend_js, 'Public Masonry must pack later items into the shortest track.' );
mygallery_test_contains( 'ResizeObserver', $frontend_js, 'Public Masonry must react to container width changes.' );
mygallery_test_contains( 'container-type: inline-size', $frontend_css, 'The gallery must respond to narrow builder containers.' );
mygallery_test_contains( 'columns: var(--mgp-columns)', $frontend_css, 'Masonry must reset the full multi-column shorthand to the validated desktop count.' );
mygallery_test_contains( 'grid-template-columns: repeat(var(--mgp-active-columns)', $frontend_css, 'Enhanced Masonry must create the exact active front-end track count.' );
mygallery_test_contains( 'grid-template-columns: repeat(var(--mgp-preview-active-columns)', $admin_css, 'Enhanced editor Masonry must create the exact active preview track count.' );
mygallery_test_contains( 'grid-template-columns: repeat(var(--mgp-mobile-columns)', $frontend_css, 'Grid must apply the selected mobile count directly within narrow containers.' );
mygallery_test_contains( 'columns: var(--mgp-mobile-columns)', $frontend_css, 'Masonry must apply the selected mobile count directly within narrow containers.' );
foreach ( range( 1, 6 ) as $tested_desktop_columns ) {
	mygallery_test_assert(
		1 === preg_match( '/\.my-gallery-pro-grid\.has-' . $tested_desktop_columns . '-columns\s*\{[^}]*--mgp-columns:\s*' . $tested_desktop_columns . ';/s', $frontend_css ),
		'Front-end CSS must map desktop column option ' . $tested_desktop_columns . ' exactly.'
	);
}
foreach ( range( 1, 3 ) as $tested_mobile_columns ) {
	mygallery_test_assert(
		1 === preg_match( '/\.my-gallery-pro-grid\.has-' . $tested_mobile_columns . '-mobile-columns\s*\{[^}]*--mgp-mobile-columns:\s*' . $tested_mobile_columns . ';/s', $frontend_css ),
		'Front-end CSS must map mobile column option ' . $tested_mobile_columns . ' exactly.'
	);
	mygallery_test_assert(
		1 === preg_match( '/\.mgp-gallery-preview\.has-' . $tested_mobile_columns . '-mobile-columns\s*\{[^}]*--mgp-preview-mobile-columns:\s*' . $tested_mobile_columns . ';/s', $admin_css ),
		'Editor preview CSS must map mobile column option ' . $tested_mobile_columns . ' exactly.'
	);
}
mygallery_test_contains( 'prefers-reduced-motion', $frontend_css, 'The gallery must respect reduced-motion preferences.' );

$admin_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/AdminPage.php' );
mygallery_test_contains( 'check_admin_referer', $admin_source, 'Mutations must verify a nonce.' );
mygallery_test_contains( "current_user_can( 'edit_post'", $admin_source, 'Updates must enforce object-level capabilities.' );
mygallery_test_contains( "current_user_can( 'delete_post'", $admin_source, 'Deletes must enforce object-level capabilities.' );
mygallery_test_contains( 'wp_trash_post', $admin_source, 'Deleting a gallery should trash only the gallery record.' );

fwrite( STDOUT, 'MY Gallery PRO v1.0.0 behavior tests passed.' . PHP_EOL );
