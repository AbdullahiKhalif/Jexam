// apps/js/report_subjects_assigned.js
$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $dept = $("#department"),
    $date = $("#date");

  const $btn = $("#btnGenerate"),
    $printTop = $("#btnPrintTop"),
    $printBottom = $("#btnPrintBottom"),
    $exportExcel = $("#btnExportExcel");

  const $results = $("#resultsSection"),
    $load = $("#loading"),
    $err = $("#errorBox"),
    $summary = $("#summaryLine"),
    $blocks = $("#deptBlocks"),
    $printArea = $("#printArea");

  let yearId = null,
    semId = null;
  let lastPayload = null; // keep latest payload for title/date
  let lastData = null; // keep latest response for export

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

  // Load Year/Sem pairs
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
        loadDepartments();
      } else {
        $pair.html('<option value="">--</option>');
        $term.html('<option value="">--</option>');
        $dept.html('<option value="">--</option>');
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
            ? r.data.map(
                (t) => `<option value="${t.id}">${esc(t.name)}</option>`
              )
            : '<option value="">--</option>'
        );
      },
      "json"
    );
  }

  function loadDepartments() {
    $.post(
      "../api/department.php",
      { action: "list" },
      (r) => {
        if (r?.status && Array.isArray(r.data)) {
          const opts = [`<option value="0">All Departments</option>`].concat(
            r.data.map((d) => `<option value="${d.id}">${esc(d.name)}</option>`)
          );
          $dept.html(opts.join(""));
        } else {
          $dept.html('<option value="0">All Departments</option>');
        }
      },
      "json"
    );
  }

  $btn.on("click", () => {
    $err.hide().empty();
    $results.hide();
    $blocks.empty();
    $summary.empty();

    const payload = {
      action: "subjectsAssignedByDate",
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
      department_id: $dept.val() || 0,
      exam_date: $date.val(),
    };

    if (
      !payload.academic_year_id ||
      !payload.semester_id ||
      !payload.term_id ||
      !payload.exam_date
    ) {
      return showError("Pick Year/Semester, Term, and Date.");
    }

    lastPayload = payload;

    $load.show();
    $.post(
      "../api/reports.php",
      payload,
      (r) => {
        $load.hide();
        if (!r?.status) {
          return showError(r?.data || "Failed to load report");
        }
        lastData = r.data || null;
        render(lastData);
        $results.show()[0].scrollIntoView({ behavior: "smooth" });
      },
      "json"
    ).fail((xhr) => {
      $load.hide();
      showError(xhr?.responseText || "Failed");
    });
  });

  $printTop.on("click", handlePrint);
  $printBottom.on("click", handlePrint);

  function handlePrint() {
    // Print ONLY the card-body (#printArea)
    const win = window.open("");
    if (!win) return;
    const title = "Subjects Assigned";
    const cssLink = `<link rel="stylesheet" href="../assets/reports/style.css">`;
    const html = `
      <html>
        <head>
          <meta charset="utf-8">
          <title>${esc(title)}</title>
          ${cssLink}
          <style>
            @page { size: A4; margin: 12mm; }
            .print-hide { display: none !important; }
             @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300&display=swap');
    body{
        font-family: 'Poppins', sans-serif;
        background-color: #fff !important; 
    }
    table{
        width: 100%;
    
    }
    th{
        font-size: 20px !important;
        font-weight: bold !important;
    }
    th , td{
        padding: 15px !important;
        text-align: left !important;
        border-bottom: 1px solid #ddd !important;
    }

    h4{
        text-transform: uppercase !important;
        margin-bottom:2px !important;
        text-align: center !important;
        padding:0px !important;
    
    }

          </style>
        </head>
        <body>
          ${$printArea.html()}
        </body>
      </html>
    `;
    win.document.open();
    win.document.write(html);
    win.document.close();
    // give styles a tick to load
    win.focus();
    setTimeout(() => win.print(), 50);
  }

  $exportExcel.on("click", () => {
    if (
      !lastData ||
      !Array.isArray(lastData.departments) ||
      lastData.departments.length === 0
    ) {
      return showError("Nothing to export. Generate the report first.");
    }
    exportToExcel(lastData);
  });

  function showError(msg) {
    $err.html(`<h4>Error</h4><p>${esc(msg)}</p>`).show();
  }

  function render(data) {
    const meta = data?.meta || {};
    const depts = data?.departments || [];

    const dateText = meta.date
      ? new Date(meta.date).toLocaleDateString("en-GB")
      : "";

    if (!depts.length) {
      $blocks.html(
        '<div class="text-muted text-center">No subjects found for the selected filters.</div>'
      );
      return;
    }

    // Build blocks per department
    const html = depts
      .map((d) => {
        const rows = (d.rows || [])
          .map(
            (r) => `
        <tr>
          <td style="width:40%; font-weight:600;">${esc(r.cohort)}</td>
          <td>${esc(r.subject)}</td>
        </tr>
      `
          )
          .join("");

        return `
        <div class="mb-4">
          <h4 class="header p-2 text-light fw-bold" style="text-transform:uppercase; margin-bottom:3px;text-align: center;">${esc(
            d.dept_name || "Department"
          )}</h4>
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
              <thead>
                <tr>
                  <th style="width:40%; font-weight: bold;">Cohort</th>
                  <th style="width:60%; font-weight: bold;">Subject</th>
                </tr>
              </thead>
              <tbody>
                ${
                  rows ||
                  `<tr><td colspan="2" class="text-muted">No subjects scheduled.</td></tr>`
                }
              </tbody>
            </table>
          </div>
        </div>
      `;
      })
      .join("");

    $blocks.html(html);
  }

  // ========= Excel export =========
  function exportToExcel(data) {
    const wb = XLSX.utils.book_new();
    const depts = data.departments || [];
    const meta = data.meta || {};
    const titleDate = meta.date
      ? new Date(meta.date).toLocaleDateString("en-GB")
      : "";

    depts.forEach((d) => {
      // AOArray:
      // Row 1: Department title
      // Row 2: blank
      // Row 3: headers
      const aoa = [
        [`${(d.dept_name || "Department").toUpperCase()} — ${titleDate}`],
        [""],
        ["Cohort", "Subject"],
      ];

      (d.rows || []).forEach((r) => {
        aoa.push([r.cohort || "", r.subject || ""]);
      });

      const ws = XLSX.utils.aoa_to_sheet(aoa);

      // Column widths
      ws["!cols"] = [{ wch: 20 }, { wch: 60 }];

      // Margins (in inches)
      ws["!margins"] = {
        left: 0.5,
        right: 0.5,
        top: 0.5,
        bottom: 0.5,
        header: 0.3,
        footer: 0.3,
      };

      // Sheet name (30 char limit, clean)
      const sheetName =
        String(d.dept_name || "Department")
          .replace(/[\\/*?[\]:]/g, "")
          .slice(0, 30) || "Sheet";
      XLSX.utils.book_append_sheet(wb, ws, sheetName);
    });

    // Workbook properties could be set if needed:
    // wb.Props = {...}

    const fileName = `Subjects_Assigned_${titleDate || "Report"}.xlsx`;
    XLSX.writeFile(wb, fileName);
  }
});
