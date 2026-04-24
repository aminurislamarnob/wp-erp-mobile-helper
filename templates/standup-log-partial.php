<?php
/**
 * Template partial: Standup log summary row + table body (also returned via AJAX)
 *
 * @var array $entries  Standup records: objects with standup_date and status
 * @var array $summary  Counts: [ 'present' => int, 'absent' => int, 'leave' => int ]
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="erp-sl-summary" id="erp-sl-summary">
    <span class="erp-sl-summary-item erp-sl-summary-present">
        <span class="dashicons dashicons-yes-alt"></span>
        <?php
        printf(
            /* translators: %d: number of present days */
            esc_html__( 'Present: %d', 'wp-erp-app-helper' ),
            (int) $summary['present']
        );
        ?>
    </span>
    <span class="erp-sl-summary-item erp-sl-summary-absent">
        <span class="dashicons dashicons-dismiss"></span>
        <?php
        printf(
            /* translators: %d: number of absent days */
            esc_html__( 'Absent: %d', 'wp-erp-app-helper' ),
            (int) $summary['absent']
        );
        ?>
    </span>
    <span class="erp-sl-summary-item erp-sl-summary-leave">
        <span class="dashicons dashicons-calendar"></span>
        <?php
        printf(
            /* translators: %d: number of leave days */
            esc_html__( 'Leave: %d', 'wp-erp-app-helper' ),
            (int) $summary['leave']
        );
        ?>
    </span>
</div>

<?php if ( empty( $entries ) ) : ?>
    <div class="erp-sl-empty-state">
        <span class="dashicons dashicons-calendar-alt erp-sl-empty-icon"></span>
        <p><?php esc_html_e( 'No standup records found for the selected month.', 'wp-erp-app-helper' ); ?></p>
    </div>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped erp-sl-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Date', 'wp-erp-app-helper' ); ?></th>
                <th><?php esc_html_e( 'Status', 'wp-erp-app-helper' ); ?></th>
            </tr>
        </thead>
        <tbody id="erp-sl-tbody">
            <?php foreach ( $entries as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->standup_date ) ) ); ?></td>
                    <td>
                        <span class="erp-sl-badge erp-sl-badge-<?php echo esc_attr( $entry->status ); ?>">
                            <?php echo esc_html( ucfirst( $entry->status ) ); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
