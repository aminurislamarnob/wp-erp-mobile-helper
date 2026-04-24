/* global erpStandupWidget, jQuery */
( function ( $ ) {
    'use strict';

    var COLORS = {
        present: '#46b450',
        absent:  '#dc3232',
        leave:   '#f0ad4e',
    };

    function init() {
        var $wrap = $( '.erp-spw-wrap' );

        if ( ! $wrap.length ) {
            return;
        }

        $wrap.each( function () {
            var $w      = $( this );
            var $period = $w.find( '#erp-spw-period' );

            function load() {
                var $chart  = $w.find( '#erp-spw-chart' );
                var $noData = $w.find( '.erp-spw-no-data' );

                $chart.hide();
                $noData.text( erpStandupWidget.i18n.loading ).show();

                $.post(
                    erpStandupWidget.ajaxurl,
                    {
                        action: 'erp_app_helper_standup_widget_data',
                        nonce:  erpStandupWidget.nonce,
                        period: $period.val(),
                    },
                    function ( res ) {
                        if ( res && res.success ) {
                            render( $w, res.data );
                        } else {
                            $noData.text( erpStandupWidget.i18n.noData );
                        }
                    }
                ).fail( function () {
                    $noData.text( erpStandupWidget.i18n.noData );
                } );
            }

            $period.on( 'change', load );
            load();
        } );
    }

    function render( $w, data ) {
        var present = parseInt( data.present, 10 ) || 0;
        var absent  = parseInt( data.absent,  10 ) || 0;
        var leave   = parseInt( data.leave,   10 ) || 0;
        var total   = present + absent + leave;

        $w.find( '.erp-spw-val[data-key="present"]' ).text( present );
        $w.find( '.erp-spw-val[data-key="absent"]'  ).text( absent );
        $w.find( '.erp-spw-val[data-key="leave"]'   ).text( leave );

        var $chart  = $w.find( '#erp-spw-chart' );
        var $noData = $w.find( '.erp-spw-no-data' );

        if ( ! total ) {
            $chart.hide();
            $noData.text( erpStandupWidget.i18n.noData ).show();
            return;
        }

        $noData.hide();
        $chart.show();

        var plotData = [];

        if ( present ) {
            plotData.push( { label: erpStandupWidget.i18n.present, data: present, color: COLORS.present } );
        }
        if ( absent ) {
            plotData.push( { label: erpStandupWidget.i18n.absent, data: absent, color: COLORS.absent } );
        }
        if ( leave ) {
            plotData.push( { label: erpStandupWidget.i18n.leave, data: leave, color: COLORS.leave } );
        }

        $.plot( $chart, plotData, {
            series: {
                pie: {
                    show:        true,
                    radius:      1,
                    innerRadius: 0.4,
                    label: {
                        show:      true,
                        radius:    2 / 3,
                        formatter: function ( label, series ) {
                            return '<div class="erp-flot-pie-label">' + series.data[ 0 ][ 1 ] + '</div>';
                        },
                    },
                },
            },
        } );
    }

    $( document ).ready( init );

} )( jQuery );
