/* apps/js/report_subject_halls.js  — updated */
$(function () {
  // ----------------- DOM -----------------
  const $pair = $("#yearsem");
  const $term = $("#term");
  const $campus = $("#campus");
  const $shift = $("#shift");
  const $subject = $("#subject");

  const $btnGen = $("#btnGenerate");
  const $btnPrint = $("#btnPrint");
  const $btnXls = $("#btnExport");

  const $err = $("#errorBox");
  const $load = $("#loading");
  const $results = $("#resultsSection");
  const $sum = $("#summaryLine");
  const $tbody = $("#tblRows");

  // ----------------- State -----------------
  let yearId = null, semId = null;

  // anti-race tokens / xhr handles
  let termReqXHR = null, shiftsReqXHR = null, subjectsReqXHR = null;
  let termReqToken = 0,  shiftsReqToken = 0,  subjectsReqToken = 0;

  const ALL_CAMPUSES_OPT = '<option value="0">All Campuses</option>';
  const ALL_SHIFTS_OPT   = '<option value="0">All Shifts</option>';

  // ----------------- Helpers -----------------
  function esc(s) {
    return String(s ?? "").replace(/[&<>"']/g, (m) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[m]));
  }
  function toCohortLabel(codeOrCohort) {
    const raw = String(codeOrCohort || "").trim().toUpperCase();
    if (/^[A-Z]+[0-9]{2}'S$/.test(raw)) return raw;
    const m = raw.match(/^([A-Z]+)\s*?([0-9]{2})/);
    return m ? `${m[1]}${m[2]}'S` : raw;
  }
  function abortIf(xhr) { try { if (xhr && xhr.readyState !== 4) xhr.abort(); } catch(e){} }
  function invalidateResults() { $results.hide(); $tbody.empty(); $sum.empty(); $err.hide().empty(); }

  // ----------------- DataTables reset (fix stale UI) -----------------
  function resetTable() {
    try {
      if ($.fn.DataTable && $.fn.DataTable.isDataTable("#tblMain")) {
        const dt = $("#tblMain").DataTable();
        dt.clear();
        dt.destroy();
      }
    } catch (e) { console.warn("DT destroy issue:", e); }

    const $wrapper = $("#tblMain").closest(".dataTables_wrapper");
    if ($wrapper.length) {
      const $tbl = $wrapper.find("#tblMain");
      $wrapper.before($tbl);
      $wrapper.remove();
    }

    $("#tblMain")
      .removeClass("dataTable no-footer compact")
      .attr("style", "");
  }

  // ----------------- Select2 lifecycle -----------------
  function destroySelect2() {
    try { if ($subject.data("select2")) $subject.select2("destroy"); } catch (e) {}
  }
  function attachSubjectHandlers() {
    $subject.off(".report")
      .on("change.report select2:select.report select2:clear.report", () => {
        $err.hide().empty();
        // You can auto-generate on selection if you wish:
        // if ($subject.val()) generateReport(); else invalidateResults();
      });
  }
  function initSelect2() {
    if (typeof $.fn.select2 === "function") {
      $subject.select2({ width: "100%", placeholder: "-- Select Subject --" });
    }
    attachSubjectHandlers();
  }
  function pickSubjectLabel() {
    if ($subject.data("select2") && typeof $subject.select2 === "function") {
      const d = $subject.select2("data");
      const txt = (Array.isArray(d) && d[0] && d[0].text) ? d[0].text : "";
      if (txt) return txt;
    }
    return $subject.find(":selected").text() || "";
  }

  // ----------------- Init: Year/Sem -----------------
  $.post("../api/academic.php", { action: "listYearSemesterPairsActive" }, (r) => {
    if (r?.status && r.data?.length) {
      $pair.html(r.data.map(
        (p) => `<option value="${p.year_id}|${p.semester_id}">${esc(p.label)}</option>`
      ).join(""));
      [yearId, semId] = ($pair.val() || "|").split("|");
      loadTerms();
      loadCampuses();
    } else {
      $pair.html('<option value="">--</option>');
      $term.html('<option value="">--</option>');
      $campus.html('<option value="">--</option>');
      $shift.html('<option value="">--</option>');
    }
  }, "json");

  $pair.on("change", () => {
    [yearId, semId] = ($pair.val() || "|").split("|");
    invalidateResults();
    loadTerms(); // subjects refreshed after terms load
  });

  function loadTerms() {
    abortIf(termReqXHR);
    const myToken = ++termReqToken;

    termReqXHR = $.ajax({
      url: "../api/academic.php",
      type: "POST",
      dataType: "json",
      cache: false,
      data: { action: "listTermsActive", semester_id: semId }
    }).done((r) => {
      if (myToken !== termReqToken) return;
      $term.html(
        r?.status && Array.isArray(r.data)
          ? r.data.map((t) => `<option value="${t.id}">${esc(t.name)}</option>`).join("")
          : '<option value="">--</option>'
      );
      loadSubjects();
    });
  }

  // ----------------- Campuses & Shifts -----------------
  function loadCampuses() {
    $.post("../api/catalog.php", { action: "listCampuses" }, (r) => {
      if (r?.status && Array.isArray(r.data) && r.data.length) {
        $campus.html(
          [ALL_CAMPUSES_OPT].concat(
            r.data.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`)
          ).join("")
        );
      } else {
        $campus.html(ALL_CAMPUSES_OPT);
      }
      loadShiftsForCampus();
    }, "json");
  }

  function loadShiftsForCampus() {
    invalidateResults();
    const campusId = Number($campus.val() || 0);

    abortIf(shiftsReqXHR);
    const myToken = ++shiftsReqToken;

    if (!campusId) {
      $shift.html([
        ALL_SHIFTS_OPT,
        '<option value="1">Shift I</option>',
        '<option value="2">Shift II</option>',
        '<option value="3">Shift III</option>',
      ].join(""));
      loadSubjects();
      return;
    }

    shiftsReqXHR = $.ajax({
      url: "../api/reports.php",
      type: "POST",
      dataType: "json",
      cache: false,
      data: { action: "listShiftsByCampus", campus_id: campusId }
    }).done((r) => {
      if (myToken !== shiftsReqToken) return;
      if (r?.status && Array.isArray(r.data) && r.data.length) {
        $shift.html(r.data.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join(""));
        $shift.prepend(ALL_SHIFTS_OPT).val("0");
      } else {
        $shift.html(ALL_SHIFTS_OPT);
      }
      loadSubjects();
    });
  }

  $campus.on("change", loadShiftsForCampus);
  $shift.on("change", () => { invalidateResults(); loadSubjects(); });
  $term.on("change",  () => { invalidateResults(); loadSubjects(); });

  // ----------------- Subjects (value = "subjectId|examDate") -----------------
  function loadSubjects() {
    destroySelect2();
    $subject.empty().append('<option value="">-- Select Subject --</option>').val("");

    abortIf(subjectsReqXHR);
    const myToken = ++subjectsReqToken;

    const payload = {
      action: "listSubjectsForFilter",
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
      campus_id: $campus.val() || 0,
      shift_id: $shift.val() || 0,
    };

    subjectsReqXHR = $.ajax({
      url: "../api/report_subject_halls.php",
      type: "POST",
      dataType: "json",
      cache: false,
      data: payload
    }).done((r) => {
      if (myToken !== subjectsReqToken) return;

      if (!r?.status || !Array.isArray(r.data)) {
        initSelect2(); // still init for UX
        return;
      }

      const opts = r.data.map((it) => {
        const id   = String(it.id);
        const nm   = it.name || "";
        const date = it.exam_date || ""; // may be empty
        const coh  = toCohortLabel(it.cohort || it.class_code || "");
        const label = `${coh} - ${nm}${date ? ` (${date})` : ""}`;

        // Composite value ensures subject_id and date always travel together
        const value = `${id}|${date}`;

        return `<option value="${esc(value)}"
                        data-name="${esc(nm)}"
                        data-cohort="${esc(coh)}"
                        data-date="${esc(date)}">${esc(label)}</option>`;
      }).join("");

      $subject.append(opts);
      initSelect2();
      $subject.val(null).trigger("change"); // clear any stale display
    }).fail(() => initSelect2());
  }

  // ----------------- Actions -----------------
  attachSubjectHandlers();

  $("#btnGenerate").on("click", () => {
    $err.hide().empty();
    const v = String($subject.val() || "");
    if (!v) {
      $err.html("<h4>Error</h4><p>Please select a subject.</p>").show();
      return;
    }
    generateReport();
  });

  $btnPrint.on("click", () => window.print());

  $btnXls.on("click", () => {
    const rows = $("#tblRows tr").get().map((tr) => {
      const t = $(tr).find("td");
      if (t.length < 8) return null;
      return [
        $(t[0]).text().trim(),
        $(t[1]).text().trim(),
        $(t[2]).text().trim(),
        $(t[3]).text().trim(),
        $(t[4]).text().trim(),
        $(t[5]).text().trim(),
        $(t[6]).text().trim(),
        $(t[7]).text().trim(),
      ];
    }).filter(Boolean);

    if (!rows.length) return alert("No data to export.");
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet([
      ["Department","Cohort","Class","Hall","Capacity","Allocated","Campus","Shift"],
      ...rows,
    ]);
    XLSX.utils.book_append_sheet(wb, ws, "Subject_Classes_Halls");
    XLSX.writeFile(wb, "Subject_Classes_Halls.xlsx");
  });

  function generateReport() {
    // Parse composite subject value "subjectId|examDate"
    const composite = String($subject.val() || "");
    const [subjectIdRaw, examDateRaw] = composite.split("|");
    const subject_id = subjectIdRaw ? subjectIdRaw.trim() : "";
    const exam_date  = (examDateRaw ?? "").trim();

    const payload = {
      action: "subjectHalls",
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val(),
      campus_id: $campus.val() || 0,
      shift_id: $shift.val() || 0,
      subject_id,
      exam_date, // send exact date paired with subject
    };

    $results.hide();
    $load.show();

    $.ajax({
      url: "../api/report_subject_halls.php",
      type: "POST",
      dataType: "json",
      cache: false,
      data: payload
    }).done((r) => {
      $load.hide();
      if (!r?.status) {
        $err.html(`<h4>Error</h4><p>${esc(r?.data || "Failed to load")}</p>`).show();
        return;
      }
      render(r.data || {});
    }).fail(() => {
      $load.hide();
      $err.html("<h4>Error</h4><p>Failed to load.</p>").show();
    });
  }

  function render(data) {
    // Reset DataTables + wrapper so fresh DOM will show
    resetTable();

    const meta = data?.meta || {};
    const subj = pickSubjectLabel();

    $sum.html(
      `<div class="text-muted">
        <b>Year:</b> ${esc(meta.year || "")} &nbsp;|&nbsp;
        <b>Semester:</b> ${esc(meta.semester || "")} &nbsp;|&nbsp;
        <b>Term:</b> ${esc(meta.term || "")} &nbsp;|&nbsp;
        <b>Campus:</b> ${esc(meta.campus || "")} &nbsp;|&nbsp;
        <b>Shift:</b> ${esc(meta.shift || "")} &nbsp;|&nbsp;
        <b>Subject:</b> ${esc(subj)}
      </div>`
    );

    const rows = Array.isArray(data?.rows) ? data.rows : (Array.isArray(data) ? data : []);
    const html = rows.map((r) => `
      <tr>
        <td>${esc(r.department || "")}</td>
        <td>${esc(r.cohort || "")}</td>
        <td>${esc(r.class_code || "")}</td>
        <td>${esc(r.hall_name || "")}</td>
        <td>${Number(r.capacity ?? 0)}</td>
        <td>${Number(r.allocated ?? 0)}</td>
        <td>${esc(r.campus || "")}</td>
        <td>${esc(r.shift || "")}</td>
      </tr>
    `).join("");

    $tbody.html(html || '<tr><td colspan="8" class="text-muted">No data</td></tr>');

    try {
      if ($.fn.DataTable) {
        $("#tblMain").DataTable({
          dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>tr<'row'<'col-sm-6'i><'col-sm-6'p>>",
          pageLength: 10,
          lengthMenu: [[10,25,50,100],[10,25,50,100]],
          order: [[0,"asc"],[1,"asc"],[2,"asc"]],
          responsive: true,
        });
      }
    } catch (e) {
      console.warn("DataTables init failed:", e);
    }

    // Finally reveal results
    $results.show();
  }
});
