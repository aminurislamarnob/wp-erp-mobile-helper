<?php
/**
 * Template: HR approve and reject modals
 */
defined( 'ABSPATH' ) || exit;
?>
<div id="erp-pr-approve-modal" class="erp-app-helper-modal" style="display: none;">
    <div class="erp-app-helper-modal-content erp-app-helper-modal-modern">
        <div class="erp-app-helper-modal-header">
            <h3><?php esc_html_e( 'Approve Payment Request', 'wp-erp-app-helper' ); ?></h3>
            <span id="erp-pr-approve-modal-close" class="erp-app-helper-close">&times;</span>
        </div>
        <div class="erp-app-helper-modal-body">
            <p>
                <?php esc_html_e( 'Request:', 'wp-erp-app-helper' ); ?> <strong id="erp-pr-approve-modal-title"></strong><br>
                <?php esc_html_e( 'Employee:', 'wp-erp-app-helper' ); ?> <strong id="erp-pr-approve-modal-employee"></strong><br>
                <?php esc_html_e( 'Amount:', 'wp-erp-app-helper' ); ?> <strong id="erp-pr-approve-modal-amount"></strong>
            </p>

            <input type="hidden" id="erp-pr-approve-modal-request-id" value="">

            <div class="erp-app-helper-form-group">
                <label for="erp-pr-approve-modal-payment-type"><?php esc_html_e( 'Payment Type', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                <select id="erp-pr-approve-modal-payment-type">
                    <option value=""><?php esc_html_e( '— Select —', 'wp-erp-app-helper' ); ?></option>
                    <option value="cash"><?php esc_html_e( 'Cash', 'wp-erp-app-helper' ); ?></option>
                    <option value="bank_transfer"><?php esc_html_e( 'Bank Transfer', 'wp-erp-app-helper' ); ?></option>
                </select>
            </div>

            <div class="erp-app-helper-form-group">
                <label for="erp-pr-approve-modal-note"><?php esc_html_e( 'Note', 'wp-erp-app-helper' ); ?></label>
                <textarea id="erp-pr-approve-modal-note" rows="3" placeholder="<?php esc_attr_e( 'Optional note to the employee...', 'wp-erp-app-helper' ); ?>"></textarea>
            </div>
        </div>
        <div class="erp-app-helper-modal-footer">
            <button type="button" id="erp-pr-approve-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'wp-erp-app-helper' ); ?></button>
            <button type="button" id="erp-pr-approve-modal-submit" class="button button-primary"><?php esc_html_e( 'Confirm Approval', 'wp-erp-app-helper' ); ?></button>
        </div>
    </div>
</div>

<div id="erp-pr-reject-modal" class="erp-app-helper-modal" style="display: none;">
    <div class="erp-app-helper-modal-content erp-app-helper-modal-modern">
        <div class="erp-app-helper-modal-header">
            <h3><?php esc_html_e( 'Reject Payment Request', 'wp-erp-app-helper' ); ?></h3>
            <span id="erp-pr-modal-close" class="erp-app-helper-close">&times;</span>
        </div>
        <div class="erp-app-helper-modal-body">
            <p>
                <?php esc_html_e( 'Request:', 'wp-erp-app-helper' ); ?> <strong id="erp-pr-modal-title"></strong><br>
                <?php esc_html_e( 'Employee:', 'wp-erp-app-helper' ); ?> <strong id="erp-pr-modal-employee"></strong>
            </p>

            <input type="hidden" id="erp-pr-modal-request-id" value="">

            <div class="erp-app-helper-form-group">
                <label for="erp-pr-modal-note"><?php esc_html_e( 'Rejection Note', 'wp-erp-app-helper' ); ?> <span class="required">*</span></label>
                <textarea id="erp-pr-modal-note" rows="4" placeholder="<?php esc_attr_e( 'Enter the reason for rejection...', 'wp-erp-app-helper' ); ?>"></textarea>
                <p class="description"><?php esc_html_e( 'This note will be visible to the employee and sent via email.', 'wp-erp-app-helper' ); ?></p>
            </div>
        </div>
        <div class="erp-app-helper-modal-footer">
            <button type="button" id="erp-pr-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'wp-erp-app-helper' ); ?></button>
            <button type="button" id="erp-pr-modal-submit" class="button button-primary"><?php esc_html_e( 'Reject Request', 'wp-erp-app-helper' ); ?></button>
        </div>
    </div>
</div>
