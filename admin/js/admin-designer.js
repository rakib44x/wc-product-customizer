/* global jQuery, wp, WCPC_ADMIN */
( function ( $ ) {
	'use strict';

	var sidesJsonInput = null;
	var $container     = null;
	var sides          = [];

	function uid( prefix ) {
		return ( prefix || 'r' ) + '_' + Math.random().toString( 36 ).slice( 2, 9 );
	}

	function sync() {
		sidesJsonInput.val( JSON.stringify( sides ) );
	}

	function renderAll() {
		$container.empty();
		sides.forEach( function ( side, idx ) {
			renderSide( side, idx );
		} );
		sync();
	}

	function renderSide( side, idx ) {
		var tmpl = wp.template( 'wcpc-side' );
		var $el  = $( tmpl( { index: idx, name: side.name || '', image: side.image || '' } ) );
		$container.append( $el );

		if ( side.image ) {
			$el.find( '.wcpc-canvas' ).addClass( 'wcpc-has-image' );
		}

		renderRegions( $el, side );
		bindSide( $el, side );
	}

	function renderRegions( $el, side ) {
		var $canvas = $el.find( '.wcpc-canvas' );
		var $list   = $el.find( '.wcpc-regions-list' ).attr( 'data-empty', 'No regions yet — drag on the image to add one.' );
		$canvas.find( '.wcpc-region-box' ).remove();
		$list.empty();

		( side.regions || [] ).forEach( function ( r, i ) {
			var $box = $(
				'<div class="wcpc-region-box type-' + r.type + '" data-region="' + r.id + '">' +
				'<span class="wcpc-region-label">' + escapeHtml( '#' + ( i + 1 ) + ' ' + ( r.name || r.type ) ) + '</span>' +
				'<span class="wcpc-resize-handle"></span>' +
				'</div>'
			);
			positionBox( $box, r, side );
			$canvas.append( $box );

			var rowTmpl = wp.template( 'wcpc-region-row' );
			var $row    = $( rowTmpl( { id: r.id, idx: i + 1, name: r.name || '', type: r.type, placeholder: r.placeholder || '' } ) );
			$list.append( $row );
		} );
	}

	function positionBox( $box, r, side ) {
		if ( ! side.width || ! side.height ) {
			$box.css( { left: r.x + 'px', top: r.y + 'px', width: r.w + 'px', height: r.h + 'px' } );
			return;
		}
		$box.css( {
			left:   ( r.x / side.width * 100 ) + '%',
			top:    ( r.y / side.height * 100 ) + '%',
			width:  ( r.w / side.width * 100 ) + '%',
			height: ( r.h / side.height * 100 ) + '%'
		} );
	}

	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ c ];
		} );
	}

	function bindSide( $el, side ) {
		var $canvas = $el.find( '.wcpc-canvas' );

		$el.find( '.wcpc-side-name' ).on( 'input', function () {
			side.name = $( this ).val();
			sync();
		} );

		$el.find( '.wcpc-upload-btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( {
				title: WCPC_ADMIN.i18n.chooseImage,
				button: { text: WCPC_ADMIN.i18n.useImage },
				multiple: false,
				library: { type: 'image' }
			} );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				side.image_id = a.id;
				side.image    = a.url;
				side.width    = a.width;
				side.height   = a.height;
				$canvas.css( 'background-image', 'url(' + a.url + ')' ).addClass( 'wcpc-has-image' ).find( '.wcpc-no-image' ).remove();
				side.regions  = side.regions || [];
				renderRegions( $el, side );
				sync();
			} );
			frame.open();
		} );

		$el.find( '.wcpc-remove-side' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! window.confirm( WCPC_ADMIN.i18n.confirmRemove ) ) {
				return;
			}
			var idx = $el.data( 'index' );
			sides.splice( idx, 1 );
			renderAll();
		} );

		// Draw a new region by dragging on the canvas.
		$canvas.on( 'mousedown', function ( e ) {
			if ( ! side.image ) {
				window.alert( WCPC_ADMIN.i18n.noImage );
				return;
			}
			if ( $( e.target ).closest( '.wcpc-region-box' ).length ) {
				return;
			}
			e.preventDefault();
			var rect    = $canvas[ 0 ].getBoundingClientRect();
			var startX  = e.clientX - rect.left;
			var startY  = e.clientY - rect.top;
			var $ghost  = $( '<div class="wcpc-region-box type-text"><span class="wcpc-region-label">new</span></div>' );
			$ghost.css( { left: startX + 'px', top: startY + 'px', width: 0, height: 0 } );
			$canvas.append( $ghost );

			function move( ev ) {
				var curX = ev.clientX - rect.left;
				var curY = ev.clientY - rect.top;
				var left = Math.min( startX, curX );
				var top  = Math.min( startY, curY );
				var w    = Math.abs( curX - startX );
				var h    = Math.abs( curY - startY );
				$ghost.css( { left: left + 'px', top: top + 'px', width: w + 'px', height: h + 'px' } );
			}
			function up() {
				$( document ).off( 'mousemove.wcpc', move ).off( 'mouseup.wcpc', up );
				var pos = $ghost.position();
				var w   = $ghost.width();
				var h   = $ghost.height();
				$ghost.remove();
				if ( w < 10 || h < 10 ) {
					return;
				}
				// Translate percent-position back to natural image pixels.
				var cw = $canvas.width();
				var ch = $canvas.height();
				var r  = {
					id: uid( 'r' ),
					name: '',
					type: 'text',
					x: pos.left / cw * side.width,
					y: pos.top / ch * side.height,
					w: w / cw * side.width,
					h: h / ch * side.height,
					placeholder: '',
					defaultColor: '#000000',
					defaultFont: 'Arial, sans-serif',
					defaultSize: 24
				};
				side.regions = side.regions || [];
				side.regions.push( r );
				renderRegions( $el, side );
				sync();
			}
			$( document ).on( 'mousemove.wcpc', move ).on( 'mouseup.wcpc', up );
		} );

		// Drag existing regions to reposition + resize handle.
		$canvas.on( 'mousedown', '.wcpc-region-box', function ( e ) {
			var $box = $( this );
			var rid  = $box.data( 'region' );
			var r    = ( side.regions || [] ).find( function ( x ) { return x.id === rid; } );
			if ( ! r ) {
				return;
			}
			e.stopPropagation();
			e.preventDefault();

			var rect    = $canvas[ 0 ].getBoundingClientRect();
			var cw      = rect.width;
			var ch      = rect.height;
			var startMx = e.clientX;
			var startMy = e.clientY;
			var startX  = r.x, startY = r.y, startW = r.w, startH = r.h;
			var isResize = $( e.target ).hasClass( 'wcpc-resize-handle' );

			function move( ev ) {
				var dxPx = ev.clientX - startMx;
				var dyPx = ev.clientY - startMy;
				var dx   = dxPx / cw * side.width;
				var dy   = dyPx / ch * side.height;
				if ( isResize ) {
					r.w = Math.max( 20, startW + dx );
					r.h = Math.max( 20, startH + dy );
				} else {
					r.x = Math.max( 0, Math.min( side.width - r.w, startX + dx ) );
					r.y = Math.max( 0, Math.min( side.height - r.h, startY + dy ) );
				}
				positionBox( $box, r, side );
			}
			function up() {
				$( document ).off( 'mousemove.wcpc-move', move ).off( 'mouseup.wcpc-move', up );
				sync();
			}
			$( document ).on( 'mousemove.wcpc-move', move ).on( 'mouseup.wcpc-move', up );
		} );

		// Region row edits.
		$el.on( 'input change', '.wcpc-region-row input, .wcpc-region-row select', function () {
			var $row = $( this ).closest( '.wcpc-region-row' );
			var rid  = $row.data( 'region' );
			var r    = ( side.regions || [] ).find( function ( x ) { return x.id === rid; } );
			if ( ! r ) {
				return;
			}
			r.name        = $row.find( '.wcpc-region-name' ).val();
			r.type        = $row.find( '.wcpc-region-type' ).val();
			r.placeholder = $row.find( '.wcpc-region-placeholder' ).val();
			// Update box class + label.
			var $box = $canvas.find( '.wcpc-region-box[data-region="' + rid + '"]' );
			$box.removeClass( 'type-text type-color type-image' ).addClass( 'type-' + r.type );
			$box.find( '.wcpc-region-label' ).text( '#' + ( $row.find( '.wcpc-region-index' ).text().replace( '#', '' ) ) + ' ' + ( r.name || r.type ) );
			sync();
		} );

		$el.on( 'click', '.wcpc-remove-region', function ( e ) {
			e.preventDefault();
			var $row = $( this ).closest( '.wcpc-region-row' );
			var rid  = $row.data( 'region' );
			side.regions = ( side.regions || [] ).filter( function ( x ) { return x.id !== rid; } );
			renderRegions( $el, side );
			sync();
		} );
	}

	$( function () {
		sidesJsonInput = $( '#wcpc_sides_json' );
		$container     = $( '#wcpc-sides' );
		if ( ! sidesJsonInput.length ) {
			return;
		}

		try {
			sides = JSON.parse( sidesJsonInput.val() || '[]' );
		} catch ( e ) {
			sides = [];
		}
		if ( ! Array.isArray( sides ) ) {
			sides = [];
		}
		// Ensure each region has an id.
		sides.forEach( function ( s ) {
			( s.regions || [] ).forEach( function ( r ) {
				if ( ! r.id ) { r.id = uid( 'r' ); }
			} );
		} );

		renderAll();

		$( '#wcpc-add-side' ).on( 'click', function ( e ) {
			e.preventDefault();
			sides.push( {
				name:    ( sides.length === 0 ) ? 'Front' : ( sides.length === 1 ? 'Back' : 'Side ' + ( sides.length + 1 ) ),
				image:   '',
				image_id: 0,
				width:   0,
				height:  0,
				regions: []
			} );
			renderAll();
		} );
	} );
} )( jQuery );
