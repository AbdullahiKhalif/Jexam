$(function () {
  const $tb = $("#tblCampus tbody"),
    $mdl = $("#mdlCampus");
  const $id = $("#campus_id"),
    $name = $("#campus_name"),
    $code = $("#campus_code"),
    $leaders = $("#leaders");

  // helpers for safe rendering
  const escHtml = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (m) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    }[m]));

  const escAttr = (s) =>
    String(s ?? "").replace(/["'&<>]/g, (m) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    }[m]));

  function load() {
    $tb.html("");
    $.post(
      "../api/campus.php",
      { action: "list" },
      function (resp) {
        if (!resp?.status || !Array.isArray(resp.data)) return;

        resp.data.forEach((c, idx) => {
          const id = c.id;
          const nm = c.name ?? "";
          const cd = c.code ?? "";
          const ld = c.leaders ?? "";

          $tb.append(
            `<tr>
              <td>${idx + 1}</td>
              <td>${escHtml(nm)}</td>
              <td>${escHtml(cd)}</td>
              <td>${escHtml(ld)}</td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-edit"
                          data-id="${escAttr(id)}"
                          data-name="${escAttr(nm)}"
                          data-code="${escAttr(cd)}"
                          data-leaders="${escAttr(ld)}">
                    <i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i>
                  </button>
                  <button class="btn btn-del" data-id="${escAttr(id)}">
                    <i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i>
                  </button>
                </div>
              </td>
            </tr>`
          );
        });
      },
      "json"
    );
  }

  $("#btnAddCampus").on("click", () => {
    $id.val("");
    $name.val("");
    $code.val("");
    $leaders.val("");
    $mdl.modal("show");
  });

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

  load();
});
