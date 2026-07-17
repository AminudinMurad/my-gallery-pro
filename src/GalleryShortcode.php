<?php
/**
 * Responsive gallery shortcode and frontend assets.
 *
 * @package MyGalleryPro
 */

namespace MyGalleryPro;

defined( 'ABSPATH' ) || exit;

/**
 * Renders published galleries with progressive enhancement.
 */
final class GalleryShortcode {

	public const TAG = 'mygallery';

	private const STYLE_HANDLE = 'my-gallery-pro-frontend';

	private const SCRIPT_HANDLE = 'my-gallery-pro-frontend';

	/**
	 * Register shortcode and frontend asset hooks.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_for_current_post' ), 20 );
	}

	/**
	 * Register first-party frontend assets without loading them globally.
	 *
	 * @return void
	 */
	public static function register_assets(): void {
		wp_register_style(
			self::STYLE_HANDLE,
			MY_GALLERY_PRO_URL . 'assets/frontend.css',
			array(),
			MY_GALLERY_PRO_VERSION
		);
		wp_register_script(
			self::SCRIPT_HANDLE,
			MY_GALLERY_PRO_URL . 'assets/frontend.js',
			array(),
			MY_GALLERY_PRO_VERSION,
			true
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'mygalleryProFrontend',
			array(
				'dialogLabel'   => __( 'Gallery image viewer', 'my-gallery-pro' ),
				'closeLabel'    => __( 'Close image viewer', 'my-gallery-pro' ),
				'previousLabel' => __( 'Previous image', 'my-gallery-pro' ),
				'nextLabel'     => __( 'Next image', 'my-gallery-pro' ),
				'positionText'  => __( 'Image %1$d of %2$d', 'my-gallery-pro' ),
			)
		);
	}

	/**
	 * Enqueue early when the queried post contains the shortcode.
	 *
	 * @return void
	 */
	public static function maybe_enqueue_for_current_post(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post || empty( $post->post_content ) || ! has_shortcode( $post->post_content, self::TAG ) ) {
			return;
		}

		self::enqueue_assets( false );
	}

	/**
	 * Render a published gallery.
	 *
	 * @param mixed       $atts    Shortcode attributes.
	 * @param string|null $content Enclosed content, unused.
	 * @param string      $tag     Shortcode tag, unused.
	 * @return string
	 */
	public static function render( $atts, $content = null, string $tag = '' ): string {
		unset( $content, $tag );

		$atts = shortcode_atts(
			array( 'id' => 0 ),
			(array) $atts,
			self::TAG
		);
		$gallery_id = absint( $atts['id'] );

		if ( 0 === $gallery_id ) {
			return '';
		}

		$gallery = get_post( $gallery_id );

		if (
			! $gallery ||
			GalleryPostType::POST_TYPE !== $gallery->post_type ||
			'publish' !== $gallery->post_status
		) {
			return '';
		}

		$items = self::get_renderable_items( $gallery_id );

		if ( empty( $items ) ) {
			return '';
		}

		$settings = GalleryPostType::get_settings( $gallery_id );
		self::enqueue_assets( $settings['lightbox'] || 'masonry' === $settings['layout'] );

		$instance   = wp_unique_id( 'my-gallery-pro-' );
		$title      = get_the_title( $gallery_id );
		$title      = '' !== $title ? $title : esc_html__( 'Photo gallery', 'my-gallery-pro' );
		$item_count = count( $items );
		$classes    = sprintf(
			'my-gallery-pro-grid is-%1$s-layout is-%2$s has-%3$d-columns has-%4$d-mobile-columns has-%5$d-gap',
			$settings['layout'],
			$settings['aspect'],
			$settings['columns'],
			$settings['mobile_columns'],
			$settings['gap']
		);

		ob_start();
		?>
		<div class="my-gallery-pro-shell">
			<div
				id="<?php echo esc_attr( $instance ); ?>"
				class="<?php echo esc_attr( $classes ); ?>"
				role="group"
				aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Gallery: %s', 'my-gallery-pro' ), $title ) ); ?>"
				data-mgp-gallery
				data-mgp-title="<?php echo esc_attr( $title ); ?>"
			>
				<?php foreach ( $items as $index => $item ) : ?>
					<?php
					$position   = $index + 1;
					$link_label = $settings['lightbox']
						? sprintf(
							/* translators: 1: image position, 2: image count, 3: gallery title. */
							esc_html__( 'Open image %1$d of %2$d in %3$s in the viewer', 'my-gallery-pro' ),
							$position,
							$item_count,
							$title
						)
						: sprintf(
							/* translators: 1: image position, 2: image count, 3: gallery title. */
							esc_html__( 'Open full-size image %1$d of %2$d in %3$s', 'my-gallery-pro' ),
							$position,
							$item_count,
							$title
						);
					?>
					<figure class="my-gallery-pro-item">
						<a
							class="my-gallery-pro-link"
							href="<?php echo esc_url( $item['full_url'] ); ?>"
							<?php if ( $settings['lightbox'] ) : ?>
								data-mgp-viewer
								data-mgp-caption="<?php echo esc_attr( $item['caption'] ); ?>"
							<?php endif; ?>
						>
							<?php
							// wp_get_attachment_image() returns core-generated markup with escaped
							// attributes (alt, srcset, sizes); re-escaping here would corrupt it.
							echo $item['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
							<span class="my-gallery-pro-screen-reader-text"><?php echo esc_html( $link_label ); ?></span>
						</a>
						<?php if ( $settings['captions'] && '' !== $item['caption'] ) : ?>
							<figcaption><?php echo esc_html( $item['caption'] ); ?></figcaption>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Ensure registered frontend assets are enqueued once.
	 *
	 * @return void
	 */
	private static function enqueue_assets( bool $include_script = true ): void {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) || ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			self::register_assets();
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		if ( $include_script ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
	}

	/**
	 * Build renderable image data while preserving saved order.
	 *
	 * @param int $gallery_id Gallery post ID.
	 * @return array<int, array{full_url:string,image:string,caption:string}>
	 */
	private static function get_renderable_items( int $gallery_id ): array {
		$items = array();

		foreach ( GalleryPostType::get_image_ids( $gallery_id ) as $attachment_id ) {
			// Core resolves an attachment's inherited status to its parent status.
			if ( 'publish' !== get_post_status( $attachment_id ) ) {
				continue;
			}

			$full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
			$image    = wp_get_attachment_image(
				$attachment_id,
				'large',
				false,
				array(
					'class'    => 'my-gallery-pro-image',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);

			if ( ! $full_url || '' === $image ) {
				continue;
			}

			$items[] = array(
				'full_url' => $full_url,
				'image'    => $image,
				'caption'  => (string) wp_get_attachment_caption( $attachment_id ),
			);
		}

		return $items;
	}
}
