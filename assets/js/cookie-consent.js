/* MaVo Cookie Consent — banner + tracking logic */
/* global mavoCookieConsent */

( function () {
	'use strict';

	var config    = window.mavoCookieConsent || {};
	var banner    = null;
	var dismissed = false;

	// -------------------------------------------------------------------------
	// Cookie helpers
	// -------------------------------------------------------------------------

	function getCookie( name ) {
		var match = document.cookie.match(
			new RegExp( '(?:^|; )' + name.replace( /([.*+?^=!:${}()|[\]/\\])/g, '\\$1' ) + '=([^;]*)' )
		);
		return match ? decodeURIComponent( match[1] ) : null;
	}

	function setCookie( name, value ) {
		var expires = new Date();
		expires.setFullYear( expires.getFullYear() + 1 );
		document.cookie =
			encodeURIComponent( name ) + '=' + encodeURIComponent( value ) +
			'; expires=' + expires.toUTCString() +
			'; path=/; SameSite=Lax';
	}

	// -------------------------------------------------------------------------
	// Tracking loaders
	// -------------------------------------------------------------------------

	function loadTracking() {
		// GA4
		if ( config.ga4Id ) {
			var ga4Script = document.createElement( 'script' );
			ga4Script.async = true;
			ga4Script.src   = 'https://www.googletagmanager.com/gtag/js?id=' + config.ga4Id;
			document.head.appendChild( ga4Script );

			window.dataLayer = window.dataLayer || [];
			window.gtag = function () {
				window.dataLayer.push( arguments );
			};
			window.gtag( 'js', new Date() );
			window.gtag( 'config', config.ga4Id );
			attachOutboundLinkTracking();
		}

		// Statcounter
		if ( config.scProject && config.scSecurity ) {
			window.sc_project   = config.scProject;
			window.sc_invisible = 1;
			window.sc_security  = config.scSecurity;

			var scScript  = document.createElement( 'script' );
			scScript.async = true;
			scScript.src   = 'https://www.statcounter.com/counter/counter.js';
			document.head.appendChild( scScript );
		}

		// Jetpack Stats
		// config.jetpackStats = { src: '...', inline: '_stq = ...' }
		if ( config.jetpackStats && ( config.jetpackStats.src || config.jetpackStats.inline ) ) {
			// Run the _stq initialisation code first (sets up the queue).
			if ( config.jetpackStats.inline ) {
				var jpInline = document.createElement( 'script' );
				jpInline.textContent = config.jetpackStats.inline;
				document.head.appendChild( jpInline );
			}

			// Load the external stats script if present.
			if ( config.jetpackStats.src ) {
				var jpScript = document.createElement( 'script' );
				jpScript.src   = config.jetpackStats.src;
				jpScript.async = true;
				document.head.appendChild( jpScript );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Outbound link tracking
	// -------------------------------------------------------------------------

	function attachOutboundLinkTracking() {
		document.addEventListener( 'click', function ( e ) {
			var anchor = e.target.closest( 'a[href]' );
			if ( ! anchor ) {
				return;
			}
			var href = anchor.getAttribute( 'href' );
			if ( ! href || /^(mailto|tel|javascript):/i.test( href ) ) {
				return;
			}
			// Resolve relative URLs via a throwaway element.
			var a  = document.createElement( 'a' );
			a.href = href;
			if ( a.hostname === window.location.hostname ) {
				return;
			}
			window.gtag( 'event', 'outbound_link', {
				link_url:    a.href,
				link_domain: a.hostname,
				link_text:   ( anchor.textContent || '' ).trim().slice( 0, 100 ),
				outbound:    true,
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Dismiss
	// -------------------------------------------------------------------------

	function dismiss() {
		if ( dismissed ) {
			return;
		}
		dismissed = true;

		setCookie( config.cookieName, '1' );

		// Restore suppressed third-party cookies.
		var pending = config.pendingCookies || [];
		for ( var i = 0; i < pending.length; i++ ) {
			setCookie( pending[ i ].name, pending[ i ].value );
		}

		loadTracking();

		if ( banner ) {
			banner.classList.add( 'mavo-cookie-banner--dismissing' );
			banner.addEventListener( 'transitionend', function onEnd() {
				banner.removeEventListener( 'transitionend', onEnd );
				if ( banner.parentNode ) {
					banner.parentNode.removeChild( banner );
				}
			} );
		}

		document.removeEventListener( 'click',  onDocumentClick );
		window.removeEventListener(   'scroll', onWindowScroll );
	}

	// -------------------------------------------------------------------------
	// Event listeners
	// -------------------------------------------------------------------------

	function onDocumentClick() {
		dismiss();
	}

	function onWindowScroll() {
		if ( ( window.pageYOffset || document.documentElement.scrollTop ) >= ( config.scrollThreshold || 300 ) ) {
			dismiss();
		}
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	function init() {
		if ( getCookie( config.cookieName ) ) {
			// Returning visitor — fire tracking immediately, never show banner.
			loadTracking();
			return;
		}

		// First-time visitor: reveal the banner.
		banner = document.getElementById( 'mavo-cookie-banner' );
		if ( banner ) {
			banner.classList.remove( 'mavo-cookie-banner--hidden' );
		}

		document.addEventListener( 'click',  onDocumentClick );
		window.addEventListener(   'scroll', onWindowScroll, { passive: true } );
	}

	// Run on DOMContentLoaded or immediately if the DOM is already ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
