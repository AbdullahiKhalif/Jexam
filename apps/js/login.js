$(function () {
  function showError(msg) {
    // Support either a custom error container or bootstrap alerts
    if ($(".alert-danger").length) {
      $(".alert-danger").removeClass("d-none").text(msg);
    }
    if ($("#loginMsg").length) {
      $("#loginMsg").text(msg);
    }
  }

  function doLogin() {
    const username = $("#username").val() || $("#username").val();
    const password = $("#password").val();
    if (!username || !password) {
      showError("Username and password are required");
      return;
    }

    const $btn = $("#btnLogin");
    if ($btn.length) {
      $btn.prop("disabled", true).text("Signing in...");
    }

    $.post(
      "../api/auth.php",
      { action: "loginUser", username, password },
      function (r) {
        if (r.status) {
          // Redirect after successful login
          window.location.replace('../views/terms.php');
        } else {
          showError(r.data || "Login failed");
        }
      },
      "json"
    )
      .fail(function (xhr) {
        showError("Server error");
      })
      .always(function () {
        if ($btn.length) {
          $btn.prop("disabled", false).text("Login");
        }
      });
  }

  // Form submit or button click
  $("#loginForm").on("submit", function (e) {
    e.preventDefault();
    doLogin();
  });
  $("#btnLogin").on("click", function () {
    doLogin();
  });
  $("#password, #username, #username").on("keydown", function (e) {
    if (e.key === "Enter") doLogin();
  });
});
