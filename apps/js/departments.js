// js/departments.js
$(function(){
  /* ---------- Modal helpers (Bootstrap 5) ---------- */
  function makeModal(id){
    const el = document.getElementById(id);
    let inst = null;
    return {
      show(){ if(!inst && window.bootstrap?.Modal){ inst=new bootstrap.Modal(el,{backdrop:'static'}); }
              inst ? inst.show() : $("#"+id).modal('show'); },
      hide(){ inst ? inst.hide() : $("#"+id).modal('hide'); }
    };
  }
  const mdlDept = makeModal('mdlDept');
  const mdlRule = makeModal('mdlRule');

  /* ---------- DOM ---------- */
  const $tbDept = $("#tblDept tbody");
  const $tbRule = $("#tblRule tbody");
  const $filterDept = $("#filterDept");

  const $deptId=$("#dept_id"), $deptName=$("#dept_name");
  const $ruleId=$("#rule_id"), $rulePrefix=$("#rule_prefix"),
        $ruleDept=$("#rule_department_id"), $rulePriority=$("#rule_priority");

  /* ---------- Utils ---------- */
  function esc(s){ return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function attr(s){ return esc(s).replace(/"/g,'&quot;'); }
  function toast(msg){ if(window.Swal?.fire) Swal.fire(msg, "", "success"); else alert(msg); }
  function alertErr(msg){ console.error(msg); if(window.Swal?.fire) Swal.fire("Error", msg, "error"); else alert("Error: "+msg); }
  function ask(title,text){ if(window.Swal?.fire) return Swal.fire({title,text,icon:"warning",showCancelButton:true}).then(r=>r.isConfirmed); return Promise.resolve(confirm(title+"\n"+(text||""))); }

  // small wrapper with consistent error handling
  function post(action, data){
    return $.ajax({
      url: "../api/department.php",
      method: "POST",
      dataType: "json",
      data: Object.assign({action}, data||{})
    }).fail(function(xhr){
      console.error("API error:", action, xhr?.status, xhr?.responseText);
    });
  }

  /* ---------- Loaders ---------- */
  function loadDeptOptions(){
    return post("listOptions").then(function(r){
      if(!r?.status){ alertErr(r?.data||"Failed to load department options"); return; }
      const opts = (r.data||[]).map(d=>`'<option value="${d.id}">${esc(d.name)}</option>`).join('');
      $ruleDept.html(opts);
      $filterDept.html('<option value="">All Departments</option>'+opts);
    });
  }

  function loadDepartments(){
    $tbDept.html('<tr><td colspan="3" class="text-muted">Loading…</td></tr>');
    return post("list").then(function(r){
      $tbDept.html("");
      if(!r?.status){ $tbDept.html('<tr><td colspan="3" class="text-danger">Load failed</td></tr>'); return; }
      const rows = r.data||[];
      if(!rows.length){ $tbDept.append('<tr><td colspan="3" class="text-muted">No departments</td></tr>'); return; }
      rows.forEach((d,i)=>{
        $tbDept.append(`
          <tr>
            <td>${i+1}</td>
            <td>${esc(d.name)}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-edit-dept m-1" data-id="${d.id}" data-name="${attr(d.name)}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
                <button class="btn btn-del-dept m-1" data-id="${d.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
              </div>
            </td>
          </tr>
        `);
      });
    });
  }

  function loadRules(){
    $tbRule.html('<tr><td colspan="6" class="text-muted">Loading…</td></tr>');
    const payload = {};
    if($filterDept.val()) payload.department_id = $filterDept.val();
    return post("listRules", payload).then(function(r){
      $tbRule.html("");
      if(!r?.status){ $tbRule.html('<tr><td colspan="6" class="text-danger">Load failed</td></tr>'); return; }
      const rows = r.data||[];
      if(!rows.length){ $tbRule.append('<tr><td colspan="6" class="text-muted">No rules</td></tr>'); return; }
      rows.forEach((x,i)=>{
        $tbRule.append(`
          <tr>
            <td>${i+1}</td>
            <td>${esc(x.prefix)}</td>
            <td>${esc(x.department)}</td>
            <td>${x.priority}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary btn-move" data-id="${x.id}" data-dir="up">↑</button>
                <button class="btn btn-outline-secondary btn-move" data-id="${x.id}" data-dir="down">↓</button>
              </div>
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-edit-rule"
                        data-id="${x.id}" data-prefix="${attr(x.prefix)}"
                        data-dept="${x.department_id}" data-priority="${x.priority}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
                <button class="btn btn-del-rule" data-id="${x.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
              </div>
            </td>
          </tr>`);
      });
    });
  }

  /* ---------- Department events ---------- */
  $("#btnAddDept").on("click", function(){
    $deptId.val(""); $deptName.val("");
    mdlDept.show();
  });

  $("#frmDept").on("submit", function(e){
    e.preventDefault();
    const isCreate = ($deptId.val()==="");
    const payload = isCreate
      ? {name:$deptName.val()}
      : {id:$deptId.val(), name:$deptName.val()};
    post(isCreate?"create":"update", payload).then(function(r){
      if(r?.status){
        mdlDept.hide(); toast(isCreate?"Created":"Updated");
        // refresh options (used by Rules) + tables
        loadDeptOptions().then(()=>{ loadDepartments(); loadRules(); });
      } else {
        alertErr(r?.data||"Save failed");
      }
    });
  });

  $tbDept.on("click",".btn-edit-dept", function(){
    $deptId.val($(this).data("id"));
    $deptName.val($(this).data("name"));
    mdlDept.show();
  });

  $tbDept.on("click",".btn-del-dept", function(){
    const id=$(this).data("id");
    ask("Delete Department?","This will remove the department (rules referencing it will fail). Continue?")
      .then(ok=>{
        if(!ok) return;
        post("delete",{id}).then(function(r){
          if(r?.status){
            toast("Deleted");
            loadDeptOptions().then(()=>{ loadDepartments(); loadRules(); });
          } else alertErr(r?.data||"Delete failed");
        });
      });
  });

  /* ---------- Rule events ---------- */
  $("#btnAddRule").on("click", function(){
    // ensure options are present before opening modal
    const needOptions = !$ruleDept.children().length;
    const proceed = () => { $ruleId.val(""); $rulePrefix.val(""); $ruleDept.val(""); $rulePriority.val(""); mdlRule.show(); };
    needOptions ? loadDeptOptions().then(proceed) : proceed();
  });

  $("#frmRule").on("submit", function(e){
    e.preventDefault();
    const isCreate = ($ruleId.val()==="");
    const payload = isCreate
      ? {prefix:$rulePrefix.val(), department_id:$ruleDept.val(), priority:$rulePriority.val()}
      : {id:$ruleId.val(), prefix:$rulePrefix.val(), department_id:$ruleDept.val(), priority:$rulePriority.val()};
    post(isCreate?"createRule":"updateRule", payload).then(function(r){
      if(r?.status){ mdlRule.hide(); toast("Saved"); loadRules(); }
      else alertErr(r?.data||"Save failed");
    });
  });

  $tbRule.on("click",".btn-edit-rule", function(){
    // ensure options before opening
    const open = ()=>{
      $ruleId.val($(this).data("id"));
      $rulePrefix.val($(this).data("prefix"));
      $ruleDept.val($(this).data("dept"));
      $rulePriority.val($(this).data("priority"));
      mdlRule.show();
    };
    !$ruleDept.children().length ? loadDeptOptions().then(open) : open();
  });

  $tbRule.on("click",".btn-del-rule", function(){
    const id=$(this).data("id");
    ask("Delete Rule?","Remove this mapping?").then(ok=>{
      if(!ok) return;
      post("deleteRule",{id}).then(function(r){
        if(r?.status){ toast("Deleted"); loadRules(); }
        else alertErr(r?.data||"Delete failed");
      });
    });
  });

  $tbRule.on("click",".btn-move", function(){
    const id=$(this).data("id"), direction=$(this).data("dir");
    post("moveRule",{id, direction}).then(function(r){
      if(r?.status){ loadRules(); } else alertErr(r?.data||"Move failed");
    });
  });

  $filterDept.on("change", loadRules);

  /* ---------- Init sequence ----------
     1) Load options first (so modals always have data)
     2) Then tables
  ----------------------------------- */
  loadDeptOptions()
    .then(loadDepartments)
    .then(loadRules)
    .catch((e)=>console.error("Init failed", e));
});
