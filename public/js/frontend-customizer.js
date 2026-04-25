/* global jQuery, fabric, WCPC */
( function ( $ ) {
	'use strict';

	if ( 'undefined' === typeof fabric ) {
		return;
	}

	var state = {
		sides:          ( WCPC.sides || [] ).map( function ( s, i ) {
			return {
				index:  i,
				name:   s.name,
				image:  s.image,
				width:  s.width || 800,
				height: s.height || 500,
				regions: s.regions || [],
				json:   null
			};
		} ),
		currentIndex:   0,
		fabricCanvases: {},
		modal:          null,
		designId:       '',
		savedOk:        false
	};

	function t( key ) {
		return ( WCPC.i18n && WCPC.i18n[ key ] ) || key;
	}

	// Build modal once.
	function buildModal() {
		if ( state.modal ) { return state.modal; }

		var fontOptions = Object.keys( WCPC.fonts || {} ).map( function ( val ) {
			return '<option value="' + val + '">' + WCPC.fonts[ val ] + '</option>';
		} ).join( '' );

		var sidesHtml = state.sides.map( function ( s, i ) {
			return (
				'<button type="button" class="wcpc-side-thumb' + ( i === 0 ? ' is-active' : '' ) + '" data-side="' + i + '">' +
				( s.image ? '<img src="' + s.image + '" alt="" />' : '' ) +
				'<span>' + escape( s.name ) + '</span>' +
				'</button>'
			);
		} ).join( '' );

		var html = '' +
			'<div class="wcpc-modal" hidden role="dialog" aria-modal="true">' +
				'<div class="wcpc-modal-dialog">' +
					'<div class="wcpc-modal-header">' +
						'<h2>' + escape( t( 'customize' ) ) + '</h2>' +
						'<button type="button" class="wcpc-modal-close" aria-label="' + escape( t( 'close' ) ) + '">&times;</button>' +
					'</div>' +

					'<aside class="wcpc-sides-rail">' +
						'<h3>' + escape( t( 'sides' ) ) + '</h3>' +
						sidesHtml +
					'</aside>' +

					'<section class="wcpc-stage">' +
						'<div class="wcpc-toolbar">' +
							'<button type="button" class="wcpc-tool" data-tool="addText">+ ' + escape( t( 'addText' ) ) + '</button>' +
							'<span class="sep"></span>' +
							'<button type="button" class="wcpc-tool" data-tool="duplicate">' + escape( t( 'duplicate' ) ) + '</button>' +
							'<button type="button" class="wcpc-tool" data-tool="delete">' + escape( t( 'delete' ) ) + '</button>' +
							'<span class="sep"></span>' +
							'<button type="button" class="wcpc-tool" data-tool="front">' + escape( t( 'bringFront' ) ) + '</button>' +
							'<button type="button" class="wcpc-tool" data-tool="back">' + escape( t( 'sendBack' ) ) + '</button>' +
						'</div>' +
						'<div class="wcpc-canvas-wrapper">' +
							'<div class="wcpc-canvas-host">' +
								'<canvas class="wcpc-canvas"></canvas>' +
							'</div>' +
						'</div>' +
					'</section>' +

					'<aside class="wcpc-props">' +
						'<h3>' + escape( t( 'bgColor' ) ) + '</h3>' +
						'<div class="row"><label>' + escape( t( 'color' ) ) + '</label><input type="color" class="wcpc-bg-color" value="' + ( ( WCPC.settings && WCPC.settings.bg_color ) || '#ffffff' ) + '" /></div>' +
						'<div class="row"><label>' + escape( t( 'opacity' ) ) + '</label><input type="number" min="0" max="1" step="0.05" class="wcpc-bg-opacity" value="1" /></div>' +

						'<div class="wcpc-text-props" hidden>' +
							'<h3>' + escape( t( 'textProps' ) ) + '</h3>' +
							'<div class="row"><label>' + escape( t( 'font' ) ) + '</label><select class="wcpc-font">' + fontOptions + '</select></div>' +
							'<div class="row"><label>' + escape( t( 'size' ) ) + '</label><input type="number" min="6" max="200" step="1" class="wcpc-size" value="24" /></div>' +
							'<div class="row"><label>' + escape( t( 'color' ) ) + '</label><input type="color" class="wcpc-fill" value="#000000" /></div>' +
							'<div class="row"><label></label>' +
								'<div class="btn-group">' +
									'<button type="button" class="wcpc-bold"><b>B</b></button>' +
									'<button type="button" class="wcpc-italic"><i>I</i></button>' +
									'<button type="button" class="wcpc-underline"><u>U</u></button>' +
								'</div>' +
							'</div>' +
							'<div class="row"><label>' + escape( t( 'align' ) ) + '</label>' +
								'<div class="btn-group">' +
									'<button type="button" class="wcpc-align" data-align="left">L</button>' +
									'<button type="button" class="wcpc-align" data-align="center">C</button>' +
									'<button type="button" class="wcpc-align" data-align="right">R</button>' +
								'</div>' +
							'</div>' +
						'</div>' +

						'<div class="wcpc-region-props" hidden>' +
							'<h3>' + escape( t( 'regionFill' ) ) + '</h3>' +
							'<div class="row"><label>' + escape( t( 'color' ) ) + '</label><input type="color" class="wcpc-region-color" value="#000000" /></div>' +
							'<div class="row"><label>' + escape( t( 'opacity' ) ) + '</label><input type="number" min="0" max="1" step="0.05" class="wcpc-region-opacity" value="1" /></div>' +
						'</div>' +
					'</aside>' +

					'<div class="wcpc-modal-footer">' +
						'<button type="button" class="wcpc-btn-secondary wcpc-cancel">' + escape( t( 'cancel' ) ) + '</button>' +
						'<button type="button" class="wcpc-btn-primary wcpc-save">' + escape( t( 'save' ) ) + '</button>' +
					'</div>' +
				'</div>' +
			'</div>';

		var $modal = $( html ).appendTo( 'body' );
		state.modal = $modal;
		bindModal( $modal );
		return $modal;
	}

	function escape( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ c ];
		} );
	}

	function computeDisplaySize( side ) {
		var wrap   = state.modal.find( '.wcpc-canvas-wrapper' );
		var maxW   = Math.max( 320, wrap.width()  - 40 );
		var maxH   = Math.max( 240, wrap.height() - 40 );
		var ratio  = side.width / side.height;
		var w, h;
		if ( maxW / ratio <= maxH ) {
			w = maxW;
			h = maxW / ratio;
		} else {
			h = maxH;
			w = maxH * ratio;
		}
		return { width: Math.round( w ), height: Math.round( h ), scale: w / side.width };
	}

	function getOrCreateCanvas( side ) {
		if ( state.fabricCanvases[ side.index ] ) {
			return state.fabricCanvases[ side.index ];
		}

		var host = state.modal.find( '.wcpc-canvas-host' )[ 0 ];
		var el   = document.createElement( 'canvas' );
		host.appendChild( el );
		el.style.display = 'none';

		var dim = computeDisplaySize( side );
		el.width  = dim.width;
		el.height = dim.height;

		var fc = new fabric.Canvas( el, {
			preserveObjectStacking: true,
			backgroundColor:        ( WCPC.settings && WCPC.settings.bg_color ) || '#ffffff'
		} );
		fc._wcpc = { side: side, scale: dim.scale };

		// Load base image + placeholders.
		if ( side.image ) {
			fabric.Image.fromURL( side.image, function ( img ) {
				img.set( {
					selectable:    false,
					evented:       false,
					hoverCursor:   'default',
					scaleX:        dim.width / img.width,
					scaleY:        dim.height / img.height,
					originX:       'left',
					originY:       'top',
					left:          0,
					top:           0,
					excludeFromExport: false
				} );
				fc.setBackgroundImage( img, fc.renderAll.bind( fc ) );
				addRegionPlaceholders( fc, side, dim.scale );
			}, { crossOrigin: 'anonymous' } );
		} else {
			addRegionPlaceholders( fc, side, dim.scale );
		}

		fc.on( 'selection:created', updateProps );
		fc.on( 'selection:updated', updateProps );
		fc.on( 'selection:cleared', updateProps );
		fc.on( 'object:modified', function () {} );

		state.fabricCanvases[ side.index ] = fc;
		return fc;
	}

	function addRegionPlaceholders( fc, side, scale ) {
		( side.regions || [] ).forEach( function ( r ) {
			if ( r.type === 'text' ) {
				var txt = new fabric.Textbox( r.placeholder || t( 'typeHere' ), {
					left:       r.x * scale,
					top:        r.y * scale,
					width:      r.w * scale,
					fontSize:   ( r.defaultSize || 24 ) * scale,
					fontFamily: r.defaultFont || 'Arial, sans-serif',
					fill:       r.defaultColor || '#000000',
					textAlign:  'left',
					editable:   true
				} );
				txt._wcpcRegion = r.id;
				fc.add( txt );
			} else if ( r.type === 'color' ) {
				var rect = new fabric.Rect( {
					left:   r.x * scale,
					top:    r.y * scale,
					width:  r.w * scale,
					height: r.h * scale,
					fill:   r.defaultColor || '#cccccc',
					selectable: true,
					hasControls: false,
					hasBorders: true,
					lockMovementX: true,
					lockMovementY: true
				} );
				rect._wcpcRegion = r.id;
				rect._wcpcType   = 'color';
				// Keep below user objects.
				fc.add( rect );
				fc.sendToBack( rect );
			}
			// image regions: intentionally left as hint only; users can upload via Add Image if extended.
		} );
	}

	function showSide( idx ) {
		state.currentIndex = idx;
		state.modal.find( '.wcpc-side-thumb' ).removeClass( 'is-active' )
			.filter( '[data-side="' + idx + '"]' ).addClass( 'is-active' );

		var side = state.sides[ idx ];
		var host = state.modal.find( '.wcpc-canvas-host' );

		// Hide all canvases except the one for this side.
		Object.keys( state.fabricCanvases ).forEach( function ( k ) {
			state.fabricCanvases[ k ].lowerCanvasEl.style.display      = 'none';
			state.fabricCanvases[ k ].upperCanvasEl.style.display      = 'none';
			state.fabricCanvases[ k ].upperCanvasEl.parentNode.style.display = 'none';
		} );

		var fc = getOrCreateCanvas( side );
		fc.lowerCanvasEl.style.display = '';
		fc.upperCanvasEl.style.display = '';
		fc.upperCanvasEl.parentNode.style.display = '';
		host.css( { width: fc.getWidth() + 'px', height: fc.getHeight() + 'px' } );

		fc.discardActiveObject();
		fc.requestRenderAll();
		updateProps();
	}

	function activeCanvas() {
		return state.fabricCanvases[ state.currentIndex ];
	}

	function updateProps() {
		var fc  = activeCanvas();
		var obj = fc ? fc.getActiveObject() : null;
		var $p  = state.modal.find( '.wcpc-props' );
		var $t  = $p.find( '.wcpc-text-props' );
		var $r  = $p.find( '.wcpc-region-props' );

		if ( obj && obj.type === 'textbox' ) {
			$t.prop( 'hidden', false );
			$r.prop( 'hidden', true );
			$t.find( '.wcpc-font' ).val( obj.fontFamily );
			$t.find( '.wcpc-size' ).val( Math.round( obj.fontSize ) );
			$t.find( '.wcpc-fill' ).val( normalizeColor( obj.fill ) );
			$t.find( '.wcpc-bold' ).toggleClass( 'is-active', obj.fontWeight === 'bold' );
			$t.find( '.wcpc-italic' ).toggleClass( 'is-active', obj.fontStyle === 'italic' );
			$t.find( '.wcpc-underline' ).toggleClass( 'is-active', !! obj.underline );
			$t.find( '.wcpc-align' ).removeClass( 'is-active' )
				.filter( '[data-align="' + ( obj.textAlign || 'left' ) + '"]' ).addClass( 'is-active' );
		} else if ( obj && obj.type === 'rect' && obj._wcpcType === 'color' ) {
			$t.prop( 'hidden', true );
			$r.prop( 'hidden', false );
			$r.find( '.wcpc-region-color' ).val( normalizeColor( obj.fill ) );
			$r.find( '.wcpc-region-opacity' ).val( obj.opacity != null ? obj.opacity : 1 );
		} else {
			$t.prop( 'hidden', true );
			$r.prop( 'hidden', true );
		}
	}

	function normalizeColor( c ) {
		if ( ! c ) { return '#000000'; }
		if ( /^#([0-9a-f]{6})$/i.test( c ) ) { return c; }
		// Try to parse via a temp element.
		var d = document.createElement( 'div' );
		d.style.color = c;
		document.body.appendChild( d );
		var rgb = getComputedStyle( d ).color;
		document.body.removeChild( d );
		var m = /rgb\((\d+),\s*(\d+),\s*(\d+)\)/.exec( rgb );
		if ( ! m ) { return '#000000'; }
		return '#' + [ 1, 2, 3 ].map( function ( i ) { return ( '0' + parseInt( m[ i ], 10 ).toString( 16 ) ).slice( -2 ); } ).join( '' );
	}

	function bindModal( $modal ) {
		$modal.on( 'click', '.wcpc-modal-close, .wcpc-cancel', function () {
			close();
		} );
		$modal.on( 'click', '.wcpc-side-thumb', function () {
			showSide( parseInt( $( this ).data( 'side' ), 10 ) );
		} );

		// Toolbar.
		$modal.on( 'click', '.wcpc-tool', function () {
			var tool = $( this ).data( 'tool' );
			var fc   = activeCanvas();
			if ( ! fc ) { return; }
			if ( tool === 'addText' ) {
				var scale = fc._wcpc.scale;
				var tb = new fabric.Textbox( t( 'typeHere' ), {
					left: fc.getWidth() / 2 - 80,
					top:  fc.getHeight() / 2 - 20,
					width: 160,
					fontSize: 24 * scale,
					fontFamily: 'Arial, sans-serif',
					fill: '#000000',
					textAlign: 'left'
				} );
				fc.add( tb ).setActiveObject( tb );
				fc.requestRenderAll();
				updateProps();
				return;
			}
			var obj = fc.getActiveObject();
			if ( ! obj ) { return; }
			if ( tool === 'duplicate' ) {
				obj.clone( function ( c ) {
					c.set( { left: obj.left + 20, top: obj.top + 20 } );
					fc.add( c ).setActiveObject( c );
					fc.requestRenderAll();
				} );
			} else if ( tool === 'delete' ) {
				fc.remove( obj );
				fc.requestRenderAll();
				updateProps();
			} else if ( tool === 'front' ) {
				fc.bringToFront( obj );
			} else if ( tool === 'back' ) {
				fc.sendToBack( obj );
			}
		} );

		// Text props bindings.
		$modal.on( 'change input', '.wcpc-font', function () { applyToText( 'fontFamily', $( this ).val() ); } );
		$modal.on( 'change input', '.wcpc-size', function () {
			var scale = activeCanvas() ? activeCanvas()._wcpc.scale : 1;
			applyToText( 'fontSize', parseFloat( $( this ).val() ) * scale );
		} );
		$modal.on( 'change input', '.wcpc-fill', function () { applyToText( 'fill', $( this ).val() ); } );
		$modal.on( 'click', '.wcpc-bold', function () {
			var fc = activeCanvas(), o = fc && fc.getActiveObject();
			if ( ! o || o.type !== 'textbox' ) { return; }
			o.set( 'fontWeight', o.fontWeight === 'bold' ? 'normal' : 'bold' );
			fc.requestRenderAll(); updateProps();
		} );
		$modal.on( 'click', '.wcpc-italic', function () {
			var fc = activeCanvas(), o = fc && fc.getActiveObject();
			if ( ! o || o.type !== 'textbox' ) { return; }
			o.set( 'fontStyle', o.fontStyle === 'italic' ? 'normal' : 'italic' );
			fc.requestRenderAll(); updateProps();
		} );
		$modal.on( 'click', '.wcpc-underline', function () {
			var fc = activeCanvas(), o = fc && fc.getActiveObject();
			if ( ! o || o.type !== 'textbox' ) { return; }
			o.set( 'underline', ! o.underline );
			fc.requestRenderAll(); updateProps();
		} );
		$modal.on( 'click', '.wcpc-align', function () {
			applyToText( 'textAlign', $( this ).data( 'align' ) );
		} );

		// Region color.
		$modal.on( 'change input', '.wcpc-region-color', function () {
			var fc = activeCanvas(), o = fc && fc.getActiveObject();
			if ( ! o || o._wcpcType !== 'color' ) { return; }
			o.set( 'fill', $( this ).val() );
			fc.requestRenderAll();
		} );
		$modal.on( 'change input', '.wcpc-region-opacity', function () {
			var fc = activeCanvas(), o = fc && fc.getActiveObject();
			if ( ! o || o._wcpcType !== 'color' ) { return; }
			o.set( 'opacity', parseFloat( $( this ).val() ) );
			fc.requestRenderAll();
		} );

		// Background color/opacity.
		$modal.on( 'change input', '.wcpc-bg-color', function () {
			var fc = activeCanvas();
			if ( ! fc ) { return; }
			fc.setBackgroundColor( $( this ).val(), fc.renderAll.bind( fc ) );
		} );
		$modal.on( 'change input', '.wcpc-bg-opacity', function () {
			var fc = activeCanvas();
			if ( ! fc || ! fc.backgroundImage ) { return; }
			fc.backgroundImage.set( 'opacity', parseFloat( $( this ).val() ) );
			fc.renderAll();
		} );

		$modal.on( 'click', '.wcpc-save', function () {
			save();
		} );
	}

	function applyToText( prop, value ) {
		var fc = activeCanvas(), o = fc && fc.getActiveObject();
		if ( ! o || o.type !== 'textbox' ) { return; }
		var patch = {}; patch[ prop ] = value;
		o.set( patch );
		fc.requestRenderAll();
		updateProps();
	}

	function close() {
		if ( state.modal ) { state.modal.prop( 'hidden', true ); }
	}

	function open() {
		if ( ! state.sides.length ) {
			window.alert( t( 'emptyDesign' ) );
			return;
		}
		buildModal();
		state.modal.prop( 'hidden', false );
		setTimeout( function () { showSide( state.currentIndex || 0 ); }, 30 );
	}

	function save() {
		var $save = state.modal.find( '.wcpc-save' ).prop( 'disabled', true ).text( t( 'saving' ) );
		var payload = {
			sides: []
		};
		state.sides.forEach( function ( side ) {
			var fc = state.fabricCanvases[ side.index ];
			if ( ! fc ) {
				// Skip unopened sides.
				payload.sides.push( {
					name:   side.name,
					width:  side.width,
					height: side.height,
					json:   null,
					png:    null
				} );
				return;
			}
			var multiplier = side.width / fc.getWidth();
			var png = fc.toDataURL( {
				format:     'png',
				multiplier: multiplier,
				quality:    1
			} );
			var json = fc.toJSON( [ '_wcpcRegion', '_wcpcType' ] );
			payload.sides.push( {
				name:   side.name,
				width:  side.width,
				height: side.height,
				json:   json,
				png:    png
			} );
		} );

		$.ajax( {
			url:    WCPC.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action:    'wcpc_save_design',
				nonce:     WCPC.nonce,
				productId: WCPC.productId,
				payload:   JSON.stringify( payload )
			}
		} ).done( function ( res ) {
			if ( res && res.success && res.data && res.data.designId ) {
				state.designId = res.data.designId;
				state.savedOk  = true;
				$( 'input[name="wcpc_design_id"]' ).val( state.designId );
				$( '.wcpc-customize-trigger .wcpc-status' ).removeClass( 'is-error' ).text( t( 'savedOk' ) );
				close();
			} else {
				$( '.wcpc-customize-trigger .wcpc-status' ).addClass( 'is-error' ).text( t( 'savedErr' ) );
			}
		} ).fail( function () {
			$( '.wcpc-customize-trigger .wcpc-status' ).addClass( 'is-error' ).text( t( 'savedErr' ) );
		} ).always( function () {
			$save.prop( 'disabled', false ).text( t( 'save' ) );
		} );
	}

	$( function () {
		$( document ).on( 'click', '.wcpc-open-customizer', open );

		// Guard the add-to-cart form: require a saved design before submit.
		$( document ).on( 'submit', 'form.cart', function ( e ) {
			if ( ! state.sides.length ) { return; }
			if ( ! state.designId ) {
				e.preventDefault();
				$( '.wcpc-customize-trigger .wcpc-status' ).addClass( 'is-error' ).text( t( 'requiresDesign' ) );
				open();
			}
		} );
	} );
} )( jQuery );
