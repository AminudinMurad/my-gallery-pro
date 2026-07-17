( function () {
	'use strict';

	var config = window.mygalleryProFrontend || {};
	var masonryGalleries = Array.prototype.slice.call( document.querySelectorAll( '.my-gallery-pro-grid.is-masonry-layout' ) );
	var masonryFrame = 0;
	var masonryResizeObserver;

	function shortestColumn( heights ) {
		var shortest = 0;

		heights.forEach( function ( height, index ) {
			if ( height < heights[ shortest ] ) {
				shortest = index;
			}
		} );

		return shortest;
	}

	function computedColumnCount( gallery, styles ) {
		var configured = parseInt( styles.getPropertyValue( '--mgp-active-columns' ), 10 );
		var template;

		if ( configured >= 1 && configured <= 6 ) {
			return configured;
		}

		template = String( styles.gridTemplateColumns || '' ).trim();

		if ( template && 'none' !== template ) {
			return Math.min( 6, template.split( /\s+/ ).length );
		}

		return gallery.classList.contains( 'has-1-columns' ) ? 1 : 3;
	}

	function layoutMasonry( gallery ) {
		var items;
		var styles;
		var columns;
		var gap;
		var heights;
		var itemHeights;

		if ( gallery.clientWidth < 1 ) {
			return;
		}

		items = Array.prototype.slice.call( gallery.children ).filter( function ( item ) {
			return item.classList.contains( 'my-gallery-pro-item' );
		} );

		if ( ! items.length ) {
			return;
		}

		gallery.classList.add( 'is-masonry-enhanced' );
		gallery.style.removeProperty( 'height' );
		items.forEach( function ( item ) {
			item.style.gridColumnStart = '1';
			item.style.transform = 'translateY(0)';
		} );

		styles = window.getComputedStyle( gallery );
		columns = computedColumnCount( gallery, styles );
		gap = parseFloat( styles.getPropertyValue( '--mgp-gap' ) ) || 0;
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

		gallery.style.height = Math.max( 0, Math.max.apply( null, heights ) - gap ) + 'px';
	}

	function scheduleMasonryLayout() {
		if ( masonryFrame ) {
			return;
		}

		masonryFrame = window.requestAnimationFrame( function () {
			masonryFrame = 0;
			masonryGalleries.forEach( layoutMasonry );
		} );
	}

	function initializeMasonry() {
		var observedWidths;

		if ( ! masonryGalleries.length || ! window.CSS || ! window.CSS.supports( 'display', 'grid' ) ) {
			return;
		}

		masonryGalleries.forEach( function ( gallery ) {
			Array.prototype.forEach.call( gallery.querySelectorAll( 'img' ), function ( galleryImage ) {
				if ( ! galleryImage.complete ) {
					galleryImage.addEventListener( 'load', scheduleMasonryLayout, { once: true } );
					galleryImage.addEventListener( 'error', scheduleMasonryLayout, { once: true } );
				}
			} );
		} );

		window.addEventListener( 'resize', scheduleMasonryLayout );

		if ( 'ResizeObserver' in window ) {
			observedWidths = new WeakMap();
			masonryResizeObserver = new window.ResizeObserver( function ( entries ) {
				var widthChanged = false;

				entries.forEach( function ( entry ) {
					var previousWidth = observedWidths.get( entry.target );
					var currentWidth = entry.contentRect.width;

					observedWidths.set( entry.target, currentWidth );
					if ( undefined === previousWidth || Math.abs( currentWidth - previousWidth ) > 0.5 ) {
						widthChanged = true;
					}
				} );

				if ( widthChanged ) {
					scheduleMasonryLayout();
				}
			} );
			masonryGalleries.forEach( function ( gallery ) {
				masonryResizeObserver.observe( gallery.parentElement || gallery );
			} );
		}

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( scheduleMasonryLayout );
		}

		scheduleMasonryLayout();
	}

	initializeMasonry();

	var dialog = document.createElement( 'dialog' );
	var links = [];
	var currentIndex = 0;
	var openingLink = null;
	var shell;
	var title;
	var image;
	var caption;
	var position;
	var status;
	var closeButton;
	var previousButton;
	var nextButton;

	if ( typeof dialog.showModal !== 'function' || ! document.querySelector( '[data-mgp-viewer]' ) ) {
		return;
	}

	function makeElement( tagName, className, text ) {
		var element = document.createElement( tagName );

		if ( className ) {
			element.className = className;
		}
		if ( typeof text === 'string' ) {
			element.textContent = text;
		}

		return element;
	}

	function positionText() {
		return String( config.positionText || 'Image %1$d of %2$d' )
			.replace( '%1$d', currentIndex + 1 )
			.replace( '%2$d', links.length );
	}

	function updateDialog() {
		var link = links[ currentIndex ];
		var thumbnail = link.querySelector( 'img' );

		image.src = link.href;
		image.alt = thumbnail ? thumbnail.alt : '';
		caption.textContent = link.getAttribute( 'data-mgp-caption' ) || '';
		position.textContent = positionText();
		previousButton.disabled = links.length < 2;
		nextButton.disabled = links.length < 2;
	}

	function announceCurrent() {
		var description = image.alt || caption.textContent || '';
		var message = positionText();

		if ( description ) {
			message += '. ' + description;
		}

		status.textContent = '';
		window.setTimeout( function () {
			status.textContent = message;
		}, 0 );
	}

	function moveTo( index ) {
		if ( ! links.length ) {
			return;
		}

		currentIndex = ( index + links.length ) % links.length;
		updateDialog();
		announceCurrent();
	}

	function openFromLink( link ) {
		var gallery = link.closest( '[data-mgp-gallery]' );

		if ( ! gallery ) {
			return;
		}

		links = Array.prototype.slice.call( gallery.querySelectorAll( '[data-mgp-viewer]' ) );
		currentIndex = links.indexOf( link );
		openingLink = link;
		title.textContent = gallery.getAttribute( 'data-mgp-title' ) || config.dialogLabel || 'Gallery image viewer';
		updateDialog();
		document.body.classList.add( 'my-gallery-pro-viewer-open' );
		dialog.showModal();
		closeButton.focus();
		announceCurrent();
	}

	dialog.className = 'my-gallery-pro-dialog';

	shell = makeElement( 'div', 'my-gallery-pro-dialog-shell' );
	var header = makeElement( 'header', 'my-gallery-pro-dialog-header' );
	title = makeElement( 'p', 'my-gallery-pro-dialog-title' );
	title.id = 'my-gallery-pro-dialog-title';
	dialog.setAttribute( 'aria-labelledby', title.id );
	closeButton = makeElement( 'button', 'my-gallery-pro-dialog-close', '×' );
	closeButton.type = 'button';
	closeButton.setAttribute( 'aria-label', config.closeLabel || 'Close image viewer' );
	header.appendChild( title );
	header.appendChild( closeButton );

	var media = makeElement( 'div', 'my-gallery-pro-dialog-media' );
	previousButton = makeElement( 'button', 'my-gallery-pro-dialog-nav my-gallery-pro-dialog-previous', '←' );
	previousButton.type = 'button';
	previousButton.setAttribute( 'aria-label', config.previousLabel || 'Previous image' );
	var figure = makeElement( 'figure', 'my-gallery-pro-dialog-figure' );
	image = makeElement( 'img', 'my-gallery-pro-dialog-image' );
	caption = makeElement( 'figcaption', 'my-gallery-pro-dialog-caption' );
	figure.appendChild( image );
	figure.appendChild( caption );
	nextButton = makeElement( 'button', 'my-gallery-pro-dialog-nav my-gallery-pro-dialog-next', '→' );
	nextButton.type = 'button';
	nextButton.setAttribute( 'aria-label', config.nextLabel || 'Next image' );
	media.appendChild( previousButton );
	media.appendChild( figure );
	media.appendChild( nextButton );

	var footer = makeElement( 'footer', 'my-gallery-pro-dialog-footer' );
	position = makeElement( 'p', 'my-gallery-pro-dialog-position' );
	status = makeElement( 'p', 'my-gallery-pro-screen-reader-text' );
	status.setAttribute( 'role', 'status' );
	status.setAttribute( 'aria-live', 'polite' );
	status.setAttribute( 'aria-atomic', 'true' );
	footer.appendChild( position );
	footer.appendChild( status );

	shell.appendChild( header );
	shell.appendChild( media );
	shell.appendChild( footer );
	dialog.appendChild( shell );
	document.body.appendChild( dialog );

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( '[data-mgp-viewer]' ) : null;

		if ( ! link ) {
			return;
		}

		event.preventDefault();
		openFromLink( link );
	} );

	closeButton.addEventListener( 'click', function () {
		dialog.close();
	} );
	previousButton.addEventListener( 'click', function () {
		moveTo( currentIndex - 1 );
	} );
	nextButton.addEventListener( 'click', function () {
		moveTo( currentIndex + 1 );
	} );

	dialog.addEventListener( 'click', function ( event ) {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );

	dialog.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			dialog.close();
		} else if ( 'ArrowLeft' === event.key ) {
			event.preventDefault();
			moveTo( currentIndex - 1 );
		} else if ( 'ArrowRight' === event.key ) {
			event.preventDefault();
			moveTo( currentIndex + 1 );
		} else if ( 'Home' === event.key ) {
			event.preventDefault();
			moveTo( 0 );
		} else if ( 'End' === event.key ) {
			event.preventDefault();
			moveTo( links.length - 1 );
		}
	} );

	dialog.addEventListener( 'close', function () {
		document.body.classList.remove( 'my-gallery-pro-viewer-open' );
		image.removeAttribute( 'src' );

		if ( openingLink && openingLink.isConnected ) {
			openingLink.focus();
		}
	} );
}() );
