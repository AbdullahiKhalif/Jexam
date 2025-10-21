$(function(){
  const $tb=$("#tblCampus tbody"), $mdl=$("#mdlCampus");
  const $id=$("#campus_id"), $name=$("#campus_name"), $code=$("#campus_code");

  function load(){
    $tb.html("");
    $.post("../api/campus.php",{action:"list"}, function(resp){
      if(!resp.status) return;
      resp.data.forEach((c,idx)=>{
        $tb.append(`<tr>
          <td>${idx+1}</td><td>${c.name}</td><td>${c.code}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-edit" data-id="${c.id}" data-name="${c.name}" data-code="${c.code}"><i class="fa fa-edit p-2 bg-primary rounded text-light fs-6"></i></button>
              <button class="btn btn-del" data-id="${c.id}"><i class="fa fa-trash p-2 bg-danger rounded text-light fs-6"></i></button>
            </div>
          </td>
        </tr>`);
      });
    },"json");
  }

  $("#btnAddCampus").on("click", ()=>{ $id.val(''); $name.val(''); $code.val(''); $mdl.modal('show'); });

  $tb.on("click",".btn-edit", function(){
    $id.val($(this).data('id')); $name.val($(this).data('name')); $code.val($(this).data('code'));
    $mdl.modal('show');
  });

  $("#frmCampus").on("submit", function(e){
    e.preventDefault();
    const data = ($id.val()==='')
      ? {action:"create", name:$name.val(), code:$code.val()}
      : {action:"update", id:$id.val(), name:$name.val(), code:$code.val()};
    $.post("../api/campus.php", data, function(resp){
      if(resp.status){ $mdl.modal('hide'); load(); } else Swal.fire("Error", resp.data, "error");
    },"json");
  });

  $tb.on("click",".btn-del", function(){
    const id=$(this).data('id');
    Swal.fire({title:"Delete Campus?", icon:"warning", showCancelButton:true}).then(s=>{
      if(!s.isConfirmed) return;
      $.post("../api/campus.php",{action:"delete", id}, function(resp){
        if(resp.status) load(); else Swal.fire("Error",resp.data,"error");
      },"json");
    });
  });

  load();
});
