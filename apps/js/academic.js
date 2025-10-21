$(function(){
  const $tb = $("#tblAcademic tbody");
  const $mdl = $("#mdlAcademic");
  const $year = $("#year_name"), $sem=$("#sem_name"), $term=$("#term_name");
  const $yid=$("#edit_year_id"), $sid=$("#edit_sem_id"), $tid=$("#edit_term_id");

  // ===== Utilities =====
  function rowId(y,s,t){ return `r-${y||0}-${s||0}-${t||0}`; }
  function chk(name, id, checked, disabled){
    return `
      <div class="form-check form-switch m-0">
        <input class="form-check-input toggle-${name}" type="checkbox"
               data-id="${id}" ${checked?'checked':''} ${disabled?'disabled':''}>
      </div>`;
  }
  function badge(on, label){ return `<span class="badge ${on?'bg-success':'bg-secondary'}">${label}</span>`; }

  // ===== Load table =====
  function loadAll(){
    $tb.html("");
    $.post("../api/academic.php",{action:"listYears"}, function(yres){
      if(!yres.status) return Swal.fire("Error", yres.data || "Load years failed","error");
      if(!yres.data.length){
        $tb.append(`<tr><td colspan="5" class="text-muted">No academic periods yet</td></tr>`);
        return;
      }
      yres.data.forEach(y=>{
        $.post("../api/academic.php",{action:"listSemesters",year_id:y.id}, function(sres){
          if(!sres.status || !sres.data.length){
            addRow(y,null,null);
          } else {
            sres.data.forEach(s=>{
              $.post("../api/academic.php",{action:"listTerms",semester_id:s.id}, function(tres){
                if(!tres.status || !tres.data.length){
                  addRow(y,s,null);
                } else {
                  tres.data.forEach(t=> addRow(y,s,t));
                }
              },"json");
            });
          }
        },"json");
      });
    },"json");
  }

  function addRow(y, s, t){
    const yActive = Number(y?.is_active) === 1;
    const sActive = s ? Number(s.is_active) === 1 : null;
    const tActive = t ? Number(t.is_active) === 1 : null;

    const yChk = chk('year', y.id, yActive, false);
    const sChk = s ? chk('sem', s.id, sActive, !yActive) : '-';
    const tChk = t ? chk('term', t.id, tActive, !(yActive && sActive)) : '-';

    const activeCol = `
      ${badge(yActive,'Year')}
      ${s? ' ' + badge(!!sActive,'Sem') : ''}
      ${t? ' ' + badge(!!tActive,'Term') : ''}
    `;

    const actions = `
      <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary btn-edit"
          data-yid="${y?.id||''}" data-sid="${s?.id||''}" data-tid="${t?.id||''}"
          data-yname="${y?.name||''}" data-sname="${s?.name||''}" data-tname="${t?.name||''}">Edit</button>
        ${t? `<button class="btn btn-outline-danger btn-del-term" data-id="${t.id}">Del T</button>`:''}
        ${s? `<button class="btn btn-outline-danger btn-del-sem"  data-id="${s.id}">Del S</button>`:''}
        <button class="btn btn-outline-danger btn-del-year" data-id="${y.id}">Del Y</button>
      </div>`;

    const trId = rowId(y?.id, s?.id, t?.id);
    $tb.append(`
      <tr id="${trId}">
        <td>${y?.name||'-'}<div class="mt-1">${yChk}</div></td>
        <td>${s?.name||'-'}${s?`<div class="mt-1">${sChk}</div>`:''}</td>
        <td>${t?.name||'-'}${t?`<div class="mt-1">${tChk}</div>`:''}</td>
        <td>${activeCol}</td>
        <td>${actions}</td>
      </tr>
    `);
  }

  // ===== Add new =====
  $("#btnAdd").on("click", ()=>{
    $yid.val(''); $sid.val(''); $tid.val('');
    $year.val(''); $sem.val(''); $term.val('');
    $mdl.modal('show');
  });

  // ===== Edit existing (rename) =====
  $tb.on("click",".btn-edit", function(){
    $yid.val($(this).data('yid'));
    $sid.val($(this).data('sid'));
    $tid.val($(this).data('tid'));
    $year.val($(this).data('yname'));
    $sem.val($(this).data('sname'));
    $term.val($(this).data('tname'));
    $mdl.modal('show');
  });

  $("#frmAcademic").on("submit", function(e){
    e.preventDefault();
    const yname=$year.val().trim(), sname=$sem.val().trim(), tname=$term.val().trim();
    const yid=$yid.val(), sid=$sid.val(), tid=$tid.val();

    if(!yid){ // create
      $.post("../api/academic.php",{action:"createYear", name:yname}, function(yres){
        if(!yres.status) return Swal.fire("Error",yres.data,"error");
        $.post("../api/academic.php",{action:"createSemester", year_id:yres.data.id, name:sname}, function(sres){
          if(!sres.status) return Swal.fire("Error",sres.data,"error");
          $.post("../api/academic.php",{action:"createTerm", semester_id:sres.data.id, name:tname}, function(tres){
            if(!tres.status) return Swal.fire("Error",tres.data,"error");
            Swal.fire("Saved","Academic period created ✅","success");
            $mdl.modal('hide'); loadAll();
          },"json");
        },"json");
      },"json");
    } else { // update names
      const updates=[];
      updates.push($.post("../api/academic.php",{action:"updateYear", id:yid, name:yname}));
      if(sid) updates.push($.post("../api/academic.php",{action:"updateSemester", id:sid, name:sname}));
      if(tid) updates.push($.post("../api/academic.php",{action:"updateTerm", id:tid, name:tname}));
      $.when.apply($,updates).then(()=>{ Swal.fire("Updated","Saved ✔","success"); $mdl.modal('hide'); loadAll();});
    }
  });

  // ===== Checkbox toggles =====
  // Year toggle
  $tb.on("change",".toggle-year", function(){
    const $c=$(this), id=$c.data('id'), isOn=$c.is(':checked')?1:0;
    $.post("../api/academic.php",{action:"toggleYear", id, is_active:isOn}, function(r){
      if(!r.status){
        $c.prop('checked', !isOn); // revert
        return Swal.fire("Error", r.data, "error");
      }
      loadAll();
    },"json").fail(()=>{ $c.prop('checked', !isOn); Swal.fire("Error","Server error","error"); });
  });

  // Semester toggle (requires Year active)
  $tb.on("change",".toggle-sem", function(){
    const $c=$(this), id=$c.data('id'), isOn=$c.is(':checked')?1:0;
    $.post("../api/academic.php",{action:"toggleSemester", id, is_active:isOn}, function(r){
      if(!r.status){
        $c.prop('checked', !isOn); // revert
        return Swal.fire("Error", r.data, "error");
      }
      loadAll();
    },"json").fail(()=>{ $c.prop('checked', !isOn); Swal.fire("Error","Server error","error"); });
  });

  // Term toggle (requires Year & Semester active)
  $tb.on("change",".toggle-term", function(){
    const $c=$(this), id=$c.data('id'), isOn=$c.is(':checked')?1:0;
    $.post("../api/academic.php",{action:"toggleTerm", id, is_active:isOn}, function(r){
      if(!r.status){
        $c.prop('checked', !isOn); // revert
        return Swal.fire("Error", r.data, "error");
      }
      loadAll();
    },"json").fail(()=>{ $c.prop('checked', !isOn); Swal.fire("Error","Server error","error"); });
  });

  // ===== Deletes =====
  $tb.on("click",".btn-del-year", function(){
    const id=$(this).data('id');
    Swal.fire({title:"Delete Year?",icon:"warning",showCancelButton:true}).then(s=>{
      if(!s.isConfirmed) return;
      $.post("../api/academic.php",{action:"deleteYear", id}, function(resp){
        if(resp.status) loadAll(); else Swal.fire("Error",resp.data,"error");
      },"json");
    });
  });
  $tb.on("click",".btn-del-sem", function(){
    const id=$(this).data('id');
    Swal.fire({title:"Delete Semester?",icon:"warning",showCancelButton:true}).then(s=>{
      if(!s.isConfirmed) return;
      $.post("../api/academic.php",{action:"deleteSemester", id}, function(resp){
        if(resp.status) loadAll(); else Swal.fire("Error",resp.data,"error");
      },"json");
    });
  });
  $tb.on("click",".btn-del-term", function(){
    const id=$(this).data('id');
    Swal.fire({title:"Delete Term?",icon:"warning",showCancelButton:true}).then(s=>{
      if(!s.isConfirmed) return;
      $.post("../api/academic.php",{action:"deleteTerm", id}, function(resp){
        if(resp.status) loadAll(); else Swal.fire("Error",resp.data,"error");
      },"json");
    });
  });

  // Init
  loadAll();
});
