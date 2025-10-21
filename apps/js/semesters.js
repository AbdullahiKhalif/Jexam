$(function(){
  const $tb = $("#tblSemester tbody");
  const $mdl = $("#mdlSemester");
  const $id  = $("#sem_id");
  const $name= $("#sem_name");
  const $yearSel = $("#sem_year_id");
  const $filterYear = $("#filterYear");

  function loadYears(){
    $.post("../api/semester.php",{action:"listYears"}, function(r){
      if(!r.status) return;
      const opts = r.data.map(y=>`<option value="${y.id}">${y.name}${Number(y.is_active)===1?'':' (inactive)'}</option>`);
      $yearSel.html(opts.join(''));
      $filterYear.append(opts.join(''));
    },"json");
  }

  function loadSemesters(){
    $tb.html("");
    const payload = {action:"list"};
    if($filterYear.val()) payload.year_id = $filterYear.val();

    $.post("../api/semester.php", payload, function(r){
      if(!r.status) return;
      if(!r.data.length){
        $tb.append(`<tr><td colspan="4" class="text-muted">No semesters</td></tr>`);
        return;
      }
      r.data.forEach(s=>{
        const active = Number(s.is_active)===1;
        const row = `
          <tr>
            <td>${s.year_name}</td>
            <td>${s.name}</td>
            <td>
              <div class="form-check form-switch m-0">
                <input class="form-check-input toggle-sem" type="checkbox" data-id="${s.id}" ${active?'checked':''}>
              </div>
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-edit" data-id="${s.id}" data-name="${s.name}" data-year="${s.academic_year_id}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
                <button class="btn btn-del" data-id="${s.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
              </div>
            </td>
          </tr>`;
        $tb.append(row);
      });
    },"json");
  }

  // Filter
  $filterYear.on("change", loadSemesters);

  // Add
  $("#btnAdd").on("click", ()=>{
    $id.val(""); $name.val(""); $mdl.modal("show");
  });

  // Edit
  $tb.on("click",".btn-edit", function(){
    $id.val($(this).data('id'));
    $name.val($(this).data('name'));
    $yearSel.val($(this).data('year'));
    $mdl.modal("show");
  });

  // Save
  $("#frmSemester").on("submit", function(e){
    e.preventDefault();
    const payload = ($id.val()==="")
      ? {action:"create", name:$name.val(), academic_year_id:$yearSel.val()}
      : {action:"update", id:$id.val(), name:$name.val(), academic_year_id:$yearSel.val()};
    $.post("../api/semester.php", payload, function(r){
      if(r.status){ $mdl.modal("hide"); loadSemesters(); }
      else Swal.fire("Error", r.data, "error");
    },"json");
  });

  // Toggle
  $tb.on("change",".toggle-sem", function(){
    const $c=$(this), id=$c.data('id'), isOn=$c.is(':checked')?1:0;
    $.post("../api/semester.php",{action:"toggle", id, is_active:isOn}, function(r){
      if(!r.status){ $c.prop('checked', !isOn); Swal.fire("Error", r.data, "error"); }
      loadSemesters();
    },"json").fail(()=>{ $c.prop('checked', !isOn); Swal.fire("Error","Server error","error"); });
  });

  // Delete
  $tb.on("click",".btn-del", function(){
    const id=$(this).data('id');
    Swal.fire({title:"Delete Semester?",icon:"warning",showCancelButton:true}).then(s=>{
      if(!s.isConfirmed) return;
      $.post("../api/semester.php",{action:"delete", id}, function(r){
        if(r.status) loadSemesters(); else Swal.fire("Error", r.data, "error");
      },"json");
    });
  });

  // Init
  loadYears();
  loadSemesters();
});
