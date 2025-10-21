$(function () {
  let btnAction = "Insert";
//   loadLookups();
  loadData();
  $("#addNew").on("click", () => {
    btnAction = "Insert";
    $("#userForm")[0].reset();
    $("#pwWrap").show();
    $("#userModal").modal("show");
    $(".modal-title").text("Add new user 😍");
  });

//   function loadLookups() {
//     $.post(
//       "../api/users.php",
//       { action: "readLookups" },
//       function (r) {
//         if (!r.status) return;
//         const fac = r.data.faculties,
//           dep = r.data.departments;
//         let f = '<option value="">—</option>';
//         fac.forEach((x) => (f += `<option value="${x.id}">${x.name}</option>`));
//         $("#faculty_id").html(f);
//         let d = '<option value="">—</option>';
//         dep.forEach((x) => (d += `<option value="${x.id}">${x.name}</option>`));
//         $("#department_id").html(d);
//       },
//       "json"
//     );
//   }

  function loadData() {
    $("#usersTable tbody").html("");
    $.post(
      "../api/users.php",
      { action: "readAllUsers" },
      function (r) {
        if (!r.status) return;
        let tr = "";
        r.data.forEach((u) => {
          tr += `<tr>
<td>${u.id}</td><td>${u.full_name}</td><td>${u.username}</td>
<td>${u.role}</td>
<td><div class='form-check form-switch'><input class='form-check-input act' type='checkbox' data-id='${
            u.id
          }' ${u.is_active == 1 ? "checked" : ""}></div></td>
<td>
<a class='edit_info' data-id='${
            u.id
          }'><i class='fa fa-edit fs-4 bg-primary text-light p-2 rounded'></i></a>
<a class='reset_pw' data-id='${
            u.id
          }'><i class='fa fa-key bg-warning fs-4 p-2 text-light'></i></a>
</td></tr>`;
        });
        $("#usersTable tbody").html(tr);
        if (!$.fn.DataTable.isDataTable("#usersTable"))
          $("#usersTable").DataTable();
      },
      "json"
    );
  }

  $("#usersTable").on("change", ".act", function () {
    const id = $(this).data("id");
    const is_active = $(this).is(":checked") ? 1 : 0;
    $.post("../api/users.php", { action: "toggleActive", id, is_active });
  });

  $("#usersTable").on("click", ".edit_info", function () {
    const id = $(this).data("id");
    $.post(
      "../api/users.php",
      { action: "readAllUsers" },
      function (r) {
        const u = r.data.find((x) => x.id == id);
        if (!u) return;
        btnAction = "Update";
        $("#userModal").modal("show");
        $(".modal-title").text("Update user 😍");
        $("#id").val(u.id);
        $("#full_name").val(u.full_name);
        $("#username").val(u.username);
        $("#role").val(u.role);
        $("#pwWrap").hide();
      },
      "json"
    );
  });

  $("#usersTable").on("click", ".reset_pw", function () {
    const id = $(this).data("id");
    const np = prompt("New password (min 6 chars)");
    if (!np) return;
    $.post(
      "../api/users.php",
      { action: "resetPassword", id, new_password: np },
      () => alert("Password updated"),
      "json"
    );
  });

  $("#userForm").on("submit", function (e) {
    e.preventDefault();
    const payload = $(this)
      .serializeArray()
      .reduce((a, x) => ((a[x.name] = x.value), a), {});
    if (btnAction === "Insert") payload.action = "registerUser";
    else payload.action = "updateUser";
    $.post(
      "../api/users.php",
      payload,
      function (r) {
        if (r.status) {
          $(".alert-success").removeClass("d-none").text(r.data);
          setTimeout(() => $("#userModal").modal("hide"), 800);
          loadData();
        } else {
          $(".alert-danger").removeClass("d-none").text(r.data);
        }
      },
      "json"
    );
  });
});
