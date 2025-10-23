// apps/js/report_allocation_template.js
$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $campus = $("#campus"),
    $shift = $("#shift");
  const $btn = $("#btnGenerate"),
    $btnPrint = $("#btnPrint");
  const $cards = $("#hallCards"),
    $results = $("#resultsSection"),
    $load = $("#loading"),
    $err = $("#errorBox");

  // Optional overrides for headings
  const $day = $("#day"),
    $date = $("#date"),
    $time = $("#time");

  let yearId = null,
    semId = null;
  let headerImg = "";
  function normalizeSubjectType(v) {
    const t = String(v || "")
      .toLowerCase()
      .replace(/[^a-z]/g, "");
    if (
      [
        "g",
        "gen",
        "general",
        "generalstudies",
        "generalstudy",
        "common",
        "foundation",
        "found",
        "gs",
      ].includes(t)
    )
      return "general";
    if (["s", "spec", "specific", "sp"].includes(t)) return "specific";
    return "specific";
  }

  // Preload logo/header (optional)
  (function preloadHeader() {
    const url = "../assets/reports/header.jpg";
    fetch(url)
      .then((r) => {
        if (!r.ok) throw new Error("Header image missing");
        return r.blob();
      })
      .then(
        (b) =>
          new Promise((res) => {
            const fr = new FileReader();
            fr.onload = () => res(fr.result);
            fr.readAsDataURL(b);
          })
      )
      .then((data) => {
        headerImg = data;
      })
      .catch(() => {});
  })();

  // Load filters
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
  function loadShifts() {
    $.post(
      "../api/catalog.php",
      { action: "listShifts", campus_id: $campus.val() },
      (r) => {
        $shift.html(
          r?.status
            ? r.data.map((s) => `<option value="${s.id}">${s.name}</option>`)
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
      action: "allocationByDate", // ← use date-aware endpoint
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
      campus_id: $campus.val(),
      shift_id: $shift.val(),
      exam_date: $date.val(), // ← send date
    };
    if (
      !payload.academic_year_id ||
      !payload.semester_id ||
      !payload.term_id ||
      !payload.campus_id ||
      !payload.shift_id ||
      !payload.exam_date
    ) {
      return showError(
        "Pick all filters: Year/Semester, Term, Campus, Shift and Date."
      );
    }
    $err.hide();
    $results.hide();
    $load.show();
    $cards.empty();
    $.post(
      "../api/reports.php",
      payload,
      function (r) {
        $load.hide();
        if (!r?.status) {
          return showError(r?.data || "Failed to load report");
        }
        render(r.data);
        $results.show()[0].scrollIntoView({ behavior: "smooth" });
      },
      "json"
    ).fail((xhr) => {
      $load.hide();
      showError(xhr?.responseText || "Failed");
    });
  });

  function showError(msg) {
    $err.html(`<h4>Error</h4><p>${esc(msg)}</p>`).show();
  }

  // Render printable cards
  function render(data) {
    const meta = data.meta || {};
    const halls = data.halls || [];
    if (!halls.length) {
      $cards.html(
        '<div class="text-muted">No allocations found for the selected filters.</div>'
      );
      return;
    }

    // natural sort for “Hall 1, Hall 2, …”
    halls.sort((a, b) => {
      const na = parseInt(String(a.hall_name).replace(/\D/g, ""), 10);
      const nb = parseInt(String(b.hall_name).replace(/\D/g, ""), 10);
      return isNaN(na) || isNaN(nb)
        ? String(a.hall_name).localeCompare(String(b.hall_name))
        : na - nb;
    });

    const manualDay = ($day.val() || "").trim();
    const dayLabel = manualDay || meta.day_label || "";
    const dateText = meta.date
      ? new Date(meta.date).toLocaleDateString("en-GB")
      : "";
    const manualTime = ($time.val() || "").trim();
    const shiftTime = meta.shift
      ? [meta.shift.start_time, meta.shift.end_time].filter(Boolean).join(" - ")
      : "";

    const CAMPUS_LEADERS = meta.campus_leaders;
    halls.forEach((h) => {
      const total = (h.rows || []).reduce((s, r) => s + (r.students || 0), 0);
      const hallNo =
        String(h.hall_name || "").replace(/\D/g, "") || h.hall_name;

      const rows = (h.rows || [])
        .map((r) => {
          const type = normalizeSubjectType(r.subject_type);
          const isGeneral = type === "general";

          const subjStyle = isGeneral
            ? "font-weight:bold; font-family: Algerian; text-transform:uppercase;"
            : "font-weight:300; font-family:'Times New Roman', Times, serif; text-transform:none;";

          const subjectText =
            r.subject_name && r.subject_name.trim() !== ""
              ? r.subject_name
              : "........................................";

          return `
      <tr style="font-family:'Times New Roman', Times, serif; text-align:center; font-size:22px;">
        <td style="font-weight:bold;">${esc(r.class_code)}</td>
        <td style="text-align:center; font-weight:bold;">${r.students}</td>
        <td style="text-align:center; font-size:22px; ${subjStyle}">${esc(
            subjectText
          )}</td>
      </tr>
    `;
        })
        .join("");

      const card = `
  <center>
    <div class="col-lg-9 col-sm-12 col-md-12">
      <div class="allocation-card">
        ${
          headerImg
            ? `<img src="${headerImg}" alt="Header" class="allocation-header-image" style="height:10vh">`
            : ""
        }
        <div class="allocation-details" style="font-family:'Times New Roman', Times, serif;">
          <div class="d-flex justify-content-between px-md-5 px-3 pt-2" style="font-size:1.3rem; font-weight:bold;">
            <span style="text-transform:uppercase;">${esc(
              meta.shift?.name || "Shift"
            )}</span>
            <span>${esc(manualTime || shiftTime)}</span>
          </div>
          <div class="d-flex justify-content-between px-md-5 px-3" style="font-size:1.3rem; font-weight:bold;">
            <span style="text-transform:uppercase;">${esc(
              meta.campus || "Campus"
            )}</span>
            <span>Hall # <span class="hall-number-box">${esc(
              hallNo
            )}</span></span>
          </div>
        </div>

        <div class="allocation-table-container">
          <table class="allocation-table">
            <thead>
              <tr class="day-date-row" style="text-align: center !important; font-family:'Times New Roman', Times, serif; font-size:21px;">
                <th colspan="2" style="text-align:center; font-size:21px;">Day: ${esc(
                  dayLabel
                )}</th>
                <th style="text-align:center; font-size:21px;">Date: ${
                  dateText || ""
                }</th>
              </tr>
              <tr>
                <th style="text-align:center; font-size:22px; font-family:'Times New Roman', Times, serif;">Class</th>
                <th style="text-align:center; font-size:22px; font-family:'Times New Roman', Times, serif;"># Of Students</th>
                <th style="text-align:center; font-size:22px; font-family:'Times New Roman', Times, serif;">Subject</th>
              </tr>
            </thead>
            <tbody>
              ${
                rows ||
                `<tr><td colspan="3" class="text-muted">No rows</td></tr>`
              }
              <tr style="font-family:'Times New Roman', Times, serif; font-weight:bold; font-size:19px;">
                <td style="text-align:right;">Total</td>
                <td style="text-align:center;">${total}</td>
                
              </tr>
            </tbody>
          </table>

          <div class="leaders mt-3">
          <h1 class="text-center fs-2" style="font-size: 20px; margin-top: 10px;">Contact Exam Officers: ${
            meta.campus_leaders || "Campus"
          }</h4>
          <div>
        </div>

      </div>
    </div>
  </center>`;
      $cards.append(card);
      $btnPrint.on("click", function () {
        // Add a print style for A5 landscape if needed
        const printWindow = window.open("", "");

        // Create a custom print stylesheet if needed
        const printStyle = `
    <style>
      /* Print styles for A5 landscape */
      @page {
        size: A5 landscape;
        margin: 0;
      }

      /* Global body styles */
      body {
        font-family: 'Arial', sans-serif;
        margin: 0;
        padding: 0;
      }

      /* Ensure full width for content */
      .results-section,
      .row,
      [class^="col-"],
      [class*=" col-"] {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      /* Hall allocation card styles */
      .allocation-card {
        margin: 0 !important;
        padding: 0 !important;
        break-inside: avoid;
        break-after: page;
        page-break-inside: avoid;
        page-break-after: always;
        border: none;
      }

      .allocation-card:last-of-type {
        break-after: auto;
        page-break-after: auto;
      }

      /* Ensure header image fits within A5 */
      .allocation-header-image {
        display: block;
        width: 100% !important;
        height: 20vh !important;
        margin: 0 !important;
      }

      /* General allocation details styling */
      .allocation-details {
        padding: 1rem 1.5rem;
        font-size: 1rem;
        font-weight: 500;
        margin: 12px 2rem;
        align-items: center !important;
      }

      .allocation-details .row > div {
        padding: 0.5rem;
      }

      .allocation-details strong {
        font-weight: 700;
        margin-right: 0.5rem;
      }

      /* Styling for hall number box */
      .hall-number-box {
        border: 2px solid black;
        padding: 0px;
        font-weight: 700;
        display: inline-block;
        border-radius: 10px;
        width: 90px;
      }

      /* Allocation table container padding */
      .allocation-table-container {
        padding: 0 1.5rem 1.5rem;
      }

      /* Allocation table basic styling */
      .allocation-table {
        width: 100%;
        border-collapse: collapse;
      }

      .allocation-table th,
      .allocation-table td {
        border: 2px solid black;
        padding: 5px;
        text-align: left;
      }

      .allocation-table th {
        font-weight: 700;
      }

      .allocation-table tr:last-child {
        font-weight: bold;
      }

      /* Specific inline styles converted into classes */
      .font-bold {
        font-weight: bold;
      }

      .text-uppercase {
        text-transform: uppercase;
      }

      .font-size-1-3rem {
        font-size: 1.3rem;
      }

      .px-md-5 {
        padding-left: 3rem;
        padding-right: 3rem;
      }

      .px-3 {
        padding-left: 1rem;
        padding-right: 1rem;
      }

      .pt-2 {
        padding-top: 0.5rem;
      }

      /* Flexbox layout for allocation details */
      .allocation-details .d-flex {
        display: flex;
        justify-content: space-between;
      }
      .leaders{
        font-size: 32px !important;
        margin-top: 10px !important;
      }
    </style>
  `;

        // Write the content into the print window
        printWindow.document.write(
          `<html><head><title>${esc(meta.campus || "Campus")} - ${esc(
            meta.shift?.name || "Shift"
          )} - ${esc(dayLabel)}</title>${printStyle}</head><body>`
        );
        printWindow.document.write(
          document.getElementById("resultsSection").innerHTML
        ); // Assuming #resultsSection has your report
        printWindow.document.write("</body></html>");
        printWindow.document.close();

        // Wait for the content to load and trigger print
        printWindow.onload = function () {
          printWindow.print();
        };
      });
    });
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
