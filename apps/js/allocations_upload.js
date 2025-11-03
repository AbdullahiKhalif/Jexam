// apps/js/allocations_upload.js
$(function () {
  console.log("[allocations_upload] JS loaded");
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $campus = $("#campus"),
    $shift = $("#shift");
  const $frm = $("#frmUpload"),
    $file = $("#file"),
    $res = $("#result"),
    $btn = $("#btnUpload");

  // preview UI
  const $pWrap = $("#previewResults"),
    $pCards = $("#previewHallCards"),
    $pLoad = $("#previewLoading"),
    $pErr = $("#previewError");

  // hidden inputs for graceful fallback (if you kept them in the DOM)
  const $hidYear = $("#hid_year"),
    $hidSem = $("#hid_sem"),
    $hidTerm = $("#hid_term"),
    $hidCampus = $("#hid_campus"),
    $hidShift = $("#hid_shift");

  let yearId = null,
    semId = null;
  let headerImg = "";

  // Optional: preload header like in printable report (ignore errors)
  (function preloadHeader() {
    const url = "../assets/reports/header.jpg";
    fetch(url)
      .then((r) => (r.ok ? r.blob() : Promise.reject()))
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

  // Load Year/Sem, Term, Campus, Shift
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
        syncHidden();
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
    syncHidden();
    loadTerms();
  });
  $term.on("change", syncHidden);
  $campus.on("change", () => {
    loadShifts();
    syncHidden();
  });
  $shift.on("change", syncHidden);

  function loadTerms() {
    $.post(
      "../api/academic.php",
      { action: "listTermsActive", semester_id: semId },
      (r) => {
        $term.html(
          r?.status
            ? r.data.map((t) => `<option value="${t.id}">${t.name}</option>`)
            : "<option></option>"
        );
        syncHidden();
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
            : "<option></option>"
        );
        loadShifts();
        syncHidden();
      },
      "json"
    );
  }
  function loadShifts() {
    const prev = $shift.val(); // remember selected shift
    $.post(
      "../api/shift.php",
      { action: "list" }, // global shifts, no campus filter
      (r) => {
        if (r?.status && Array.isArray(r.data)) {
          $shift.html(
            r.data
              .map((s) => `<option value="${s.id}">${s.name}</option>`)
              .join("")
          );
          if (prev) $shift.val(prev);
        } else {
          $shift.html('<option value="">--</option>');
        }
        syncHidden();
      },
      "json"
    );
  }

  function syncHidden() {
    if ($hidYear.length) $hidYear.val(yearId || "");
    if ($hidSem.length) $hidSem.val(semId || "");
    if ($hidTerm.length) $hidTerm.val($term.val() || "");
    if ($hidCampus.length) $hidCampus.val($campus.val() || "");
    if ($hidShift.length) $hidShift.val($shift.val() || "");
  }

  /* =========================
   * PREVIEW (Client-only)
   * ========================= */
  $("#btnPreview").on("click", function () {
    const f = $file[0]?.files?.[0];
    if (!f) return Swal.fire("Choose file", ".xlsx required", "warning");
    if (!/\.xlsx$/i.test(f.name))
      return Swal.fire("Invalid file", "Please select a .xlsx file", "error");
    if (!yearId || !semId || !$term.val() || !$campus.val() || !$shift.val()) {
      return Swal.fire(
        "Pick period",
        "Year, Semester, Term, Campus, Shift required",
        "warning"
      );
    }

    // No date prompt: leave date blank in the preview header
    const pickedDate = ""; // blank on purpose

    $pErr.hide().empty();
    $pCards.empty();
    $pWrap.hide();
    $pLoad.show();

    // Read file → workbook → first sheet → AOA
    const reader = new FileReader();
    reader.onload = () => {
      try {
        const wb = XLSX.read(reader.result, { type: "array" });
        const ws = wb.Sheets[wb.SheetNames[0]];
        const aoa = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false });

        const model = buildPreviewModelFromAOA(aoa);
        renderPreviewCards(model, {
          campusName: $("#campus option:selected").text(),
          shiftName: $("#shift option:selected").text(),
          date: pickedDate, // blank
        });

        $pLoad.hide();
        $pWrap.show()[0].scrollIntoView({ behavior: "smooth" });
      } catch (e) {
        $pLoad.hide();
        showPreviewError(e.message || "Failed to parse XLSX");
      }
    };
    reader.onerror = () => {
      $pLoad.hide();
      showPreviewError("Failed to read file");
    };
    reader.readAsArrayBuffer(f);
  });

  // Parse your Excel format (header, capacities, rows) → hall structure
  function buildPreviewModelFromAOA(aoa) {
    // Find header row: needs "Class" and "# of Std." (case-insensitive)
    let headerRow = -1,
      idxClass = -1,
      idxTotal = -1;
    for (let r = 0; r < aoa.length; r++) {
      const row = (aoa[r] || []).map((v) => String(v || "").trim());
      const lc = row.map((v) => v.toLowerCase());
      const posClass = lc.indexOf("class");
      if (posClass >= 0) {
        // Find a "# of Std." to the right (or any cell that contains "std")
        let posTotal = -1;
        for (let c = posClass + 1; c < lc.length; c++) {
          if (
            lc[c] === "# of std." ||
            lc[c] === "# of std" ||
            lc[c].includes("std") ||
            lc[c].includes("student")
          ) {
            posTotal = c;
            break;
          }
        }
        if (posTotal >= 0) {
          headerRow = r;
          idxClass = posClass;
          idxTotal = posTotal;
          break;
        }
      }
    }
    if (headerRow < 0)
      throw new Error('Header row not found (need "Class" and "# of Std.").');

    // Halls = non-empty cells after idxTotal in header row
    const header = aoa[headerRow] || [];
    const hallCols = [];
    const hallNames = [];
    for (let c = idxTotal + 1; c < header.length; c++) {
      const nm = String(header[c] || "").trim();
      if (nm) {
        hallCols.push(c);
        hallNames.push(nm);
      }
    }
    if (!hallCols.length)
      throw new Error("No hall columns found after '# of Std.'");

    // capacities row = the next row that has the word "Size" in Class column (case-insensitive)
    let capsRow = -1;
    for (let r = headerRow + 1; r < aoa.length; r++) {
      const row = aoa[r] || [];
      const cell = String(row[idxClass] || "")
        .trim()
        .toLowerCase();
      if (cell === "size") {
        capsRow = r;
        break;
      }
    }
    if (capsRow < 0)
      throw new Error(
        'Capacities row not found (look for "Size" under Class column)'
      );

    // Build base hall map with capacities
    const halls = {}; // hall_name => { hall_name, capacity, rows:[], total }
    for (let i = 0; i < hallCols.length; i++) {
      const nm = hallNames[i];
      const cap = toInt((aoa[capsRow] || [])[hallCols[i]]);
      halls[nm] = { hall_name: nm, capacity: cap, rows: [], total: 0 };
    }

    // Data rows = from capsRow+1 down until blank class
    for (let r = capsRow + 1; r < aoa.length; r++) {
      const row = aoa[r] || [];
      const code = String(row[idxClass] || "").trim();
      if (!code) continue; // skip blanks
      if (!/^[A-Za-z]{2,}\d+/.test(code)) continue; // skip non-class

      // per-hall splits
      for (let i = 0; i < hallCols.length; i++) {
        const nm = hallNames[i];
        const n = toInt(row[hallCols[i]]);
        if (n > 0) {
          halls[nm].rows.push({
            class_code: code,
            students: n,
            subject_name: "", // unknown from Excel – leave blank
            subject_type: "Specific", // default style
          });
          halls[nm].total += n;
        }
      }
    }

    // Return only halls with rows
    return Object.values(halls).filter((h) => h.rows.length > 0);
  }

  function toInt(v) {
    const n = parseInt(String(v || "").replace(/[^\d\-]/g, ""), 10);
    return Number.isFinite(n) ? n : 0;
  }

  function showPreviewError(msg) {
    $pErr.html(`<h4>Error</h4><p>${esc(msg)}</p>`).show();
  }

  // Render cards (same look and feel as report_allocation_template)
  function renderPreviewCards(halls, meta) {
    $pCards.empty();

    // natural sort for “Hall 1, Hall 2, …”
    halls.sort((a, b) => {
      const na = parseInt(String(a.hall_name).replace(/\D/g, ""), 10);
      const nb = parseInt(String(b.hall_name).replace(/\D/g, ""), 10);
      return isNaN(na) || isNaN(nb)
        ? String(a.hall_name).localeCompare(String(b.hall_name))
        : na - nb;
    });

    const dayLabel = formatDayLabel(meta.date); // blank → ""
    const dateText = meta.date
      ? new Date(meta.date).toLocaleDateString("en-GB")
      : "";
    const shiftTime = ""; // not known in preview

    halls.forEach((h) => {
      const total = (h.rows || []).reduce((s, r) => s + (r.students || 0), 0);
      const hallNo =
        String(h.hall_name || "").replace(/\D/g, "") || h.hall_name;

      const rowsHtml = (h.rows || [])
        .map((r) => {
          const isGeneral = false;
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
          </tr>`;
        })
        .join("");

      const card = `
        <center>
          <div class="col-lg-9 col-sm-12 col-md-12">
            <div class="allocation-card">
              ${
                headerImg
                  ? `<img src="${headerImg}" alt="Header" class="allocation-header-image" style="height:15vh">`
                  : ""
              }
              <div class="allocation-details" style="font-family:'Times New Roman', Times, serif;">
                <div class="d-flex justify-content-between px-md-5 px-3 pt-2" style="font-size:1.3rem; font-weight:bold;">
                  <span style="text-transform:uppercase;">${esc(
                    meta.shiftName || "Shift"
                  )}</span>
                  <span>${esc(shiftTime)}</span>
                </div>
                <div class="d-flex justify-content-between px-md-5 px-3" style="font-size:1.3rem; font-weight:bold;">
                  <span style="text-transform:uppercase;">${esc(
                    meta.campusName || "Campus"
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
                      rowsHtml ||
                      `<tr><td colspan="3" class="text-muted">No rows</td></tr>`
                    }
                    <tr style="font-family:'Times New Roman', Times, serif; font-weight:bold; font-size:19px;">
                      <td style="text-align:right;">Total</td>
                      <td style="text-align:center;">${total}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </center>`;
      $pCards.append(card);
    });
  }

  function formatDayLabel(dateStr) {
    if (!dateStr) return "";
    const d = new Date(dateStr + "T00:00:00");
    const names = [
      "Sunday",
      "Monday",
      "Tuesday",
      "Wednesday",
      "Thursday",
      "Friday",
      "Saturday",
    ];
    if (isNaN(d)) return "";
    return names[d.getDay()];
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

  /* =========================
   * UPLOAD (Server import) with confirmation
   * ========================= */
  $frm.on("submit", function (e) {
    e.preventDefault();

    const f = $file[0].files[0];
    if (!f) return Swal.fire("Choose file", ".xlsx required", "warning");
    if (!/\.xlsx$/i.test(f.name))
      return Swal.fire("Invalid file", "Please upload a .xlsx file", "error");
    if (!yearId || !semId || !$term.val() || !$campus.val() || !$shift.val())
      return Swal.fire("Pick period", "All selectors are required", "warning");

    // ✨ Confirmation dialog showing file name and context
    Swal.fire({
      title: "Register/Update Allocation?",
      html: `
        <div class="text-start">
          You are about to import:
          <ul style="margin-top:6px;">
            <li><b>File:</b> ${esc(f.name)}</li>
            <li><b>Year/Semester:</b> ${esc(
              $("#yearsem option:selected").text()
            )}</li>
            <li><b>Term:</b> ${esc($("#term option:selected").text())}</li>
            <li><b>Campus:</b> ${esc($("#campus option:selected").text())}</li>
            <li><b>Shift:</b> ${esc($("#shift option:selected").text())}</li>
          </ul>
          This will upsert classes, class sizes, and hall allocations for the selected period.
        </div>`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, upload",
      cancelButtonText: "Cancel",
      reverseButtons: true,
      focusCancel: true,
    }).then((res) => {
      if (!res.isConfirmed) return;

      const fd = new FormData();
      fd.append("action", "uploadExcel");
      fd.append("academic_year_id", yearId);
      fd.append("semester_id", semId);
      fd.append("term_id", $term.val());
      fd.append("campus_id", $campus.val());
      fd.append("shift_id", $shift.val());
      fd.append("file", f);

      $btn.prop("disabled", true).text("Uploading…");
      $.ajax({
        url: "../api/allocation_upload.php",
        method: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
      })
        .done((r) => {
          if (r?.status) {
            const d = r.data || r,
              w = d.warnings || [];
            $res.show().html(
              `
          <div class="alert alert-success">
            <div><b>Halls:</b> ${(d.halls || []).join(", ") || "-"}</div>
            <div><b>Classes created:</b> ${d.created_classes || 0}</div>
            <div><b>Classes updated:</b> ${d.updated_classes || 0}</div>
            <div><b>Sizes upserted:</b> ${d.upserted_sizes || 0}</div>
            <div><b>Allocations upserted:</b> ${
              d.upserted_allocations || 0
            }</div>
            ${
              w.length
                ? `<hr><div class="text-warning">Totals mismatch for: ${w.join(
                    ", "
                  )}</div>`
                : ""
            }
          </div>`
            );
            Swal.fire("Done", "Excel imported successfully", "success");
          } else {
            Swal.fire("Error", r?.data || "Import failed", "error");
          }
        })
        .fail((xhr) => {
          Swal.fire("Error", xhr?.responseText || "Import failed", "error");
        })
        .always(() => {
          $btn.prop("disabled", false).text("Upload & Register");
        });
    });
  });
});
