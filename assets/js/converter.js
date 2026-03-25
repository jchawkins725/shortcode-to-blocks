jQuery(document).ready(function ($) {
  function showAdminNotice(message, type) {
    type = type || "success";
    var notice = $(
      '<div class="notice notice-' +
        type +
        ' is-dismissible"><p>' +
        message +
        "</p></div>"
    );
    $("#stb-convert-button").closest(".postbox").prepend(notice);
    setTimeout(function () {
      notice.fadeOut(300, function () {
        notice.remove();
      });
    }, 5000);
  }

  $("#stb-convert-button").on("click", function () {
    var postId = $(this).data("post-id");
    var $btn = $(this);
    $btn.attr("disabled", true).text("Converting...");

    $.ajax({
      url: stbConvert.ajaxUrl,
      type: "POST",
      data: {
        action: "stb_convert",
        post_id: postId,
        stb_convert_nonce_field: stbConvert.nonce,
      },
      success: function (response) {
        if (response.success) {
          showAdminNotice("Successfully converted to Gutenberg!");
          location.reload();
        } else {
          showAdminNotice(response.data, "error");
        }
      },
      error: function () {
        alert("AJAX error, please try again.");
      },
      complete: function () {
        $btn.attr("disabled", false).text("Convert Content");
      },
    });
  });

  $("#stb-revert-button").on("click", function () {
    if (
      !confirm(
        "Are you sure you want to revert to the original WPBakery content?"
      )
    )
      return;

    var postId = $(this).data("post-id");
    var $btn = $(this);
    $btn.attr("disabled", true).text("Reverting...");

    $.ajax({
      url: stbConvert.ajaxUrl,
      type: "POST",
      data: {
        action: "stb_revert",
        post_id: postId,
        stb_convert_nonce_field: stbConvert.nonce,
      },
      success: function (response) {
        if (response.success) {
          showAdminNotice("Content reverted to WPBakery.", "warning");
          location.reload();
        } else {
          showAdminNotice(response.data, "error");
        }
      },
      error: function () {
        alert("AJAX error, please try again.");
      },
      complete: function () {
        $btn.attr("disabled", false).text("Revert");
      },
    });
  });
});
