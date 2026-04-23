<div id="erp-app-helper-leave-modal" class="erp-app-helper-modal">
    <div class="erp-app-helper-modal-content">
        <div class="erp-app-helper-modal-header">
            <h2><?php esc_html_e( 'Required Approval', 'wp-erp-app-helper' ); ?></h2>
            <button type="button" id="erp-app-helper-modal-close" class="erp-app-helper-modal-close-btn">&times;</button>
        </div>
        <div class="erp-app-helper-modal-body">
            <div class="erp-app-helper-form-group">
                <p><?php printf( __( 'Set a required approver for <span id="erp-app-helper-modal-employee-name" class="employee-name-highlight"></span>\'s leave request.', 'wp-erp-app-helper' ) ); ?></p>
            </div>
            
            <input type="hidden" id="erp-app-helper-modal-request-id" value="">
            
            <div class="erp-app-helper-form-group">
                <label for="erp-app-helper-approver-select"><?php esc_html_e( 'Select Approver', 'wp-erp-app-helper' ); ?></label>
                <select id="erp-app-helper-approver-select">
                    <!-- Options populated via AJAX -->
                </select>
                <p class="description"><?php esc_html_e( 'Only Team Leads and Project Leads are shown.', 'wp-erp-app-helper' ); ?></p>
            </div>
        </div>
        <div class="erp-app-helper-modal-footer">
            <button type="button" class="erp-app-helper-btn erp-app-helper-btn-secondary" id="erp-app-helper-modal-close"><?php esc_html_e( 'Cancel', 'wp-erp-app-helper' ); ?></button>
            <button type="button" class="erp-app-helper-btn erp-app-helper-btn-primary" id="erp-app-helper-modal-save"><?php esc_html_e( 'Save', 'wp-erp-app-helper' ); ?></button>
        </div>
    </div>
</div>
