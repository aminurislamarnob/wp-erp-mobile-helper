<?php
/**
 * Template: Employee Standup Log profile tab
 *
 * @var int    $employee_id   The viewed employee's WP user ID
 * @var string $current_month Current month as YYYY-MM
 * @var array  $entries       Standup records for the current month
 * @var array  $summary       Counts keyed by status: present, absent, leave
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="erp-sl-wrap">

    <div class="erp-sl-header">
        <h3><?php esc_html_e( 'Standup Log', 'wp-erp-app-helper' ); ?></h3>
        <div class="erp-sl-month-picker-wrap">
            <label for="erp-sl-month-picker" class="screen-reader-text">
                <?php esc_html_e( 'Select month', 'wp-erp-app-helper' ); ?>
            </label>
            <input
                type="text"
                id="erp-sl-month-picker"
                class="erp-sl-month-picker"
                value="<?php echo esc_attr( date_i18n( 'F Y', strtotime( $current_month . '-01' ) ) ); ?>"
                data-month-value="<?php echo esc_attr( $current_month ); ?>"
                data-employee-id="<?php echo esc_attr( $employee_id ); ?>"
                readonly="readonly"
            >
            <button type="button" id="erp-sl-filter-btn" class="button button-primary erp-sl-filter-btn">
                <?php esc_html_e( 'Filter', 'wp-erp-app-helper' ); ?>
            </button>
            <span class="spinner" id="erp-sl-spinner"></span>
        </div>
    </div>

    <div id="erp-sl-content">
        <?php
        welabs_wp_erp_app_helper()->get_template(
            'standup-log-partial.php',
            [
                'entries' => $entries,
                'summary' => $summary,
            ]
        );
        ?>
    </div>

</div>
