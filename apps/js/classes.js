// apps/js/classes.js
$(function () {
  const $pair = $("#yearsem"), $term = $("#term");
  const $tb = $("#tblClass tbody");
  const $mdl = $("#mdlClass");
  
  const $id = $("#class_id"),
        $code = $("#class_code"),
        $dept = $("#class_department_id"),
        $level = $("#class_level");

  let yearId = null, semId = null;

  // Load Departments for select dropdown
  $.post('../api/subject.php', { action: 'listDepartments' }, function(r) {
    if (r?.status) {
      $dept.html('<option value="">-- None --</option>' + r.data.map(d => `<option value="${d.id}">${d.name}</option>`).join(''));
    }
  }, 'json');

  // --------- Filters: Year/Sem -> Term ---------
  $.post("../api/academic.php", { action: "listYearSemesterPairsActive" }, function (r) {
    if (r?.status && r.data?.length) {
      // Append a generic "All periods" option
      $pair.html('<option value="|">-- All / Unfiltered --</option>' + r.data.map(p => `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`).join(''));
      loadTerms();
      loadClasses();
    } else {
      $pair.html('<option value="|">-- No active semesters --</option>');
      $term.html('<option value="">--</option>');
      loadClasses();
    }
  }, "json");

  $pair.on("change", function () {
    [yearId, semId] = ($(this).val() || "|").split("|");
    loadTerms();
    loadClasses();
  });
  
  $term.on("change", loadClasses);

  function loadTerms() {
    if (!semId) {
      $term.html('<option value="">-- All Terms --</option>');
      return;
    }
    $.post("../api/academic.php", { action: "listTermsActive", semester_id: semId }, function (r) {
      $term.html('<option value="">-- All Terms --</option>' + (r?.status ? r.data.map(t => `<option value="${t.id}">${t.name}</option>`).join('') : ''));
      loadClasses();
    }, "json");
  }

  // --------- Data loading ---------
  function loadClasses() {
    $tb.html('<tr><td colspan="6" class="text-muted">Loading...</td></tr>');
    const termId = $term.val() || null;
    
    $.post("../api/class.php", {
      action: "list",
      academic_year_id: yearId || null,
      semester_id: semId || null,
      term_id: termId
    }, function (r) {
      $tb.empty();
      if (!r?.status || !r.data?.length) {
        return $tb.html('<tr><td colspan="6" class="text-muted">No classes found</td></tr>');
      }
      r.data.forEach((c, idx) => {
        $tb.append(`
          <tr>
            <td>${idx + 1}</td>
            <td class="fw-bold">${String(c.code).replace(/</g,"&lt;")}</td>
            <td>${c.department || '-'}</td>
            <td>${c.level || '-'}</td>
            <td>${c.year_name || '-'} ${c.semester_name || ''} ${c.term_name || ''}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-edit" data-id="${c.id}" data-code="${String(c.code).replace(/"/g,"&quot;")}" data-dept="${c.department_id || ''}" data-level="${c.level || ''}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
                <button class="btn btn-del" data-id="${c.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
              </div>
            </td>
          </tr>
        `);
      });
    }, "json");
  }

  // --------- Modals/Actions ---------
  $("#btnAddClass").on("click", function () {
    $id.val(""); 
    $code.val(""); 
    $level.val(""); 
    $dept.val("");
    $mdl.modal("show");
  });

  $tb.on("click", ".btn-edit", function () {
    $id.val($(this).data("id"));
    $code.val($(this).data("code"));
    $level.val($(this).data("level"));
    $dept.val($(this).data("dept"));
    $mdl.modal("show");
  });

  $("#frmClass").on("submit", function (e) {
    e.preventDefault();
    const payload = {
      action: $id.val() === "" ? "create" : "update",
      id: $id.val(),
      code: $code.val(),
      department_id: $dept.val() || null,
      level: $level.val() || null,
      academic_year_id: yearId || null,
      semester_id: semId || null,
      term_id: $term.val() || null
    };

    $.post("../api/class.php", payload, function (r) {
      if (!r.status) return Swal.fire("Error", r.data, "error");
      $mdl.modal("hide");
      loadClasses();
      Swal.fire("Success", "Class saved successfully", "success");
    }, "json");
  });

  $tb.on("click", ".btn-del", function () {
    const id = $(this).data("id");
    Swal.fire({ title: "Delete Class?", icon: "warning", showCancelButton: true }).then(s => {
      if (!s.isConfirmed) return;
      $.post("../api/class.php", { action: "delete", id }, function (r) {
        if (r.status) {
          loadClasses();
          Swal.fire("Deleted!", "Class has been deleted.", "success");
        } else {
          Swal.fire("Error", "Could not delete class. It might be in use elsewhere.", "error");
        }
      }, "json");
    });
  });
});
