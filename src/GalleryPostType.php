<?php
/**
 * Gallery data model backed by a private WordPress post type.
 *
 * @package MyGalleryPro
 */

namespace MyGalleryPro;

defined( 'ABSPATH' ) || exit;

/**
 * Registers galleries and provides validated access to gallery settings.
 */
final class GalleryPostType {

	public const POST_TYPE = 'mgp_gallery';

	public const META_IMAGE_IDS = '_my_gallery_pro_image_ids';

	public const META_LAYOUT = '_my_gallery_pro_layout';

	public const META_COLUMNS = '_my_gallery_pro_columns';

	public const META_MOBILE_COLUMNS = '_my_gallery_pro_mobile_columns';

	public const META_GAP = '_my_gallery_pro_gap';

	public const META_ASPECT = '_my_gallery_pro_aspect';

	public const META_LIGHTBOX = '_my_gallery_pro_lightbox';

	public const META_CAPTIONS = '_my_gallery_pro_captions';

	public const MAX_IMAGES = 200;

	/**
	 * Register the gallery post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => esc_html__( 'Galleries', 'my-gallery-pro' ),
					'singular_name' => esc_html__( 'Gallery', 'my-gallery-pro' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'map_meta_cap'        => true,
				'capability_type'     => 'post',
				'supports'            => array( 'title', 'author' ),
				'can_export'          => true,
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Normalize and validate an ordered image ID list.
	 *
	 * @param mixed $value Submitted or stored image IDs.
	 * @return array<int, int>
	 */
	public static function sanitize_image_ids( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$image_ids = array();
		$seen      = array();

		foreach ( $value as $candidate ) {
			if ( is_array( $candidate ) || is_object( $candidate ) ) {
				continue;
			}
			if ( is_int( $candidate ) ) {
				if ( $candidate < 1 ) {
					continue;
				}
			} elseif ( ! is_string( $candidate ) || 1 !== preg_match( '/\A[0-9]+\z/D', trim( $candidate ) ) ) {
				continue;
			}

			$attachment_id = absint( $candidate );

			if (
				0 === $attachment_id ||
				isset( $seen[ $attachment_id ] ) ||
				! wp_attachment_is_image( $attachment_id )
			) {
				continue;
			}

			$seen[ $attachment_id ] = true;
			$image_ids[]             = $attachment_id;

			if ( self::MAX_IMAGES === count( $image_ids ) ) {
				break;
			}
		}

		return $image_ids;
	}

	/**
	 * Return the current valid ordered image IDs for a gallery.
	 *
	 * @param int $gallery_id Gallery post ID.
	 * @return array<int, int>
	 */
	public static function get_image_ids( int $gallery_id ): array {
		return self::sanitize_image_ids( get_post_meta( $gallery_id, self::META_IMAGE_IDS, true ) );
	}

	/**
	 * Normalize gallery display settings.
	 *
	 * @param mixed $layout         Requested layout style.
	 * @param mixed $columns        Requested desktop columns.
	 * @param mixed $mobile_columns Requested mobile columns.
	 * @param mixed $gap            Requested gap in pixels.
	 * @param mixed $aspect         Requested image aspect.
	 * @param mixed $lightbox       Whether the viewer is enabled.
	 * @param mixed $captions       Whether visible captions are enabled.
	 * @return array{layout:string,columns:int,mobile_columns:int,gap:int,aspect:string,lightbox:bool,captions:bool}
	 */
	public static function sanitize_settings( $layout, $columns, $mobile_columns, $gap, $aspect, $lightbox, $captions ): array {
		$allowed_layouts        = array( 'grid', 'masonry' );
		$allowed_columns        = array( 1, 2, 3, 4, 5, 6 );
		$allowed_mobile_columns = array( 1, 2, 3 );
		$allowed_gaps           = array( 0, 8, 16, 24, 32 );
		$allowed_aspects        = array( 'natural', 'square', 'landscape', 'portrait' );

		$layout         = is_string( $layout ) ? sanitize_key( $layout ) : '';
		$columns        = absint( $columns );
		$mobile_columns = absint( $mobile_columns );
		$gap            = absint( $gap );
		$aspect         = is_string( $aspect ) ? sanitize_key( $aspect ) : '';

		return array(
			'layout'         => in_array( $layout, $allowed_layouts, true ) ? $layout : 'grid',
			'columns'        => in_array( $columns, $allowed_columns, true ) ? $columns : 3,
			'mobile_columns' => in_array( $mobile_columns, $allowed_mobile_columns, true ) ? $mobile_columns : 1,
			'gap'            => in_array( $gap, $allowed_gaps, true ) ? $gap : 16,
			'aspect'         => in_array( $aspect, $allowed_aspects, true ) ? $aspect : 'square',
			'lightbox'       => ! empty( $lightbox ),
			'captions'       => ! empty( $captions ),
		);
	}

	/**
	 * Return normalized display settings for a gallery.
	 *
	 * @param int $gallery_id Gallery post ID.
	 * @return array{layout:string,columns:int,mobile_columns:int,gap:int,aspect:string,lightbox:bool,captions:bool}
	 */
	public static function get_settings( int $gallery_id ): array {
		$lightbox = get_post_meta( $gallery_id, self::META_LIGHTBOX, true );

		if ( '' === $lightbox ) {
			$lightbox = '1';
		}

		return self::sanitize_settings(
			get_post_meta( $gallery_id, self::META_LAYOUT, true ),
			get_post_meta( $gallery_id, self::META_COLUMNS, true ),
			get_post_meta( $gallery_id, self::META_MOBILE_COLUMNS, true ),
			get_post_meta( $gallery_id, self::META_GAP, true ),
			get_post_meta( $gallery_id, self::META_ASPECT, true ),
			$lightbox,
			get_post_meta( $gallery_id, self::META_CAPTIONS, true )
		);
	}

	/**
	 * Persist validated gallery images and settings.
	 *
	 * @param int                 $gallery_id Gallery post ID.
	 * @param mixed               $image_ids  Submitted ordered image IDs.
	 * @param array<string,mixed> $settings   Submitted display settings.
	 * @return void
	 */
	public static function save_meta( int $gallery_id, $image_ids, array $settings ): void {
		$image_ids = self::sanitize_image_ids( $image_ids );
		$settings  = self::sanitize_settings(
			$settings['layout'] ?? 'grid',
			$settings['columns'] ?? 3,
			$settings['mobile_columns'] ?? 1,
			$settings['gap'] ?? 16,
			$settings['aspect'] ?? 'square',
			$settings['lightbox'] ?? false,
			$settings['captions'] ?? false
		);

		update_post_meta( $gallery_id, self::META_IMAGE_IDS, $image_ids );
		update_post_meta( $gallery_id, self::META_LAYOUT, $settings['layout'] );
		update_post_meta( $gallery_id, self::META_COLUMNS, $settings['columns'] );
		update_post_meta( $gallery_id, self::META_MOBILE_COLUMNS, $settings['mobile_columns'] );
		update_post_meta( $gallery_id, self::META_GAP, $settings['gap'] );
		update_post_meta( $gallery_id, self::META_ASPECT, $settings['aspect'] );
		update_post_meta( $gallery_id, self::META_LIGHTBOX, $settings['lightbox'] ? '1' : '0' );
		update_post_meta( $gallery_id, self::META_CAPTIONS, $settings['captions'] ? '1' : '0' );
	}
}
