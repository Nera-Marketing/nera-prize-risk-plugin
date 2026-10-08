/**
 * Prize Risk report: client-side column sort for the competitions table and the rollups.
 * Numeric when the column's cells carry data-value (empty values go last), else text
 * (data-sort or the cell text). Only tbody rows move; tfoot totals stay put. The page
 * renders in its default order without this script.
 */
( function () {
	'use strict';

	function cellKey( cell, numeric ) {
		if ( ! cell ) {
			return null;
		}
		if ( numeric ) {
			var v = cell.getAttribute( 'data-value' );
			return v === null || v === '' || isNaN( parseFloat( v ) ) ? null : parseFloat( v );
		}
		var s = cell.getAttribute( 'data-sort' );
		return ( s !== null ? s : cell.textContent ).trim();
	}

	function setup( table ) {
		var tbody = table.tBodies[ 0 ];
		if ( ! tbody || ! table.tHead || tbody.querySelector( 'tr.no-items' ) ) {
			return;
		}
		var rows = Array.prototype.slice.call( tbody.rows );
		var heads = Array.prototype.slice.call( table.tHead.rows[ 0 ].cells );

		heads.forEach( function ( th, col ) {
			var button = th.querySelector( '.nera-prize-risk-sort' );
			if ( ! button ) {
				return;
			}
			button.addEventListener( 'click', function () {
				var dir = th.getAttribute( 'aria-sort' ) === 'ascending' ? -1 : 1;
				var numeric = rows.some( function ( row ) {
					return row.cells[ col ] && row.cells[ col ].hasAttribute( 'data-value' );
				} );
				var keyed = rows.map( function ( row, index ) {
					return { row: row, index: index, key: cellKey( row.cells[ col ], numeric ) };
				} );
				keyed.sort( function ( a, b ) {
					if ( a.key === null || b.key === null ) {
						if ( a.key !== b.key ) {
							return a.key === null ? 1 : -1;
						}
						return a.index - b.index;
					}
					var c = numeric ? a.key - b.key : a.key.localeCompare( b.key );
					return c ? c * dir : a.index - b.index;
				} );
				var frag = document.createDocumentFragment();
				keyed.forEach( function ( k ) {
					frag.appendChild( k.row );
				} );
				tbody.appendChild( frag );

				heads.forEach( function ( h ) {
					if ( ! h.querySelector( '.nera-prize-risk-sort' ) ) {
						return;
					}
					h.classList.remove( 'sorted', 'asc', 'desc' );
					h.classList.add( 'sortable' );
					h.removeAttribute( 'aria-sort' );
				} );
				th.classList.remove( 'sortable' );
				th.classList.add( 'sorted', dir === 1 ? 'asc' : 'desc' );
				th.setAttribute( 'aria-sort', dir === 1 ? 'ascending' : 'descending' );
			} );
		} );
	}

	function init() {
		document.querySelectorAll( '.nera-prize-risk table.nera-prize-risk-table' ).forEach( setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
