( function ( $, wp ) {
	'use strict';

	var config = window.mygalleryProAdmin || {};
	var $list = $( '#my-gallery-pro-images' );
	var $empty = $( '#my-gallery-pro-empty-images' );
	var $count = $( '#my-gallery-pro-image-count' );
	var $status = $( '#my-gallery-pro-image-status' );
	var $addButton = $( '#my-gallery-pro-add-images' );
	var $preview = $( '#my-gallery-pro-preview' );
	var $emptyPreview = $( '#my-gallery-pro-empty-preview' );
	var $tabs = $( '[data-mgp-tab]' );
	var $panels = $( '[data-mgp-panel]' );
	var $layout = $( '#my-gallery-pro-layout' );
	var $columns = $( '#my-gallery-pro-columns' );
	var $mobileColumns = $( '#my-gallery-pro-mobile-columns' );
	var $gap = $( '#my-gallery-pro-gap' );
	var $aspect = $( '#my-gallery-pro-aspect' );
	var maxImages = parseInt( config.maxImages, 10 ) || 200;
	var frame;
	var masonryFrame = 0;
	var previewResizeObserver;

	$( document ).on( 'submit', '.mgp-delete-form', function ( event ) {
		var message = $( this ).attr( 'data-confirm' );

		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	function legacyCopy( shortcode ) {
		var textarea = document.createElement( 'textarea' );
		var copied = false;

		textarea.value = shortcode;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();
		textarea.setSelectionRange( 0, shortcode.length );

		try {
			copied = document.execCommand( 'copy' );
		} catch ( error ) {
			void error;
		}

		document.body.removeChild( textarea );
		return copied;
	}

	function showCopyFeedback( $button, copied ) {
		var $label = $button.find( '.mgp-shortcode-copy-label' );
		var $status = $button.siblings( '.mgp-shortcode-copy-status' );
		var message = copied ? ( config.copiedText || 'Copied!' ) : ( config.copyFailedText || 'Copy failed' );
		var resetTimer = $button.data( 'mgp-copy-reset' );

		if ( resetTimer ) {
			window.clearTimeout( resetTimer );
		}

		$label.text( message );
		$status.text( '' );
		window.setTimeout( function () {
			$status.text( message );
		}, 0 );
		$button.data( 'mgp-copy-reset', window.setTimeout( function () {
			$label.text( config.copyText || 'Copy' );
		}, 1800 ) );
	}

	$( document ).on( 'click', '.mgp-shortcode-copy', function () {
		var $button = $( this );
		var shortcode = String( $button.attr( 'data-shortcode' ) || '' );

		if ( ! shortcode ) {
			showCopyFeedback( $button, false );
			return;
		}

		if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
			try {
				window.navigator.clipboard.writeText( shortcode ).then( function () {
					showCopyFeedback( $button, true );
				}, function () {
					showCopyFeedback( $button, legacyCopy( shortcode ) );
				} );
				return;
			} catch ( error ) {
				void error;
			}
		}

		showCopyFeedback( $button, legacyCopy( shortcode ) );
	} );

	if ( ! $list.length ) {
		return;
	}

	function selectedIds() {
		return $list.children( '.mgp-media-item' ).map( function () {
			return parseInt( $( this ).attr( 'data-attachment-id' ), 10 );
		} ).get().filter( function ( id ) {
			return Number.isInteger( id ) && id > 0;
		} );
	}

	function formatText( template, title, position, total ) {
		return String( template || '' )
			.replace( '%1$s', title )
			.replace( '%2$d', position )
			.replace( '%3$d', total );
	}

	function itemTitle( $item ) {
		return $.trim( $item.find( '.mgp-media-title' ).first().text() ) || config.imageFallback || 'Image';
	}

	function announce( message ) {
		$status.text( '' );
		window.setTimeout( function () {
			$status.text( message );
		}, 0 );
	}

	function selectedSetting( $field, allowed, fallback ) {
		var value = String( $field.val() || '' );

		return allowed.indexOf( value ) >= 0 ? value : fallback;
	}

	function shortestColumn( heights ) {
		var shortest = 0;

		heights.forEach( function ( height, index ) {
			if ( height < heights[ shortest ] ) {
				shortest = index;
			}
		} );

		return shortest;
	}

	function computedPreviewColumnCount( preview, styles ) {
		var configured = parseInt( styles.getPropertyValue( '--mgp-preview-active-columns' ), 10 );
		var template;

		if ( configured >= 1 && configured <= 6 ) {
			return configured;
		}

		template = String( styles.gridTemplateColumns || '' ).trim();

		if ( template && 'none' !== template ) {
			return Math.min( 6, template.split( /\s+/ ).length );
		}

		return preview.classList.contains( 'has-1-columns' ) ? 1 : 3;
	}

	function resetPreviewMasonry() {
		if ( ! $preview.length ) {
			return;
		}

		$preview.removeClass( 'is-masonry-enhanced' )[0].style.removeProperty( 'height' );
		$preview.children( '.mgp-preview-item' ).each( function () {
			this.style.removeProperty( 'grid-column-start' );
			this.style.removeProperty( 'transform' );
		} );
	}

	function layoutPreviewMasonry() {
		var preview;
		var items;
		var styles;
		var columns;
		var gap;
		var heights;
		var itemHeights;

		if ( ! $preview.length || ! $preview.hasClass( 'is-masonry-layout' ) ) {
			resetPreviewMasonry();
			return;
		}

		preview = $preview[0];

		if ( preview.clientWidth < 1 ) {
			return;
		}

		items = Array.prototype.slice.call( preview.querySelectorAll( '.mgp-preview-item' ) );

		if ( ! items.length ) {
			resetPreviewMasonry();
			return;
		}

		preview.classList.add( 'is-masonry-enhanced' );
		preview.style.removeProperty( 'height' );
		items.forEach( function ( item ) {
			item.style.gridColumnStart = '1';
			item.style.transform = 'translateY(0)';
		} );

		styles = window.getComputedStyle( preview );
		columns = computedPreviewColumnCount( preview, styles );
		gap = parseFloat( styles.getPropertyValue( '--mgp-preview-gap' ) ) || 0;
		heights = Array.apply( null, Array( columns ) ).map( function () {
			return 0;
		} );
		itemHeights = items.map( function ( item ) {
			return item.getBoundingClientRect().height;
		} );

		items.forEach( function ( item, index ) {
			var column = index < columns ? index : shortestColumn( heights );

			item.style.gridColumnStart = String( column + 1 );
			item.style.transform = 'translateY(' + heights[ column ] + 'px)';
			heights[ column ] += itemHeights[ index ] + gap;
		} );

		preview.style.height = Math.max( 0, Math.max.apply( null, heights ) - gap ) + 'px';
	}

	function schedulePreviewMasonry() {
		if ( masonryFrame ) {
			return;
		}

		masonryFrame = window.requestAnimationFrame( function () {
			masonryFrame = 0;
			layoutPreviewMasonry();
		} );
	}

	function refreshPreview() {
		var layout = selectedSetting( $layout, [ 'grid', 'masonry' ], 'grid' );
		var columns = selectedSetting( $columns, [ '1', '2', '3', '4', '5', '6' ], '3' );
		var mobileColumns = selectedSetting( $mobileColumns, [ '1', '2', '3' ], '1' );
		var gap = selectedSetting( $gap, [ '0', '8', '16', '24', '32' ], '16' );
		var aspect = selectedSetting( $aspect, [ 'natural', 'square', 'landscape', 'portrait' ], 'square' );
		var total = $list.children( '.mgp-media-item' ).length;

		if ( ! $preview.length ) {
			return;
		}

		$preview
			.attr( 'class', 'mgp-gallery-preview is-' + layout + '-layout is-' + aspect + ' has-' + columns + '-columns has-' + mobileColumns + '-mobile-columns has-' + gap + '-gap' )
			.empty();

		$list.children( '.mgp-media-item' ).each( function () {
			var $source = $( this ).find( '.mgp-media-thumbnail img' ).first();
			var sourceUrl = $source.attr( 'src' ) || '';
			var $item;

			if ( ! sourceUrl ) {
				return;
			}

			$item = $( '<figure>', { 'class': 'mgp-preview-item' } );
			$item.append( $( '<img>', { src: sourceUrl, alt: '' } ).on( 'load error', schedulePreviewMasonry ) );
			$preview.append( $item );
		} );

		$emptyPreview.toggleClass( 'is-hidden', total > 0 );
		schedulePreviewMasonry();
	}

	function updateState() {
		var total = selectedIds().length;
		var text = total === 1 ? config.countSingular : String( config.countPlural || '%d images selected' ).replace( '%d', total );

		$empty.toggleClass( 'is-hidden', total > 0 );
		if ( $count.text() !== text ) {
			$count.text( text );
		}

		refreshPreview();
	}

	function refreshControls() {
		var $items = $list.children( '.mgp-media-item' );
		var total = $items.length;

		$items.each( function ( index ) {
			var $item = $( this );
			var title = itemTitle( $item );
			var position = index + 1;

			$item.find( '.mgp-move-earlier' )
				.prop( 'disabled', 0 === index )
				.attr( 'aria-label', formatText( config.moveEarlierLabel || 'Move %1$s earlier; position %2$d of %3$d', title, position, total ) );
			$item.find( '.mgp-move-later' )
				.prop( 'disabled', index === total - 1 )
				.attr( 'aria-label', formatText( config.moveLaterLabel || 'Move %1$s later; position %2$d of %3$d', title, position, total ) );
			$item.find( '.mgp-remove-image' )
				.attr( 'aria-label', formatText( config.removeLabel || 'Remove %1$s; position %2$d of %3$d', title, position, total ) );
		} );

		updateState();
	}

	function announceMoved( $item ) {
		var position = $item.index() + 1;
		var total = $list.children( '.mgp-media-item' ).length;

		announce( formatText( config.movedText || '%1$s moved to position %2$d of %3$d.', itemTitle( $item ), position, total ) );
	}

	function thumbnailUrl( attachment ) {
		if ( attachment.sizes && attachment.sizes.thumbnail ) {
			return attachment.sizes.thumbnail.url;
		}

		return attachment.icon || attachment.url || '';
	}

	function addAttachment( attachment ) {
		var id = parseInt( attachment.id, 10 );
		var title;
		var $item;
		var $thumbnail;
		var $actions;

		if ( ! Number.isInteger( id ) || id < 1 || selectedIds().length >= maxImages || $list.find( '[data-attachment-id="' + id + '"]' ).length ) {
			return;
		}

		title = attachment.title || attachment.filename || ( config.imageFallback || 'Image' ) + ' ' + id;
		$item = $( '<li>', {
			'class': 'mgp-media-item',
			'data-attachment-id': id
		} );
		$item.append( $( '<input>', {
			type: 'hidden',
			name: 'my_gallery_pro_image_ids[]',
			value: id
		} ) );

		$thumbnail = $( '<div>', { 'class': 'mgp-media-thumbnail' } );
		$thumbnail.append( $( '<img>', {
			src: thumbnailUrl( attachment ),
			alt: ''
		} ) );
		$item.append( $thumbnail );
		$item.append( $( '<strong>', { 'class': 'mgp-media-title' } ).text( title ) );

		$actions = $( '<div>', { 'class': 'mgp-media-actions' } );
		$actions.append( $( '<button>', {
			type: 'button',
			'class': 'button-link mgp-move-earlier',
			text: '←'
		} ) );
		$actions.append( $( '<button>', {
			type: 'button',
			'class': 'button-link mgp-move-later',
			text: '→'
		} ) );
		$actions.append( $( '<button>', {
			type: 'button',
			'class': 'button-link-delete mgp-remove-image',
			text: config.removeText || 'Remove'
		} ) );
		$item.append( $actions );
		$list.append( $item );
	}

	$addButton.on( 'click', function () {
		if ( ! frame ) {
			frame = wp.media( {
				title: config.frameTitle || 'Choose gallery images',
				button: { text: config.frameButton || 'Use selected images' },
				library: { type: 'image' },
				multiple: 'add'
			} );

			frame.on( 'open', function () {
				var selection = frame.state().get( 'selection' );

				selection.reset();
				selectedIds().forEach( function ( id ) {
					selection.add( wp.media.attachment( id ) );
				} );
			} );

			frame.on( 'select', function () {
				var existing = {};
				var selection = frame.state().get( 'selection' );

				$list.children( '.mgp-media-item' ).each( function () {
					var $item = $( this );
					existing[ parseInt( $item.attr( 'data-attachment-id' ), 10 ) ] = $item.detach();
				} );

				selection.each( function ( model, index ) {
					var attachment = model.toJSON();
					var id = parseInt( attachment.id, 10 );

					if ( index >= maxImages ) {
						return;
					}

					if ( existing[ id ] ) {
						$list.append( existing[ id ] );
						delete existing[ id ];
					} else {
						addAttachment( attachment );
					}
				} );
				refreshControls();

				if ( selection.length > maxImages ) {
					announce( String( config.limitText || 'Only the first %d images were selected.' ).replace( '%d', maxImages ) );
				} else {
					announce( $count.text() );
				}
			} );
		}

		frame.open();
	} );

	$tabs.on( 'click', function () {
		var target = String( $( this ).attr( 'data-mgp-tab' ) || '' );

		if ( [ 'manage', 'preview' ].indexOf( target ) < 0 ) {
			return;
		}

		$tabs.each( function () {
			var $tab = $( this );
			var isSelected = target === $tab.attr( 'data-mgp-tab' );

			$tab
				.toggleClass( 'is-active', isSelected )
				.attr( 'aria-selected', isSelected ? 'true' : 'false' )
				.attr( 'tabindex', isSelected ? '0' : '-1' );
		} );
		$panels.each( function () {
			var $panel = $( this );

			$panel.prop( 'hidden', target !== $panel.attr( 'data-mgp-panel' ) );
		} );

		if ( 'preview' === target ) {
			refreshPreview();
		}
	} );

	$tabs.on( 'keydown', function ( event ) {
		var currentIndex;
		var nextIndex;

		if ( 'ArrowLeft' !== event.key && 'ArrowRight' !== event.key ) {
			return;
		}

		currentIndex = $tabs.index( this );
		nextIndex = 'ArrowRight' === event.key ? currentIndex + 1 : currentIndex - 1;

		if ( nextIndex < 0 ) {
			nextIndex = $tabs.length - 1;
		} else if ( nextIndex >= $tabs.length ) {
			nextIndex = 0;
		}

		event.preventDefault();
		$tabs.eq( nextIndex ).trigger( 'focus' ).trigger( 'click' );
	} );

	$layout.add( $columns ).add( $mobileColumns ).add( $gap ).add( $aspect ).on( 'change', refreshPreview );
	$( window ).on( 'resize', schedulePreviewMasonry );

	if ( $preview.length && 'ResizeObserver' in window ) {
		var previewWidth;

		previewResizeObserver = new window.ResizeObserver( function ( entries ) {
			var currentWidth = entries[0] ? entries[0].contentRect.width : 0;

			if ( undefined === previewWidth || Math.abs( currentWidth - previewWidth ) > 0.5 ) {
				previewWidth = currentWidth;
				schedulePreviewMasonry();
			}
		} );
		previewResizeObserver.observe( $preview[0] );
	}

	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( schedulePreviewMasonry );
	}

	$list.on( 'click', '.mgp-remove-image', function () {
		var $item = $( this ).closest( '.mgp-media-item' );
		var title = itemTitle( $item );
		var $focusTarget = $item.next( '.mgp-media-item' ).find( '.mgp-remove-image' ).first();

		if ( ! $focusTarget.length ) {
			$focusTarget = $item.prev( '.mgp-media-item' ).find( '.mgp-remove-image' ).first();
		}

		$item.remove();
		refreshControls();

		if ( $focusTarget.length ) {
			$focusTarget.trigger( 'focus' );
		} else {
			$addButton.trigger( 'focus' );
		}

		announce( formatText( config.removedText || '%1$s removed. %2$d images selected.', title, selectedIds().length, 0 ) );
	} );

	$list.on( 'click', '.mgp-move-earlier', function () {
		var $button = $( this );
		var $item = $button.closest( '.mgp-media-item' );
		var $previous = $item.prev( '.mgp-media-item' );

		if ( $previous.length ) {
			$item.insertBefore( $previous );
			refreshControls();
			if ( $button.prop( 'disabled' ) ) {
				$item.find( '.mgp-move-later' ).trigger( 'focus' );
			} else {
				$button.trigger( 'focus' );
			}
			announceMoved( $item );
		}
	} );

	$list.on( 'click', '.mgp-move-later', function () {
		var $button = $( this );
		var $item = $button.closest( '.mgp-media-item' );
		var $next = $item.next( '.mgp-media-item' );

		if ( $next.length ) {
			$item.insertAfter( $next );
			refreshControls();
			if ( $button.prop( 'disabled' ) ) {
				$item.find( '.mgp-move-earlier' ).trigger( 'focus' );
			} else {
				$button.trigger( 'focus' );
			}
			announceMoved( $item );
		}
	} );

	if ( $.fn.sortable ) {
		$list.sortable( {
			items: '.mgp-media-item',
			handle: '.mgp-media-thumbnail, .mgp-media-title',
			placeholder: 'mgp-media-placeholder',
			forcePlaceholderSize: true,
			update: function ( event, ui ) {
				void event;
				refreshControls();
				announceMoved( ui.item );
			}
		} );
	}

	refreshControls();
}( window.jQuery, window.wp ) );
