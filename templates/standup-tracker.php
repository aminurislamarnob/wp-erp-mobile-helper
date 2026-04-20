<div class="wrap erp-standup-tracker-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Daily Standup Tracker', 'wp-erp-app-helper' ); ?></h1>
    <hr class="wp-header-end">

    <!-- LIST VIEW -->
    <div id="st-list-view" class="st-card">
        <div class="st-header">
            <div class="st-header-left">
                <h2><?php esc_html_e('Standup Overview', 'wp-erp-app-helper'); ?></h2>
                <input type="month" id="st-month-filter" value="<?php echo date('Y-m'); ?>" max="<?php echo date('Y-m'); ?>">
            </div>
            <div class="st-actions">
                <button class="st-btn st-btn-secondary" id="btn-show-report">
                    <span class="dashicons dashicons-chart-bar st-btn-icon-right"></span>
                    <?php esc_html_e('Report', 'wp-erp-app-helper'); ?>
                </button>
                <button class="st-btn st-btn-primary" id="btn-show-add">
                    <span class="dashicons dashicons-plus-alt2 st-btn-icon-right"></span>
                    <?php esc_html_e('Add Standup', 'wp-erp-app-helper'); ?>
                </button>
            </div>
        </div>
        <div id="st-history-loading" class="st-loading"><span class="dashicons dashicons-update st-spin"></span> Loading...</div>
        <table class="st-table st-hidden" id="st-history-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'wp-erp-app-helper'); ?></th>
                    <th><?php esc_html_e('Present', 'wp-erp-app-helper'); ?></th>
                    <th><?php esc_html_e('Absent', 'wp-erp-app-helper'); ?></th>
                    <th><?php esc_html_e('Leave', 'wp-erp-app-helper'); ?></th>
                    <th class="st-text-right"><?php esc_html_e('Action', 'wp-erp-app-helper'); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="st-history-empty" class="st-hidden st-empty st-history-empty">
            <span class="dashicons dashicons-calendar-alt st-history-empty-icon"></span>
            <div class="st-history-empty-text"><?php esc_html_e('No standup records found for the selected month.', 'wp-erp-app-helper'); ?></div>
        </div>
    </div>

    <!-- FORM VIEW -->
    <div id="st-form-view" class="st-card st-hidden">
        <div class="st-header">
            <div class="st-header-left">
                <button class="st-btn st-btn-secondary" id="btn-back-list" title="Back">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                </button>
                <h2><?php esc_html_e('Record Standup', 'wp-erp-app-helper'); ?></h2>
            </div>
            <div>
                <?php
                // Max date is today
                $today = date('Y-m-d');
                ?>
                <input type="date" id="st-date-picker" value="<?php echo esc_attr( $today ); ?>" max="<?php echo esc_attr( $today ); ?>">
            </div>
        </div>

        <div class="st-bulk-actions">
            <span class="st-bulk-label"><?php esc_html_e('Bulk Actions:', 'wp-erp-app-helper'); ?></span>
            <button class="button" onclick="stMarkAll('present')"><?php esc_html_e('Mark All Present', 'wp-erp-app-helper'); ?></button>
            <button class="button" onclick="stMarkAll('absent')"><?php esc_html_e('Mark All Absent', 'wp-erp-app-helper'); ?></button>
            <button class="button" onclick="stMarkAll('leave')"><?php esc_html_e('Mark All Leave', 'wp-erp-app-helper'); ?></button>
        </div>

        <div id="st-form-loading" class="st-loading"><span class="dashicons dashicons-update st-spin"></span> Fetching Employees...</div>
        
        <table class="st-table st-hidden" id="st-employee-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Employee', 'wp-erp-app-helper'); ?></th>
                    <th><?php esc_html_e('Status', 'wp-erp-app-helper'); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        
        <div id="st-form-empty" class="st-hidden st-empty">
            <?php esc_html_e('No employees found with a shift on this date.', 'wp-erp-app-helper'); ?>
        </div>

        <div id="st-form-actions" class="st-hidden st-form-footer">
            <button class="st-btn st-btn-secondary" id="btn-cancel"><?php esc_html_e('Cancel', 'wp-erp-app-helper'); ?></button>
            <button class="st-btn st-btn-primary" id="btn-save">
                <?php esc_html_e('Save Standup', 'wp-erp-app-helper'); ?>
                <span id="st-save-spinner" class="dashicons dashicons-update st-spin st-hidden st-btn-icon-left st-spinner-small"></span>
            </button>
        </div>
    </div>

    <!-- REPORT MODAL -->
    <div id="st-report-modal" class="st-modal st-hidden">
        <div class="st-modal-backdrop"></div>
        <div class="st-modal-container">
            <div class="st-modal-header">
                <h3><?php esc_html_e('Generate Standup Report', 'wp-erp-app-helper'); ?></h3>
                <button class="st-btn-close">&times;</button>
            </div>
            <div class="st-modal-body">
                <p><?php esc_html_e('Select a month to download the attendance report in CSV format.', 'wp-erp-app-helper'); ?></p>
                <div class="st-field-group">
                    <label for="st-report-month"><?php esc_html_e('Select Month', 'wp-erp-app-helper'); ?></label>
                    <input type="month" id="st-report-month" value="<?php echo date('Y-m'); ?>" max="<?php echo date('Y-m'); ?>">
                </div>
            </div>
            <div class="st-modal-footer">
                <button class="st-btn st-btn-secondary btn-modal-close"><?php esc_html_e('Cancel', 'wp-erp-app-helper'); ?></button>
                <button class="st-btn st-btn-primary" id="btn-download-csv">
                    <span class="dashicons dashicons-download st-btn-icon-right"></span>
                    <?php esc_html_e('Download CSV', 'wp-erp-app-helper'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
