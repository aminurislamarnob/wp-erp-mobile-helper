<?php
/**
 * Standup Progress dashboard widget.
 *
 * @package WeLabs\WpErpAppHelper
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="erp-spw-wrap">

    <div class="erp-spw-chart-wrap">
        <div class="erp-spw-chart" id="erp-spw-chart"></div>
        <p class="erp-spw-no-data"></p>
    </div>

    <div class="erp-spw-legend">
        <span class="erp-spw-legend-item erp-spw-present">
            <span class="erp-spw-dot"></span>
            <?php esc_html_e( 'Present', 'wp-erp-app-helper' ); ?>
            <strong class="erp-spw-val" data-key="present">–</strong>
        </span>
        <span class="erp-spw-legend-item erp-spw-absent">
            <span class="erp-spw-dot"></span>
            <?php esc_html_e( 'Absent', 'wp-erp-app-helper' ); ?>
            <strong class="erp-spw-val" data-key="absent">–</strong>
        </span>
        <span class="erp-spw-legend-item erp-spw-leave">
            <span class="erp-spw-dot"></span>
            <?php esc_html_e( 'Leave', 'wp-erp-app-helper' ); ?>
            <strong class="erp-spw-val" data-key="leave">–</strong>
        </span>
    </div>

    <div class="erp-spw-filter">
        <span><?php esc_html_e( 'Filter By', 'wp-erp-app-helper' ); ?>: </span>
        <select id="erp-spw-period">
            <option value="this_month"><?php esc_html_e( 'This Month', 'wp-erp-app-helper' ); ?></option>
            <option value="last_month"><?php esc_html_e( 'Last Month', 'wp-erp-app-helper' ); ?></option>
        </select>
    </div>

</div>
