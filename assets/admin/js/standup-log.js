/* global erpStandupLog, jQuery */
( function ( $ ) {
    'use strict';

    var $picker    = $( '#erp-sl-month-picker' );
    var $filterBtn = $( '#erp-sl-filter-btn' );
    var $content   = $( '#erp-sl-content' );
    var $spinner   = $( '#erp-sl-spinner' );

    var today     = new Date();
    var maxYear   = today.getFullYear();
    var maxMonth  = today.getMonth(); // 0-based

    var MONTH_LABELS = [
        'January', 'February', 'March', 'April',
        'May', 'June', 'July', 'August',
        'September', 'October', 'November', 'December'
    ];
    var MONTH_SHORT = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];

    /* ── Helpers ────────────────────────────────────────────────────────── */

    function getSelectedParts() {
        var val   = ( $picker.data( 'month-value' ) || '' ).toString();
        var parts = val.split( '-' );
        return {
            year:  parts[0] ? parseInt( parts[0], 10 ) : maxYear,
            month: parts[1] ? parseInt( parts[1], 10 ) - 1 : maxMonth,
        };
    }

    /* ── Custom month-grid dropdown ─────────────────────────────────────── */

    var $dropdown   = null;
    var displayYear = maxYear;

    function renderGrid() {
        var sel   = getSelectedParts();
        var $grid = $dropdown.find( '.erp-sl-dp-grid' );
        var $next = $dropdown.find( '.erp-sl-dp-next' );

        $dropdown.find( '.erp-sl-dp-year' ).text( displayYear );
        $next.prop( 'disabled', displayYear >= maxYear );

        $grid.empty();

        for ( var m = 0; m < 12; m++ ) {
            var isFuture   = ( displayYear === maxYear && m > maxMonth );
            var isSelected = ( displayYear === sel.year && m === sel.month );
            var $btn = $( '<button type="button" class="erp-sl-dp-month"></button>' )
                .text( MONTH_SHORT[ m ] )
                .attr( 'data-month', m );

            if ( isFuture ) {
                $btn.addClass( 'is-disabled' ).prop( 'disabled', true );
            }
            if ( isSelected ) {
                $btn.addClass( 'is-selected' );
            }

            $grid.append( $btn );
        }
    }

    function openDropdown() {
        if ( $dropdown ) {
            closeDropdown();
            return;
        }

        displayYear = getSelectedParts().year;

        $dropdown = $(
            '<div class="erp-sl-dp">' +
                '<div class="erp-sl-dp-header">' +
                    '<button type="button" class="erp-sl-dp-nav erp-sl-dp-prev">&#8249;</button>' +
                    '<span class="erp-sl-dp-year"></span>' +
                    '<button type="button" class="erp-sl-dp-nav erp-sl-dp-next">&#8250;</button>' +
                '</div>' +
                '<div class="erp-sl-dp-grid"></div>' +
            '</div>'
        );

        $picker.closest( '.erp-sl-month-picker-wrap' ).append( $dropdown );
        renderGrid();

        $dropdown.on( 'click', '.erp-sl-dp-prev', function ( e ) {
            e.stopPropagation();
            displayYear--;
            renderGrid();
        } );

        $dropdown.on( 'click', '.erp-sl-dp-next', function ( e ) {
            e.stopPropagation();
            if ( displayYear < maxYear ) {
                displayYear++;
                renderGrid();
            }
        } );

        $dropdown.on( 'click', '.erp-sl-dp-month', function ( e ) {
            e.stopPropagation();
            var m        = parseInt( $( this ).attr( 'data-month' ), 10 );
            var monthNum = ( '0' + ( m + 1 ) ).slice( -2 );
            var newVal   = displayYear + '-' + monthNum;
            var newLabel = MONTH_LABELS[ m ] + ' ' + displayYear;

            $picker.val( newLabel ).data( 'month-value', newVal );
            closeDropdown();
        } );

        $( document ).on( 'click.erp-sl-dp', function ( e ) {
            if ( ! $( e.target ).closest( '.erp-sl-dp, #erp-sl-month-picker' ).length ) {
                closeDropdown();
            }
        } );
    }

    function closeDropdown() {
        if ( $dropdown ) {
            $dropdown.remove();
            $dropdown = null;
        }
        $( document ).off( 'click.erp-sl-dp' );
    }

    $picker.on( 'click', function ( e ) {
        e.stopPropagation();
        openDropdown();
    } );

    /* ── AJAX load ──────────────────────────────────────────────────────── */

    function loadMonth( monthValue ) {
        var employeeId = parseInt( $picker.data( 'employee-id' ), 10 );

        if ( ! monthValue || ! employeeId ) {
            return;
        }

        $picker.prop( 'disabled', true );
        $filterBtn.prop( 'disabled', true );
        $spinner.addClass( 'is-active' );

        $.post(
            erpStandupLog.ajaxurl,
            {
                action:      'erp_app_helper_standup_log',
                nonce:       erpStandupLog.nonce,
                employee_id: employeeId,
                month:       monthValue,
            },
            function ( response ) {
                if ( response.success && response.data && response.data.html ) {
                    $content.html( response.data.html );
                } else {
                    $content.html( '<p class="erp-sl-error">' + erpStandupLog.i18n.error + '</p>' );
                }
            }
        ).fail( function () {
            $content.html( '<p class="erp-sl-error">' + erpStandupLog.i18n.error + '</p>' );
        } ).always( function () {
            $picker.prop( 'disabled', false );
            $filterBtn.prop( 'disabled', false );
            $spinner.removeClass( 'is-active' );
        } );
    }

    $filterBtn.on( 'click', function () {
        loadMonth( $picker.data( 'month-value' ) );
    } );

} )( jQuery );
