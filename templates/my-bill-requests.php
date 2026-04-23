<?php
/**
 * Template: Employee "My Bill Requests" list page
 *
 * @var string   $base_url       Base URL for status tab links
 * @var string   $current_status Active status slug
 * @var array    $statuses       slug => label map
 * @var int      $total          Total request count (all statuses)
 * @var array    $counts_raw     Raw status counts keyed by status slug
 * @var array    $requests               Requests for the current status filter
 * @var array    $attachments_by_request Attachments keyed by request ID
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
    <h2>
        <?php esc_html_e( 'My Bill Requests', 'wp-erp-app-helper' ); ?>
        <button type="button" id="erp-pr-open-form-modal" class="page-title-action"><?php esc_html_e( 'New Request', 'wp-erp-app-helper' ); ?></button>
    </h2>

    <ul class="subsubsub">
        <?php
        foreach ( $statuses as $slug => $label ) :
            $tab_count = ( 'all' === $slug ) ? $total : ( isset( $counts_raw[ $slug ] ) ? (int) $counts_raw[ $slug ]->count : 0 );
            $tab_url   = ( 'all' === $slug ) ? $base_url : add_query_arg( 'status', $slug, $base_url );
            $class     = ( $current_status === $slug ) ? 'current' : '';
            ?>
            <li>
                <a href="<?php echo esc_url( $tab_url ); ?>" class="<?php echo esc_attr( $class ); ?>">
                    <?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) $tab_count; ?>)</span>
                </a> |
            </li>
        <?php endforeach; ?>
    </ul>
    <br class="clear">

    <?php if ( empty( $requests ) ) : ?>
        <p><?php esc_html_e( 'No requests found.', 'wp-erp-app-helper' ); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Title', 'wp-erp-app-helper' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'wp-erp-app-helper' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'wp-erp-app-helper' ); ?></th>
                    <th><?php esc_html_e( 'Submitted', 'wp-erp-app-helper' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wp-erp-app-helper' ); ?></th>
                    <th><?php esc_html_e( 'HR Note', 'wp-erp-app-helper' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wp-erp-app-helper' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $requests as $request ) : ?>
                    <tr id="erp-pr-row-<?php echo esc_attr( $request->id ); ?>">
                        <td><?php echo esc_html( $request->title ); ?></td>
                        <td><strong>BDT <?php echo esc_html( number_format( $request->amount, 2 ) ); ?></strong></td>
                        <td><?php echo esc_html( wp_trim_words( $request->description, 15, '...' ) ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request->created_at ) ) ); ?></td>
                        <td>
                            <span class="erp-pr-status-badge status-<?php echo esc_attr( $request->status ); ?>">
                                <?php echo esc_html( ucfirst( $request->status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( 'rejected' === $request->status && ! empty( $request->hr_note ) ) : ?>
                                <em><?php echo esc_html( $request->hr_note ); ?></em>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ( 'pending' === $request->status ) :
                                $atts = isset( $attachments_by_request[ $request->id ] ) ? $attachments_by_request[ $request->id ] : [];
                                $request_data = wp_json_encode(
                                    [
										'id'                => (int) $request->id,
										'title'             => $request->title,
										'amount'            => (float) $request->amount,
										'description'       => $request->description,
										'purchase_date'     => $request->purchase_date,
										'expect_payment_by' => $request->expect_payment_by,
										'attachments'       => $atts,
									]
                                );
								?>
                                <button type="button" class="button erp-pr-edit-btn"
                                        data-request="<?php echo esc_attr( $request_data ); ?>">
                                    <?php esc_html_e( 'Edit', 'wp-erp-app-helper' ); ?>
                                </button>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
