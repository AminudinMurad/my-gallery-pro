<?php
/**
 * Dependency-free WordPress stubs for MY Gallery PRO tests.
 *
 * @package MyGalleryPro
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

/** @var array<string,mixed> $mygallery_test_state */
$mygallery_test_state = array();

/**
 * Reset mutable WordPress test state.
 *
 * @return void
 */
function mygallery_test_reset_state(): void {
	global $mygallery_test_state;

	$mygallery_test_state = array(
		'actions'            => array(),
		'filters'            => array(),
		'fired_actions'      => array(),
		'shortcodes'         => array(),
		'menu_pages'         => array(),
		'submenu_pages'      => array(),
		'post_types'         => array(),
		'posts'              => array(),
		'meta'               => array(),
		'images'             => array(),
		'allowed'            => true,
		'denied_caps'        => array(),
		'denied_objects'     => array(),
		'current_user_id'    => 7,
		'registered_styles'  => array(),
		'registered_scripts' => array(),
		'enqueued_styles'    => array(),
		'enqueued_scripts'   => array(),
		'localized'          => array(),
		'media_calls'        => array(),
		'last_get_posts_args'=> array(),
		'current_post'       => null,
		'is_singular'        => false,
		'unique_id'          => 0,
		'nonce_valid'        => true,
		'redirect'           => '',
		'redirect_throws'    => false,
		'next_post_id'       => 500,
	);
}

mygallery_test_reset_state();

final class WP_Error {
	/** @var string */
	public $message;

	public function __construct( string $message = '' ) {
		$this->message = $message;
	}
}

final class MyGalleryTestRedirect extends RuntimeException {
}

function plugin_dir_path( string $file ): string {
	return dirname( $file ) . '/';
}

function plugin_dir_url(): string {
	return 'https://example.test/wp-content/plugins/my-gallery-pro/';
}

