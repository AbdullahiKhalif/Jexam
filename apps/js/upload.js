// js/upload.js
$(function () {
  loadClasses();

  $("#uploadForm").on("submit", function (e) {
    e.preventDefault();

    const file = $("#excel").prop("files")[0];
    if (!file) {
      showMsg("err", "Please choose an Excel file.");
      return;
    }
    const fd = new FormData();
    fd.append("excel", file);

    $.ajax({
      url: "../api/upload_master.php",
      method: "POST",
      data: fd,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (resp) {
        if (resp.status) {
          showMsg("ok", resp.data);
          loadClasses(true);
        } else {
          showMsg("err", resp.data);
        }
      },
      error: function (xhr) {
        showMsg("err", xhr.responseText || "Upload failed");
      },
    });
  });

  function loadClasses(reload = false) {
    $.ajax({
      url: "../api/class.php",
      method: "POST",
      data: { action: "readAllClass" },
      dataType: "json",
      success: function (resp) {
        if (!resp.status) return;
        const rows = resp.data;
        const tb = $("#classTable tbody");
        tb.html("");
        rows.forEach((r, idx) => {
          tb.append(`
            <tr>
              <td>${idx + 1}</td>
              <td>${r.code}</td>
              <td>${r.academic_year_group ?? ""}</td>
              <td>${r.department ?? ""}</td>
              <td>${r.is_active == 1 ? "Yes" : "No"}</td>
            </tr>
          `);
        });
        if (reload) {
          // if DataTable already init, destroy & re-init
          if ($.fn.DataTable.isDataTable("#classTable")) {
            $("#classTable").DataTable().destroy();
          }
        }
        $("#classTable").DataTable();
      },
    });
  }

  function showMsg(which, text) {
    const ok = $("#ok");
    const err = $("#err");
    ok.addClass("d-none");
    err.addClass("d-none");
    if (which === "ok") {
      ok.text(text).removeClass("d-none");
    } else {
      err.text(text).removeClass("d-none");
    }
    setTimeout(() => {
      $("#ok,#err").addClass("d-none");
    }, 12000);
  }
});
