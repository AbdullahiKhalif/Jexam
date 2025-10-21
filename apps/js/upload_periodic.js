$(function () {
  const $pair   = $('#yearsem');     // combined year+semester
  const $term   = $('#term');
  const $campus = $('#campus');
  const $shift  = $('#shift');
  const $ok     = $("#ok"), $err = $("#err");

  let currentYearId = null;
  let currentSemId  = null;

  const flash = (type, txt) => {
    $ok.addClass('d-none'); $err.addClass('d-none');
    (type==='ok' ? $ok : $err).text(txt).removeClass('d-none');
    setTimeout(()=>{ $ok.addClass('d-none'); $err.addClass('d-none'); }, 8000);
  };

  /* ===========================
     1) Load ACTIVE Semester+Year pairs
     =========================== */
  $.post('../api/academic.php', {action:'listYearSemesterPairsActive'}, (resp)=>{
    if (resp?.status && resp.data?.length) {
      const opts = resp.data.map(p => 
        `<option value="${p.year_id}|${p.semester_id}">${p.label}</option>`
      );
      $pair.html(opts.join(''));
      const [y,s] = ($pair.val()||'|').split('|');
      currentYearId = y; currentSemId = s;
      loadTermsForSemester(currentSemId);
    } else {
      $pair.html('<option value="">-- No active semesters --</option>');
      $term.html('<option value="">--</option>');
    }
  }, 'json').fail(()=> $pair.html('<option value="">Error loading</option>'));

  // When user changes pair → reload terms
  $pair.on('change', function(){
    const [y,s] = ($(this).val()||'|').split('|');
    currentYearId = y; currentSemId = s;
    loadTermsForSemester(currentSemId);
  });

  function loadTermsForSemester(semester_id){
    if (!semester_id) { $term.html('<option value="">--</option>'); return; }
    $.post('../api/academic.php', {action:'listTermsActive', semester_id}, (resp)=>{
      if (resp?.status && resp.data?.length) {
        $term.html(resp.data.map(t=>`<option value="${t.id}">${t.name}</option>`).join(''));
      } else {
        $term.html('<option value="">-- No active terms --</option>');
      }
    }, 'json').fail(()=> $term.html('<option value="">Error</option>'));
  }

  /* ===========================
     2) Campuses & Shifts
     =========================== */
  $.post('../api/catalog.php',{action:'listCampuses'}, (resp)=>{
    if(resp?.status && resp.data?.length){
      $campus.html(resp.data.map(c=>`<option value="${c.id}">${c.name}</option>`).join(''));
      loadShifts();
    } else {
      $campus.html('<option value="">-- No campus --</option>');
      $shift.html('<option value="">--</option>');
    }
  },'json').fail(()=> $campus.html('<option value="">Error</option>'));

  function loadShifts(){
    const campus_id = $campus.val();
    if(!campus_id){ $shift.html('<option value="">--</option>'); return; }
    $.post('../api/catalog.php',{action:'listShifts', campus_id}, (resp)=>{
      if(resp?.status && resp.data?.length){
        $shift.html(resp.data.map(s=>`<option value="${s.id}">${s.name}</option>`).join(''));
      } else {
        $shift.html('<option value="">-- No shifts --</option>');
      }
    },'json').fail(()=> $shift.html('<option value="">Error</option>'));
  }
  $campus.on('change', loadShifts);

  /* ===========================
     3) Upload
     =========================== */
  $('#btnUpload').on('click', function(){
    const f = $('#excel').prop('files')[0];
    if(!f) return flash('err','Please choose a .xlsx file');

    // Ensure selections are present
    const term_id = $term.val();
    if(!currentYearId || !currentSemId) return flash('err','Select Semester + Year');
    if(!term_id) return flash('err','Select a Term');
    if(!$campus.val()) return flash('err','Select a Campus');
    if(!$shift.val()) return flash('err','Select a Shift');

    const fd = new FormData();
    fd.append('excel', f);
    fd.append('year_id', currentYearId);
    fd.append('semester_id', currentSemId);
    fd.append('term_id', term_id);
    fd.append('campus_id', $campus.val());
    fd.append('shift_id', $shift.val());

    $.ajax({
      url:'../api/upload_master.php',
      method:'POST',
      data: fd,
      processData:false,
      contentType:false,
      dataType:'json',
      success: (resp)=>{
        if(resp?.status){
          let msg = resp.data || 'Uploaded';
          if(resp.warnings?.length) msg += ` | Warnings: ${resp.warnings.length}`;
          flash('ok', msg);
        } else {
          flash('err', resp?.data || 'Upload failed');
        }
      },
      error: (xhr)=> flash('err', xhr?.responseText || 'Upload failed')
    });
  });
});