function plugin_basename( string $file ): string {
	return 'my-gallery-pro/' . basename( $file );
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	global $mygallery_test_state;

	$mygallery_test_state['actions'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	global $mygallery_test_state;

	$mygallery_test_state['filters'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

function do_action( string $hook ): void {
	global $mygallery_test_state;

	$mygallery_test_state['fired_actions'][] = $hook;
}

function add_shortcode( string $tag, callable $callback ): void {
	global $mygallery_test_state;

	$mygallery_test_state['shortcodes'][ $tag ] = $callback;
}

function add_menu_page(
	string $page_title,
	string $menu_title,
	string $capability,
	string $menu_slug,
	callable $callback,
	string $icon_url = '',
	$position = null
): string {
	global $mygallery_test_state;

	$hook = 'toplevel_page_' . $menu_slug;
	$mygallery_test_state['menu_pages'][ $menu_slug ] = compact(
		'page_title',
		'menu_title',
		'capability',
		'menu_slug',
		'callback',
		'icon_url',
		'position',
		'hook'
	);

	return $hook;
}

function add_submenu_page(
	string $parent_slug,
	string $page_title,
	string $menu_title,
	string $capability,
	string $menu_slug,
	callable $callback
): string {
	global $mygallery_test_state;

	$hook = $parent_slug . '_page_' . $menu_slug;
	$mygallery_test_state['submenu_pages'][ $menu_slug ] = compact(
		'parent_slug',
		'page_title',
		'menu_title',
		'capability',
		'menu_slug',
		'callback',
		'hook'
	);

	return $hook;
}

/**
 * Resolve the effective admin page title using WordPress submenu precedence.
 *
 * @param string $menu_slug Registered menu slug.
 * @return string
 */
function mygallery_test_admin_page_title( string $menu_slug ): string {
	global $mygallery_test_state;

	if ( isset( $mygallery_test_state['submenu_pages'][ $menu_slug ]['page_title'] ) ) {
		return (string) $mygallery_test_state['submenu_pages'][ $menu_slug ]['page_title'];
	}

	return (string) ( $mygallery_test_state['menu_pages'][ $menu_slug ]['page_title'] ?? '' );
}

function register_post_type( string $post_type, array $args ) {
	global $mygallery_test_state;

	$mygallery_test_state['post_types'][ $post_type ] = $args;

	return (object) array( 'name' => $post_type );
}

function esc_html__( string $text, string $domain = '' ): string {
	unset( $domain );
	return esc_html( $text );
}

function esc_attr__( string $text, string $domain = '' ): string {
	unset( $domain );
	return esc_attr( $text );
}

function __( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function _n( string $single, string $plural, int $number, string $domain = '' ): string {
	unset( $domain );
	return 1 === $number ? $single : $plural;
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( string $url ): string {
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function esc_js( string $text ): string {
	return str_replace( array( "\r", "\n", "'", '"' ), array( '', '\\n', "\\'", '\\"' ), $text );
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function add_query_arg( $key, $value = null, $url = null ): string {
	if ( is_array( $key ) ) {
		$args = $key;
		$url  = is_string( $value ) ? $value : '';
	} else {
		$args = array( (string) $key => $value );
		$url  = is_string( $url ) ? $url : '';
	}

	$separator = false === strpos( $url, '?' ) ? '?' : '&';
	return $url . $separator . http_build_query( $args );
}

function current_user_can( string $capability, ...$args ): bool {
	global $mygallery_test_state;
	$object_id = isset( $args[0] ) ? absint( $args[0] ) : 0;

	return $mygallery_test_state['allowed'] &&
		empty( $mygallery_test_state['denied_caps'][ $capability ] ) &&
		( 0 === $object_id || empty( $mygallery_test_state['denied_objects'][ $capability ][ $object_id ] ) );
}

function wp_die( string $message ): void {
	throw new RuntimeException( $message );
}

function absint( $value ): int {
	return abs( (int) $value );
}

function sanitize_key( $key ): string {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
}

function sanitize_text_field( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return trim( strip_tags( (string) $value ) );
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function wp_attachment_is_image( int $attachment_id ): bool {
	global $mygallery_test_state;
	return isset( $mygallery_test_state['images'][ $attachment_id ] );
}

function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
	global $mygallery_test_state;
	unset( $single );

	if ( isset( $mygallery_test_state['meta'][ $post_id ][ $key ] ) ) {
		return $mygallery_test_state['meta'][ $post_id ][ $key ];
	}

	if ( '_wp_attachment_image_alt' === $key && isset( $mygallery_test_state['images'][ $post_id ]['alt'] ) ) {
		return $mygallery_test_state['images'][ $post_id ]['alt'];
	}

	return '';
}

function update_post_meta( int $post_id, string $key, $value ): bool {
	global $mygallery_test_state;

	$mygallery_test_state['meta'][ $post_id ][ $key ] = $value;
	return true;
}

function get_post( int $post_id ) {
	global $mygallery_test_state;
	return $mygallery_test_state['posts'][ $post_id ] ?? null;
}

function get_post_status( $post = null ) {
	global $mygallery_test_state;

	$post_id = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : absint( $post );

	if ( isset( $mygallery_test_state['posts'][ $post_id ]->post_status ) ) {
		return (string) $mygallery_test_state['posts'][ $post_id ]->post_status;
	}

	if ( isset( $mygallery_test_state['images'][ $post_id ] ) ) {
		return isset( $mygallery_test_state['images'][ $post_id ]['status'] )
			? (string) $mygallery_test_state['images'][ $post_id ]['status']
			: 'publish';
	}

	return false;
}

function get_posts( array $args = array() ): array {
	global $mygallery_test_state;
	$mygallery_test_state['last_get_posts_args'] = $args;
	$posts = array_values( $mygallery_test_state['posts'] );

	$posts = array_values(
		array_filter(
			$posts,
			static function ( $post ) use ( $args ): bool {
				if ( isset( $args['post_type'] ) && $args['post_type'] !== $post->post_type ) {
					return false;
				}

				if ( isset( $args['post_status'] ) ) {
					$statuses = (array) $args['post_status'];
					if ( ! in_array( $post->post_status, $statuses, true ) ) {
						return false;
					}
				}

				if ( isset( $args['author'] ) && absint( $args['author'] ) !== absint( $post->post_author ?? 0 ) ) {
					return false;
				}

				return true;
			}
		)
	);

	if ( isset( $args['offset'] ) || isset( $args['posts_per_page'] ) ) {
		$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;
		$limit  = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;
		$posts  = array_slice( $posts, $offset, $limit > 0 ? $limit : null );
	}

	return $posts;
}

function get_the_title( int $post_id ): string {
	global $mygallery_test_state;

	if ( isset( $mygallery_test_state['posts'][ $post_id ]->post_title ) ) {
		return (string) $mygallery_test_state['posts'][ $post_id ]->post_title;
	}

	return isset( $mygallery_test_state['images'][ $post_id ]['title'] ) ? (string) $mygallery_test_state['images'][ $post_id ]['title'] : '';
}

function wp_get_attachment_image( int $attachment_id, $size = 'thumbnail', bool $icon = false, $attr = '' ): string {
	global $mygallery_test_state;
	unset( $size, $icon );

	if ( ! isset( $mygallery_test_state['images'][ $attachment_id ] ) ) {
		return '';
	}

	$image = $mygallery_test_state['images'][ $attachment_id ];
	$attr  = is_array( $attr ) ? $attr : array();
	$class = isset( $attr['class'] ) ? (string) $attr['class'] : 'attachment-thumbnail';
	$alt   = array_key_exists( 'alt', $attr ) ? (string) $attr['alt'] : ( isset( $image['alt'] ) ? (string) $image['alt'] : '' );

	return sprintf(
		'<img data-image-id="%1$d" src="%2$s" srcset="%2$s 800w" sizes="100vw" class="%3$s" alt="%4$s" />',
		$attachment_id,
		esc_url( (string) $image['url'] ),
		esc_attr( $class ),
		esc_attr( $alt )
	);
}

function wp_get_attachment_image_url( int $attachment_id, $size = 'thumbnail' ) {
	global $mygallery_test_state;
	unset( $size );
	return $mygallery_test_state['images'][ $attachment_id ]['url'] ?? false;
}

function wp_get_attachment_caption( int $attachment_id ): string {
	global $mygallery_test_state;
	return isset( $mygallery_test_state['images'][ $attachment_id ]['caption'] ) ? (string) $mygallery_test_state['images'][ $attachment_id ]['caption'] : '';
}

function wp_register_style( string $handle, string $src, array $deps = array(), $version = false ): bool {
	global $mygallery_test_state;
	$mygallery_test_state['registered_styles'][ $handle ] = compact( 'src', 'deps', 'version' );
	return true;
}

function wp_register_script( string $handle, string $src, array $deps = array(), $version = false, $in_footer = false ): bool {
	global $mygallery_test_state;
	$mygallery_test_state['registered_scripts'][ $handle ] = compact( 'src', 'deps', 'version', 'in_footer' );
	return true;
}

function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $version = false ): void {
	global $mygallery_test_state;
	if ( '' !== $src ) {
		wp_register_style( $handle, $src, $deps, $version );
	}
	$mygallery_test_state['enqueued_styles'][ $handle ] = true;
}

function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $version = false, $in_footer = false ): void {
	global $mygallery_test_state;
	if ( '' !== $src ) {
		wp_register_script( $handle, $src, $deps, $version, $in_footer );
	}
	$mygallery_test_state['enqueued_scripts'][ $handle ] = true;
}

function wp_style_is( string $handle, string $status = 'enqueued' ): bool {
	global $mygallery_test_state;
	return 'registered' === $status ? isset( $mygallery_test_state['registered_styles'][ $handle ] ) : isset( $mygallery_test_state['enqueued_styles'][ $handle ] );
}

function wp_script_is( string $handle, string $status = 'enqueued' ): bool {
	global $mygallery_test_state;
	return 'registered' === $status ? isset( $mygallery_test_state['registered_scripts'][ $handle ] ) : isset( $mygallery_test_state['enqueued_scripts'][ $handle ] );
}

function wp_localize_script( string $handle, string $object_name, array $data ): bool {
	global $mygallery_test_state;
	$mygallery_test_state['localized'][ $handle ][ $object_name ] = $data;
	return true;
}

function wp_enqueue_media( array $args = array() ): void {
	global $mygallery_test_state;
	$mygallery_test_state['media_calls'][] = $args;
}

function is_singular(): bool {
	global $mygallery_test_state;
	return (bool) $mygallery_test_state['is_singular'];
}

function get_queried_object() {
	global $mygallery_test_state;
	return $mygallery_test_state['current_post'];
}

function has_shortcode( string $content, string $tag ): bool {
	return false !== strpos( $content, '[' . $tag );
}

function shortcode_atts( array $pairs, $atts, string $shortcode = '' ): array {
	unset( $shortcode );
	return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) );
}

function wp_unique_id( string $prefix = '' ): string {
	global $mygallery_test_state;
	++$mygallery_test_state['unique_id'];
	return $prefix . $mygallery_test_state['unique_id'];
}

function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
	unset( $referer );
	$field = sprintf( '<input type="hidden" name="%s" value="nonce-%s" />', esc_attr( $name ), esc_attr( $action ) );

	if ( $display ) {
		echo $field;
	}

	return $field;
}

function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): int {
	global $mygallery_test_state;
	unset( $action, $query_arg );

	if ( ! $mygallery_test_state['nonce_valid'] ) {
		wp_die( 'Invalid nonce.' );
	}

	return 1;
}

function selected( $selected, $current = true, bool $display = true ): string {
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function checked( $checked, $current = true, bool $display = true ): string {
	$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function get_current_user_id(): int {
	global $mygallery_test_state;
	return (int) $mygallery_test_state['current_user_id'];
}

function wp_insert_post( array $postarr, bool $wp_error = false ) {
	global $mygallery_test_state;
	unset( $wp_error );
	$id = ++$mygallery_test_state['next_post_id'];
	$mygallery_test_state['posts'][ $id ] = (object) array_merge( array( 'ID' => $id ), $postarr );
	return $id;
}

function wp_update_post( array $postarr, bool $wp_error = false ) {
	global $mygallery_test_state;
	unset( $wp_error );
	$id = absint( $postarr['ID'] ?? 0 );
	if ( ! isset( $mygallery_test_state['posts'][ $id ] ) ) {
		return new WP_Error( 'Missing post.' );
	}
	foreach ( $postarr as $key => $value ) {
		if ( 'ID' !== $key ) {
			$mygallery_test_state['posts'][ $id ]->{$key} = $value;
		}
	}
	return $id;
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function wp_trash_post( int $post_id ) {
	global $mygallery_test_state;
	if ( isset( $mygallery_test_state['posts'][ $post_id ] ) ) {
		$mygallery_test_state['posts'][ $post_id ]->post_status = 'trash';
		return $mygallery_test_state['posts'][ $post_id ];
	}
	return false;
}

function wp_safe_redirect( string $location ): bool {
	global $mygallery_test_state;
	$mygallery_test_state['redirect'] = $location;

	if ( $mygallery_test_state['redirect_throws'] ) {
		throw new MyGalleryTestRedirect( $location );
	}

	return true;
}
