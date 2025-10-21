// apps/js/subject_upload.js
$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $dept = $("#department"),
    $file = $("#file"),
    $btn = $("#btnUpload"),
    $res = $("#result"),
    $dur = $("#duration");

  // Optional (only if you add a control with this id): force all rows to use this shift
  const $forceShift = $("#forceShift"); // e.g. <select id="forceShift">...</select>

  let yearId = null,
    semId = null;

  // load Year/Sem pairs (active)
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
        loadDepartments();
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
  function loadDepartments() {
    $.post(
      "../api/department.php",
      { action: "list" },
      (r) => {
        if (r?.status) {
          $dept.html(
            r.data.map((d) => `<option value="${d.id}">${d.name}</option>`)
          );
        }
      },
      "json"
    );
  }

  $btn.on("click", () => {
    $res.text("");
    const termId = $term.val(),
      deptId = $dept.val();
    if (!yearId || !semId || !termId || !deptId) {
      return Swal.fire(
        "Pick all filters",
        "Year/Semester, Term, and Department are required.",
        "warning"
      );
    }
    const f = $file[0].files[0];
    if (!f)
      return Swal.fire(
        "File required",
        "Please choose a .xlsx file",
        "warning"
      );
    if (!/\.xlsx$/i.test(f.name))
      return Swal.fire("Invalid file", "Only .xlsx is accepted", "error");

    const fd = new FormData();
    fd.append("action", "upload");
    fd.append("academic_year_id", yearId);
    fd.append("semester_id", semId);
    fd.append("term_id", termId);
    fd.append("department_id", deptId);
    fd.append("default_duration", $dur.val() || 120);
    // Optional forced shift (only sent if control exists and has a value)
    if ($forceShift.length && $forceShift.val()) {
      fd.append("shift_id", $forceShift.val());
    }
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

        // New: show updated counts too (subjects & events)
        // Backend keys expected:
        //  - subjects_created, subjects_updated
        //  - links_created
        //  - events_created, events_updated
        //  - skipped_rows
        let text = `✅ Upload complete
Subjects:           created ${nz(s.subjects_created)} | updated ${nz(
          s.subjects_updated
        )}
Class links:        created ${nz(s.links_created)}
Exam events:        created ${nz(s.events_created)} | updated ${nz(
          s.events_updated
        )}
Skipped rows:       ${nz(s.skipped_rows)}

Sheets:
`;

        (s.sheets || []).forEach((sh) => {
          // Per-sheet we’ll show created/updated/events/updated
          text +=
            ` - ${sh.name} [prefix ${sh.prefix || "?"}]: ` +
            `rows=${nz(sh.rows)}, ` +
            `subjects created=${nz(sh.created_subjects)}, updated=${nz(
              sh.updated_subjects
            )}, ` +
            `events created=${nz(sh.events)}, updated=${nz(
              sh.events_updated
            )}, ` +
            `linked=${nz(sh.linked)}, skipped=${nz(sh.skipped)}` +
            `${sh.error ? " ⚠ " + sh.error : ""}\n`;
        });

        
        // Swal.fire("Done","Upload finished","success");
        Swal.fire({
          title: "Are you sure?",
          text: "To make overriding this Subject Upload?!",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: "Yes, delete it!",
        }).then((result) => {
          if (result.isConfirmed) {
            Swal.fire("Done", "Upload finished", "success");
            $res.text(text);
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // This code will execute if the user clicks the "Cancel" button or dismisses the dialog
            Swal.fire("Cancelled", "Your imaginary file is safe :)", "error");
          }
        });
      },
      error: function (xhr) {
        $btn.prop("disabled", false).text($btn.data("orig") || "Upload");
        // Try to surface JSON error from server if any
        const msg = xhr?.responseJSON?.data || xhr?.responseText || "Failed";
        Swal.fire("Error", msg, "error");
      },
    });
  });

  function nz(v) {
    return v === 0 || v ? v : 0;
  }
});
