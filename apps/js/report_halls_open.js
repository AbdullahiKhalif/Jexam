// apps/js/report_halls_open.js
$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $campus = $("#campus"),
    $shift = $("#shift");

  const $btn = $("#btnGenerate"),
    $print = $("#btnPrint"),
    $excel = $("#btnExportExcel");

  const $results = $("#resultsSection"),
    $load = $("#loading"),
    $err = $("#errorBox"),
    $sum = $("#summaryLine");

  const $tbody = $("#hallsTable tbody");

  let yearId = null,
    semId = null;
  let lastData = null;

  // Load Year/Sem pairs
  $.post(
    "../api/academic.php",
    { action: "listYearSemesterPairsActive" },
    (r) => {
      if (r?.status && r.data?.length) {
        $pair.html(
          r.data.map(
            (p) =>
              `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`
          )
        );
        [yearId, semId] = ($pair.val() || "|").split("|");
        loadTerms();
        loadCampuses();
      } else {
        $pair.html('<option value="">--</option>');
        $term.html('<option value="">--</option>');
      }
    },
    "json"
  );

  $pair.on("change", () => {
    [yearId, semId] = ($pair.val() || "|").split("|");
    loadTerms();
  });

  function loadTerms() {
    $.post(
      "../api/academic.php",
      { action: "listTermsActive", semester_id: semId },
      (r) => {
        $term.html(
          r?.status
            ? r.data.map((t) => `<option value="${t.id}">${t.name}</option>`)
            : '<option value="">--</option>'
        );
      },
      "json"
    );
  }

  function loadCampuses() {
    $.post(
      "../api/catalog.php",
      { action: "listCampuses" },
      (r) => {
        $campus.html(
          r?.status
            ? r.data.map((c) => `<option value="${c.id}">${c.name}</option>`)
            : '<option value="">--</option>'
        );
        loadShifts();
      },
      "json"
    );
  }

  // Shifts filtered by campus (uses your tolerant helper in reports.php)
  function loadShifts() {
    $.post(
      "../api/reports.php",
      { action: "listShiftsByCampus", campus_id: $campus.val() },
      (r) => {
        $shift.html(
          r?.status
            ? r.data.map(
                (s) =>
                  `<option value="${s.id}">${s.name} (${s.start_time} - ${s.end_time})</option>`
              )
            : '<option value="">--</option>'
        );
      },
      "json"
    );
  }
  $campus.on("change", loadShifts);

  // Generate
  $btn.on("click", () => {
    const payload = {
      action: "openHallsByCampusShift",
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
      campus_id: $campus.val(),
      shift_id: $shift.val(),
    };
    if (
      !payload.academic_year_id ||
      !payload.semester_id ||
      !payload.term_id ||
      !payload.campus_id ||
      !payload.shift_id
    ) {
      return showError(
        "Pick all filters: Year/Semester, Term, Campus and Shift."
      );
    }

    $err.hide();
    $results.hide();
    $load.show();
    $tbody.empty();
    $sum.empty();

    $.post(
      "../api/reports.php",
      payload,
      (r) => {
        $load.hide();
        if (!r?.status) return showError(r?.data || "Failed to load report");
        lastData = r.data || null;
        renderTable(r.data);
        $results.show()[0].scrollIntoView({ behavior: "smooth" });
      },
      "json"
    ).fail((xhr) => {
      $load.hide();
      showError(xhr?.responseText || "Failed");
    });
  });

  // Print
  $print.on("click", () => {
    $results.show();
    requestAnimationFrame(() => setTimeout(() => window.print(), 50));
  });

  // Excel (3 columns only)
  $excel.on("click", () => {
    if (!lastData?.halls?.length) return;
    const wb = XLSX.utils.book_new();
    const rows = [["Hall Name", "Capacity", "Allocated Students"]];
    lastData.halls.forEach((h) =>
      rows.push([h.hall_name, h.capacity, h.total || 0])
    );
    const ws = XLSX.utils.aoa_to_sheet(rows);
    XLSX.utils.book_append_sheet(wb, ws, "Open Halls");
    XLSX.writeFile(wb, "Open_Halls_Report.xlsx");
  });

  function showError(msg) {
    $err.html(`<h4>Error</h4><p>${esc(msg)}</p>`).show();
  }

  function renderTable(data) {
    const meta = data.meta || {};
    const halls = data.halls || [];
    const count = data.count_open_halls || 0;

    // summary badge
    $sum.html(
      `
       <div class="table table-bordered table-striped table-hover">
            <table class="table table-bordered bg-secondary">
                <thead style="color:#fff;"> 
                    <th style="color:#fff;">Campus Name</th>
                    <th style="color:#fff;">Shift</th>
                    <th style="color:#fff;">Term</th>
                    <th style="color:#fff;">Total Halls Open</th>
                <thead>
                <tbody>
                    <tr>
                        <td style="color:#fff;">${esc(meta.campus || "Campus")}</td>
                        <td style="color:#fff;">${esc(meta.shift?.name || "Shift")}</td>
                        <td style="color:#fff;">${esc(meta.term || "Term")}</td>
                        <td style="color:#fff;">${count}</td>
                    </tr>
                </tbody>
            </table>
       </div>
       `
    );

    if (!halls.length) {
      $tbody.html(
        `<tr><td colspan="5" class="text-muted">No halls open for the selected filters.</td></tr>`
      );
      return;
    }

    // Sort halls naturally by their number
    halls.sort((a, b) => {
      const na = parseInt(String(a.hall_name).replace(/\D/g, ""), 10);
      const nb = parseInt(String(b.hall_name).replace(/\D/g, ""), 10);
      return isNaN(na) || isNaN(nb)
        ? String(a.hall_name).localeCompare(String(b.hall_name))
        : na - nb;
    });

    const rowsHtml = halls
      .map(
        (h, index) => `
  <tr>
    <td>${index + 1}</td>
    <td>${esc(h.hall_name)}</td>
    <td>${Number(h.capacity || 0)}</td>
    <td>${Number(h.total || 0)}</td>
  </tr>
`
      )
      .join("");

    $tbody.html(rowsHtml);
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
});
