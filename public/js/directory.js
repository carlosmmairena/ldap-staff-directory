/**
 * LDAP Staff Directory — public JS
 *
 * Two independent, small pieces of behavior:
 * 1. Employee search (department detail view) is server-side — this only
 *    strips an empty ldap_search from the form submission to keep URLs clean.
 * 2. Department search (department menu view) is client-side — it filters
 *    the already-rendered .ldap-dept-row elements by substring match, with
 *    no network request. Scoped to at most the total number of departments;
 *    it never touches employee cards or pagination.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.ldap-search-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function () {
				var input = form.querySelector( '[name="ldap_search"]' );
				if ( input && '' === input.value.trim() ) {
					input.disabled = true;
				}
			} );
		} );

		var deptSearchInput = document.getElementById( 'ldap-dept-search-input' );
		if ( ! deptSearchInput ) {
			return;
		}

		var wrap = deptSearchInput.closest( '.ldap-directory-wrap' );
		if ( ! wrap ) {
			return;
		}

		var deptRows      = Array.prototype.slice.call( wrap.querySelectorAll( '.ldap-dept-row' ) );
		var deptNoResults = wrap.querySelector( '.ldap-dept-no-results' );

		deptSearchInput.addEventListener( 'input', function () {
			var query   = deptSearchInput.value.trim().toLowerCase();
			var visible = 0;

			deptRows.forEach( function ( row ) {
				var matches = '' === query || row.dataset.name.toLowerCase().indexOf( query ) !== -1;
				row.hidden  = ! matches;
				if ( matches ) {
					visible++;
				}
			} );

			if ( deptNoResults ) {
				deptNoResults.hidden = visible > 0;
			}
		} );
	} );
} () );
