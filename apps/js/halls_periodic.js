// js/halls_periodic.js
$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $campus = $("#campus"),
    $shift = $("#shift");
  const $tb = $("#tblHallAvail tbody");

  // Modal + fields
  const mdlEl = document.getElementById("mdlHall");
  let mdl = null;
  function openModal() {
    if (!mdl && window.bootstrap?.Modal) {
      mdl = new bootstrap.Modal(mdlEl, { backdrop: "static" });
    }
    mdl ? mdl.show() : $("#mdlHall").modal("show");
  }
  function closeModal() {
    mdl ? mdl.hide() : $("#mdlHall").modal("hide");
  }

  const $hid = $("#hall_id"),
    $hcamp = $("#hall_campus_id"),
    $hname = $("#hall_name"),
    $hcap = $("#hall_capacity"),
    $hactive = $("#hall_is_active");

  let yearId = null,
    semId = null;

  // --------- Year/Sem → Term (ACTIVE) ---------
  $.post(
    "../api/academic.php",
    { action: "listYearSemesterPairsActive" },
    function (r) {
      if (r?.status && r.data?.length) {
        $pair.html(
          r.data.map(
            (p) =>
              `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`
          )
        );
        [yearId, semId] = ($pair.val() || "|").split("|");
        loadTerms(semId);
      } else {
        $pair.html('<option value="">-- No active semesters --</option>');
        $term.html('<option value="">--</option>');
      }
      loadCampuses();
    },
    "json"
  );

  $pair.on("change", function () {
    [yearId, semId] = ($(this).val() || "|").split("|");
    loadTerms(semId);
    loadAvail();
  });
  $term.on("change", loadAvail);

  function loadTerms(semester_id) {
    $.post(
      "../api/academic.php",
      { action: "listTermsActive", semester_id },
      function (r) {
        $term.html(
          r?.status
            ? r.data.map((t) => `<option value="${t.id}">${t.name}</option>`)
            : '<option value="">--</option>'
        );
        loadAvail();
      },
      "json"
    );
  }

  // --------- Campus → Shifts ---------
  function loadCampuses() {
    $.post(
      "../api/catalog.php",
      { action: "listCampuses" },
      function (r) {
        if (r?.status && r.data?.length) {
          $campus.html(
            r.data.map((c) => `<option value="${c.id}">${c.name}</option>`)
          );
          loadShifts();
        } else {
          $campus.html('<option value="">--</option>');
          $shift.html('<option value="">--</option>');
        }
      },
      "json"
    );
  }
  function loadShifts() {
    $.post(
      "../api/catalog.php",
      { action: "listShifts", campus_id: $campus.val() },
      function (r) {
        if (r?.status && r.data?.length) {
          $shift.html(
            r.data.map((s) => `<option value="${s.id}">${s.name}</option>`)
          );
        } else {
          $shift.html('<option value="">--</option>');
        }
        loadAvail();
      },
      "json"
    );
  }
  $campus.on("change", loadShifts);
  $shift.on("change", loadAvail);

  // --------- Table: availability + CRUD buttons ---------
  function loadAvail() {
    $tb.html("");
    const payload = {
      action: "listAvailability",
      campus_id: $campus.val(),
      shift_id: $shift.val(),
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
    };
    if (!payload.campus_id || !payload.shift_id) {
      $tb.append(
        '<tr><td colspan="5" class="text-muted">Pick campus & shift</td></tr>'
      );
      return;
    }
    $.post(
      "../api/hall.php",
      payload,
      function (r) {
        $tb.html("");
        if (!r?.status || !r.data?.length) {
          $tb.append(
            '<tr><td colspan="5" class="text-muted">No halls</td></tr>'
          );
          return;
        }
        r.data.forEach((h, i) => {
          const openNow = Number(h.is_available) === 1;
          const active = Number(h.is_active) === 1;
          $tb.append(`
          <tr>
            <td>${i + 1}</td>
            <td>${esc(h.name)}</td>
            <td>${h.capacity}</td>
            <td>
              ${
                openNow
                  ? '<span class="badge bg-success">Available</span>'
                  : '<span class="badge bg-secondary">Closed</span>'
              }
              ${
                !active
                  ? '<div class="small text-danger mt-1">Hall is inactive</div>'
                  : ""
              }
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-toggle"
                        data-id="${h.id}" data-val="${openNow ? 0 : 1}">
                  ${openNow ? "<i class='fa fa-unlock fs-1 text-success round'></i>" : "<i class='fa fa-lock fs-1 text-secondary round'></i>"}
                </button>
                <button class="btn btn-edit"
                        data-id="${h.id}" 
                        data-name="${attr(h.name)}"
                        data-cap="${h.capacity}"
                        data-active="${active ? 1 : 0}"
                        data-campus="${
                          h.campus_id || $campus.val()
                        }"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i> </button>
                <button class="btn btn-del" data-id="${
                  h.id
                }"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i> </button>
              </div>
            </td>
          </tr>`);
        });
      },
      "json"
    ).fail(() =>
      $tb.html(
        '<tr><td colspan="5" class="text-danger">Failed to load</td></tr>'
      )
    );
  }

  // --------- Availability toggle (period-aware) ---------
  $("#tblHallAvail").on("click", ".btn-toggle", function () {
    const hall_id = $(this).data("id"),
      is_available = $(this).data("val");
    const payload = {
      action: "setAvailabilityForPeriod",
      hall_id,
      shift_id: $shift.val(),
      is_available,
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
    };
    $.post(
      "../api/hall.php",
      payload,
      function (r) {
        if (r.status) loadAvail();
        else Swal.fire("Error", r.data || "Update failed", "error");
      },
      "json"
    );
  });

  // --------- Add Hall ---------
  $("#btnAddHall").on("click", function () {
    resetHallForm(true);
    openModal();
  });

  // --------- Edit Hall ---------
  $("#tblHallAvail").on("click", ".btn-edit", function () {
    resetHallForm(false);
    $hid.val($(this).data("id"));
    $hname.val($(this).data("name"));
    $hcap.val($(this).data("cap"));
    $hactive.prop("checked", Number($(this).data("active")) === 1);
    // Campus in row (from API) or fallback to selected campus
    const rowCampus = $(this).data("campus") || $campus.val();
    $hcamp.val(rowCampus);
    openModal();
  });

  // --------- Delete Hall ---------
  $("#tblHallAvail").on("click", ".btn-del", function () {
    const id = $(this).data("id");
    Swal.fire({
      title: "Delete Hall?",
      text: "This cannot be undone.",
      icon: "warning",
      showCancelButton: true,
    }).then((s) => {
      if (!s.isConfirmed) return;
      $.post(
        "../api/hall.php",
        { action: "deleteHall", id },
        function (r) {
          if (r?.status) {
            Swal.fire("Deleted", "Hall removed", "success");
            loadAvail();
          } else Swal.fire("Error", r?.data || "Delete failed", "error");
        },
        "json"
      );
    });
  });

  // --------- Modal Save (Create/Update) ---------
  $("#frmHall").on("submit", function (e) {
    e.preventDefault();
    const payload =
      $hid.val() === ""
        ? {
            action: "registerHall",
            campus_id: $hcamp.val(),
            name: $hname.val(),
            capacity: $hcap.val(),
          }
        : {
            action: "updateHall",
            id: $hid.val(),
            campus_id: $hcamp.val(),
            name: $hname.val(),
            capacity: $hcap.val(),
            is_active: $hactive.is(":checked") ? 1 : 0,
          };
    $.post(
      "../api/hall.php",
      payload,
      function (r) {
        if (r?.status) {
          closeModal();
          Swal.fire("Saved", "Hall saved", "success");
          loadShifts(); /* reload list */
        } else Swal.fire("Error", r?.data || "Save failed", "error");
      },
      "json"
    );
  });

  // --------- Helpers ---------
  function resetHallForm(isCreate) {
    // Clone campus options from the main campus selector (no extra API call)
    $hcamp.html($campus.html());
    $hcamp.val($campus.val());
    $hid.val("");
    $hname.val("");
    $hcap.val(0);
    $hactive.prop("checked", true);
  }
  function esc(s) {
    return String(s ?? "").replace(
      /[&<>"']/g,
      (m) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        }[m])
    );
  }
  function attr(s) {
    return esc(s).replace(/"/g, "&quot;");
  }
});
