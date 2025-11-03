// apps/js/shifts.js
$(function () {
  const $tbody = $("#tblShift tbody");
  const $form  = $("#frmShift");
  const $btnAdd = $("#btnAddShift");

  const $id    = $("#shift_id");
  const $name  = $("#shift_name");
  const $start = $("#start_time");
  const $end   = $("#end_time");

  // Bootstrap 5 modal instance (more reliable than $mdl.modal('show'))
  const modalEl = document.getElementById("mdlShift");
  const modal   = new bootstrap.Modal(modalEl, { backdrop: "static" });

  function rowHtml(s, idx) {
    return `
      <tr>
        <td>${idx + 1}</td>
        <td>${s.name}</td>
        <td>${s.start_time || "-"}</td>
        <td>${s.end_time || "-"}</td>
        <td>${Number(s.is_enabled) === 1 ? "Yes" : "No"}</td>
        <td>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary btn-edit"
                    data-id="${s.id}"
                    data-name="${s.name}"
                    data-start="${s.start_time || ""}"
                    data-end="${s.end_time || ""}">Edit</button>

            <button class="btn btn-outline-warning btn-toggle"
                    data-id="${s.id}"
                    data-enabled="${Number(s.is_enabled)}">
              ${Number(s.is_enabled) === 1 ? "Disable" : "Enable"}
            </button>

            <button class="btn btn-outline-danger btn-del"
                    data-id="${s.id}">Delete</button>
          </div>
        </td>
      </tr>`;
  }

  function loadShifts() {
    $tbody.html(`<tr><td colspan="6" class="text-muted">Loading…</td></tr>`);
    $.post("../api/shift.php", { action: "list" }, function (resp) {
      if (!resp || !resp.status) {
        $tbody.html(
          `<tr><td colspan="6" class="text-danger">
            ${resp?.data || "Failed to load shifts."}
          </td></tr>`
        );
        return;
      }
      const rows = resp.data || [];
      if (!rows.length) {
        $tbody.html(
          `<tr><td colspan="6" class="text-muted">No shifts yet.</td></tr>`
        );
        return;
      }
      $tbody.empty();
      rows.forEach((s, i) => $tbody.append(rowHtml(s, i)));
    }, "json").fail(() => {
      $tbody.html(
        `<tr><td colspan="6" class="text-danger">Request failed.</td></tr>`
      );
    });
  }

  // Open "Add" modal
  $btnAdd.on("click", () => {
    $id.val("");
    $name.val("");
    $start.val("");
    $end.val("");
    modal.show();
  });

  // Edit
  $tbody.on("click", ".btn-edit", function () {
    const $b = $(this);
    $id.val($b.data("id"));
    $name.val($b.data("name"));
    $start.val(($b.data("start") || "").toString());
    $end.val(($b.data("end") || "").toString());
    modal.show();
  });

  // Create/Update
  $form.on("submit", function (e) {
    e.preventDefault();

    const creating = $id.val() === "";
    const payload = creating
      ? {
          action: "create",
          name: $name.val().trim(),
          start_time: $start.val(),
          end_time: $end.val(),
        }
      : {
          action: "update",
          id: $id.val(),
          name: $name.val().trim(),
          start_time: $start.val(),
          end_time: $end.val(),
        };

    if (!payload.name) {
      Swal.fire("Missing", "Name is required", "warning");
      return;
    }

    $.post("../api/shift.php", payload, function (resp) {
      if (!resp || !resp.status) {
        Swal.fire("Error", resp?.data || "Save failed.", "error");
        return;
      }
      modal.hide();
      loadShifts();
    }, "json").fail(() => Swal.fire("Error", "Request failed.", "error"));
  });

  // Enable/Disable
  $tbody.on("click", ".btn-toggle", function () {
    const id = Number($(this).data("id"));
    const enabled = Number($(this).data("enabled")); // 1 or 0
    const newVal = enabled ? 0 : 1;

    $.post("../api/shift.php",
      { action: "toggle", id, is_enabled: newVal },
      function (resp) {
        if (!resp || !resp.status) {
          Swal.fire("Error", resp?.data || "Update failed.", "error");
          return;
        }
        loadShifts();
      },
      "json"
    ).fail(() => Swal.fire("Error", "Request failed.", "error"));
  });

  // Delete
  $tbody.on("click", ".btn-del", function () {
    const id = Number($(this).data("id"));
    Swal.fire({
      title: "Delete shift?",
      text: "This cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Delete",
    }).then((res) => {
      if (!res.isConfirmed) return;
      $.post("../api/shift.php", { action: "delete", id }, function (resp) {
        if (!resp || !resp.status) {
          Swal.fire("Error", resp?.data || "Delete failed.", "error");
          return;
        }
        loadShifts();
      }, "json").fail(() => Swal.fire("Error", "Request failed.", "error"));
    });
  });

  // initial load
  loadShifts();
});
