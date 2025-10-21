// js/classes_periodic.js
$(function () {
  const $pair = $("#yearsem");     // "<year_id>|<semester_id>"
  const $term = $("#term");
  const $tb   = $("#tblClass tbody");
  const modalEl = document.getElementById("mdlClass");

  // Bootstrap 5 modal instance (with safe fallback)
  let mdlClass = null;
  function ensureModal(){
    try {
      if (!mdlClass && window.bootstrap?.Modal) {
        mdlClass = new bootstrap.Modal(modalEl, { backdrop: 'static' });
      }
    } catch(e) {}
  }
  function openModal(){
    ensureModal();
    if (mdlClass) mdlClass.show();
    else if ($("#mdlClass").modal) $("#mdlClass").modal("show"); // fallback if using BS4
  }
  function closeModal(){
    if (mdlClass) mdlClass.hide();
    else if ($("#mdlClass").modal) $("#mdlClass").modal("hide");
  }

  // Form fields
  const $id    = $("#class_id");
  const $code  = $("#class_code");
  const $dept  = $("#class_department_id");
  const $level = $("#class_level");

  let yearId = null, semId = null;

  // 1) Year+Semester (active) then Terms (active)
  $.post("../api/academic.php", { action: "listYearSemesterPairsActive" }, function (r) {
    if (r?.status && r.data?.length) {
      $pair.html(r.data.map(p => `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`));
      [yearId, semId] = ($pair.val() || "|").split("|");
      loadTerms(semId);
      loadClasses();
    } else {
      $pair.html('<option value="">-- No active semesters --</option>');
      $term.html('<option value="">--</option>');
    }
  }, "json");

  $pair.on("change", function () {
    [yearId, semId] = ($(this).val() || "|").split("|");
    loadTerms(semId);
    loadClasses();
  });

  $term.on("change", loadClasses);

  function loadTerms(semester_id) {
    if (!semester_id) { $term.html('<option value="">--</option>'); return; }
    $.post("../api/academic.php", { action: "listTermsActive", semester_id }, function (r) {
      if (r?.status && r.data?.length) {
        $term.html(r.data.map(t => `<option value="${t.id}">${t.name}</option>`));
      } else {
        $term.html('<option value="">-- No active terms --</option>');
      }
    }, "json");
  }

  // 2) List classes (period-aware)
  function loadClasses() {
    const payload = { action: "list", academic_year_id: yearId, semester_id: semId, term_id: $term.val() || "" };
    $tb.html(`<tr><td colspan="6" class="text-muted">Loading…</td></tr>`);
    $.post("../api/class.php", payload, function (r) {
      $tb.html("");
      if (!r?.status || !r.data?.length) {
        $tb.append('<tr><td colspan="6" class="text-muted">No classes</td></tr>');
        return;
      }
      r.data.forEach((c, i) => {
        $tb.append(`
          <tr>
            <td>${i + 1}</td>
            <td>${escapeHtml(c.code)}</td>
            <td>${escapeHtml(c.department || "-")}</td>
            <td>${escapeHtml(c.level || "-")}</td>
            <td>${escapeHtml(c.year_name || "-" )} / ${escapeHtml(c.semester_name || "-" )} / ${escapeHtml(c.term_name || "-" )}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-edit"
                  data-id="${c.id}"
                  data-code="${escapeAttr(c.code)}"
                  data-dept="${c.department_id||''}"
                  data-level="${escapeAttr(c.level||'')}">Edit</button>
                <button class="btn btn-outline-danger btn-del" data-id="${c.id}">Delete</button>
              </div>
            </td>
          </tr>`);
      });
    }, "json").fail(()=> {
      $tb.html('<tr><td colspan="6" class="text-danger">Failed to load classes</td></tr>');
    });
  }

  // 3) Add Class → open modal
  $("#btnAddClass").on("click", () => {
    // clear form
    $id.val(""); $code.val(""); $dept.val(""); $level.val("");
    openModal();
  });

  // 4) Save (Create/Update)
  $("#frmClass").on("submit", function (e) {
    e.preventDefault();
    const termId = $term.val();
    if (!yearId || !semId || !termId) {
      return sweet("Pick a period", "Select Year / Semester / Term first", "warning");
    }
    const isCreate = ($id.val() === "");
    const payload = isCreate
      ? { action: "create",
          code: $code.val(), department_id: $dept.val() || null, level: $level.val() || null,
          academic_year_id: yearId, semester_id: semId, term_id: termId }
      : { action: "update",
          id: $id.val(),
          code: $code.val(), department_id: $dept.val() || null, level: $level.val() || null,
          academic_year_id: yearId, semester_id: semId, term_id: termId };

    $.post("../api/class.php", payload, function (r) {
      if (r?.status) {
        closeModal();
        sweet(isCreate ? "Created" : "Updated", "Saved successfully", "success");
        loadClasses();
      } else {
        sweet("Error", r?.data || "Save failed", "error");
      }
    }, "json").fail((xhr)=>{
      sweet("Error", xhr?.responseText || "Save failed", "error");
    });
  });

  // 5) Edit
  $("#tblClass").on("click", ".btn-edit", function () {
    $id.val($(this).data("id"));
    $code.val($(this).data("code"));
    $dept.val($(this).data("dept"));
    $level.val($(this).data("level"));
    openModal();
  });

  // 6) Delete
  $("#tblClass").on("click", ".btn-del", function () {
    const id = $(this).data("id");
    ask("Delete Class?", "This cannot be undone. Continue?", "warning").then((ok)=>{
      if (!ok) return;
      $.post("../api/class.php", { action: "delete", id }, function (r) {
        if (r?.status) {
          sweet("Deleted", "Class removed", "success");
          loadClasses();
        } else {
          sweet("Error", r?.data || "Delete failed", "error");
        }
      }, "json").fail((xhr)=> sweet("Error", xhr?.responseText || "Delete failed", "error"));
    });
  });

  /* ---------- helpers ---------- */
  function sweet(title, text, icon){
    if (window.Swal?.fire) return Swal.fire(title, text, icon);
    alert(`${title}\n${text || ''}`);
  }
  function ask(title, text, icon){
    if (window.Swal?.fire){
      return Swal.fire({title, text, icon, showCancelButton:true}).then(r=>r.isConfirmed);
    }
    return Promise.resolve(confirm(`${title}\n${text||''}`));
  }
  function escapeHtml(s){
    return String(s ?? "").replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }
  function escapeAttr(s){ return escapeHtml(s).replace(/"/g,'&quot;'); }
});
