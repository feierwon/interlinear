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

	// Active filter: single slug or null.
	var activeFilter = null;
	var previousFilter = null;

	// Content area element.
	var contentArea = null;

	/**
	 * Find the content container that holds tagged spans.
	 */
	function findContentArea() {
		if ( taggedSpans.length === 0 ) {
			return null;
		}

		var el = taggedSpans[ 0 ].parentElement;
		while ( el && el !== document.body ) {
			if (
				el.classList.contains( 'entry-content' ) ||
				el.classList.contains( 'post-content' ) ||
				el.classList.contains( 'page-content' ) ||
				el.classList.contains( 'article-content' ) ||
				el.tagName === 'ARTICLE'
			) {
				return el;
			}
			el = el.parentElement;
		}

		return taggedSpans[ 0 ].parentElement;
	}

	// Underline styles cycle: solid, dotted, dashed for distinct categories.
	var UNDERLINE_STYLES = [ 'solid', 'dotted', 'dashed' ];

	// Build underline style lookup by category index.
	var underlineMap = {};
	for ( var s = 0; s < categories.length; s++ ) {
		underlineMap[ categories[ s ].slug ] = UNDERLINE_STYLES[ s % UNDERLINE_STYLES.length ];
	}

	/**
	 * Convert a hex color to rgba with a given alpha.
	 */
	function hexToRgba( hex, alpha ) {
		var r = parseInt( hex.slice( 1, 3 ), 16 );
		var g = parseInt( hex.slice( 3, 5 ), 16 );
		var b = parseInt( hex.slice( 5, 7 ), 16 );
		return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
	}

	/**
	 * Dim block-level children of the content area that don't contain
	 * highlighted spans. This avoids z-index/stacking issues with overlays.
	 */
	function applyContentDimming( show ) {
		if ( ! contentArea ) {
			return;
		}

		if ( show ) {
			contentArea.classList.add( 'il-content-area--filtering' );
			var children = contentArea.children;
			for ( var i = 0; i < children.length; i++ ) {
				var child = children[ i ];
				var hasHighlight = child.querySelector( '[data-il-category].il-highlighted' );
				if ( hasHighlight ) {
					child.classList.remove( 'il-block-dimmed' );
				} else {
					child.classList.add( 'il-block-dimmed' );
				}
			}
		} else {
			contentArea.classList.remove( 'il-content-area--filtering' );
			var dimmed = contentArea.querySelectorAll( '.il-block-dimmed' );
			for ( var j = 0; j < dimmed.length; j++ ) {
				dimmed[ j ].classList.remove( 'il-block-dimmed' );
			}
		}
	}

	/**
	 * Read persisted state from localStorage.
	 */
	function readState() {
		if ( ! persistence ) {
			return null;
		}
		try {
			var stored = localStorage.getItem( STORAGE_KEY );
			if ( stored ) {
				var parsed = JSON.parse( stored );
				if ( typeof parsed === 'string' && categoryMap[ parsed ] ) {
					return parsed;
				}
			}
		} catch ( e ) {
			// Silent fail.
		}
		return null;
	}

	/**
	 * Write state to localStorage.
	 */
	function writeState( slug ) {
		if ( ! persistence ) {
			return;
		}
		try {
			if ( slug ) {
				localStorage.setItem( STORAGE_KEY, JSON.stringify( slug ) );
			} else {
				localStorage.removeItem( STORAGE_KEY );
			}
		} catch ( e ) {
			// Silent fail.
		}
	}

	/**
	 * Update the DOM to reflect active filter.
	 */
	function applyFilters() {
		var isAllActive = ! activeFilter;
		var isNewSelection = activeFilter && activeFilter !== previousFilter;
		var cat = activeFilter ? categoryMap[ activeFilter ] : null;
		var highlightColor = cat ? hexToRgba( cat.color, 0.2 ) : '';

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
			var isPressed = slug === activeFilter;
			btn.setAttribute( 'aria-pressed', isPressed ? 'true' : 'false' );
			if ( isPressed ) {
				btn.classList.add( 'il-filter--active' );
			} else {
				btn.classList.remove( 'il-filter--active' );
			}
		}

		// Collect matching spans sorted by document position for sweep animation.
		var matchingSpans = [];

		// Update tagged spans.
		for ( var j = 0; j < taggedSpans.length; j++ ) {
			var span = taggedSpans[ j ];
			var spanCategory = span.getAttribute( 'data-il-category' );

			// Clear previous animation state.
			span.classList.remove( 'il-sweep' );
			span.style.animationDelay = '';

			if ( isAllActive ) {
				span.classList.remove( 'il-highlighted' );
				span.style.removeProperty( '--il-highlight' );
				span.style.removeProperty( '--il-highlight-solid' );
				span.style.removeProperty( '--il-underline-style' );
			} else if ( spanCategory === activeFilter ) {
				span.classList.add( 'il-highlighted' );
				span.style.setProperty( '--il-highlight', highlightColor );
				span.style.setProperty( '--il-highlight-solid', cat.color );
				span.style.setProperty( '--il-underline-style', underlineMap[ spanCategory ] || 'solid' );
				if ( isNewSelection ) {
					matchingSpans.push( span );
				}
			} else {
				span.classList.remove( 'il-highlighted' );
				span.style.removeProperty( '--il-highlight' );
				span.style.removeProperty( '--il-highlight-solid' );
				span.style.removeProperty( '--il-underline-style' );
			}
		}

		// Sweep animation: stagger highlights top-to-bottom, left-to-right.
		if ( matchingSpans.length > 0 ) {
			matchingSpans.sort( function ( a, b ) {
				var ar = a.getBoundingClientRect();
				var br = b.getBoundingClientRect();
				if ( Math.abs( ar.top - br.top ) > 10 ) {
					return ar.top - br.top;
				}
				return ar.left - br.left;
			} );

			var totalDuration = Math.min( 1200, matchingSpans.length * 80 );
			var step = matchingSpans.length > 1 ? totalDuration / ( matchingSpans.length - 1 ) : 0;

			for ( var k = 0; k < matchingSpans.length; k++ ) {
				( function ( span, delay ) {
					void span.offsetWidth;
					span.style.animationDelay = delay + 'ms';
					span.classList.add( 'il-sweep' );
				} )( matchingSpans[ k ], Math.round( k * step ) );
			}
		}

		// Dim non-matching blocks in the content area.
		applyContentDimming( ! isAllActive );

		// Announce state.
		announce( isAllActive );

		// Persist.
		writeState( activeFilter );

		previousFilter = activeFilter;
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

		var cat = categoryMap[ activeFilter ];
		if ( cat ) {
			announcer.textContent = 'Showing: ' + cat.label + '. Other content de-emphasized.';
		}
	}

	/**
	 * Toggle a filter. Single-select: clicking the active one deactivates it,
	 * clicking a different one swaps to it.
	 */
	function toggleFilter( slug ) {
		if ( ! categoryMap[ slug ] ) {
			return;
		}

		if ( activeFilter === slug ) {
			activeFilter = null;
		} else {
			activeFilter = slug;
		}

		applyFilters();
	}

	/**
	 * Reset to "All" state.
	 */
	function resetAll() {
		activeFilter = null;
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
		contentArea = findContentArea();
		if ( contentArea ) {
			contentArea.classList.add( 'il-content-area--ready' );
		}
		enhanceAccessibility();
		bindEvents();

		// Restore persisted state.
		activeFilter = readState();
		applyFilters();
	}

	// Run on DOMContentLoaded or immediately if already loaded.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
