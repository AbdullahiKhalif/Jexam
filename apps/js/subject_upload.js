// apps/js/subject_upload.js
$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $allTerms = $("#allTerms"),
    $file = $("#file"),
    $btn = $("#btnUpload"),
    $res = $("#result"),
    $dur = $("#duration");

  let yearId = null,
    semId = null;

  // Load Year/Sem pairs (active)
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

  $allTerms.on("change", () => {
    $term.prop("disabled", $allTerms.is(":checked"));
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

  $btn.on("click", async () => {
    $res.text("");

    if (!yearId || !semId)
      return Swal.fire("Pick filters", "Academic Year and Semester are required.", "warning");

    const allTerms = $allTerms.is(":checked");
    const termId = $term.val();

    if (!allTerms && !termId)
      return Swal.fire("Pick Term", "Select a Term or enable All Terms.", "warning");

    const f = $file[0].files[0];
    if (!f) return Swal.fire("File required", "Please choose a .xlsx file", "warning");
    if (!/\.xlsx$/i.test(f.name)) return Swal.fire("Invalid file", "Only .xlsx is accepted", "error");

    const { isConfirmed } = await Swal.fire({
      title: "Proceed with upload?",
      text: allTerms
        ? "Data will be created/updated for ALL active Terms in the selected Semester."
        : "Data will be created/updated for the selected Term.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes, upload",
    });
    if (!isConfirmed) return;

    const fd = new FormData();
    fd.append("action", "upload");
    fd.append("academic_year_id", yearId);
    fd.append("semester_id", semId);
    fd.append("term_id", termId || 0);
    fd.append("all_terms", allTerms ? 1 : 0);
    fd.append("default_duration", $dur.val() || 120);
    fd.append("file", f);

    $btn.prop("disabled", true).data("orig", "Upload").text("Uploading…");

    $.ajax({
      url: "../api/subject_upload.php",
      method: "POST",
      data: fd,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (r) {
        $btn.prop("disabled", false).text($btn.data("orig") || "Upload");
        if (!r?.status) {
          Swal.fire("Error", r?.data || "Failed", "error");
          return;
        }
        const s = r.data || {};
        let text = `✅ Upload complete
Subjects:           created ${nz(s.subjects_created)} | updated ${nz(s.subjects_updated)}
Class links:        created ${nz(s.links_created)}
Exam events:        created ${nz(s.events_created)} | updated ${nz(s.events_updated)}
Skipped rows:       ${nz(s.skipped_rows)}
Lookup failed:      ${nz(s.lookup_failed)}
Skipped events (no shift/campus/date): ${nz(s.skipped_event_no_shift_or_campus)}

Sheets:
`;

        (s.sheets || []).forEach((sh) => {
          text +=
            ` - ${sh.name}: ` +
            `rows=${nz(sh.rows)}, ` +
            `subjects created=${nz(sh.created_subjects)}, updated=${nz(sh.updated_subjects)}, ` +
            `events created=${nz(sh.events)}, updated=${nz(sh.events_updated)}, ` +
            `linked=${nz(sh.linked)}, skipped=${nz(sh.skipped)}, ` +
            `lookup_failed=${nz(sh.lookup_failed)}, no_shift_or_campus=${nz(sh.skipped_event_no_shift_or_campus)}` +
            `${sh.error ? " ⚠ " + sh.error : ""}\n`;
        });

        $res.text(text);
        Swal.fire("Done", "Upload finished", "success");
      },
      error: function (xhr) {
        $btn.prop("disabled", false).text($btn.data("orig") || "Upload");
        const msg = xhr?.responseJSON?.data || xhr?.responseText || "Failed";
        Swal.fire("Error", msg, "error");
      },
    });
  });

  function nz(v) { return v === 0 || v ? v : 0; }
});
