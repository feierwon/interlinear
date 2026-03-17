/**
 * Interlinear — Front-end filter module.
 *
 * Self-contained. Receives configuration via window.interlinearData.
 * No WordPress dependencies.
 */
( function () {
	'use strict';

	var data = window.interlinearData;
	if ( ! data || ! data.categories || ! data.categories.length ) {
		return;
	}

	var postId = data.postId;
	var categories = data.categories;
	var persistence = data.persistence;
	var dimOpacity = data.opacity !== undefined ? data.opacity : 0.35;

	var STORAGE_KEY = 'interlinear_' + postId + '_state';

	var sidebar = document.getElementById( 'il-sidebar' );
	if ( ! sidebar ) {
		return;
	}

	var announcer = sidebar.querySelector( '.il-announcer' );
	var allButton = sidebar.querySelector( '[data-il-filter="all"]' );
	var filterButtons = sidebar.querySelectorAll( '.il-filter:not(.il-filter--all)' );
	var taggedSpans = document.querySelectorAll( '[data-il-category]' );

	// Build category lookup.
	var categoryMap = {};
	for ( var i = 0; i < categories.length; i++ ) {
		categoryMap[ categories[ i ].slug ] = categories[ i ];
	}

	// Active filter state: array of slugs.
	var activeFilters = [];

	/**
	 * Read persisted state from localStorage.
	 */
	function readState() {
		if ( ! persistence ) {
			return [];
		}
		try {
			var stored = localStorage.getItem( STORAGE_KEY );
			if ( stored ) {
				var parsed = JSON.parse( stored );
				if ( Array.isArray( parsed ) ) {
					return parsed;
				}
			}
		} catch ( e ) {
			// Silent fail.
		}
		return [];
	}

	/**
	 * Write state to localStorage.
	 */
	function writeState( state ) {
		if ( ! persistence ) {
			return;
		}
		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
		} catch ( e ) {
			// Silent fail — quota exceeded, etc.
		}
	}

	/**
	 * Update the DOM to reflect active filters.
	 */
	function applyFilters() {
		var isAllActive = activeFilters.length === 0;

		// Update "All" button.
		allButton.setAttribute( 'aria-pressed', isAllActive ? 'true' : 'false' );
		if ( isAllActive ) {
			allButton.classList.add( 'il-filter--active' );
		} else {
			allButton.classList.remove( 'il-filter--active' );
		}

		// Update category buttons.
		for ( var i = 0; i < filterButtons.length; i++ ) {
			var btn = filterButtons[ i ];
			var slug = btn.getAttribute( 'data-il-filter' );
			var isPressed = activeFilters.indexOf( slug ) !== -1;
			btn.setAttribute( 'aria-pressed', isPressed ? 'true' : 'false' );
			if ( isPressed ) {
				btn.classList.add( 'il-filter--active' );
			} else {
				btn.classList.remove( 'il-filter--active' );
			}
		}

		// Update tagged spans.
		for ( var j = 0; j < taggedSpans.length; j++ ) {
			var span = taggedSpans[ j ];
			var spanCategory = span.getAttribute( 'data-il-category' );

			if ( isAllActive || activeFilters.indexOf( spanCategory ) !== -1 ) {
				span.style.opacity = '1';
				span.classList.remove( 'il-dimmed' );
				span.classList.add( 'il-highlighted' );
			} else {
				span.style.opacity = String( dimOpacity );
				span.classList.add( 'il-dimmed' );
				span.classList.remove( 'il-highlighted' );
			}
		}

		// Announce state.
		announce( isAllActive );

		// Persist.
		writeState( activeFilters );
	}

	/**
	 * Announce filter state to screen readers.
	 */
	function announce( isAll ) {
		if ( ! announcer ) {
			return;
		}

		if ( isAll ) {
			announcer.textContent = 'Showing all content.';
			return;
		}

		var labels = [];
		for ( var i = 0; i < activeFilters.length; i++ ) {
			var cat = categoryMap[ activeFilters[ i ] ];
			if ( cat ) {
				labels.push( cat.label );
			}
		}

		announcer.textContent = 'Showing: ' + labels.join( ', ' ) + '. Other content de-emphasized.';
	}

	/**
	 * Toggle a filter.
	 */
	function toggleFilter( slug ) {
		var cat = categoryMap[ slug ];
		if ( ! cat ) {
			return;
		}

		var idx = activeFilters.indexOf( slug );
		var isCurrentlyActive = idx !== -1;

		if ( cat.mode === 'exclusive' ) {
			if ( isCurrentlyActive ) {
				// Deactivate — remove this exclusive filter.
				activeFilters.splice( idx, 1 );
			} else {
				// Deactivate other exclusive categories, keep multi-select ones.
				var kept = [];
				for ( var i = 0; i < activeFilters.length; i++ ) {
					var activeCat = categoryMap[ activeFilters[ i ] ];
					if ( activeCat && activeCat.mode !== 'exclusive' ) {
						kept.push( activeFilters[ i ] );
					}
				}
				kept.push( slug );
				activeFilters = kept;
			}
		} else {
			// Multi-select: simple toggle.
			if ( isCurrentlyActive ) {
				activeFilters.splice( idx, 1 );
			} else {
				activeFilters.push( slug );
			}
		}

		applyFilters();
	}

	/**
	 * Reset to "All" state.
	 */
	function resetAll() {
		activeFilters = [];
		applyFilters();
	}

	/**
	 * Bind events.
	 */
	function bindEvents() {
		allButton.addEventListener( 'click', function () {
			resetAll();
		} );

		for ( var i = 0; i < filterButtons.length; i++ ) {
			( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var slug = btn.getAttribute( 'data-il-filter' );
					toggleFilter( slug );
				} );
			} )( filterButtons[ i ] );
		}
	}

	/**
	 * Add aria-label to tagged spans for screen readers.
	 */
	function enhanceAccessibility() {
		for ( var i = 0; i < taggedSpans.length; i++ ) {
			var span = taggedSpans[ i ];
			var slug = span.getAttribute( 'data-il-category' );
			var cat = categoryMap[ slug ];

			if ( cat ) {
				span.setAttribute( 'role', 'mark' );
				span.setAttribute( 'aria-label', cat.label + ': ' );
			}
		}
	}

	/**
	 * Show the sidebar (hidden by default via CSS until JS confirms).
	 */
	function showSidebar() {
		sidebar.classList.add( 'il-sidebar--active' );
	}

	/**
	 * Initialize.
	 */
	function init() {
		showSidebar();
		enhanceAccessibility();
		bindEvents();

		// Restore persisted state.
		activeFilters = readState();
		applyFilters();
	}

	// Run on DOMContentLoaded or immediately if already loaded.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
