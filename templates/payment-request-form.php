<?php
/**
 * Template: Employee payment request submission form
 *
 * @var string $list_url URL to redirect back to the list
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
    <h2>
        <?php esc_html_e( 'New Bill Request', 'wp-erp-app-helper' ); ?>
        <a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'Back to My Bill Requests', 'wp-erp-app-helper' ); ?></a>
    </h2>

    <div id="erp-pr-form-messages"></div>

    <form id="erp-pr-submit-form" method="post">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="erp-pr-title"><?php esc_html_e( 'Title', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="erp-pr-title" name="title" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Hotel reimbursement – April 2026', 'wp-erp-app-helper' ); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erp-pr-amount"><?php esc_html_e( 'Amount ($)', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="number" id="erp-pr-amount" name="amount" class="small-text" min="0.01" step="0.01" required placeholder="0.00" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erp-pr-description"><?php esc_html_e( 'Description', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <textarea id="erp-pr-description" name="description" rows="4" class="large-text" required placeholder="<?php esc_attr_e( 'Brief description of the expense...', 'wp-erp-app-helper' ); ?>"></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e( 'Attachments', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <div id="erp-pr-attachment-list" class="erp-pr-attachment-list"></div>
                    <button type="button" id="erp-pr-attach-btn" class="button">
                        <?php esc_html_e( 'Add Files (PDF, JPG, PNG — max 10 MB each)', 'wp-erp-app-helper' ); ?>
                    </button>
                    <p class="description"><?php esc_html_e( 'Upload receipts or supporting documents. At least one file is required.', 'wp-erp-app-helper' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" id="erp-pr-submit-btn" class="button button-primary"><?php esc_html_e( 'Submit Request', 'wp-erp-app-helper' ); ?></button>
            <a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'wp-erp-app-helper' ); ?></a>
        </p>
    </form>
</div>
