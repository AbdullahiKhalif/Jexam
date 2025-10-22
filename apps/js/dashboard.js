// apps/js/dashboard.js
$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $campus = $("#campus"),
    $shift = $("#shift"),
    $date = $("#date"),
    $btn = $("#btnGenerate"),
    $print = $("#btnPrint"),
    $printBody = $("#btnPrintOnlyBody"),
    $export = $("#btnExportExcel"),
    $results = $("#resultsSection"),
    $load = $("#loading"),
    $err = $("#errorBox"),
    $summary = $("#summaryLine"),
    $kpis = $("#kpiRow"),
    $tblOver = $("#tblOverCap"),
    $tblEmpty = $("#tblEmpty"),
    $tblToday = $("#tblClassesToday"); // tbody

  let yearId = null,
    semId = null;

  const ALL_CAMPUSES_OPT = '<option value="0">All Campuses</option>';
  const ALL_SHIFTS_OPT = '<option value="0">All Shifts</option>';

  // ---------- utils ----------
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
  // Turn class code (CA218) → cohort label (CA21'S). If already CA24'S, keep it.
  function toCohortLabel(codeOrCohort) {
    const raw = String(codeOrCohort || "")
      .trim()
      .toUpperCase();
    if (/^[A-Z]+[0-9]{2}'S$/.test(raw)) return raw;
    const m = raw.match(/^([A-Z]+)\s*?([0-9]{2})/);
    if (m) return `${m[1]}${m[2]}'S`;
    return raw;
  }
  function norm(s) {
    return String(s || "")
      .trim()
      .toLowerCase();
  }

  // Try common API field names for campus/shift ids & names
  function rowCampusId(r) {
    const cand = [r.campus_id, r.campusId, r.class_campus_id, r.hall_campus_id];
    for (const v of cand) {
      const n = Number(v);
      if (Number.isFinite(n)) return n;
    }
    return null;
  }
  function rowCampusName(r) {
    return r.campus ?? r.campus_name ?? r.campusTitle ?? "";
  }
  function rowShiftId(r) {
    const cand = [r.shift_id, r.shiftId, r.class_shift_id];
    for (const v of cand) {
      const n = Number(v);
      if (Number.isFinite(n)) return n;
    }
    return null;
  }

  function matchesCampus(r, selectedCampusId) {
    if (!selectedCampusId) return true; // "All Campuses"
    const cid = rowCampusId(r);
    if (cid !== null) return cid === Number(selectedCampusId);
    // Fallback compare by name if the API row has no campus_id
    const rowNm = norm(rowCampusName(r));
    const selNm = norm($("#campus option:selected").text());
    return rowNm && selNm && rowNm === selNm;
  }
  function matchesShift(r, selectedShiftId) {
    if (!selectedShiftId) return true; // "All Shifts"
    const sid = rowShiftId(r);
    return sid === Number(selectedShiftId);
  }

  // ---------- initial loads ----------
  $.post(
    "../api/academic.php",
    { action: "listYearSemesterPairsActive" },
    (r) => {
      if (r?.status && Array.isArray(r.data) && r.data.length) {
        $pair.html(
          r.data.map(
            (p) =>
              `<option value="${p.year_id}|${p.semester_id}">${esc(
                p.label
              )}</option>`
          )
        );
        [yearId, semId] = ($pair.val() || "|").split("|");
        loadTerms();
        loadCampuses();
        $date.val(new Date().toISOString().slice(0, 10)); // default = today
      } else {
        $pair.html('<option value="">--</option>');
        $term.html('<option value="">--</option>');
        $campus.html('<option value="">--</option>');
        $shift.html('<option value="">--</option>');
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
          r?.status && Array.isArray(r.data)
            ? r.data
                .map((t) => `<option value="${t.id}">${esc(t.name)}</option>`)
                .join("")
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
        if (r?.status && Array.isArray(r.data) && r.data.length) {
          $campus.html(
            [ALL_CAMPUSES_OPT]
              .concat(
                r.data.map(
                  (c) => `<option value="${c.id}">${esc(c.name)}</option>`
                )
              )
              .join("")
          );
          loadShiftsForCampus();
        } else {
          $campus.html(ALL_CAMPUSES_OPT);
          loadShiftsForCampus();
        }
      },
      "json"
    );
  }

  // If All Campuses → I/II/III + "All"; else load actual shifts for that campus
  function loadShiftsForCampus() {
    const campusId = Number($campus.val() || 0);
    if (!campusId) {
      $shift.html(
        [
          ALL_SHIFTS_OPT,
          '<option value="1">Shift I</option>',
          '<option value="2">Shift II</option>',
          '<option value="3">Shift III</option>',
        ].join("")
      );
      return;
    }
    $.post(
      "../api/reports.php",
      { action: "listShiftsByCampus", campus_id: campusId },
      (r) => {
        if (r?.status && Array.isArray(r.data) && r.data.length) {
          $shift.html(
            r.data
              .map((s) => `<option value="${s.id}">${esc(s.name)}</option>`)
              .join("")
          );
          $shift.prepend(ALL_SHIFTS_OPT).val("0"); // keep "All Shifts"
        } else {
          $shift.html(ALL_SHIFTS_OPT);
        }
      },
      "json"
    );
  }
  $campus.on("change", loadShiftsForCampus);

  // ---------- buttons ----------
  $btn.on("click", generateDashboard);
  $print.on("click", () => window.print());
  $printBody.on("click", () => {
    const area = document.getElementById("printArea");
    if (!area) return window.print();
    const w = window.open("", "_blank");
    w.document.write(`
      <html>
      <head>
        <title>Print</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <style>
          @page{size:A4;margin:12mm}
          body{padding:12px}
          table{width:100%;border-collapse:collapse}
          th,td{border:1px solid #ddd;padding:6px}
          thead th{background:#f5f5f5}
        </style>
      </head>
      <body>${area.innerHTML}</body>
      </html>
    `);
    w.document.close();
    w.focus();
    w.print();
    w.close();
  });
  $export.on("click", exportTodayExcel);

  // ---------- main fetch ----------
  function generateDashboard() {
    const payload = {
      action: "summary",
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
      campus_id: $campus.val() || 0,
      shift_id: $shift.val() || 0,
      date: $date.val(),
    };
    if (!payload.term_id || !payload.date)
      return showError("Please select term and date.");

    $err.hide().empty();
    $results.hide();
    $load.show();

    $.post(
      "../api/dashboard.php",
      payload,
      (r) => {
        $load.hide();
        if (!r?.status) return showError(r?.data || "Failed to load.");
        render(r.data || {});
        $results.show();
      },
      "json"
    ).fail(() => {
      $load.hide();
      showError("Failed to load dashboard.");
    });
  }

  function showError(msg) {
    $err.html(`<h4>Error</h4><p>${esc(msg)}</p>`).show();
  }

  // ---------- render ----------
  function render(data) {
    const meta = data.meta || {};
    const cards = data.cards || {};
    const dateText = meta.date
      ? new Date(meta.date).toLocaleDateString("en-GB")
      : "";

    $summary.html(
      `<b>${esc(meta.year || "")}</b> / ${esc(meta.semester || "")}
       &nbsp;&bull;&nbsp; Term: ${esc(meta.term || "")}
       &nbsp;&bull;&nbsp; Campus: ${esc(meta.campus || "All")}
       &nbsp;&bull;&nbsp; Shift: ${esc(meta.shift || "All")}
       &nbsp;&bull;&nbsp; ${esc(dateText)}`
    );

    // KPIs
    const kpi = [
      {
        t: "Halls Open",
        k: "halls_open",
        ic: "fa-door-open",
        c: "text-primary",
      },
      {
        t: "Seats Allocated",
        k: "seats_allocated",
        ic: "fa-users",
        c: "text-success",
      },
      {
        t: "Engaged Capacity",
        k: "engaged_capacity",
        ic: "fa-chair",
        c: "text-info",
      },
      {
        t: "Utilization %",
        k: "utilization_pct",
        ic: "fa-gauge",
        c: "text-warning",
      },
      {
        t: "Classes Covered",
        k: "classes_covered",
        ic: "fa-school",
        c: "text-danger",
      },
    ];
    $kpis.html(
      kpi
        .map(
          (x) => `
        <div class="col-md-3 col-sm-6">
          <div class="card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="rounded-circle bg-light ${
                x.c
              } d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                <i class="fa ${x.ic} fa-lg"></i>
              </div>
              <div>
                <div class="fw-semibold small text-muted">${esc(x.t)}</div>
                <div class="fs-4 fw-bold">${cards[x.k] ?? 0}</div>
              </div>
            </div>
          </div>
        </div>
      `
        )
        .join("")
    );

    // Over-capacity
    const over = data.over_capacity || [];
    $tblOver.html(
      over.length
        ? over
            .map(
              (r) => `
            <tr>
              <td>${esc(r.hall_name)}</td>
              <td>${Number(r.capacity || 0)}</td>
              <td class="text-danger fw-bold">${Number(r.allocated || 0)}</td>
            </tr>
          `
            )
            .join("")
        : '<tr><td colspan="3" class="text-muted">None 🎉</td></tr>'
    );

    // Empty halls
    const empty = data.empty_halls || [];
    $tblEmpty.html(
      empty.length
        ? empty
            .map(
              (r) => `
            <tr>
              <td>${esc(r.hall_name)}</td>
              <td>${Number(r.capacity || 0)}</td>
            </tr>
          `
            )
            .join("")
        : '<tr><td colspan="2" class="text-muted">All engaged</td></tr>'
    );

    // ==== Classes having exam today (Department, Cohort, Campus, Subject) ====
    const rawToday = data.classes_today || data.recent_subjects || [];
    const campusSel = Number($campus.val() || 0);
    const shiftSel = Number($shift.val() || 0);

    // strict filtering by campus & shift (when selected)
    const filtered = rawToday.filter(
      (r) => matchesCampus(r, campusSel) && matchesShift(r, shiftSel)
    );

    // dedupe: one row per cohort per dept
    // - specific campus → key = dept|cohort
    // - all campuses     → key = dept|cohort|campus
    const unique = new Map();
    for (const r of filtered) {
      const department = r.department || r.department_name || "";
      const cohort = toCohortLabel(r.cohort || r.class_code || "");
      const campus = r.campus || r.campus_name || "";
      const subject = r.subject || r.subject_name || "";

      const key = campusSel
        ? `${department}|${cohort}`
        : `${department}|${cohort}|${campus}`;
      if (!unique.has(key))
        unique.set(key, { department, cohort, campus, subject });
    }

    const rows = Array.from(unique.values()).sort((a, b) => {
      const d = a.department.localeCompare(b.department);
      return d || a.cohort.localeCompare(b.cohort);
    });

    $tblToday.html(
      rows.length
        ? rows
            .map(
              (x) => `
            <tr>
              <td>${esc(x.department)}</td>
              <td>${esc(x.cohort)}</td>
              <td>${esc(x.campus)}</td>
              <td>${esc(x.subject)}</td>
            </tr>
          `
            )
            .join("")
        : '<tr><td colspan="4" class="text-muted">No exams today</td></tr>'
    );
  }

  // ---------- export ----------
  function exportTodayExcel() {
    const rows = $("#tblClassesToday tr")
      .get()
      .map((tr) => {
        const tds = $(tr).find("td");
        if (tds.length < 4) return null;
        return [
          $(tds[0]).text().trim(), // Department
          $(tds[1]).text().trim(), // Cohort
          $(tds[2]).text().trim(), // Campus
          $(tds[3]).text().trim(), // Subject
        ];
      })
      .filter(Boolean);

    if (!rows.length) {
      alert("No data to export.");
      return;
    }

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet([
      ["Department", "Cohort", "Campus", "Subject (Today)"],
      ...rows,
    ]);
    XLSX.utils.book_append_sheet(wb, ws, "Today_Exams");
    XLSX.writeFile(wb, "Classes_Having_Exam_Today.xlsx");
  }
});
