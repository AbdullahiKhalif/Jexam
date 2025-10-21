// apps/js/allocations_upload.js
$(function(){
  console.log('[allocations_upload] JS loaded');
  const $pair=$("#yearsem"), $term=$("#term"), $campus=$("#campus"), $shift=$("#shift");
  const $frm=$("#frmUpload"), $file=$("#file"), $res=$("#result"), $btn=$("#btnUpload");

  // hidden inputs for graceful fallback
  const $hidYear=$("#hid_year"), $hidSem=$("#hid_sem"), $hidTerm=$("#hid_term"),
        $hidCampus=$("#hid_campus"), $hidShift=$("#hid_shift");

  let yearId=null, semId=null;

  // Load Year/Sem, Term, Campus, Shift
  $.post("../api/academic.php",{action:"listYearSemesterPairsActive"}, r=>{
    if(r?.status && r.data?.length){
      $pair.html(r.data.map(p=>`<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`));
      [yearId,semId]=($pair.val()||"|").split("|"); syncHidden(); loadTerms(); loadCampuses();
    }else{
      $pair.html('<option value="">--</option>'); $term.html('<option value="">--</option>');
    }
  },'json');

  $pair.on("change", ()=>{ [yearId,semId]=($pair.val()||"|").split("|"); syncHidden(); loadTerms(); });
  $term.on("change", syncHidden);
  $campus.on("change", ()=>{ loadShifts(); syncHidden(); });
  $shift.on("change", syncHidden);

  function loadTerms(){
    $.post("../api/academic.php",{action:"listTermsActive", semester_id:semId}, r=>{
      $term.html(r?.status? r.data.map(t=>`<option value="${t.id}">${t.name}</option>`):'<option></option>');
      syncHidden();
    },'json');
  }
  function loadCampuses(){
    $.post("../api/catalog.php",{action:"listCampuses"}, r=>{
      $campus.html(r?.status? r.data.map(c=>`<option value="${c.id}">${c.name}</option>`):'<option></option>');
      loadShifts();
      syncHidden();
    },'json');
  }
  function loadShifts(){
    $.post("../api/catalog.php",{action:"listShifts", campus_id:$campus.val()}, r=>{
      $shift.html(r?.status? r.data.map(s=>`<option value="${s.id}">${s.name}</option>`):'<option></option>');
      syncHidden();
    },'json');
  }

  function syncHidden(){
    $hidYear.val(yearId||'');
    $hidSem.val(semId||'');
    $hidTerm.val($term.val()||'');
    $hidCampus.val($campus.val()||'');
    $hidShift.val($shift.val()||'');
  }

  // AJAX upload; prevents default form submit (but still keeps fallback)
  $frm.on("submit", function(e){
    e.preventDefault(); // comment this line to test fallback post

    const f = $file[0].files[0];
    if(!f) return Swal.fire("Choose file",".xlsx required","warning");
    if(!/\.xlsx$/i.test(f.name)) return Swal.fire("Invalid file","Please upload an .xlsx file","error");
    if(!yearId || !semId || !$term.val() || !$campus.val() || !$shift.val())
      return Swal.fire("Pick period","All selectors are required","warning");

    const fd = new FormData();
    fd.append("action","uploadExcel");
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
      data: fd, processData: false, contentType: false, dataType: "json"
    }).done(r=>{
      if(r?.status){
        const d=r.data||r, w=d.warnings||[];
        $res.show().html(`
          <div class="alert alert-success">
            <div><b>Halls:</b> ${ (d.halls||[]).join(', ') || '-' }</div>
            <div><b>Classes created:</b> ${d.created_classes||0}</div>
            <div><b>Classes updated:</b> ${d.updated_classes||0}</div>
            <div><b>Sizes upserted:</b> ${d.upserted_sizes||0}</div>
            <div><b>Allocations upserted:</b> ${d.upserted_allocations||0}</div>
            ${ w.length ? `<hr><div class="text-warning">Totals mismatch for: ${w.join(', ')}</div>` : '' }
          </div>`);
        Swal.fire("Done","Excel imported successfully","success");
      } else {
        Swal.fire("Error", r?.data||"Import failed","error");
      }
    }).fail(xhr=>{
      Swal.fire("Error", xhr?.responseText||"Import failed","error");
    }).always(()=>{
      $btn.prop("disabled", false).text("Upload & Register");
    });
  });
});
