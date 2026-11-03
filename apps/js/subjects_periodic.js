// apps/js/subjects_periodic.js
$(function () {
  const $pair = $("#yearsem"), $term = $("#term");
  const $tb = $("#tblSubject tbody");
  const $mdl = $("#mdlSubject");
  
  const $id = $("#subject_id"),
        $name = $("#subject_name"),
        $dept = $("#subject_department_id"),
        $level = $("#subject_level"),
        $examDate = $("#subject_default_exam_date"),
        $cohortList = $("#cohortList");

  let yearId = null, semId = null;

  // --------- Filters: Year/Sem -> Term ---------
  $.post("../api/academic.php", { action: "listYearSemesterPairsActive" }, function (r) {
    if (r?.status && r.data?.length) {
      $pair.html(r.data.map(p => `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`));
      [yearId, semId] = ($pair.val() || "|").split("|");
      loadTerms();
    } else {
      $pair.html('<option value="">-- No active semesters --</option>');
      $term.html('<option value="">--</option>');
    }
  }, "json");

  $pair.on("change", function () {
    [yearId, semId] = ($(this).val() || "|").split("|");
    loadTerms();
    loadSubjects();
  });
  $term.on("change", loadSubjects);

  function loadTerms() {
    $.post("../api/academic.php", { action: "listTermsActive", semester_id: semId }, function (r) {
      $term.html(r?.status ? r.data.map(t => `<option value="${t.id}">${t.name}</option>`) : '<option value="">--</option>');
      loadSubjects();
    }, "json");
  }

  // --------- Data loading ---------
  function loadSubjects() {
    $tb.html('<tr><td colspan="6" class="text-muted">Loading...</td></tr>');
    const termId = $term.val() || null;
    if (!yearId || !semId) return $tb.html('<tr><td colspan="6" class="text-muted">Select Academic Period</td></tr>');

    $.post("../api/subject.php", {
      action: "list", academic_year_id: yearId, semester_id: semId, term_id: termId
    }, function (r) {
      $tb.empty();
      if (!r?.status || !r.data?.length) {
        return $tb.html('<tr><td colspan="6" class="text-muted">No subjects found</td></tr>');
      }
      r.data.forEach((s, idx) => {
        $tb.append(`
          <tr>
            <td>${idx + 1}</td>
            <td>${String(s.name).replace(/</g,"&lt;")}</td>
            <td>${s.department || ''}</td>
            <td>${s.level}</td>
            <td>${s.year_name || ''} ${s.semester_name || ''} ${s.term_name || ''}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-edit" data-id="${s.id}" data-name="${String(s.name).replace(/"/g,"&quot;")}" data-dept="${s.department_id || ''}" data-level="${s.level}" data-date="${s.default_exam_date || ''}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
                <button class="btn btn-del" data-id="${s.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
              </div>
            </td>
          </tr>
        `);
      });
    }, "json");
  }

  // --------- Department -> Cohorts ---------
  $dept.on("change", function () {
    const dept_id = $(this).val();
    if (!dept_id) return $cohortList.html('<div class="text-muted small">Pick a department...</div>');

    $cohortList.html('<div class="text-muted small">Loading cohorts...</div>');
    $.post("../api/class.php", {
      action: "listCohortsByDepartment",
      department_id: dept_id,
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val()
    }, function (r) {
      if (!r?.status || !r.data?.length) return $cohortList.html('<div class="text-muted small">No cohorts found in this period.</div>');
      let html = '';
      r.data.forEach(c => {
        html += `<div class="form-check form-check-inline mt-1">
                   <input class="form-check-input chk-cohort" type="checkbox" id="coh_${c.label}" value="${c.class_ids.join(',')}" checked>
                   <label class="form-check-label" for="coh_${c.label}">${c.label} (${c.count})</label>
                 </div>`;
      });
      $cohortList.html(html);
    }, "json");
  });

  // --------- Modals/Actions ---------
  $("#btnAddSubject").on("click", function () {
    $id.val(""); $name.val(""); $examDate.val("");
    $dept.val("").trigger("change");
    $mdl.modal("show");
  });

  $tb.on("click", ".btn-edit", function () {
    $id.val($(this).data("id"));
    $name.val($(this).data("name"));
    $level.val($(this).data("level"));
    $examDate.val($(this).data("date"));
    $dept.val($(this).data("dept")).trigger("change");
    $mdl.modal("show");
  });

  $("#frmSubject").on("submit", function (e) {
    e.preventDefault();
    const payload = {
      action: $id.val() === "" ? "create" : "update",
      id: $id.val(),
      name: $name.val(),
      department_id: $dept.val(),
      level: $level.val(),
      default_exam_date: $examDate.val(),
      academic_year_id: yearId,
      semester_id: semId,
      term_id: $term.val() || null
    };

    $.post("../api/subject.php", payload, function (r) {
      if (!r.status) return Swal.fire("Error", r.data, "error");
      
      const subjId = $id.val() === "" ? r.data.id : $id.val();
      const $checkedCohorts = $(".chk-cohort:checked");
      
      if ($checkedCohorts.length > 0) {
        let classIds = [];
        $checkedCohorts.each(function() {
          let ids = $(this).val().split(',');
          classIds = classIds.concat(ids);
        });
        
        $.post("../api/subject.php", {
          action: "assignSubjectToClasses",
          subject_id: subjId,
          academic_year_id: yearId,
          semester_id: semId,
          term_id: $term.val() || 0,
          class_ids: classIds.join(',')
        }, function(assignRes) {
          $mdl.modal("hide");
          loadSubjects();
          Swal.fire("Success", "Subject saved and assigned to " + (assignRes.data?.assigned || 0) + " class(es)", "success");
        }, "json");
      } else {
        $mdl.modal("hide");
        loadSubjects();
        Swal.fire("Success", "Subject saved (No cohorts assigned)", "success");
      }
    }, "json");
  });

  $tb.on("click", ".btn-del", function () {
    const id = $(this).data("id");
    Swal.fire({ title: "Delete subject?", icon: "warning", showCancelButton: true }).then(s => {
      if (!s.isConfirmed) return;
      $.post("../api/subject.php", { action: "delete", id }, function (r) {
        if (r.status) loadSubjects(); else Swal.fire("Error", r.data, "error");
      }, "json");
    });
  });
});
