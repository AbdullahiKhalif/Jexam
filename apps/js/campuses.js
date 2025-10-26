$(function () {
  const $tb = $("#tblCampus tbody"),
    $mdl = $("#mdlCampus");
  const $id = $("#campus_id"),
    $name = $("#campus_name"),
    $code = $("#campus_code"),
    $leaders = $("#leaders");

  // helpers for safe rendering
  const escHtml = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (m) => (
      { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m]
    ));

  const escAttr = (s) =>
    String(s ?? "").replace(/["'&<>]/g, (m) => (
      { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m]
    ));

  function load() {
    $tb.html("");
    $.post(
      "../api/campus.php",
      { action: "list" },
      function (resp) {
        if (!resp?.status || !Array.isArray(resp.data) || resp.data.length === 0) {
          $tb.append('<tr><td colspan="6" class="text-muted">No campuses</td></tr>');
          return;
        }

        resp.data.forEach((c, idx) => {
          const id = c.id;
          const nm = c.name ?? "";
          const cd = c.code ?? "";
          const ld = c.leaders ?? "";
          const av = Number(c.is_available) === 1; // open?

          $tb.append(
            `<tr>
              <td>${idx + 1}</td>
              <td>${escHtml(nm)}</td>
              <td>${escHtml(cd)}</td>
              <td>${escHtml(ld)}</td>
              <td>
                ${
                  av
                    ? '<span class="badge bg-success">Available</span>'
                    : '<span class="badge bg-secondary">Closed</span>'
                }
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-toggle"
                          title="${av ? "Close campus" : "Open campus"}"
                          data-id="${escAttr(id)}"
                          data-val="${av ? 0 : 1}">
                    ${
                      av
                        ? "<i class='fa fa-unlock p-2 bg-success rounded text-light fs-6'></i>"
                        : "<i class='fa fa-lock p-2 bg-secondary rounded text-light fs-6'></i>"
                    }
                  </button>
                  <button class="btn btn-edit"
                          title="Edit"
                          data-id="${escAttr(id)}"
                          data-name="${escAttr(nm)}"
                          data-code="${escAttr(cd)}"
                          data-leaders="${escAttr(ld)}">
                    <i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i>
                  </button>
                  <button class="btn btn-del" title="Delete" data-id="${escAttr(id)}">
                    <i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i>
                  </button>
                </div>
              </td>
            </tr>`
          );
        });
      },
      "json"
    ).fail(() => {
      $tb.html('<tr><td colspan="6" class="text-danger">Failed to load</td></tr>');
    });
  }

  // Add Campus
  $("#btnAddCampus").on("click", () => {
    $id.val("");
    $name.val("");
    $code.val("");
    $leaders.val("");
    $mdl.modal("show");
  });

  // Edit Campus (populate modal)
  $tb.on("click", ".btn-edit", function () {
    // primary path: from data-* attributes
    const did = $(this).data("id");
    const dname = $(this).data("name");
    const dcode = $(this).data("code");
    let dleaders = $(this).data("leaders");

    // fallback if attribute missing: read the row’s 4th cell (leaders)
    if (typeof dleaders === "undefined") {
      dleaders = $(this).closest("tr").find("td").eq(3).text();
    }

    $id.val(did ?? "");
    $name.val(dname ?? "");
    $code.val(dcode ?? "");
    $leaders.val(dleaders ?? "");
    $mdl.modal("show");
  });

  // Create/Update submit
  $("#frmCampus").on("submit", function (e) {
    e.preventDefault();
    const payload =
      ($id.val() || "") === ""
        ? {
            action: "create",
            name: $name.val(),
            code: $code.val(),
            leaders: $leaders.val(),
          }
        : {
            action: "update",
            id: $id.val(),
            name: $name.val(),
            code: $code.val(),
            leaders: $leaders.val(),
          };

    $.post(
      "../api/campus.php",
      payload,
      function (resp) {
        if (resp?.status) {
          $mdl.modal("hide");
          load();
        } else {
          Swal.fire("Error", resp?.data || "Failed", "error");
        }
      },
      "json"
    );
  });

  // Delete Campus
  $tb.on("click", ".btn-del", function () {
    const id = $(this).data("id");
    Swal.fire({
      title: "Delete Campus?",
      icon: "warning",
      showCancelButton: true,
    }).then((s) => {
      if (!s.isConfirmed) return;
      $.post(
        "../api/campus.php",
        { action: "delete", id },
        function (resp) {
          if (resp?.status) {
            load();
          } else {
            Swal.fire("Error", resp?.data || "Delete failed", "error");
          }
        },
        "json"
      );
    });
  });

  // Toggle Availability (Open/Close)
  $tb.on("click", ".btn-toggle", function () {
    const id = $(this).data("id");
    const is_available = $(this).data("val"); // 0 or 1
    $.post(
      "../api/campus.php",
      { action: "toggle", id, is_available },
      function (resp) {
        if (resp?.status) {
          load();
        } else {
          Swal.fire("Error", resp?.data || "Toggle failed", "error");
        }
      },
      "json"
    );
  });

  load();
});
