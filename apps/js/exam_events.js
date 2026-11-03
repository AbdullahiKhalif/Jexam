// apps/js/exam_events.js
$(function () {
  const $pair = $("#yearsem"), $term = $("#term");
  const $filterCampus = $("#campus"), $filterShift = $("#shift"), $filterClass = $("#class"), $filterSubject = $("#subject");
  const $tb = $("#tblExamEvents tbody");
  const $mdl = $("#mdlExamEvent");
  const $mdlBulk = $("#mdlBulkEdit");
  
  const $chkSelectAll = $("#chkSelectAll");
  
  const $id = $("#event_id"),
        $class = $("#event_class"),
        $subject = $("#event_subject"),
        $date = $("#event_date"),
        $shift = $("#event_shift_id");

  let yearId = null, semId = null;

  // Load Filter Options
  $.post('../api/campus.php', { action: 'list' }, function(r) {
    if (r?.status) {
      $filterCampus.html('<option value="">-- All Campuses --</option>' + r.data.map(c => `<option value="${c.id}">${c.name}</option>`).join(''));
    }
  }, 'json');

  $.post('../api/shift.php', { action: 'list' }, function(r) {
    if (r?.status) {
      $filterShift.html('<option value="">-- All Shifts --</option>' + r.data.map(s => `<option value="${s.id}">${s.name}</option>`).join(''));
    }
  }, 'json');

  $.post('../api/class.php', { action: 'list' }, function(r) {
    if (r?.status) {
      $filterClass.html('<option value="">-- All Classes --</option>' + r.data.map(c => `<option value="${c.id}">${c.code}</option>`).join(''));
    }
  }, 'json');

  $.post('../api/subject.php', { action: 'list' }, function(r) {
    if (r?.status) {
      $filterSubject.html('<option value="">-- All Subjects --</option>' + r.data.map(s => `<option value="${s.id}">${s.name}</option>`).join(''));
    }
  }, 'json');

  // Load Shifts for modal select dropdown
  $.post('../api/shift.php', { action: 'list' }, function(r) {
    if (r?.status) {
      $shift.html('<option value="">-- No Shift --</option>' + r.data.map(s => `<option value="${s.id}">${s.name} (${s.start_time} - ${s.end_time})</option>`).join(''));
      $("#bulk_shift_id").html('<option value="">-- No Shift --</option>' + r.data.map(s => `<option value="${s.id}">${s.name} (${s.start_time} - ${s.end_time})</option>`).join(''));
    }
  }, 'json');

  // --------- Filters: Year/Sem -> Term ---------
  $.post("../api/academic.php", { action: "listYearSemesterPairsActive" }, function (r) {
    if (r?.status && r.data?.length) {
      $pair.html('<option value="|">-- All / Unfiltered --</option>' + r.data.map(p => `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`).join(''));
      loadTerms();
      loadEvents();
    } else {
      $pair.html('<option value="|">-- No active semesters --</option>');
      $term.html('<option value="">--</option>');
      loadEvents();
    }
  }, "json");

  $pair.on("change", function () {
    [yearId, semId] = ($(this).val() || "|").split("|");
    loadTerms();
    loadEvents();
  });
  
  $term.on("change", loadEvents);
  $filterCampus.on("change", loadEvents);
  $filterShift.on("change", loadEvents);
  $filterClass.on("change", loadEvents);
  $filterSubject.on("change", loadEvents);
  $("#btnRefresh").on("click", loadEvents);

  function loadTerms() {
    if (!semId) {
      $term.html('<option value="">-- All Terms --</option>');
      return;
    }
    $.post("../api/academic.php", { action: "listTermsActive", semester_id: semId }, function (r) {
      $term.html('<option value="">-- All Terms --</option>' + (r?.status ? r.data.map(t => `<option value="${t.id}">${t.name}</option>`).join('') : ''));
      loadEvents();
    }, "json");
  }

  // --------- Data loading ---------
  function loadEvents() {
    $tb.html('<tr><td colspan="9" class="text-muted">Loading...</td></tr>');
    $chkSelectAll.prop('checked', false);
    
    const termId = $term.val() || null;
    const campusId = $filterCampus.val() || null;
    const shiftId = $filterShift.val() || null;
    const classId = $filterClass.val() || null;
    const subjectId = $filterSubject.val() || null;
    
    $.post("../api/exam_event.php", {
      action: "list",
      academic_year_id: yearId || null,
      semester_id: semId || null,
      term_id: termId,
      campus_id: campusId,
      shift_id: shiftId,
      class_id: classId,
      subject_id: subjectId
    }, function (r) {
      $tb.empty();
      if (!r?.status || !r.data?.length) {
        return $tb.html('<tr><td colspan="9" class="text-muted text-center">No exam events found</td></tr>');
      }
      r.data.forEach((e, idx) => {
        $tb.append(`
          <tr>
            <td><input type="checkbox" class="form-check-input chk-row" value="${e.id}"></td>
            <td>${idx + 1}</td>
            <td class="fw-bold">${e.class_code || '-'}</td>
            <td>${e.subject_name || '-'}</td>
            <td>${e.exam_date || '-'}</td>
            <td>${e.shift_name || '-'}</td>
            <td>${e.campus_name || '-'}</td>
            <td>${e.duration_minutes || '120'}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-edit" data-id="${e.id}" data-cls="${String(e.class_code || '').replace(/"/g,"&quot;")}" data-sub="${String(e.subject_name || '').replace(/"/g,"&quot;")}" data-date="${e.exam_date || ''}" data-shift="${e.shift_id || ''}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
                <button class="btn btn-del" data-id="${e.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
              </div>
            </td>
          </tr>
        `);
      });
    }, "json");
  }

  // --------- Modals/Actions ---------
  $tb.on("click", ".btn-edit", function () {
    $id.val($(this).data("id"));
    $class.val($(this).data("cls"));
    $subject.val($(this).data("sub"));
    $date.val($(this).data("date"));
    $shift.val($(this).data("shift"));
    $mdl.modal("show");
  });

  $("#frmExamEvent").on("submit", function (e) {
    e.preventDefault();
    const payload = {
      action: "update",
      id: $id.val(),
      exam_date: $date.val(),
      shift_id: $shift.val() || null
    };

    $.post("../api/exam_event.php", payload, function (r) {
      if (!r.status) return Swal.fire("Error", r.data, "error");
      $mdl.modal("hide");
      loadEvents();
      Swal.fire("Success", "Exam event updated", "success");
    }, "json");
  });

  $tb.on("click", ".btn-del", function () {
    const id = $(this).data("id");
    Swal.fire({ title: "Delete Exam Event?", text: "This will remove the assigned exam date/shift for this class.", icon: "warning", showCancelButton: true }).then(s => {
      if (!s.isConfirmed) return;
      $.post("../api/exam_event.php", { action: "delete", id }, function (r) {
        if (r.status) {
          loadEvents();
          Swal.fire("Deleted!", "Exam event has been deleted.", "success");
        } else {
          Swal.fire("Error", "Could not delete exam event.", "error");
        }
      }, "json");
    });
  });

  // --------- Bulk Actions ---------
  $chkSelectAll.on("change", function () {
    $(".chk-row").prop("checked", $(this).is(":checked"));
  });

  function getSelectedIds() {
    return $(".chk-row:checked").map(function () { return $(this).val(); }).get();
  }

  $("#btnBulkEdit").on("click", function () {
    const ids = getSelectedIds();
    if (ids.length === 0) return Swal.fire("Warning", "Please select at least one exam event.", "warning");
    
    $("#bulkCount").text(ids.length);
    $("#bulk_date").val("");
    $("#bulk_shift_id").val("");
    $mdlBulk.modal("show");
  });

  $("#frmBulkEdit").on("submit", function (e) {
    e.preventDefault();
    const ids = getSelectedIds();
    if (ids.length === 0) return;

    const payload = {
      action: "bulk_update",
      ids: ids,
      exam_date: $("#bulk_date").val(),
      shift_id: $("#bulk_shift_id").val() || null
    };

    $.post("../api/exam_event.php", payload, function (r) {
      if (!r.status) return Swal.fire("Error", r.data, "error");
      $mdlBulk.modal("hide");
      loadEvents();
      Swal.fire("Success", `Successfully updated ${ids.length} exam events.`, "success");
    }, "json");
  });

  $("#btnBulkDelete").on("click", function () {
    const ids = getSelectedIds();
    if (ids.length === 0) return Swal.fire("Warning", "Please select at least one exam event.", "warning");
    
    Swal.fire({ 
      title: "Bulk Delete Exam Events?", 
      text: `You are about to delete ${ids.length} exam events. This action cannot be undone.`, 
      icon: "warning", 
      showCancelButton: true,
      confirmButtonText: "Yes, delete them!"
    }).then(s => {
      if (!s.isConfirmed) return;
      
      $.post("../api/exam_event.php", { action: "bulk_delete", ids: ids }, function (r) {
        if (r.status) {
          loadEvents();
          Swal.fire("Deleted!", `Successfully deleted ${ids.length} exam events.`, "success");
        } else {
          Swal.fire("Error", "Could not delete exam events.", "error");
        }
      }, "json");
    });
  });

});
