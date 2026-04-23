<?php
/**
 * Template: New / Edit Bill Request — modal form
 */
defined( 'ABSPATH' ) || exit;
?>
<div id="erp-pr-form-modal" class="erp-app-helper-modal" style="display:none;">
    <div class="erp-app-helper-modal-content erp-pr-form-modal-inner">
        <div class="erp-app-helper-modal-header">
            <h3 id="erp-pr-form-modal-title"><?php esc_html_e( 'New Bill Request', 'wp-erp-app-helper' ); ?></h3>
            <span id="erp-pr-form-modal-close" class="erp-app-helper-close">&times;</span>
        </div>

        <div class="erp-app-helper-modal-body">
            <div id="erp-pr-form-modal-messages"></div>

            <div id="erp-pr-form-success" class="erp-pr-success-panel" style="display:none;">
                <div class="erp-pr-success-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h3 id="erp-pr-success-title"></h3>
                <p id="erp-pr-success-message"></p>
                <p class="erp-pr-success-redirecting"><?php esc_html_e( 'Redirecting to your bill requests…', 'wp-erp-app-helper' ); ?></p>
                <div class="erp-pr-success-progress">
                    <div class="erp-pr-success-progress-bar"></div>
                </div>
            </div>

            <form id="erp-pr-submit-form">
                <input type="hidden" id="erp-pr-request-id" name="request_id" value="0" />

                <div class="erp-app-helper-form-group">
                    <label for="erp-pr-title"><?php esc_html_e( 'Title', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                    <input type="text" id="erp-pr-title" name="title" class="widefat"
                            placeholder="<?php esc_attr_e( 'e.g. Hotel reimbursement – April 2026', 'wp-erp-app-helper' ); ?>" />
                </div>

                <div class="erp-app-helper-form-group">
                    <label for="erp-pr-amount"><?php esc_html_e( 'Amount (BDT)', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                    <input type="number" id="erp-pr-amount" name="amount" class="widefat"
                            min="0.01" step="0.01" placeholder="0.00" />
                </div>

                <div class="erp-app-helper-form-group">
                    <label for="erp-pr-description"><?php esc_html_e( 'Description', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                    <textarea id="erp-pr-description" name="description" rows="3" class="widefat"
                                placeholder="<?php esc_attr_e( 'Brief description of the expense...', 'wp-erp-app-helper' ); ?>"></textarea>
                </div>

                <div class="erp-pr-form-row">
                    <div class="erp-app-helper-form-group">
                        <label for="erp-pr-purchase-date"><?php esc_html_e( 'Purchase Date', 'wp-erp-app-helper' ); ?></label>
                        <input type="text" id="erp-pr-purchase-date" name="purchase_date" class="widefat erp-pr-datepicker" autocomplete="off" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'wp-erp-app-helper' ); ?>" />
                    </div>
                    <div class="erp-app-helper-form-group">
                        <label for="erp-pr-expect-payment-by"><?php esc_html_e( 'Expect Payment By', 'wp-erp-app-helper' ); ?></label>
                        <input type="text" id="erp-pr-expect-payment-by" name="expect_payment_by" class="widefat erp-pr-datepicker" autocomplete="off" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'wp-erp-app-helper' ); ?>" />
                    </div>
                </div>

                <div class="erp-app-helper-form-group">
                    <label><?php esc_html_e( 'Attachments', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                    <div id="erp-pr-attachment-list" class="erp-pr-attachment-list"></div>
                    <button type="button" id="erp-pr-attach-btn" class="button">
                        <?php esc_html_e( 'Add Files', 'wp-erp-app-helper' ); ?>
                    </button>
                    <p class="description"><?php esc_html_e( 'PDF, JPG, or PNG — max 10 MB each. At least one file required.', 'wp-erp-app-helper' ); ?></p>
                </div>
            </form>
        </div>

        <div class="erp-app-helper-modal-footer">
            <button type="button" id="erp-pr-form-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'wp-erp-app-helper' ); ?></button>
            <button type="button" id="erp-pr-submit-btn" class="button button-primary"><?php esc_html_e( 'Submit Request', 'wp-erp-app-helper' ); ?></button>
        </div>
    </div>
</div>
