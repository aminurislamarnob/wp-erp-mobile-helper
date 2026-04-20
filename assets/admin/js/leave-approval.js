(function($) {
    'use strict';

    var LeaveApproval = {
        init: function() {
            var self = this;
            $(document).on('click', '.erp-app-helper-required-approval', this.openModal.bind(this));
            $(document).on('click', '#erp-app-helper-modal-close', this.closeModal.bind(this));
            $(document).on('click', '#erp-app-helper-modal-save', this.saveApproval.bind(this));
            
            // Close on escape
            $(document).keyup(function(e) {
                if (e.keyCode === 27) self.closeModal();
            });
        },

        openModal: function(e) {
            e.preventDefault();
            var self = this;
            var $el = $(e.currentTarget);
            var requestId = $el.data('id');
            var employeeName = $el.data('name');

            $('#erp-app-helper-leave-modal').fadeIn();
            $('#erp-app-helper-modal-request-id').val(requestId);
            $('#erp-app-helper-modal-employee-name').text(employeeName);
            
            // Load team leads
            $('#erp-app-helper-approver-select').html('<option>' + erpAppHelper.i18n.loading + '</option>');
            
            $.ajax({
                url: erpAppHelper.ajaxurl,
                type: 'POST',
                data: {
                    action: 'erp_app_helper_get_team_leads',
                    nonce: erpAppHelper.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var options = '<option value="">' + erpAppHelper.i18n.selectClead + '</option>';
                        response.data.forEach(function(user) {
                            options += '<option value="' + user.id + '">' + user.name + '</option>';
                        });
                        $('#erp-app-helper-approver-select').html(options);
                    }
                }
            });
        },

        closeModal: function() {
            $('#erp-app-helper-leave-modal').fadeOut();
        },

        saveApproval: function(e) {
            var self = this;
            var requestId = $('#erp-app-helper-modal-request-id').val();
            var approverId = $('#erp-app-helper-approver-select').val();

            if (!approverId) {
                alert(erpAppHelper.i18n.selectClead);
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(erpAppHelper.i18n.loading);

            $.ajax({
                url: erpAppHelper.ajaxurl,
                type: 'POST',
                data: {
                    action: 'erp_app_helper_save_required_approval',
                    request_id: requestId,
                    approver_id: approverId,
                    nonce: erpAppHelper.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(erpAppHelper.i18n.success);
                        self.closeModal();
                        location.reload(); // Reload to see the system message in the list
                    } else {
                        alert(response.data);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text(erpAppHelper.i18n.save);
                }
            });
        }
    };

    $(document).ready(function() {
        LeaveApproval.init();

        // Inject status for employee history table
        if ($('#erp-hr-empl-leave-history-table').length) {
            $.ajax({
                url: erpAppHelper.ajaxurl,
                type: 'POST',
                data: {
                    action: 'erp_app_helper_get_employee_approval_status',
                    nonce: erpAppHelper.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length) {
                        $('#erp-hr-empl-leave-history-table tbody tr').each(function() {
                            var $row = $(this);
                            var dateRange = $row.find('td:first').text().trim();
                            var policyName = $row.find('td:nth-child(2)').text().trim();
                            
                            // Replace en-dash with em-dash or safe dash for comparison if needed
                            dateRange = dateRange.replace(/\u2013|\u2014/g, "—"); // Standardize dashes

                            response.data.forEach(function(item) {
                                var itemDate = item.date_range.replace(/\u2013|\u2014/g, "—");
                                if (dateRange === itemDate && policyName === item.leave_name) {
                                    var $statusCell = $row.find('td:last');
                                    var note = '<div style="font-size: 11px; margin-top: 4px; color: #666; font-style: italic;">(Waiting for: ' + item.approver_name + ')</div>';
                                    $statusCell.append(note);
                                }
                            });
                        });
                    }
                }
            });
        }
    });

})(jQuery);
