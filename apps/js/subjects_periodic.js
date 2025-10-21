// js/subjects_periodic.js
$(function () {
  const $pair = $("#yearsem"), $term = $("#term");
  const $tb = $("#tblSubject tbody");
  const $mdlEl = document.getElementById("mdlSubject");

  const $id = $("#subject_id"),
        $name = $("#subject_name"),
        $dept = $("#subject_department_id"),
        $level = $("#subject_level"),
        $examDate = $("#subject_default_exam_date"),
        $cohortList = $("#cohortList");

  let yearId=null, semId=null, modal=null, deptOptionsLoaded=false;

  /* ----------------- Modal helpers (Bootstrap 5) ----------------- */
  function openModal(){ if(!modal && window.bootstrap?.Modal){ modal=new bootstrap.Modal($mdlEl,{backdrop:'static'}); } modal?modal.show():$("#mdlSubject").modal('show'); }
  function closeModal(){ modal?modal.hide():$("#mdlSubject").modal('hide'); }

  /* ----------------- Department options loader ----------------- */
  function loadDepartmentOptions() {
    // Prefer the dedicated departments API
    return $.post("../api/department.php", { action: "listOptions" }, null, "json")
      .then(r => {
        if (r?.status) {
          const opts = ['<option value="">— All Departments (General) —</option>']
            .concat((r.data||[]).map(d => `<option value="${d.id}">${esc(d.name)}</option>`));
          $dept.html(opts.join(''));
          deptOptionsLoaded = true;
          // Keep the Level consistent with selection
          updateLevelState();
        } else {
          throw new Error(r?.data || "Failed to load departments");
        }
      })
      .catch(() => {
        // Fallback to subject.php if you didn’t add department.php
        return $.post("../api/subject.php", { action: "listDepartments" }, null, "json")
          .then(r => {
            if (r?.status) {
              const opts = ['<option value="">— All Departments (General) —</option>']
                .concat((r.data||[]).map(d => `<option value="${d.id}">${esc(d.name)}</option>`));
              $dept.html(opts.join(''));
              deptOptionsLoaded = true;
              updateLevelState();
            } else {
              $dept.html('<option value="">(no departments)</option>');
            }
          })
          .catch(() => $dept.html('<option value="">(failed to load)</option>'));
      });
  }

  /* ----------------- Enforce Level vs Department ----------------- */
  function updateLevelState() {
    const hasDept = !!$dept.val();
    if (hasDept) {
      // Specific when a department is chosen
      $level.val("Specific").prop("disabled", true);
    } else {
      // General when no department (applies to all)
      $level.val("General").prop("disabled", true);
    }
  }

  $dept.on("change", function(){
    updateLevelState();
    loadCohorts(); // cohorts only make sense when a department is selected
  });

  /* ----------------- Year/Sem/Term loaders ----------------- */
  $.post("../api/academic.php", { action: "listYearSemesterPairsActive" }, function (r) {
    if (r?.status && r.data?.length) {
      $pair.html(r.data.map(p => `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`));
      [yearId, semId] = ($pair.val() || "|").split("|");
      loadTerms(semId);
      loadDepartmentOptions(); // load depts early so the modal has options
      loadSubjects();
    } else {
      $pair.html('<option value="">-- No active semesters --</option>');
      $term.html('<option value="">--</option>');
    }
  }, "json");

  $pair.on("change", function () {
    [yearId, semId] = ($(this).val() || "|").split("|");
    loadTerms(semId);
    loadSubjects();
  });
  $term.on("change", loadSubjects);

  function loadTerms(semester_id) {
    if (!semester_id) { $term.html('<option value="">--</option>'); return; }
    $.post("../api/academic.php", { action: "listTermsActive", semester_id }, function (r) {
      $term.html(r?.status ? r.data.map(t => `<option value="${t.id}">${t.name}</option>`) : '<option value="">--</option>');
    }, "json");
  }

  /* ----------------- Subjects table ----------------- */
  function loadSubjects() {
    const payload = { action: "list", academic_year_id: yearId, semester_id: semId, term_id: $term.val() || "" };
    $tb.html('<tr><td colspan="6" class="text-muted">Loading…</td></tr>');
    $.post("../api/subject.php", payload, function (r) {
      $tb.html("");
      if (!r?.status || !r.data?.length) { $tb.append('<tr><td colspan="6" class="text-muted">No subjects</td></tr>'); return; }
      r.data.forEach((s, i) => {
        $tb.append(`
          <tr>
            <td>${i + 1}</td>
            <td>${esc(s.name)}</td>
            <td>${esc(s.department || "-")}</td>
            <td>${esc(s.level)}</td>
            <td>${esc(s.year_name || "-" )} / ${esc(s.semester_name || "-" )} / ${esc(s.term_name || "-" )}
                ${s.default_exam_date ? `<div class="small text-muted">Exam: ${esc(s.default_exam_date)}</div>` : ''}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-edit"
                        data-id="${s.id}"
                        data-name="${attr(s.name)}"
                        data-dept="${s.department_id||''}"
                        data-level="${attr(s.level)}"
                        data-exam="${s.default_exam_date||''}">Edit</button>
                <button class="btn btn-outline-danger btn-del" data-id="${s.id}">Delete</button>
              </div>
            </td>
          </tr>`);
      });
    }, "json").fail(()=> $tb.html('<tr><td colspan="6" class="text-danger">Load failed</td></tr>'));
  }

  /* ----------------- Modal open / reset ----------------- */
  $("#btnAddSubject").on("click", () => {
    resetForm();
    // ensure department options loaded before opening
    const ready = deptOptionsLoaded ? Promise.resolve() : loadDepartmentOptions();
    ready.then(()=> { updateLevelState(); openModal(); });
  });

  function resetForm(){
    $id.val(""); $name.val("");
    $dept.val(""); // no department → General
    $level.val("General").prop("disabled", true);
    $examDate.val("");
    $cohortList.html('<div class="text-muted small">Pick a department to load cohorts…</div>');
  }

  /* ----------------- Cohorts loader ----------------- */
  function loadCohorts(){
    const dep = $dept.val();
    const termId = $term.val();
    if(!dep || !yearId || !semId || !termId){
      $cohortList.html('<div class="text-muted small">Select department & period first</div>'); return;
    }
    const payload = { action:"listCohortsByDepartment", department_id: dep, academic_year_id: yearId, semester_id: semId, term_id: termId };
    $.post("../api/class.php", payload, function(r){
      if(!r?.status){ $cohortList.html('<div class="text-danger small">Failed to load cohorts</div>'); return; }
      const rows = r.data || [];
      if(!rows.length){ $cohortList.html('<div class="text-muted small">No cohorts found</div>'); return; }
      const html = rows.map(co=>{
        const ids = co.class_ids.join(',');
        const tip = (co.sample_codes||[]).join(', ');
        return `<label class="d-flex align-items-center gap-2 mb-1">
          <input type="checkbox" class="form-check-input me-2 cohort-check" data-class-ids="${ids}">
          <span><b>${esc(co.label)}</b> <span class="text-muted">(${co.count})</span> <span class="text-muted small">${esc(tip)}</span></span>
        </label>`;
      }).join('');
      $cohortList.html(html);
    },'json');
  }

  /* ----------------- Save (create/update) + register cohorts ----------------- */
  $("#frmSubject").on("submit", function(e){
    e.preventDefault();
    const termId = $term.val();
    if(!yearId || !semId || !termId) return sweet("Pick a period","Select Year / Semester / Term first","warning");

    // Level is enforced by updateLevelState()
    const payload = ($id.val()==="")
      ? { action:"create", name:$name.val(), department_id:$dept.val()||null, level:$level.val(),
          academic_year_id:yearId, semester_id:semId, term_id:termId, default_exam_date:$examDate.val()||null }
      : { action:"update", id:$id.val(), name:$name.val(), department_id:$dept.val()||null, level:$level.val(),
          academic_year_id:yearId, semester_id:semId, term_id:termId, default_exam_date:$examDate.val()||null };

    $.post("../api/subject.php", payload, function(r){
      if(!r?.status){ sweet("Error", r?.data||"Save failed", "error"); return; }

      // Gather class IDs from checked cohorts
      const ids = [];
      $cohortList.find(".cohort-check:checked").each(function(){
        const csv = $(this).data('class-ids') || '';
        csv.split(',').forEach(x=>{ const v=parseInt(x,10); if(v) ids.push(v); });
      });

      if(ids.length===0){ // just save subject
        closeModal(); sweet("Saved","Subject saved","success"); loadSubjects(); return;
      }

      const subject_id = ($id.val()==="") ? r.data.id : $id.val();
      $.post("../api/subject.php", {
        action:"assignSubjectToClasses",
        subject_id, academic_year_id:yearId, semester_id:semId, term_id:termId,
        class_ids: ids.join(',')
      }, function(rr){
        closeModal();
        if(rr?.status) sweet("Saved","Subject saved and registered to selected cohorts","success");
        else sweet("Partial","Subject saved, but cohort registration failed: "+(rr?.data||''),"warning");
        loadSubjects();
      }, 'json');

    }, 'json').fail((xhr)=> sweet("Error", xhr?.responseText||"Save failed", "error"));
  });

  /* ----------------- Edit / Delete ----------------- */
  $("#tblSubject").on("click",".btn-edit", function(){
    // ensure options exist before filling/locking level
    const ready = deptOptionsLoaded ? Promise.resolve() : loadDepartmentOptions();
    ready.then(()=>{
      $id.val($(this).data("id"));
      $name.val($(this).data("name"));
      $dept.val($(this).data("dept") || "");
      // Enforce the rule: General if no dept, Specific if dept selected
      updateLevelState();
      // exam date
      $examDate.val($(this).data("exam") || "");
      openModal();
      loadCohorts();
    });
  });

  $("#tblSubject").on("click",".btn-del", function(){
    const id=$(this).data("id");
    ask("Delete Subject?","This cannot be undone.","warning").then(ok=>{
      if(!ok) return;
      $.post("../api/subject.php",{action:"delete", id}, function(r){
        if(r?.status){ sweet("Deleted","Subject removed","success"); loadSubjects(); }
        else sweet("Error", r?.data||"Delete failed","error");
      },'json');
    });
  });

  /* ----------------- helpers ----------------- */
  function sweet(t,x,i){ if(window.Swal?.fire) return Swal.fire(t,x,i); alert(`${t}\n${x||''}`); }
  function ask(t,x,i){ if(window.Swal?.fire) return Swal.fire({title:t,text:x,icon:i,showCancelButton:true}).then(r=>r.isConfirmed); return Promise.resolve(confirm(`${t}\n${x||''}`)); }
  function esc(s){ return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function attr(s){ return esc(s).replace(/"/g,'&quot;'); }
});
