$(function () {
  const $tb = $("#tblTerm tbody");
  const $mdl = $("#mdlTerm");
  const $id = $("#term_id");
  const $name = $("#term_name");
  const $semSel = $("#term_semester_id");
  const $filterSem = $("#filterSemester");
  const $sd = $("#start_date");
  const $ed = $("#end_date");

  function loadSemesters() {
    $.post("../api/term.php", { action: "listSemesters" }, function (r) {
      if (!r.status) return;
      const opts = r.data.map(
        (s) => `<option value="${s.id}">${s.name}${Number(s.is_active)===1?'':' (inactive)'}  ${s.year_name}</option>`
      );
      $semSel.html(opts.join(""));
      $filterSem.append(opts.join(""));
    }, "json");
  }

  function loadTerms() {
    $tb.html("");
    const payload = { action: "list" };
    if ($filterSem.val()) payload.semester_id = $filterSem.val();

    $.post("../api/term.php", payload, function (r) {
      if (!r.status) return;
      if (!r.data.length) {
        $tb.append(`<tr><td colspan="7" class="text-muted">No terms</td></tr>`);
        return;
      }
      r.data.forEach((t) => {
        const active = Number(t.is_active) === 1;
        const row = `
          <tr>
            <td>${t.year_name}</td>
            <td>${t.semester_name}</td>
            <td>${t.name}</td>
            <td>${t.start_date || ''}</td>
            <td>${t.end_date || ''}</td>
            <td>
              <div class="form-check form-switch m-0">
                <input class="form-check-input toggle-term" type="checkbox" data-id="${t.id}" ${active ? "checked" : ""}>
              </div>
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-edit m-1"
                  data-id="${t.id}"
                  data-name="${t.name}"
                  data-sem="${t.semester_id}"
                  data-sd="${t.start_date || ''}"
                  data-ed="${t.end_date || ''}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
                <button class="btn btn-del m-1" data-id="${t.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
              </div>
            </td>
          </tr>`;
        $tb.append(row);
      });
    }, "json");
  }

  // Filter
  $filterSem.on("change", loadTerms);

  // Add
  $("#btnAdd").on("click", () => {
    $id.val("");
    $name.val("");
    $sd.val("");
    $ed.val("");
    $mdl.modal("show");
  });

  // Edit
  $tb.on("click", ".btn-edit", function () {
    $id.val($(this).data("id"));
    $name.val($(this).data("name"));
    $semSel.val($(this).data("sem"));
    $sd.val($(this).data("sd") || "");
    $ed.val($(this).data("ed") || "");
    $mdl.modal("show");
  });

  // Save
  $("#frmTerm").on("submit", function (e) {
    e.preventDefault();
    const payload =
      $id.val() === ""
        ? { action: "create", name: $name.val(), start_date: $sd.val(), end_date: $ed.val(), semester_id: $semSel.val() }
        : { action: "update", id: $id.val(), name: $name.val(), start_date: $sd.val(), end_date: $ed.val(), semester_id: $semSel.val() };

    $.post("../api/term.php", payload, function (r) {
      if (r.status) { $mdl.modal("hide"); loadTerms(); }
      else Swal.fire("Error", r.data, "error");
    }, "json");
  });

  // Toggle
  $tb.on("change", ".toggle-term", function () {
    const $c = $(this), id = $c.data("id"), isOn = $c.is(":checked") ? 1 : 0;
    $.post("../api/term.php", { action: "toggle", id, is_active: isOn }, function (r) {
      if (!r.status) { $c.prop("checked", !isOn); Swal.fire("Error", r.data, "error"); }
      loadTerms();
    }, "json").fail(() => { $c.prop("checked", !isOn); Swal.fire("Error", "Server error", "error"); });
  });

  // Delete
  $tb.on("click", ".btn-del", function () {
    const id = $(this).data("id");
    Swal.fire({ title: "Delete Term?", icon: "warning", showCancelButton: true }).then((s) => {
      if (!s.isConfirmed) return;
      $.post("../api/term.php", { action: "delete", id }, function (r) {
        if (r.status) loadTerms(); else Swal.fire("Error", r.data, "error");
      }, "json");
    });
  });

  // Init
  loadSemesters();
  loadTerms();
});
