/**
 * LDAP Staff Directory — admin JS
 * Handles: Test Connection, Clear Cache, and Refresh Department List buttons.
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
		// ── Test Connection ─────────────────────────────────────────────────
		$( '#ldap-ed-test-btn' ).on( 'click', function () {
			const $btn      = $( this );
			const $result   = $( '#ldap-ed-test-result' );
			const labelOrig = $btn.text();

			$btn.prop( 'disabled', true ).text( ldapEdAdmin.i18n.testing );
			$result.removeClass( 'is-success is-error' ).hide();

			$.post( ldapEdAdmin.ajaxUrl, {
				action: 'ldap_ed_test_connection',
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
	} );
} ( jQuery ) );
