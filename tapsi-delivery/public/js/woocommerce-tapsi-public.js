(function( $ ) {
	'use strict';

	/**
	 * Public-facing JS for checkout and cart
	 */

	var map = null;

	// Run on DOM ready
	$(function() {
		/**
		 * Check if a node is blocked for processing.
		 *
		 * @param {JQuery Object} $node
		 * @return {bool} True if the DOM Element is UI Blocked, false if not.
		 */
		var is_blocked = function( $node ) {
			return $node.is( '.processing' ) || $node.parents( '.processing' ).length;
		};

		/**
		 * Block a node visually for processing.
		 *
		 * @param {JQuery Object} $node
		 */
		var block = function( $node ) {
			if ( ! is_blocked( $node ) ) {
				$node.addClass( 'processing' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}
		};

		/**
		 * Unblock a node after processing is complete.
		 *
		 * @param {JQuery Object} $node
		 */
		var unblock = function( $node ) {
			$node.removeClass( 'processing' ).unblock();
		};


		// Enhanced select on location dropdown
		// Need to make this fire when the shipping method is selected/updated as well
		// $('#tapsi_pickup_location').selectWoo();


		// Updates session when changing pickup location on cart
		$('body.woocommerce-cart').on( 'change', '#tapsi_pickup_location', function() {
			block( $('.cart_totals') );
			$.ajax({
				type: 'POST',
				url: woocommerce_params.ajax_url,
				data: {
					"action": "wcdd_update_pickup_location", 
					"location_id":this.value,
					"nonce":$('#wcdd_set_pickup_location_nonce').val()
				},
				success: function( data ) {
					$(document).trigger('wc_update_cart');
				},
				fail: function( data ) {
					unblock( $('.cart_totals') );
				}
			});
		} );

		// const addButtonListeners = () => {
		// 	// Attach a click event listener to the body or a common ancestor
		// 	$(document.body).on('click','#wctd-tapsi-pack-show-map-button',undefined ,function(event) {
		// 		// Check if the clicked element s your button
		// 		console.log('maryam open event', event);
		// 		event?.preventDefault();
		// 		event?.stopPropagation();
		// 		$('#wctd-tapsi-pack-maplibre-map-modal-container').css({visibility: "visible"});
		// 		// $('#wctd-tapsi-pack-maplibre-map-container-id').css({width: "100%", height: "100%", minHeight: "100%", minWidth: "100px"})
		// 		$('#wctd-tapsi-pack-maplibre-map-center-marker-id').css({visibility: "visible"});
		// 		map?.resize();
		// 		document.body.append('<div id="maryam">heello</div>');
		// 	});
		//
		// 	$(document.body).on('click','#wctd-tapsi-pack-maplibre-map-modal-container',undefined ,function(event) {
		// 		// Check if the clicked element s your button
		// 		console.log('maryam close event', event);
		// 		event?.preventDefault();
		// 		event?.stopPropagation();
		// 		if (event.target !== this)
		// 			return;
		// 		$('#wctd-tapsi-pack-maplibre-map-center-marker-id').css({visibility: "hidden"});
		// 		// $('#wctd-tapsi-pack-maplibre-map-container-id').css({width: "0", height: "0", minHeight: "0", minWidth: "0"})
		// 		// $('#wctd-tapsi-pack-maplibre-map-modal-container').css({visibility: "hidden"});
		// 	});
		// }
		// addButtonListeners();
		// const chooseLocationButton = $('#wctd-tapsi-pack-show-map-button');
		// chooseLocationButton.html('در حال بارگزاری...');
		// const prepareMapBeforeLoad = () => {
		// 	console.log('maryam says hello. loading the scripts...');
		//
		// 	console.log('chooseLocationButton', chooseLocationButton);
		//
		// 	// Define the MapLibre CSS and JavaScript URLs
		// 	var maplibreCSSUrl = 'https://unpkg.com/maplibre-gl@3.3.1/dist/maplibre-gl.css';
		// 	var maplibreJSUrl = 'https://unpkg.com/maplibre-gl@3.3.1/dist/maplibre-gl.js';
		//
		// 	// Load MapLibre CSS dynamically (optional)
		// 	var maplibreCSS = document.createElement('link');
		// 	maplibreCSS.rel = 'stylesheet';
		// 	maplibreCSS.id = 'wctd-tapsi-pack-maplibre-stylesheet';
		// 	maplibreCSS.href = maplibreCSSUrl;
		// 	document.head.appendChild(maplibreCSS);
		//
		// 	// var mapContainer = document.createElement('div');
		// 	// maplibreCSS.id = 'wctd-tapsi-pack-maplibre-map-modal-container';
		// 	// document.body.appendChild(mapContainer)
		//
		// 	// Load MapLibre JavaScript dynamically
		// 	var maplibreJS = document.createElement('script');
		// 	maplibreJS.src = maplibreJSUrl;
		// 	maplibreJS.id = 'wctd-tapsi-pack-maplibre-library-source';
		// 	maplibreJS.onload = () => {
		// 		initializeMap();
		// 	};
		// 	document.head.appendChild(maplibreJS);
		//
		// }
		// prepareMapBeforeLoad();
		// // initializeMap();
		// const applyNecessaryHtmlChanges = () => {
		// 	$('#wctd-tapsi-pack-maplibre-map-center-marker-id').css({display: "block"});
		// 	chooseLocationButton.html('آدرس رو انتخاب کن');
		// }
		// function initializeMap() {
		// 	console.log('initializing the map')
		// 	// Add other map-related code here
		// 	const MAP_CONTAINER_ID = 'wctd-tapsi-pack-maplibre-map-modal-container';
		// 	const MAP_STYLE = 'http://localhost/tapsipack/wp-content/plugins/serve/mapsi-style.json';
		// 	const getLat = $('#wctd-tapsi-pack-maplibre-map-location-form-lat-field-id');
		// 	const getLong = $('#wctd-tapsi-pack-maplibre-map-location-form-lng-field-id');
		// 	let centerLocation = [51.337762, 35.699927]; // Azadi Square
		// 	map = new maplibregl.Map({
		// 		container: MAP_CONTAINER_ID, // container id
		// 		style: MAP_STYLE,
		// 		center: centerLocation, // starting position
		// 		zoom: 15, // starting zoom
		// 	});
		// 	maplibregl.setRTLTextPlugin(
		// 		'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.2.3/mapbox-gl-rtl-text.min.js',
		// 		null,
		// 		false, // Lazy load the plugin
		// 	);
		// 	map.addControl(new maplibregl.NavigationControl());
		//
		// 	console.log('map initilazed',map);
		// 	console.log('map is loaded',map.loaded());
		//
		// 	applyNecessaryHtmlChanges();
		// }
	});

	/**
	 * Adds mobile classes to containers based on the width of the shipping method container.
	 * This can probably be reworked in the future with CSS container queries.
	 */
	var mobileViews = function() {
		var containerWidth = $( "tr.woocommerce-shipping-totals.shipping td" ).width();
		var $deliveryOptions = $('.wcdd-delivery-options');
		if ( containerWidth < 195 && containerWidth >= 155 ) {
			// if the width of the shipping container is less than 195px and greater than 155px, then add the class to the options container
			$deliveryOptions.addClass('mobile-view');
		} else if ( containerWidth < 155 ) {
			// if the width of the shipping container is less than 155px
			$deliveryOptions.addClass('tiny-view');
		} else if ( containerWidth >= 195 ) {
			// if the width of the shipping container is greater than 155px and less than 195px, then remove the class to the options container
			$deliveryOptions.removeClass('tiny-view mobile-view');	
		}
	}

	// Run mobileViews on resize
	var resizeTimeout;
	window.onresize = function() {
		clearTimeout( resizeTimeout );
		resizeTimeout = setTimeout( mobileViews, 100 );
	};

	/**
	 * This runs each time the quote/totals are updated.
	 */
	var updateTimeout;
	$(window).on( 'updated_checkout', function() {
		clearTimeout( updateTimeout );
		// Automatically update the quote every four minutes to avoid expirations
		updateTimeout = setTimeout( function() {
			$( document.body ).trigger( 'update_checkout' );
			console.log('Updated Tapsi delivery quote', $('#tapsi_external_delivery_id').val());
		}, 1000 * 60 * 4 );

		// Add tabindex to tip radio labels for accessibility
		$('.wcdd-delivery-options label.radio').each( function() {
			$(this).attr('tabindex', '0');
		} );

		// Add mobile view classes if necessary
		mobileViews();
	} );

})( jQuery );
