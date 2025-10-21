$(function(){
  const $campus=$("#campus_sel"), $tb=$("#tblShift tbody");
  const $mdl=$("#mdlShift"), $id=$("#shift_id"), $name=$("#shift_name"), $start=$("#start_time"), $end=$("#end_time");

  function loadCampuses(){
    $.post("../api/campus.php",{action:"list"}, function(resp){
      if(resp.status){
        $campus.html(resp.data.map(c=>`<option value="${c.id}">${c.name}</option>`));
        loadShifts();
      }
    },"json");
  }
  function loadShifts(){
    $tb.html("");
    $.post("../api/shift.php",{action:"list", campus_id:$campus.val()}, function(resp){
      if(resp.status){
        resp.data.forEach((s,idx)=>{
          $tb.append(`<tr>
            <td>${idx+1}</td>
            <td>${s.name}</td>
            <td>${s.start_time||'-'}</td>
            <td>${s.end_time||'-'}</td>
            <td>${s.is_enabled==1?'Yes':'No'}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-edit" data-id="${s.id}" data-name="${s.name}" data-start="${s.start_time||''}" data-end="${s.end_time||''}">Edit</button>
                <button class="btn btn-outline-warning btn-toggle" data-id="${s.id}" data-val="${s.is_enabled?0:1}">${s.is_enabled? 'Disable':'Enable'}</button>
                <button class="btn btn-outline-danger btn-del" data-id="${s.id}">Delete</button>
              </div>
            </td>
          </tr>`);
        });
      }
    },"json");
  }

  $("#btnAddShift").on("click", ()=>{ $id.val(''); $name.val(''); $start.val(''); $end.val(''); $mdl.modal('show'); });
  $campus.on("change", loadShifts);

  $tb.on("click",".btn-edit", function(){
    $id.val($(this).data('id')); $name.val($(this).data('name')); $start.val($(this).data('start')); $end.val($(this).data('end'));
    $mdl.modal('show');
  });

  $("#frmShift").on("submit", function(e){
    e.preventDefault();
    const payload = ($id.val()==='')
      ? {action:"create", campus_id:$campus.val(), name:$name.val(), start_time:$start.val(), end_time:$end.val()}
      : {action:"update", id:$id.val(), name:$name.val(), start_time:$start.val(), end_time:$end.val()};
    $.post("../api/shift.php", payload, function(resp){
      if(resp.status){ $mdl.modal('hide'); loadShifts(); } else Swal.fire("Error", resp.data, "error");
    },"json");
  });

  $tb.on("click",".btn-toggle", function(){
    $.post("../api/shift.php",{action:"toggle", id:$(this).data('id'), is_enabled:$(this).data('val')}, ()=>loadShifts(),'json');
  });

  $tb.on("click",".btn-del", function(){
    const id=$(this).data('id');
    Swal.fire({title:"Delete Shift?", icon:"warning", showCancelButton:true}).then(s=>{
      if(!s.isConfirmed) return;
      $.post("../api/shift.php",{action:"delete", id}, function(resp){
        if(resp.status) loadShifts(); else Swal.fire("Error",resp.data,"error");
      },"json");
    });
  });

  loadCampuses();
});
