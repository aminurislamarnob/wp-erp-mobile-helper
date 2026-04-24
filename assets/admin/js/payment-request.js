/* global erpPaymentRequest, wp */
(function ($) {
  "use strict";

  var MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
  var ALLOWED_EXTS = ["pdf", "jpg", "jpeg", "png"];
  var mediaFrame = null;
  var addedIds = {};

  // ── Form modal helpers ─────────────────────────────────────────────────

  function openFormModal(requestData) {
    resetFormModal();

    if (requestData && requestData.id) {
      // Edit mode
      $("#erp-pr-form-modal-title").text(erpPaymentRequest.i18n.editTitle);
      $("#erp-pr-submit-btn").text(erpPaymentRequest.i18n.saveChanges);
      $("#erp-pr-request-id").val(requestData.id);
      $("#erp-pr-title").val(requestData.title);
      $("#erp-pr-amount").val(requestData.amount);
      $("#erp-pr-description").val(requestData.description);
      $("#erp-pr-purchase-date").val(requestData.purchase_date || "");
      $("#erp-pr-expect-payment-by").val(requestData.expect_payment_by || "");

      if ($("#erp-pr-employee-id").length && requestData.employee_id) {
        $("#erp-pr-employee-id")
          .val(String(requestData.employee_id))
          .trigger("change");
      }

      if (requestData.attachments && requestData.attachments.length) {
        $.each(requestData.attachments, function (i, att) {
          renderAttachment(att.id, att.filename);
        });
      }
    } else {
      // Create mode
      $("#erp-pr-form-modal-title").text(erpPaymentRequest.i18n.newTitle);
      $("#erp-pr-submit-btn").text(erpPaymentRequest.i18n.submit);
    }

    $("#erp-pr-form-modal").fadeIn(200);
  }

  function closeFormModal() {
    $("#erp-pr-form-modal").fadeOut(200);
  }

  function resetFormModal() {
    $("#erp-pr-submit-form")[0].reset();
    $("#erp-pr-request-id").val("0");
    if ($("#erp-pr-employee-id").length) {
      $("#erp-pr-employee-id").trigger("change");
    }
    $("#erp-pr-attachment-list").empty();
    addedIds = {};
    mediaFrame = null;
    $("#erp-pr-form-modal-messages").empty();
    $("#erp-pr-submit-btn").prop("disabled", false);
  }

  function showFormError(msg) {
    $("#erp-pr-form-modal-messages").html(
      '<div class="notice notice-error inline"><p>' + msg + "</p></div>",
    );
  }

  function showFormSuccess(isEdit) {
    var title = isEdit
      ? erpPaymentRequest.i18n.successTitleEdit
      : erpPaymentRequest.i18n.successTitle;
    var msg = isEdit
      ? erpPaymentRequest.i18n.successMessageEdit
      : erpPaymentRequest.i18n.successMessage;

    $("#erp-pr-form-modal-messages").hide();
    $("#erp-pr-submit-form").hide();
    $("#erp-pr-form-modal .erp-app-helper-modal-footer").hide();
    $("#erp-pr-form-modal-close").hide();

    $("#erp-pr-success-title").text(title);
    $("#erp-pr-success-message").text(msg);
    $("#erp-pr-form-success").show();

    setTimeout(function () {
      $(".erp-pr-success-progress-bar").css("width", "100%");
    }, 50);

    setTimeout(function () {
      window.location.href = erpPaymentRequest.listUrl;
    }, 2200);
  }

  // ── Datepicker ─────────────────────────────────────────────────────────

  $(document).ready(function () {
    $(".erp-pr-datepicker").datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
    });
  });

  // ── Open / close ───────────────────────────────────────────────────────

  $(document).on("click", "#erp-pr-open-form-modal", function (e) {
    e.preventDefault();
    openFormModal();
  });

  $(document).on("click", ".erp-pr-edit-btn", function (e) {
    e.preventDefault();
    var requestData = {};
    try {
      requestData = JSON.parse($(this).attr("data-request"));
    } catch (err) {
      /* ignore */
    }
    openFormModal(requestData);
  });

  $(document).on(
    "click",
    "#erp-pr-form-modal-close, #erp-pr-form-modal-cancel",
    function (e) {
      e.preventDefault();
      closeFormModal();
    },
  );

  $(document).on("click", "#erp-pr-form-modal", function (e) {
    if ($(e.target).is("#erp-pr-form-modal")) {
      closeFormModal();
    }
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $("#erp-pr-form-modal").is(":visible")) {
      closeFormModal();
    }
  });

  // ── wp.media uploader ──────────────────────────────────────────────────

  $(document).on("click", "#erp-pr-attach-btn", function (e) {
    e.preventDefault();

    if (!window.wp || !wp.media) {
      alert("Media uploader not available.");
      return;
    }

    if (!mediaFrame) {
      mediaFrame = wp.media({
        title: erpPaymentRequest.i18n.selectFiles,
        button: { text: erpPaymentRequest.i18n.attachFiles },
        multiple: true,
      });

      mediaFrame.on("select", function () {
        mediaFrame
          .state()
          .get("selection")
          .each(function (attachment) {
            var att = attachment.toJSON();
            var ext = att.filename
              ? att.filename.split(".").pop().toLowerCase()
              : "";

            if (ALLOWED_EXTS.indexOf(ext) === -1) {
              showFormError(
                erpPaymentRequest.i18n.invalidType.replace(
                  "{name}",
                  att.filename,
                ),
              );
              return;
            }
            if ((att.filesizeInBytes || 0) > MAX_FILE_SIZE) {
              showFormError(
                erpPaymentRequest.i18n.fileTooLarge.replace(
                  "{name}",
                  att.filename,
                ),
              );
              return;
            }

            renderAttachment(att.id, att.filename);
          });
      });
    }

    mediaFrame.open();
  });

  function renderAttachment(id, filename) {
    if (addedIds[id]) {
      return;
    }
    addedIds[id] = true;

    $("#erp-pr-attachment-list").append(
      '<div class="erp-pr-attachment-item" id="erp-pr-attachment-' +
        id +
        '">' +
        '<input type="hidden" name="attachment_ids[]" value="' +
        id +
        '">' +
        '<span class="dashicons dashicons-paperclip"></span>' +
        '<span class="erp-pr-attachment-name">' +
        filename +
        "</span>" +
        '<button type="button" class="erp-pr-remove-attachment" data-id="' +
        id +
        '">&times;</button>' +
        "</div>",
    );
  }

  $(document).on("click", ".erp-pr-remove-attachment", function () {
    var id = $(this).data("id");
    delete addedIds[id];
    $("#erp-pr-attachment-" + id).remove();
  });

  // ── Submit / update form ───────────────────────────────────────────────

  $(document).on("click", "#erp-pr-submit-btn", function (e) {
    e.preventDefault();

    var $btn = $(this);
    var requestId = parseInt($("#erp-pr-request-id").val(), 10);
    var isEdit = requestId > 0;

    if (!$("#erp-pr-title").val().trim()) {
      showFormError(
        erpPaymentRequest.i18n.fieldRequired.replace("{field}", "Title"),
      );
      return;
    }
    if (!(parseFloat($("#erp-pr-amount").val()) > 0)) {
      showFormError(
        erpPaymentRequest.i18n.fieldRequired.replace("{field}", "Amount"),
      );
      return;
    }
    if (!$("#erp-pr-description").val().trim()) {
      showFormError(
        erpPaymentRequest.i18n.fieldRequired.replace("{field}", "Description"),
      );
      return;
    }
    if (
      !isEdit &&
      $("#erp-pr-employee-id").length &&
      !$("#erp-pr-employee-id").val()
    ) {
      showFormError(erpPaymentRequest.i18n.selectEmployee);
      return;
    }
    if ($("#erp-pr-attachment-list .erp-pr-attachment-item").length === 0) {
      showFormError(erpPaymentRequest.i18n.attachmentRequired);
      return;
    }

    $btn.prop("disabled", true).text(erpPaymentRequest.i18n.submitting);

    var action = isEdit
      ? "erp_app_helper_update_payment_request"
      : "erp_app_helper_submit_payment_request";
    var formData = $("#erp-pr-submit-form").serialize();
    formData += "&action=" + action + "&nonce=" + erpPaymentRequest.nonce;

    $.post(erpPaymentRequest.ajaxurl, formData)
      .done(function (response) {
        if (response.success) {
          showFormSuccess(isEdit);
        } else {
          showFormError(response.data || erpPaymentRequest.i18n.error);
          $btn
            .prop("disabled", false)
            .text(
              isEdit
                ? erpPaymentRequest.i18n.saveChanges
                : erpPaymentRequest.i18n.submit,
            );
        }
      })
      .fail(function () {
        showFormError(erpPaymentRequest.i18n.error);
        $btn
          .prop("disabled", false)
          .text(
            isEdit
              ? erpPaymentRequest.i18n.saveChanges
              : erpPaymentRequest.i18n.submit,
          );
      });
  });

  // ── Approve request (HR) — opens approve modal ─────────────────────────

  $(document).on("click", ".erp-pr-approve-btn", function (e) {
    e.preventDefault();

    var $btn = $(this);
    $("#erp-pr-approve-modal-request-id").val($btn.data("id"));
    $("#erp-pr-approve-modal-title").text($btn.data("title"));
    $("#erp-pr-approve-modal-employee").text($btn.data("employee"));
    $("#erp-pr-approve-modal-amount").text("BDT " + $btn.data("amount"));
    $("#erp-pr-approve-modal-payment-type").val("");
    $("#erp-pr-approve-modal-note").val("");
    $("#erp-pr-approve-modal-submit")
      .prop("disabled", false)
      .text(erpPaymentRequest.i18n.confirmApproval);
    $("#erp-pr-approve-modal").fadeIn(200);
  });

  $(document).on(
    "click",
    "#erp-pr-approve-modal-close, #erp-pr-approve-modal-cancel",
    function (e) {
      e.preventDefault();
      $("#erp-pr-approve-modal").fadeOut(200);
    },
  );

  $(document).on("click", "#erp-pr-approve-modal", function (e) {
    if ($(e.target).is("#erp-pr-approve-modal")) {
      $("#erp-pr-approve-modal").fadeOut(200);
    }
  });

  $(document).on("click", "#erp-pr-approve-modal-submit", function (e) {
    e.preventDefault();

    var paymentType = $("#erp-pr-approve-modal-payment-type").val();
    if (!paymentType) {
      alert(erpPaymentRequest.i18n.paymentTypeRequired);
      return;
    }

    var $btn = $(this);
    var id = $("#erp-pr-approve-modal-request-id").val();
    var note = $("#erp-pr-approve-modal-note").val();
    $btn.prop("disabled", true).text(erpPaymentRequest.i18n.submitting);

    if ($.fn.select2 && $("#erp-pr-employee-id").length) {
      $("#erp-pr-employee-id").select2({
        width: "100%",
        placeholder: $("#erp-pr-employee-id").data("placeholder") || "",
        dropdownParent: $("#erp-pr-form-modal .erp-app-helper-modal-content"),
      });
    }
    $.post(erpPaymentRequest.ajaxurl, {
      action: "erp_app_helper_review_payment_request",
      nonce: erpPaymentRequest.nonce,
      request_id: id,
      action_type: "approve",
      payment_type: paymentType,
      hr_note: note,
    })
      .done(function (response) {
        if (response.success) {
          window.location.reload();
        } else {
          alert(response.data || erpPaymentRequest.i18n.error);
          $btn
            .prop("disabled", false)
            .text(erpPaymentRequest.i18n.confirmApproval);
        }
      })
      .fail(function () {
        alert(erpPaymentRequest.i18n.error);
        $btn
          .prop("disabled", false)
          .text(erpPaymentRequest.i18n.confirmApproval);
      });
  });

  // ── Reject request (HR) — opens reject modal ───────────────────────────

  $(document).on("click", ".erp-pr-reject-btn", function (e) {
    e.preventDefault();

    var $btn = $(this);
    $("#erp-pr-modal-request-id").val($btn.data("id"));
    $("#erp-pr-modal-title").text($btn.data("title"));
    $("#erp-pr-modal-employee").text($btn.data("employee"));
    $("#erp-pr-modal-note").val("");
    $("#erp-pr-reject-modal").fadeIn(200);
  });

  $(document).on(
    "click",
    "#erp-pr-modal-close, #erp-pr-modal-cancel",
    function (e) {
      e.preventDefault();
      $("#erp-pr-reject-modal").fadeOut(200);
    },
  );

  $(document).on("click", "#erp-pr-reject-modal", function (e) {
    if ($(e.target).is("#erp-pr-reject-modal")) {
      $("#erp-pr-reject-modal").fadeOut(200);
    }
  });

  $(document).on("click", "#erp-pr-modal-submit", function (e) {
    e.preventDefault();

    var note = $("#erp-pr-modal-note").val().trim();
    if (!note) {
      alert(erpPaymentRequest.i18n.noteRequired);
      return;
    }

    var $btn = $(this);
    var id = $("#erp-pr-modal-request-id").val();
    $btn.prop("disabled", true).text(erpPaymentRequest.i18n.submitting);

    $.post(erpPaymentRequest.ajaxurl, {
      action: "erp_app_helper_review_payment_request",
      nonce: erpPaymentRequest.nonce,
      request_id: id,
      action_type: "reject",
      hr_note: note,
    })
      .done(function (response) {
        if (response.success) {
          window.location.reload();
        } else {
          alert(response.data || erpPaymentRequest.i18n.error);
          $btn
            .prop("disabled", false)
            .text(erpPaymentRequest.i18n.submitRejection);
        }
      })
      .fail(function () {
        alert(erpPaymentRequest.i18n.error);
        $btn
          .prop("disabled", false)
          .text(erpPaymentRequest.i18n.submitRejection);
      });
  });
})(jQuery);
