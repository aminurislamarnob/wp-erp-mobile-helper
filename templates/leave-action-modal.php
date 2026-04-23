<div id="erp-app-helper-action-modal" class="erp-app-helper-modal" style="display: none;">
    <div class="erp-app-helper-modal-content erp-app-helper-modal-modern">
        <div class="erp-app-helper-modal-header">
            <h3 id="erp-app-helper-action-title"></h3>
            <span id="erp-app-helper-action-modal-close" class="erp-app-helper-close">&times;</span>
        </div>
        <div class="erp-app-helper-modal-body">
            <p><?php esc_html_e( 'Employee:', 'wp-erp-app-helper' ); ?> <strong id="erp-app-helper-action-employee-name"></strong></p>
            
            <input type="hidden" id="erp-app-helper-action-request-id" value="">
            <input type="hidden" id="erp-app-helper-action-type" value="">
            
            <div class="erp-app-helper-form-group">
                <label for="erp-app-helper-action-message" id="erp-app-helper-action-label"></label>
                <textarea id="erp-app-helper-action-message" rows="4" placeholder="<?php esc_html_e( 'Enter your message here...', 'wp-erp-app-helper' ); ?>"></textarea>
            </div>
        </div>
        <div class="erp-app-helper-modal-footer">
            <button type="button" id="erp-app-helper-action-modal-save" class="button button-primary"></button>
            <button type="button" id="erp-app-helper-action-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'wp-erp-app-helper' ); ?></button>
        </div>
    </div>
</div>
