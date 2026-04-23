(function($) {
    'use strict';

    var LeaveApproval = {
        init: function() {
            var self = this;
            $(document).on('click', '.erp-app-helper-required-approval', this.openModal.bind(this));
            $(document).on('click', '#erp-app-helper-modal-close', this.closeModal.bind(this));
            $(document).on('click', '#erp-app-helper-modal-save', this.saveApproval.bind(this));

            // New Action Modal
            $(document).on('click', '.erp-app-helper-action-btn', this.openActionModal.bind(this));
            $(document).on('click', '#erp-app-helper-action-modal-close, #erp-app-helper-action-modal-cancel', this.closeActionModal.bind(this));
            $(document).on('click', '#erp-app-helper-action-modal-save', this.processAction.bind(this));

            // Close on escape
            $(document).keyup(function(e) {
                if (e.keyCode === 27) {
                    self.closeModal();
                    self.closeActionModal();
                }
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
        },

        openActionModal: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var action = $btn.data('action'); // 'approve' or 'reject'
            var id = $btn.data('id');
            var name = $btn.data('name');

            $('#erp-app-helper-action-request-id').val(id);
            $('#erp-app-helper-action-type').val(action);
            $('#erp-app-helper-action-employee-name').text(name);

            if (action === 'approve') {
                $('#erp-app-helper-action-title').text(erpAppHelper.i18n.approveTitle);
                $('#erp-app-helper-action-label').text(erpAppHelper.i18n.approveLabel);
                $('#erp-app-helper-action-modal-save').removeClass('button-secondary').addClass('button-primary').text(erpAppHelper.i18n.approve);
            } else {
                $('#erp-app-helper-action-title').text(erpAppHelper.i18n.rejectTitle);
                $('#erp-app-helper-action-label').text(erpAppHelper.i18n.rejectLabel);
                $('#erp-app-helper-action-modal-save').removeClass('button-primary').addClass('button-secondary').text(erpAppHelper.i18n.reject);
            }

            $('#erp-app-helper-action-modal').fadeIn();
            $('#erp-app-helper-action-message').val('').focus();
        },

        closeActionModal: function() {
            $('#erp-app-helper-action-modal').fadeOut();
        },

        processAction: function(e) {
            var self = this;
            var requestId = $('#erp-app-helper-action-request-id').val();
            var actionType = $('#erp-app-helper-action-type').val();
            var message = $('#erp-app-helper-action-message').val();

            if (actionType === 'reject' && !message.trim()) {
                alert(erpAppHelper.i18n.rejectLabel);
                return;
            }

            var $btn = $(e.currentTarget);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text(erpAppHelper.i18n.loading);

            $.ajax({
                url: erpAppHelper.ajaxurl,
                type: 'POST',
                data: {
                    action: 'erp_app_helper_process_leave_action',
                    request_id: requestId,
                    action_type: actionType,
                    message: message,
                    nonce: erpAppHelper.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        self.closeActionModal();
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    $(document).ready(function() {
        LeaveApproval.init();
    });

})(jQuery);
