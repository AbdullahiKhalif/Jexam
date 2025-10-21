<?php
// apps/views/academic.php
// ini_set('display_errors',1); error_reporting(E_ALL);

#include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">

<div class="container-fluid p-0">
  <div class="card mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Academic Year + Semester (active)</label>
        <select id="yearsem" class="form-select"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Term (active)</label>
        <select id="term" class="form-select"></select>
      </div>
      <div class="col-md-5 text-end">
        <button class="btn btn-primary" id="btnAddClass">Add Class</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <table id="tblClass" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Code</th>
            <th>Department</th>
            <th>Level</th>
            <th>Period</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody><!-- filled by JS --></tbody>
      </table>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="mdlClass" tabindex="-1">
    <div class="modal-dialog">
      <form id="frmClass" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Class</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="class_id">
          <div class="mb-2">
            <label class="form-label">Code</label>
            <input type="text" id="class_code" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Department</label>
            <select id="class_department_id" class="form-select"></select>
          </div>
          <div class="mb-2">
            <label class="form-label">Level</label>
            <input type="text" id="class_level" class="form-control" placeholder="e.g., Year 2">
          </div>
          <div class="small text-muted">
            This class will be saved for the selected Year / Semester / Term.
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/classes_periodic.js"></script>
<script>
  // Fill department dropdown (reuse your existing endpoint if different)
  $(function(){
    // If you already have api/catalog.php?action=listDepartments, prefer that.
    $.post('../api/subject.php',{action:'listDepartments'}, function(r){
      if(r?.status){
        $('#class_department_id').html(r.data.map(d=>`<option value="${d.id}">${d.name}</option>`));
      }
    }, 'json');
  });
</script>
