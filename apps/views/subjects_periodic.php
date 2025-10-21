<!-- views/subjects_periodic.php -->
<?php
// apps/views/subjects_periodic.php
// ini_set('display_errors',1); error_reporting(E_ALL);

#include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>

<main class="content">
<div class="container-fuild p-0">
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
        <button class="btn btn-primary" id="btnAddSubject">Add Subject</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <table id="tblSubject" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th><th>Subject</th><th>Department</th><th>Level</th><th>Period</th><th>Actions</th>
          </tr>
        </thead>
        <tbody><!-- filled by JS --></tbody>
      </table>
    </div>
  </div>

<!-- ...top of file unchanged... -->
  <div class="modal fade" id="mdlSubject" tabindex="-1">
    <div class="modal-dialog">
      <form id="frmSubject" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="subject_id">

          <div class="mb-2">
            <label class="form-label">Name</label>
            <input type="text" id="subject_name" class="form-control" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Department</label>
            <select id="subject_department_id" class="form-select"></select>
          </div>

          <div class="mb-2">
            <label class="form-label">Level</label>
            <select id="subject_level" class="form-select">
              <option value="General">General</option>
              <option value="Specific">Specific</option>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label">Default Exam Date (optional)</label>
            <input type="date" id="subject_default_exam_date" class="form-control">
          </div>

          <hr class="my-3">

          <div class="mb-2">
            <label class="form-label">Classes by Cohort (for the selected Department & Period)</label>
            <div id="cohortList" class="border rounded p-2" style="max-height:220px; overflow:auto;">
              <div class="text-muted small">Pick a department to load cohorts…</div>
            </div>
            <div class="form-text">Tick cohorts like <b>CA24</b>, <b>CA23</b> to register this subject to all those classes.</div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save & Register</button>
        </div>
      </form>
    </div>
  </div>
<!-- ...rest unchanged... -->

</div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/subjects_periodic.js"></script>
<script>
  $(function(){
    $.post('../api/subject.php',{action:'listDepartments'}, function(r){
      if(r?.status){
        $('#subject_department_id').html(r.data.map(d=>`<option value="${d.id}">${d.name}</option>`));
      }
    }, 'json');
  });
</script>