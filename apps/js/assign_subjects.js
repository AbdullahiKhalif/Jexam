$(function () {
  const $pair = $("#yearsem"),
    $term = $("#term"),
    $dept = $("#department"),
    $campus = $("#campus"),
    $shift = $("#shift"),
    $classList = $("#classList"),
    $btn = $("#btnAssign"),
    $res = $("#result");

  const $subject = $("#subject_name"),
    $date = $("#assigned_date"),
    $dur = $("#duration"),
    $level = $("#level");

  let yearId = null,
    semId = null;

  // Load Year/Sem pairs (active)
  $.post(
    "../api/academic.php",
    { action: "listYearSemesterPairsActive" },
    (r) => {
      if (r?.status && Array.isArray(r.data) && r.data.length) {
        $pair.html(
          r.data.map(
            (p) =>
              `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`
          )
        );
        [yearId, semId] = ($pair.val() || "|").split("|");
        loadTerms();
        loadDepartments();
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

  // Load Terms based on Semester
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

  // Load Departments
  function loadDepartments() {
    $.post(
      "../api/department.php",
      { action: "list" },
      (r) => {
        if (r?.status && Array.isArray(r.data)) {
          $dept.html(
            r.data.map((d) => `<option value="${d.id}">${esc(d.name)}</option>`)
          );
          loadClasses(); // Load classes immediately after departments
        }
      },
      "json"
    );
  }

function loadClasses() {
    const dept_id = $dept.val(); // Get the department ID from the select element
    $classList.html('<div class="text-muted">Loading classes…</div>'); // Show loading text

    // Make an AJAX request to the PHP backend to get the classes for the selected department
    $.post("../api/assign_subjects.php", { action: "listClassesByDepartment", department_id: dept_id }, r => {
    console.log("Server response:", r);  // Debugging line

    if (!r?.status) {
        $classList.html('<div class="text-danger">Failed to load classes</div>');
        return;
    }

    if (!Array.isArray(r.data) || r.data.length === 0) {
        $classList.html('<div class="text-muted">No active classes found for this department.</div>');
        return;
    }

    const html = r.data.map(c => `
        <label class="d-flex align-items-center border rounded p-2 mb-2">
            <input type="checkbox" class="form-check-input me-2 cls" value="${c.id}">
            <span><strong>${esc(c.code)}</strong> <span class="text-muted">${esc(c.name || '')}</span></span>
        </label>
    `).join("");

    $classList.html(html);
}, "json").fail(function(xhr, status, error) {
    console.error("Error loading classes:", error);  // Debugging line
    $classList.html('<div class="text-danger">Error loading classes.</div>');
});

}


  $dept.on("change", loadClasses);

  // Load Campuses
  function loadCampuses() {
    $.post(
      "../api/catalog.php",
      { action: "listCampuses" },
      (r) => {
        $campus.html(
          r?.status && Array.isArray(r.data)
            ? r.data.map(
                (c) => `<option value="${c.id}">${esc(c.name)}</option>`
              )
            : '<option value="">--</option>'
        );
        loadShifts();
      },
      "json"
    );
  }

  // Load Shifts based on Campus
  function loadShifts() {
    $.post(
      "../api/assign_subjects.php",
      { action: "listShiftsByCampus", campus_id: $campus.val() },
      (r) => {
        $shift.html(
          r?.status && Array.isArray(r.data)
            ? r.data.map(
                (s) =>
                  `<option value="${s.id}">${esc(s.name)} (${esc(
                    s.start_time
                  )} - ${esc(s.end_time)})</option>`
              )
            : '<option value="">--</option>'
        );
      },
      "json"
    );
  }

  // Handle subject assignment on button click
  $btn.on("click", () => {
    $res.text("");

    const termId = $term.val(),
      deptId = $dept.val(),
      campusId = $campus.val(),
      shiftId = $shift.val(),
      subjectName = $subject.val().trim(),
      assignedDate = ($date.val() || "").trim(),
      duration = parseInt($dur.val(), 10) || 120,
      level = $level.val() || "Specific";

    const classIds = $(".cls:checked")
      .toArray()
      .map((i) => $(i).val());

    if (
      !yearId ||
      !semId ||
      !termId ||
      !deptId ||
      !campusId ||
      !shiftId ||
      !subjectName ||
      classIds.length === 0
    ) {
      return alert("Please fill all fields and select at least one class.");
    }

    const payload = {
      action: "assignSubjects",
      academic_year_id: yearId,
      semester_id: semId,
      term_id: termId,
      department_id: deptId,
      campus_id: campusId,
      shift_id: shiftId,
      class_ids: classIds,
      subject_name: subjectName,
      assigned_date: assignedDate,
      duration: duration,
      level: level,
    };

    $btn.prop("disabled", true).data("txt", $btn.text()).text("Assigning…");

    $.post(
      "../api/assign_subjects.php",
      payload,
      (r) => {
        $btn.prop("disabled", false).text($btn.data("txt") || "Assign");
        if (!r?.status) {
          $res.text(r?.data || "Failed");
          return;
        }
        const s = r.data || {};
        $res.text(
          `✅ Done
Classes assigned: ${s.classes_assigned || 0}
Exam events:     created ${s.events_created || 0} | updated ${
            s.events_updated || 0
          }`
        );
      },
      "json"
    ).fail((xhr) => {
      $btn.prop("disabled", false).text($btn.data("txt") || "Assign");
      $res.text(xhr?.responseText || "Failed");
    });
  });

  // Utility function to escape HTML
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
