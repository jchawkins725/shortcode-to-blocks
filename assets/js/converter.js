jQuery(document).ready(function ($) {
  const { __ } = wp.i18n;

  function showAdminNotice(message, type) {
    type = type || "success";
    var $notice = jQuery("<div>", {
      class: "notice notice-" + type + " is-dismissible"
    });

    var $p = jQuery("<p>");
    $p.text(String(message || ""));
    $notice.append($p);
    $("#stbc-convert-button").closest(".postbox").prepend($notice);
    setTimeout(function () {
      $notice.fadeOut(300, function () {
        $notice.remove();
      });
    }, 5000);
  }

  $("#stbc-convert-button").on("click", function () {
    var postId = $(this).data("post-id");
    var editUrl = String($(this).data("edit-url") || "");
    var $btn = $(this);
    $btn.attr("disabled", true).text(__("Converting...", "shortcode-to-blocks"));

    $.ajax({
      url: stbcConvert.ajaxUrl,
      type: "POST",
      data: {
        action: "stbc_convert",
        post_id: postId,
        stbc_convert_nonce_field: stbcConvert.nonce,
      },
      success: function (response) {
        if (response.success) {
          showAdminNotice(__("Successfully converted to Gutenberg!", "shortcode-to-blocks"));
          if (editUrl) {
            window.location.assign(editUrl);
          } else {
            location.reload();
          }
        } else {
          showAdminNotice(response.data, "error");
        }
      },
      error: function () {
        alert(__("AJAX error, please try again.", "shortcode-to-blocks"));
      },
      complete: function () {
        $btn.attr("disabled", false).text(__("Convert Content", "shortcode-to-blocks"));
      },
    });
  });

  $("#stbc-revert-button").on("click", function () {
    if (
      !confirm(
        __("Are you sure you want to revert to the original WPBakery content?", "shortcode-to-blocks")
      )
    ) {
      return;
    }

    var postId = $(this).data("post-id");
    var $btn = $(this);
    $btn.attr("disabled", true).text(__("Reverting...", "shortcode-to-blocks"));

    $.ajax({
      url: stbcConvert.ajaxUrl,
      type: "POST",
      data: {
        action: "stbc_revert",
        post_id: postId,
        stbc_convert_nonce_field: stbcConvert.nonce,
      },
      success: function (response) {
        if (response.success) {
          showAdminNotice(__("Content reverted to WPBakery.", "shortcode-to-blocks"), "warning");
          location.reload();
        } else {
          showAdminNotice(response.data, "error");
        }
      },
      error: function () {
        alert(__("AJAX error, please try again.", "shortcode-to-blocks"));
      },
      complete: function () {
        $btn.attr("disabled", false).text(__("Revert", "shortcode-to-blocks"));
      },
    });
  });
});