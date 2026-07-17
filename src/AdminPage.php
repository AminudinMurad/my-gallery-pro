<?php
/**
 * WordPress admin experience for MY Gallery PRO.
 *
 * @package MyGalleryPro
 */

namespace MyGalleryPro;

defined( 'ABSPATH' ) || exit;

/**
 * Renders gallery management and handles authorized gallery mutations.
 */
final class AdminPage {

	private const PAGE_SLUG = 'my-gallery-pro';

	private const EDITOR_SLUG = 'my-gallery-pro-new';

	private const CAPABILITY = 'upload_files';

	private const SAVE_ACTION = 'my_gallery_pro_save_gallery';

	private const DELETE_ACTION = 'my_gallery_pro_delete_gallery';

	private const GALLERIES_PER_PAGE = 24;

	/** @var string */
	private static $overview_hook = '';

	/** @var string */
	private static $editor_hook = '';

	/**
	 * Register WordPress hooks for the admin experience.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'register' ), 9 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( self::class, 'handle_delete' ) );
		add_filter(
			'plugin_action_links_' . MY_GALLERY_PRO_BASENAME,
			array( self::class, 'add_action_link' )
		);
	}

	/**
	 * Register the plugin menu and gallery editor submenu.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! self::can_manage_galleries() ) {
			return;
		}

		$overview_page_title = sprintf(
			/* translators: 1: plugin name, 2: plugin version number. */
			esc_html__( '%1$s — Version v%2$s', 'my-gallery-pro' ),
			MY_GALLERY_PRO_NAME,
			MY_GALLERY_PRO_VERSION
		);
		$editor_action = self::requested_gallery_id() > 0
			? esc_html__( 'Edit Gallery', 'my-gallery-pro' )
			: esc_html__( 'Add Gallery', 'my-gallery-pro' );
		$editor_page_title = sprintf(
			/* translators: 1: editor action, 2: plugin name, 3: plugin version number. */
			esc_html__( '%1$s — %2$s v%3$s', 'my-gallery-pro' ),
			$editor_action,
			MY_GALLERY_PRO_NAME,
			MY_GALLERY_PRO_VERSION
		);

		self::$overview_hook = add_menu_page(
			$overview_page_title,
			MY_GALLERY_PRO_NAME,
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_overview' ),
			'dashicons-format-gallery',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			$overview_page_title,
			esc_html__( 'Galleries', 'my-gallery-pro' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render_overview' )
		);

		self::$editor_hook = add_submenu_page(
			self::PAGE_SLUG,
			$editor_page_title,
			esc_html__( 'Add Gallery', 'my-gallery-pro' ),
			self::CAPABILITY,
			self::EDITOR_SLUG,
			array( self::class, 'render_editor' )
		);
	}

	/**
	 * Add a Manage Galleries link to the plugin row.
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public static function add_action_link( array $links ): array {
		if ( ! self::can_manage_galleries() ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::get_overview_url() ),
				esc_html__( 'Manage Galleries', 'my-gallery-pro' )
			)
		);

		return $links;
	}

	/**
	 * Enqueue assets only on MY Gallery PRO screens.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::$overview_hook !== $hook_suffix && self::$editor_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'my-gallery-pro-admin',
			MY_GALLERY_PRO_URL . 'assets/admin.css',
			array(),
			MY_GALLERY_PRO_VERSION
		);

		$dependencies = array( 'jquery' );

		if ( self::$editor_hook === $hook_suffix ) {
			$dependencies[] = 'jquery-ui-sortable';
			wp_enqueue_media( self::media_library_args() );
		}

		wp_enqueue_script(
			'my-gallery-pro-admin',
			MY_GALLERY_PRO_URL . 'assets/admin.js',
			$dependencies,
			MY_GALLERY_PRO_VERSION,
			true
		);
		wp_localize_script(
			'my-gallery-pro-admin',
			'mygalleryProAdmin',
			array(
				'frameTitle'       => __( 'Choose gallery images', 'my-gallery-pro' ),
				'frameButton'      => __( 'Use selected images', 'my-gallery-pro' ),
				'removeText'       => __( 'Remove', 'my-gallery-pro' ),
				'imageFallback'    => __( 'Image', 'my-gallery-pro' ),
				'removeLabel'      => __( 'Remove %1$s; position %2$d of %3$d', 'my-gallery-pro' ),
				'moveEarlierLabel' => __( 'Move %1$s earlier; position %2$d of %3$d', 'my-gallery-pro' ),
				'moveLaterLabel'   => __( 'Move %1$s later; position %2$d of %3$d', 'my-gallery-pro' ),
				'emptyText'        => __( 'No images selected yet.', 'my-gallery-pro' ),
				'countSingular'    => __( '1 image selected', 'my-gallery-pro' ),
				'countPlural'      => __( '%d images selected', 'my-gallery-pro' ),
				'movedText'        => __( '%1$s moved to position %2$d of %3$d.', 'my-gallery-pro' ),
				'removedText'      => __( '%1$s removed. %2$d images selected.', 'my-gallery-pro' ),
				'limitText'        => __( 'Only the first %d images were selected.', 'my-gallery-pro' ),
				'copyText'         => __( 'Copy', 'my-gallery-pro' ),
				'copiedText'       => __( 'Copied!', 'my-gallery-pro' ),
				'copyFailedText'   => __( 'Copy failed', 'my-gallery-pro' ),
				'maxImages'        => GalleryPostType::MAX_IMAGES,
			)
		);
	}

	/**
	 * Render the gallery overview and useful empty state.
	 *
	 * @return void
	 */
	public static function render_overview(): void {
		self::authorize();

		$current_page = self::requested_page_number();
		$query_args   = array(
			'post_type'      => GalleryPostType::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => self::GALLERIES_PER_PAGE + 1,
			'offset'         => ( $current_page - 1 ) * self::GALLERIES_PER_PAGE,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'perm'           => 'readable',
			'no_found_rows'  => true,
		);

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$query_args['author'] = get_current_user_id();
		}

		$galleries = get_posts( $query_args );
		$galleries = array_values(
			array_filter(
				$galleries,
				static function ( $gallery ): bool {
					return isset( $gallery->ID ) && current_user_can( 'edit_post', (int) $gallery->ID );
				}
			)
		);
		$has_next  = count( $galleries ) > self::GALLERIES_PER_PAGE;
		$galleries = array_slice( $galleries, 0, self::GALLERIES_PER_PAGE );
		?>
		<div class="wrap my-gallery-pro-admin">
			<?php self::render_notice(); ?>
			<header class="mgp-page-header">
				<div class="mgp-title-row">
					<h1 id="mgp-page-title"><?php echo esc_html__( 'Galleries', 'my-gallery-pro' ); ?></h1>
					<a class="page-title-action" href="<?php echo esc_url( self::get_editor_url() ); ?>"><?php echo esc_html__( 'Add New Gallery', 'my-gallery-pro' ); ?></a>
					<span class="mgp-version-inline"><?php echo esc_html( MY_GALLERY_PRO_NAME . ' v' . MY_GALLERY_PRO_VERSION ); ?></span>
				</div>
				<p><?php echo esc_html__( 'Create responsive image galleries from your WordPress Media Library.', 'my-gallery-pro' ); ?></p>
			</header>

			<section class="mgp-section" aria-labelledby="mgp-galleries-title">
				<div class="mgp-list-summary">
					<h2 id="mgp-galleries-title" class="screen-reader-text"><?php echo esc_html__( 'Gallery list', 'my-gallery-pro' ); ?></h2>
					<strong><?php echo esc_html__( 'All galleries', 'my-gallery-pro' ); ?></strong>
					<span aria-label="<?php echo esc_attr__( 'Gallery count', 'my-gallery-pro' ); ?>"><?php echo esc_html( '(' . (string) count( $galleries ) . ')' ); ?></span>
				</div>

				<?php if ( empty( $galleries ) ) : ?>
					<div class="mgp-empty-state">
						<span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
						<h3><?php echo esc_html__( 'Create your first gallery', 'my-gallery-pro' ); ?></h3>
						<p><?php echo esc_html__( 'Select images, choose a layout, then paste the generated shortcode into any page or post.', 'my-gallery-pro' ); ?></p>
						<a class="button button-primary" href="<?php echo esc_url( self::get_editor_url() ); ?>">
							<?php echo esc_html__( 'Add Gallery', 'my-gallery-pro' ); ?>
						</a>
					</div>
				<?php else : ?>
					<div class="mgp-table-wrap">
						<table class="widefat fixed striped mgp-gallery-table">
							<thead>
								<tr>
									<th class="column-preview" scope="col"><?php echo esc_html__( 'Preview', 'my-gallery-pro' ); ?></th>
									<th class="column-title" scope="col"><?php echo esc_html__( 'Title', 'my-gallery-pro' ); ?></th>
									<th class="column-layout" scope="col"><?php echo esc_html__( 'Layout', 'my-gallery-pro' ); ?></th>
									<th class="column-images" scope="col"><?php echo esc_html__( 'Images', 'my-gallery-pro' ); ?></th>
									<th class="column-shortcode" scope="col"><?php echo esc_html__( 'Shortcode', 'my-gallery-pro' ); ?></th>
									<th class="column-actions" scope="col"><?php echo esc_html__( 'Actions', 'my-gallery-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $galleries as $gallery ) : ?>
									<?php self::render_gallery_row( $gallery ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>

			<?php self::render_pagination( $current_page, $has_next ); ?>
		</div>
		<?php
	}

	/**
	 * Render the create/edit gallery form.
	 *
	 * @return void
	 */
	public static function render_editor(): void {
		self::authorize();

		$gallery_id = self::requested_gallery_id();
		$gallery    = null;

		if ( $gallery_id > 0 ) {
			$gallery = get_post( $gallery_id );

			if (
				! $gallery ||
				GalleryPostType::POST_TYPE !== $gallery->post_type ||
				! current_user_can( 'edit_post', $gallery_id )
			) {
				wp_die( esc_html__( 'You do not have permission to edit this gallery.', 'my-gallery-pro' ) );
			}

			if ( ! self::is_editable_status( $gallery ) ) {
				wp_die( esc_html__( 'This gallery is in the trash and cannot be edited.', 'my-gallery-pro' ) );
			}
		}

		$image_ids = $gallery ? self::sanitize_submitted_image_ids( GalleryPostType::get_image_ids( $gallery_id ) ) : array();
		$settings  = $gallery ? GalleryPostType::get_settings( $gallery_id ) : GalleryPostType::sanitize_settings( 'grid', 3, 1, 16, 'square', true, false );
		$title     = $gallery ? get_the_title( $gallery_id ) : '';
		?>
		<div class="wrap my-gallery-pro-admin mgp-editor">
			<header class="mgp-editor-header">
				<div>
					<a class="mgp-back-link" href="<?php echo esc_url( self::get_overview_url() ); ?>">&larr; <?php echo esc_html__( 'All galleries', 'my-gallery-pro' ); ?></a>
					<h1><?php echo $gallery ? esc_html__( 'Edit Gallery', 'my-gallery-pro' ) : esc_html__( 'Add New Gallery', 'my-gallery-pro' ); ?></h1>
				</div>
				<span class="mgp-version-inline"><?php echo esc_html( MY_GALLERY_PRO_NAME . ' v' . MY_GALLERY_PRO_VERSION ); ?></span>
			</header>

			<form class="mgp-editor-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
				<input type="hidden" name="gallery_id" value="<?php echo esc_attr( (string) $gallery_id ); ?>" />
				<?php wp_nonce_field( self::SAVE_ACTION, 'my_gallery_pro_nonce' ); ?>
				<label class="screen-reader-text" for="my-gallery-pro-title"><?php echo esc_html__( 'Gallery title', 'my-gallery-pro' ); ?></label>
				<input id="my-gallery-pro-title" class="mgp-title-input" type="text" name="gallery_title" value="<?php echo esc_attr( $title ); ?>" maxlength="160" placeholder="<?php echo esc_attr__( 'Gallery title', 'my-gallery-pro' ); ?>" required />

				<div class="mgp-editor-layout">
					<div class="mgp-editor-main">
					<section class="mgp-panel mgp-gallery-items-panel" aria-labelledby="mgp-images-title">
						<div class="mgp-panel-heading">
							<div>
								<h2 id="mgp-images-title"><?php echo esc_html__( 'Gallery Images', 'my-gallery-pro' ); ?></h2>
								<p><?php echo esc_html__( 'Add images from the Media Library, then drag them or use the arrow buttons to change their order.', 'my-gallery-pro' ); ?></p>
							</div>
							<button type="button" class="button button-primary" id="my-gallery-pro-add-images">
								<?php echo esc_html__( 'Add from Media Library', 'my-gallery-pro' ); ?>
							</button>
						</div>
						<div class="mgp-editor-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Gallery image views', 'my-gallery-pro' ); ?>">
							<button type="button" class="mgp-editor-tab is-active" id="mgp-manage-tab" role="tab" aria-selected="true" aria-controls="mgp-manage-panel" data-mgp-tab="manage"><?php echo esc_html__( 'Manage Images', 'my-gallery-pro' ); ?></button>
							<button type="button" class="mgp-editor-tab" id="mgp-preview-tab" role="tab" aria-selected="false" aria-controls="mgp-preview-panel" tabindex="-1" data-mgp-tab="preview"><?php echo esc_html__( 'Gallery Preview', 'my-gallery-pro' ); ?></button>
						</div>

						<div id="mgp-manage-panel" class="mgp-editor-tab-panel" role="tabpanel" aria-labelledby="mgp-manage-tab" data-mgp-panel="manage">
							<p class="mgp-media-policy">
								<?php
								printf(
									/* translators: %d: maximum number of images per gallery. */
									esc_html__( 'Choose up to %d images. Images inherited from draft or private content remain hidden from the public gallery until that content is published.', 'my-gallery-pro' ),
									GalleryPostType::MAX_IMAGES
								);
								?>
							</p>
							<p id="my-gallery-pro-image-count" class="mgp-selection-count"></p>
							<ul id="my-gallery-pro-images" class="mgp-image-picker" aria-labelledby="mgp-images-title">
								<?php foreach ( $image_ids as $index => $attachment_id ) : ?>
									<?php self::render_media_item( $attachment_id, $index, count( $image_ids ) ); ?>
								<?php endforeach; ?>
							</ul>
							<p id="my-gallery-pro-image-status" class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></p>
							<p id="my-gallery-pro-empty-images" class="mgp-media-empty<?php echo empty( $image_ids ) ? '' : ' is-hidden'; ?>">
								<?php echo esc_html__( 'Your gallery is empty. Add images from the Media Library to get started.', 'my-gallery-pro' ); ?>
							</p>
						</div>

						<div id="mgp-preview-panel" class="mgp-editor-tab-panel" role="tabpanel" aria-labelledby="mgp-preview-tab" data-mgp-panel="preview" hidden>
							<p class="mgp-preview-help"><?php echo esc_html__( 'This quick preview reflects image order and the basic layout settings below. Save the gallery before checking the public shortcode.', 'my-gallery-pro' ); ?></p>
							<div id="my-gallery-pro-preview" class="mgp-gallery-preview" aria-live="polite"></div>
							<p id="my-gallery-pro-empty-preview" class="mgp-media-empty<?php echo empty( $image_ids ) ? '' : ' is-hidden'; ?>"><?php echo esc_html__( 'Add images to see a preview.', 'my-gallery-pro' ); ?></p>
						</div>
					</section>

					<section class="mgp-panel" aria-labelledby="mgp-layout-title">
						<h2 id="mgp-layout-title"><?php echo esc_html__( 'Gallery Settings', 'my-gallery-pro' ); ?></h2>
						<p class="mgp-panel-intro"><?php echo esc_html__( 'Choose only the essential layout and display options for this gallery.', 'my-gallery-pro' ); ?></p>
						<?php self::render_settings_fields( $settings ); ?>
					</section>
					</div>

				<aside class="mgp-editor-sidebar">
					<section class="mgp-panel mgp-publish-card" aria-labelledby="mgp-publish-title">
						<h2 id="mgp-publish-title"><?php echo esc_html__( 'Publish', 'my-gallery-pro' ); ?></h2>
						<p><?php echo esc_html__( 'A gallery needs a title and at least one valid Media Library image.', 'my-gallery-pro' ); ?></p>
						<div class="mgp-save-actions">
							<button type="submit" class="button button-primary button-hero">
								<?php echo $gallery ? esc_html__( 'Update Gallery', 'my-gallery-pro' ) : esc_html__( 'Publish Gallery', 'my-gallery-pro' ); ?>
							</button>
							<a class="button button-link" href="<?php echo esc_url( self::get_overview_url() ); ?>"><?php echo esc_html__( 'Cancel', 'my-gallery-pro' ); ?></a>
						</div>
					</section>
					<?php if ( $gallery_id > 0 ) : ?>
						<section class="mgp-panel mgp-shortcode-card" aria-labelledby="mgp-shortcode-title">
							<h2 id="mgp-shortcode-title"><?php echo esc_html__( 'Gallery Shortcode', 'my-gallery-pro' ); ?></h2>
							<p><?php echo esc_html__( 'Click to copy, then paste the shortcode into any post, page, or compatible builder:', 'my-gallery-pro' ); ?></p>
							<?php self::render_shortcode_copy( $gallery_id ); ?>
						</section>
					<?php endif; ?>
				</aside>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle an authenticated create or update request.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		self::require_post_request();
		self::authorize();
		check_admin_referer( self::SAVE_ACTION, 'my_gallery_pro_nonce' );

		$gallery_id = self::posted_integer( 'gallery_id' );
		$title      = self::posted_text( 'gallery_title' );

		if ( '' === $title ) {
			wp_die( esc_html__( 'Enter a gallery name before saving.', 'my-gallery-pro' ) );
		}
		if ( self::text_length( $title ) > 160 ) {
			wp_die( esc_html__( 'Gallery names cannot be longer than 160 characters.', 'my-gallery-pro' ) );
		}
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to publish galleries.', 'my-gallery-pro' ) );
		}

		$image_ids = isset( $_POST['my_gallery_pro_image_ids'] ) ? wp_unslash( $_POST['my_gallery_pro_image_ids'] ) : array();
		$image_ids = self::sanitize_submitted_image_ids( $image_ids );

		if ( empty( $image_ids ) ) {
			wp_die( esc_html__( 'Choose at least one valid Media Library image before saving.', 'my-gallery-pro' ) );
		}

		if ( $gallery_id > 0 ) {
			$gallery = get_post( $gallery_id );

			if (
				! $gallery ||
				GalleryPostType::POST_TYPE !== $gallery->post_type ||
				! current_user_can( 'edit_post', $gallery_id )
			) {
				wp_die( esc_html__( 'You do not have permission to update this gallery.', 'my-gallery-pro' ) );
			}

			if ( ! self::is_editable_status( $gallery ) ) {
				wp_die( esc_html__( 'This gallery is in the trash and cannot be edited.', 'my-gallery-pro' ) );
			}

			$result = wp_update_post(
				array(
					'ID'         => $gallery_id,
					'post_title' => $title,
					'post_status' => 'publish',
				),
				true
			);
		} else {
			$result = wp_insert_post(
				array(
					'post_type'   => GalleryPostType::POST_TYPE,
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				),
				true
			);
		}

		if ( is_wp_error( $result ) || 0 === $result ) {
			wp_die( esc_html__( 'WordPress could not save this gallery. Please try again.', 'my-gallery-pro' ) );
		}

		$gallery_id = (int) $result;
		GalleryPostType::save_meta(
			$gallery_id,
			$image_ids,
			array(
				'layout'         => self::posted_text( 'gallery_layout' ),
				'columns'        => self::posted_text( 'gallery_columns' ),
				'mobile_columns' => self::posted_text( 'gallery_mobile_columns' ),
				'gap'            => self::posted_text( 'gallery_gap' ),
				'aspect'         => self::posted_text( 'gallery_aspect' ),
				'lightbox'       => isset( $_POST['gallery_lightbox'] ),
				'captions'       => isset( $_POST['gallery_captions'] ),
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle an authenticated request to move a gallery to the trash.
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		self::require_post_request();
		self::authorize();

		$gallery_id = self::posted_integer( 'gallery_id' );
		check_admin_referer( self::DELETE_ACTION . '_' . $gallery_id, 'my_gallery_pro_nonce' );

		$gallery = get_post( $gallery_id );

		if (
			! $gallery ||
			GalleryPostType::POST_TYPE !== $gallery->post_type ||
			! current_user_can( 'delete_post', $gallery_id )
		) {
			wp_die( esc_html__( 'You do not have permission to delete this gallery.', 'my-gallery-pro' ) );
		}

		if ( ! wp_trash_post( $gallery_id ) ) {
			wp_die( esc_html__( 'WordPress could not move this gallery to the trash. Please try again.', 'my-gallery-pro' ) );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Return the overview URL.
	 *
	 * @return string
	 */
	public static function get_overview_url(): string {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Return the create/edit URL.
	 *
	 * @param int $gallery_id Optional gallery ID.
	 * @return string
	 */
	public static function get_editor_url( int $gallery_id = 0 ): string {
		$args = array( 'page' => self::EDITOR_SLUG );

		if ( $gallery_id > 0 ) {
			$args['gallery_id'] = $gallery_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Render an accessible one-click shortcode copy control.
	 *
	 * @param int $gallery_id Gallery ID.
	 * @return void
	 */
	private static function render_shortcode_copy( int $gallery_id ): void {
		$shortcode = sprintf( '[mygallery id="%d"]', $gallery_id );
		$copy_label = sprintf(
			/* translators: %s: gallery shortcode. */
			__( 'Copy shortcode %s', 'my-gallery-pro' ),
			$shortcode
		);
		?>
		<span class="mgp-shortcode-copy-wrap">
			<button type="button" class="mgp-shortcode-copy" data-shortcode="<?php echo esc_attr( $shortcode ); ?>" aria-label="<?php echo esc_attr( $copy_label ); ?>">
				<code><?php echo esc_html( $shortcode ); ?></code>
				<span class="mgp-shortcode-copy-label" aria-hidden="true"><?php echo esc_html__( 'Copy', 'my-gallery-pro' ); ?></span>
			</button>
			<span class="screen-reader-text mgp-shortcode-copy-status" aria-live="polite"></span>
		</span>
		<?php
	}

	/**
	 * Render one gallery row in the overview.
	 *
	 * @param object $gallery Gallery post object.
	 * @return void
	 */
	private static function render_gallery_row( $gallery ): void {
		$gallery_id = (int) $gallery->ID;
		$image_ids  = self::sanitize_submitted_image_ids( GalleryPostType::get_image_ids( $gallery_id ) );
		$settings   = GalleryPostType::get_settings( $gallery_id );
		$title      = get_the_title( $gallery_id );
		$layout     = 'masonry' === $settings['layout'] ? esc_html__( 'Masonry', 'my-gallery-pro' ) : esc_html__( 'Grid', 'my-gallery-pro' );

		if ( '' === $title ) {
			$title = esc_html__( 'Untitled gallery', 'my-gallery-pro' );
		}
		?>
		<tr>
			<td class="column-preview">
				<div class="mgp-gallery-row-preview">
				<?php if ( ! empty( $image_ids ) ) : ?>
					<?php echo wp_get_attachment_image( $image_ids[0], 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
				<?php endif; ?>
				</div>
			</td>
			<td class="column-title">
				<strong><a class="row-title" href="<?php echo esc_url( self::get_editor_url( $gallery_id ) ); ?>"><?php echo esc_html( $title ); ?></a></strong>
			</td>
			<td class="column-layout">
				<strong><?php echo esc_html( $layout ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: desktop columns, 2: mobile columns. */
						esc_html__( '%1$d desktop / %2$d mobile', 'my-gallery-pro' ),
						(int) $settings['columns'],
						(int) $settings['mobile_columns']
					);
					?>
				</span>
			</td>
			<td class="column-images">
				<?php
				printf(
					/* translators: %d: number of images. */
					esc_html( _n( '%d image', '%d images', count( $image_ids ), 'my-gallery-pro' ) ),
					(int) count( $image_ids )
				);
				?>
			</td>
			<td class="column-shortcode"><?php self::render_shortcode_copy( $gallery_id ); ?></td>
			<td class="column-actions">
				<div class="mgp-row-actions">
					<?php if ( current_user_can( 'edit_post', $gallery_id ) ) : ?>
						<a class="button button-small" href="<?php echo esc_url( self::get_editor_url( $gallery_id ) ); ?>"><?php echo esc_html__( 'Edit', 'my-gallery-pro' ); ?></a>
					<?php endif; ?>
					<?php if ( current_user_can( 'delete_post', $gallery_id ) ) : ?>
						<form class="mgp-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php echo esc_attr__( 'Move this gallery to the trash?', 'my-gallery-pro' ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_ACTION ); ?>" />
							<input type="hidden" name="gallery_id" value="<?php echo esc_attr( (string) $gallery_id ); ?>" />
							<?php wp_nonce_field( self::DELETE_ACTION . '_' . $gallery_id, 'my_gallery_pro_nonce' ); ?>
							<button type="submit" class="button button-small button-link-delete"><?php echo esc_html__( 'Trash', 'my-gallery-pro' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a selected Media Library image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $index         Zero-based image position.
	 * @param int $total         Total selected images.
	 * @return void
	 */
	private static function render_media_item( int $attachment_id, int $index, int $total ): void {
		$title     = get_the_title( $attachment_id );
		$title     = '' !== $title ? $title : sprintf( esc_html__( 'Image %d', 'my-gallery-pro' ), $attachment_id );
		$position  = $index + 1;
		$is_public = 'publish' === get_post_status( $attachment_id );
		?>
		<li class="mgp-media-item<?php echo $is_public ? '' : ' is-not-public'; ?>" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
			<input type="hidden" name="my_gallery_pro_image_ids[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />
			<div class="mgp-media-thumbnail"><?php echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => '' ) ); ?></div>
			<strong class="mgp-media-title"><?php echo esc_html( $title ); ?></strong>
			<?php if ( ! $is_public ) : ?>
				<span class="mgp-media-visibility"><?php echo esc_html__( 'Hidden until published', 'my-gallery-pro' ); ?></span>
			<?php endif; ?>
			<div class="mgp-media-actions">
				<button type="button" class="button-link mgp-move-earlier" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Move %1$s earlier; position %2$d of %3$d', 'my-gallery-pro' ), $title, $position, $total ) ); ?>"<?php echo 1 === $position ? ' disabled' : ''; ?>>&larr;</button>
				<button type="button" class="button-link mgp-move-later" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Move %1$s later; position %2$d of %3$d', 'my-gallery-pro' ), $title, $position, $total ) ); ?>"<?php echo $position === $total ? ' disabled' : ''; ?>>&rarr;</button>
				<button type="button" class="button-link-delete mgp-remove-image" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Remove %1$s; position %2$d of %3$d', 'my-gallery-pro' ), $title, $position, $total ) ); ?>"><?php echo esc_html__( 'Remove', 'my-gallery-pro' ); ?></button>
			</div>
		</li>
		<?php
	}

	/**
	 * Render layout settings.
	 *
	 * @param array{layout:string,columns:int,mobile_columns:int,gap:int,aspect:string,lightbox:bool,captions:bool} $settings Gallery settings.
	 * @return void
	 */
	private static function render_settings_fields( array $settings ): void {
		?>
		<div class="mgp-settings-grid">
		<label class="mgp-field" for="my-gallery-pro-layout">
			<span><?php echo esc_html__( 'Layout style', 'my-gallery-pro' ); ?></span>
			<select id="my-gallery-pro-layout" name="gallery_layout">
				<option value="grid" <?php selected( $settings['layout'], 'grid' ); ?>><?php echo esc_html__( 'Grid', 'my-gallery-pro' ); ?></option>
				<option value="masonry" <?php selected( $settings['layout'], 'masonry' ); ?>><?php echo esc_html__( 'Masonry', 'my-gallery-pro' ); ?></option>
			</select>
			<small><?php echo esc_html__( 'Grid uses even rows. Masonry keeps each image tile together while filling vertical columns.', 'my-gallery-pro' ); ?></small>
		</label>
		<label class="mgp-field" for="my-gallery-pro-columns">
			<span><?php echo esc_html__( 'Desktop columns', 'my-gallery-pro' ); ?></span>
			<select id="my-gallery-pro-columns" name="gallery_columns">
				<?php foreach ( array( 1, 2, 3, 4, 5, 6 ) as $columns ) : ?>
					<option value="<?php echo esc_attr( (string) $columns ); ?>" <?php selected( $settings['columns'], $columns ); ?>><?php echo esc_html( (string) $columns ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="mgp-field" for="my-gallery-pro-mobile-columns">
			<span><?php echo esc_html__( 'Mobile columns', 'my-gallery-pro' ); ?></span>
			<select id="my-gallery-pro-mobile-columns" name="gallery_mobile_columns">
				<?php foreach ( array( 1, 2, 3 ) as $columns ) : ?>
					<option value="<?php echo esc_attr( (string) $columns ); ?>" <?php selected( $settings['mobile_columns'], $columns ); ?>><?php echo esc_html( (string) $columns ); ?></option>
				<?php endforeach; ?>
			</select>
			<small><?php echo esc_html__( 'Used when the gallery container is 782 pixels wide or narrower.', 'my-gallery-pro' ); ?></small>
		</label>
		<label class="mgp-field" for="my-gallery-pro-gap">
			<span><?php echo esc_html__( 'Image spacing', 'my-gallery-pro' ); ?></span>
			<select id="my-gallery-pro-gap" name="gallery_gap">
				<?php foreach ( array( 0, 8, 16, 24, 32 ) as $gap ) : ?>
					<option value="<?php echo esc_attr( (string) $gap ); ?>" <?php selected( $settings['gap'], $gap ); ?>>
						<?php echo 0 === $gap ? esc_html__( 'None', 'my-gallery-pro' ) : esc_html( sprintf( '%d px', $gap ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="mgp-field" for="my-gallery-pro-aspect">
			<span><?php echo esc_html__( 'Image shape', 'my-gallery-pro' ); ?></span>
			<select id="my-gallery-pro-aspect" name="gallery_aspect">
				<option value="natural" <?php selected( $settings['aspect'], 'natural' ); ?>><?php echo esc_html__( 'Natural', 'my-gallery-pro' ); ?></option>
				<option value="square" <?php selected( $settings['aspect'], 'square' ); ?>><?php echo esc_html__( 'Square', 'my-gallery-pro' ); ?></option>
				<option value="landscape" <?php selected( $settings['aspect'], 'landscape' ); ?>><?php echo esc_html__( 'Landscape', 'my-gallery-pro' ); ?></option>
				<option value="portrait" <?php selected( $settings['aspect'], 'portrait' ); ?>><?php echo esc_html__( 'Portrait', 'my-gallery-pro' ); ?></option>
			</select>
		</label>
		</div>
		<div class="mgp-settings-checks">
		<label class="mgp-check-field">
			<input type="checkbox" name="gallery_lightbox" value="1" <?php checked( $settings['lightbox'] ); ?> />
			<span><?php echo esc_html__( 'Open images in the accessible viewer', 'my-gallery-pro' ); ?></span>
		</label>
		<label class="mgp-check-field">
			<input type="checkbox" name="gallery_captions" value="1" <?php checked( $settings['captions'] ); ?> />
			<span><?php echo esc_html__( 'Show Media Library captions below gallery images', 'my-gallery-pro' ); ?></span>
		</label>
		</div>
		<?php
	}

	/**
	 * Render save/delete notices from an allow-listed query flag.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		$saved   = self::requested_flag( 'saved' );
		$deleted = self::requested_flag( 'deleted' );

		if ( '1' === $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gallery saved.', 'my-gallery-pro' ) . '</p></div>';
		}

		if ( '1' === $deleted ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gallery moved to the trash. Media Library images were not deleted.', 'my-gallery-pro' ) . '</p></div>';
		}
	}

	/**
	 * Render compact previous/next navigation for the bounded overview query.
	 *
	 * @param int  $current_page Current one-based page number.
	 * @param bool $has_next     Whether another page of manageable galleries exists.
	 * @return void
	 */
	private static function render_pagination( int $current_page, bool $has_next ): void {
		if ( 1 === $current_page && ! $has_next ) {
			return;
		}
		?>
		<nav class="mgp-pagination" aria-label="<?php echo esc_attr__( 'Gallery pages', 'my-gallery-pro' ); ?>">
			<?php if ( $current_page > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( self::get_overview_page_url( $current_page - 1 ) ); ?>">&larr; <?php echo esc_html__( 'Previous', 'my-gallery-pro' ); ?></a>
			<?php endif; ?>
			<span>
				<?php
				printf(
					/* translators: %d: current gallery overview page number. */
					esc_html__( 'Page %d', 'my-gallery-pro' ),
					$current_page
				);
				?>
			</span>
			<?php if ( $has_next ) : ?>
				<a class="button" href="<?php echo esc_url( self::get_overview_page_url( $current_page + 1 ) ); ?>"><?php echo esc_html__( 'Next', 'my-gallery-pro' ); ?> &rarr;</a>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Return a paginated overview URL.
	 *
	 * @param int $page_number One-based page number.
	 * @return string
	 */
	private static function get_overview_page_url( int $page_number ): string {
		return add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				'gallery_page' => max( 1, $page_number ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build safe wp_enqueue_media arguments for the requested editor object.
	 *
	 * WordPress expects a real post when the post argument is supplied, so an
	 * invalid or unauthorized query value must never be forwarded.
	 *
	 * @return array<string,int>
	 */
	private static function media_library_args(): array {
		$gallery_id = self::requested_gallery_id();

		if ( $gallery_id < 1 ) {
			return array();
		}

		$gallery = get_post( $gallery_id );

		if (
			! $gallery ||
			GalleryPostType::POST_TYPE !== $gallery->post_type ||
			! self::is_editable_status( $gallery ) ||
			! current_user_can( 'edit_post', $gallery_id )
		) {
			return array();
		}

		return array( 'post' => $gallery_id );
	}

	/**
	 * Validate Media Library selections against the current user's object access.
	 *
	 * @param mixed $value Submitted attachment IDs.
	 * @return array<int,int>
	 */
	private static function sanitize_submitted_image_ids( $value ): array {
		return array_values(
			array_filter(
				GalleryPostType::sanitize_image_ids( $value ),
				static function ( int $attachment_id ): bool {
					return current_user_can( 'edit_post', $attachment_id );
				}
			)
		);
	}

	/**
	 * Return a safe character count with a conservative extension fallback.
	 *
	 * @param string $text Text to measure.
	 * @return int
	 */
	private static function text_length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/**
	 * Determine whether a gallery is in a state the editor may modify.
	 *
	 * Mirrors core's Trash behavior: a trashed gallery must not be edited or
	 * silently republished by the save handler, which forces publish status.
	 * The allow list matches the statuses the overview query lists.
	 *
	 * @param object $gallery Gallery post object.
	 * @return bool
	 */
	private static function is_editable_status( $gallery ): bool {
		$status = isset( $gallery->post_status ) ? (string) $gallery->post_status : '';

		return in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true );
	}

	/**
	 * Require gallery-management capability.
	 *
	 * @return void
	 */
	private static function authorize(): void {
		if ( ! self::can_manage_galleries() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'my-gallery-pro' ) );
		}
	}

	/**
	 * Determine whether the current user can access all gallery-management flows.
	 *
	 * @return bool
	 */
	private static function can_manage_galleries(): bool {
		return current_user_can( self::CAPABILITY ) && current_user_can( 'edit_posts' ) && current_user_can( 'publish_posts' );
	}

	/**
	 * Require POST for mutating handlers.
	 *
	 * @return void
	 */
	private static function require_post_request(): void {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( 'POST' !== $request_method ) {
			wp_die( esc_html__( 'This action requires a POST request.', 'my-gallery-pro' ) );
		}
	}

	/**
	 * Read an integer from POST without accepting arrays or objects.
	 *
	 * @param string $key POST key.
	 * @return int
	 */
	private static function posted_integer( string $key ): int {
		if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) || is_object( $_POST[ $key ] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Read sanitized text from POST without accepting arrays or objects.
	 *
	 * @param string $key POST key.
	 * @return string
	 */
	private static function posted_text( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) || is_object( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Read a gallery ID from the current admin request.
	 *
	 * @return int
	 */
	private static function requested_gallery_id(): int {
		if ( ! isset( $_GET['gallery_id'] ) || is_array( $_GET['gallery_id'] ) || is_object( $_GET['gallery_id'] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_GET['gallery_id'] ) );
	}

	/**
	 * Read the bounded one-based overview page number.
	 *
	 * @return int
	 */
	private static function requested_page_number(): int {
		if ( ! isset( $_GET['gallery_page'] ) || is_array( $_GET['gallery_page'] ) || is_object( $_GET['gallery_page'] ) ) {
			return 1;
		}

		return max( 1, absint( wp_unslash( $_GET['gallery_page'] ) ) );
	}

	/**
	 * Read an allow-listed scalar query flag.
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private static function requested_flag( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) || is_object( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_key( wp_unslash( $_GET[ $key ] ) );
	}
}
