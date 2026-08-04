/**
 * LDAP Staff Directory — admin JS
 * Handles: tab navigation, advanced-settings accordions, help popovers, the conditional
 * Extension Attribute reveal, Test Connection, Clear Cache, Refresh Department List, the
 * bind-password show/hide toggle, and the "Copy request for IT" button.
 */
( function ( $ ) {
	'use strict';

	/**
	 * Rebuild the department checklist markup, preserving which departments
	 * were already checked (by name) so a refresh doesn't silently discard
	 * exclusions the admin already configured.
	 *
	 * @param {Array} departments Array of { name, count }.
	 */
	function ldapEdRenderDepartments( departments ) {
		const $field     = $( '#ldap-ed-departments-field' );
		const $checklist = $( '#ldap-ed-departments-checklist' );
		const checkedNames = {};

		$checklist.find( 'input[type="checkbox"]:checked' ).each( function () {
			checkedNames[ $( this ).val() ] = true;
		} );

		if ( ! departments.length ) {
			$field.find( '.description, #ldap-ed-departments-checklist' ).remove();
			$( '<p class="description"></p>' )
				.text( ldapEdAdmin.i18n.noDepartmentsFound )
				.prependTo( $field );
			return;
		}

		const $newChecklist = $( '<div id="ldap-ed-departments-checklist" class="ldap-ed-departments-checklist"></div>' );

		departments.forEach( function ( dept ) {
			const isChecked = !! checkedNames[ dept.name ];
			const $label  = $( '<label></label>' );
			const $input  = $( '<input type="checkbox">' ).attr( {
				name: 'ldap_ed_settings[excluded_departments][]',
				value: dept.name,
			} );
			if ( isChecked ) {
				$input.prop( 'checked', true );
			}
			$label.append( $input ).append( document.createTextNode( ' ' + dept.name + ' (' + dept.count + ')' ) );
			$newChecklist.append( $label );
		} );

		if ( $checklist.length ) {
			$checklist.replaceWith( $newChecklist );
		} else {
			$field.find( '.description' ).remove();
			$newChecklist.insertBefore( $field.find( 'p' ).first() );
		}
	}

	$( function () {
		// ── Tab navigation ──────────────────────────────────────────────────
		// Switching tabs never reloads the page; each tab's own <form> still redirects back
		// to itself on save via the hidden _wp_http_referer override rendered server-side.
		const $tabs     = $( '#ldap-ed-tabs a[data-tab]' );
		const $tabpanels = $( '.ldap-ed-tabpanel' );

		function ldapEdActivateTab( tab, pushState ) {
			$tabs.each( function () {
				const $tab      = $( this );
				const isActive  = $tab.data( 'tab' ) === tab;
				$tab.toggleClass( 'nav-tab-active', isActive );
				$tab.attr( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			$tabpanels.each( function () {
				const $panel = $( this );
				$panel.prop( 'hidden', $panel.data( 'tabpanel' ) !== tab );
			} );
			if ( pushState && window.history && window.history.pushState ) {
				const url = new URL( window.location.href );
				url.searchParams.set( 'tab', tab );
				window.history.pushState( { ldapEdTab: tab }, '', url.toString() );
			}
		}

		$tabs.on( 'click', function ( e ) {
			e.preventDefault();
			ldapEdActivateTab( $( this ).data( 'tab' ), true );
		} );

		window.addEventListener( 'popstate', function ( e ) {
			const tab = ( e.state && e.state.ldapEdTab ) || 'connection';
			ldapEdActivateTab( tab, false );
		} );

		// ── Advanced settings accordion ─────────────────────────────────────
		// Toggling only flips the `hidden` attribute — the fields inside are never removed
		// from the DOM or disabled, so they still travel with the form on save or on a live
		// "Test Connection" serialize, whether the accordion is expanded or not.
		$( '.ldap-ed-advanced__toggle' ).on( 'click', function () {
			const $btn  = $( this );
			const $body = $btn.closest( '.ldap-ed-advanced' ).find( '.ldap-ed-advanced__body' );
			const willExpand = $body.prop( 'hidden' );
			$body.prop( 'hidden', ! willExpand );
			$btn.attr( 'aria-expanded', willExpand ? 'true' : 'false' );
			$btn.toggleClass( 'is-expanded', willExpand );
		} );

		// ── Conditional Extension Attribute reveal ──────────────────────────
		$( '.ldap-ed-field-checkbox__input[data-field="extension"]' ).on( 'change', function () {
			$( '#ldap-ed-extension-block' ).prop( 'hidden', ! this.checked );
		} );

		// ── Help popovers ────────────────────────────────────────────────────
		const $popover = $( '<div class="ldap-ed-popover" role="tooltip" hidden></div>' ).appendTo( 'body' );
		let $openInfoBtn = null;

		function ldapEdClosePopover() {
			$popover.prop( 'hidden', true );
			if ( $openInfoBtn ) {
				$openInfoBtn.attr( 'aria-expanded', 'false' ).trigger( 'focus' );
				$openInfoBtn = null;
			}
		}

		$( '.ldap-ed-info-btn' ).on( 'click', function ( e ) {
			e.stopPropagation();
			const $btn = $( this );

			if ( $openInfoBtn && $openInfoBtn.is( $btn ) ) {
				ldapEdClosePopover();
				return;
			}

			$popover.text( $btn.data( 'help' ) ).prop( 'hidden', false );
			const rect = $btn[ 0 ].getBoundingClientRect();
			$popover.css( {
				position: 'fixed',
				top: rect.bottom + 6 + 'px',
				left: Math.max( 8, rect.left - 110 ) + 'px',
			} );

			if ( $openInfoBtn ) {
				$openInfoBtn.attr( 'aria-expanded', 'false' );
			}
			$btn.attr( 'aria-expanded', 'true' );
			$openInfoBtn = $btn;
		} );

		$( document ).on( 'click', function ( e ) {
			if ( $openInfoBtn && ! $( e.target ).closest( '.ldap-ed-popover' ).length ) {
				ldapEdClosePopover();
			}
		} );

		$( document ).on( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && $openInfoBtn ) {
				ldapEdClosePopover();
			}
		} );

		// ── Copy request for IT ──────────────────────────────────────────────
		$( '#ldap-ed-copy-request-btn' ).on( 'click', function () {
			const $result = $( '#ldap-ed-copy-result' );
			const text    = ldapEdAdmin.i18n.itRequestTemplate;

			function showCopied() {
				$result.removeClass( 'is-error' ).addClass( 'is-success' ).text( ldapEdAdmin.i18n.itRequestCopied ).show();
			}
			function showFailed() {
				$result.removeClass( 'is-success' ).addClass( 'is-error' ).text( ldapEdAdmin.i18n.itRequestCopyFailed ).show();
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( showCopied, showFailed );
				return;
			}

			const $tmp = $( '<textarea readonly></textarea>' ).val( text ).appendTo( 'body' ).select();
			try {
				document.execCommand( 'copy' ) ? showCopied() : showFailed();
			} catch ( err ) {
				showFailed();
			}
			$tmp.remove();
		} );

		// ── Test Connection ─────────────────────────────────────────────────
		// Serializes the Connection form's current (possibly unsaved) values instead of only
		// testing what's already persisted — collapsed "Advanced settings" fields are still
		// included since they're only hidden via the `hidden` attribute, never removed/disabled.
		$( '#ldap-ed-test-btn' ).on( 'click', function () {
			const $btn      = $( this );
			const $result   = $( '#ldap-ed-test-result' );
			const labelOrig = $btn.text();

			$btn.prop( 'disabled', true ).text( ldapEdAdmin.i18n.testing );
			$result.removeClass( 'is-success is-error' ).hide();

			// Drop the Settings-API-only fields (action=update, option_page, _wpnonce) that
			// settings_fields() renders into this form — they're meaningless to this AJAX
			// endpoint and would otherwise sit alongside our own `action`/`nonce` below.
			const skip = { action: true, option_page: true, _wpnonce: true };
			const data = $( '#ldap-ed-connection-form' ).serializeArray().filter( function ( field ) {
				return ! skip[ field.name ];
			} );
			data.push( { name: 'action', value: 'ldap_ed_test_connection' } );
			data.push( { name: 'nonce', value: ldapEdAdmin.nonce } );

			$.post( ldapEdAdmin.ajaxUrl, data )
				.done( function ( res ) {
					if ( res.success ) {
						$result.addClass( 'is-success' ).text( res.data.message ).show();
					} else {
						$result.addClass( 'is-error' ).text( res.data.message ).show();
					}
				} )
				.fail( function ( xhr ) {
					$result.addClass( 'is-error' ).text( 'HTTP ' + xhr.status + ': ' + xhr.statusText ).show();
				} )
				.always( function () {
					$btn.prop( 'disabled', false ).text( labelOrig );
				} );
		} );

		// ── Clear Cache ─────────────────────────────────────────────────────
		$( '#ldap-ed-clear-cache-btn' ).on( 'click', function () {
			const $btn      = $( this );
			const $result   = $( '#ldap-ed-cache-result' );
			const labelOrig = $btn.text();

			$btn.prop( 'disabled', true ).text( ldapEdAdmin.i18n.clearing );
			$result.removeClass( 'is-success is-error' ).hide();

			$.post( ldapEdAdmin.ajaxUrl, {
				action: 'ldap_ed_clear_cache',
				nonce:  ldapEdAdmin.nonce,
			} )
			.done( function ( res ) {
				if ( res.success ) {
					$result.addClass( 'is-success' ).text( res.data.message ).show();
				} else {
					$result.addClass( 'is-error' ).text( res.data.message ).show();
				}
			} )
			.fail( function ( xhr ) {
				$result.addClass( 'is-error' ).text( 'HTTP ' + xhr.status + ': ' + xhr.statusText ).show();
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( labelOrig );
			} );
		} );

		// ── Scheme → Port placeholder ───────────────────────────────────────
		// Only ever touches the placeholder, never the actual value — an empty field
		// keeps showing the right default as a hint, but anything the admin has typed
		// (including a value that happens to match a default) is never overwritten.
		$( '#ldap_ed_scheme' ).on( 'change', function () {
			const $port  = $( '#ldap_ed_port' );
			const scheme = $( this ).val();
			const defaultPort = 'ldap' === scheme ? $port.data( 'default-ldap' ) : $port.data( 'default-ldaps' );
			$port.attr( 'placeholder', defaultPort );
		} );

		// ── Bind Password show/hide ─────────────────────────────────────────
		$( '.ldap-ed-password-toggle' ).on( 'click', function () {
			const $btn      = $( this );
			const $input    = $( '#' + $btn.data( 'target' ) );
			const willShow  = ! $btn.hasClass( 'is-visible' );

			$input.attr( 'type', willShow ? 'text' : 'password' );
			$btn.toggleClass( 'is-visible', willShow );
			$btn.attr( 'aria-pressed', willShow ? 'true' : 'false' );
			$btn.attr( 'aria-label', willShow ? ldapEdAdmin.i18n.hidePassword : ldapEdAdmin.i18n.showPassword );
		} );

		// ── Refresh Department List ────────────────────────────────────────
		$( '#ldap-ed-refresh-departments-btn' ).on( 'click', function () {
			const $btn      = $( this );
			const $result   = $( '#ldap-ed-departments-result' );
			const labelOrig = $btn.text();

			$btn.prop( 'disabled', true ).text( ldapEdAdmin.i18n.loadingDepartments );
			$result.removeClass( 'is-success is-error' ).hide();

			$.post( ldapEdAdmin.ajaxUrl, {
				action: 'ldap_ed_get_departments',
				nonce:  ldapEdAdmin.nonce,
			} )
			.done( function ( res ) {
				if ( res.success ) {
					ldapEdRenderDepartments( res.data.departments );

					const $noDeptLabel = $( '#ldap-ed-exclude-no-department-label' );
					if ( $noDeptLabel.length ) {
						const noDeptCount = res.data.no_department_count;
						const template    = noDeptCount
							? ldapEdAdmin.i18n.noDepartmentLabelWithCount.replace( '%d', noDeptCount )
							: ldapEdAdmin.i18n.noDepartmentLabel;
						$noDeptLabel.contents().filter( function () {
							return 3 === this.nodeType;
						} ).last().replaceWith( ' ' + template );
					}
				} else {
					$result.addClass( 'is-error' ).text( res.data.message ).show();
				}
			} )
			.fail( function ( xhr ) {
				$result.addClass( 'is-error' ).text( 'HTTP ' + xhr.status + ': ' + xhr.statusText ).show();
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( labelOrig );
			} );
		} );

		// ── Land on Connection tab with the password field focused ─────────
		// Followed the link from the WP-salt-rotation admin notice.
		if ( '#ldap_ed_bind_pass' === window.location.hash ) {
			ldapEdActivateTab( 'connection', false );
			const $pass = $( '#ldap_ed_bind_pass' );
			if ( $pass.length ) {
				$pass.trigger( 'focus' );
			}
		}
	} );
}( jQuery ) );
