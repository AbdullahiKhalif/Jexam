<?php
// apps/views/classes.php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>

<main class="content">
<div class="container-fluid p-0">
  <div class="card mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Academic Year + Semester</label>
        <select id="yearsem" class="form-select"></select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Term</label>
        <select id="term" class="form-select"></select>
      </div>
      <div class="col-md-5 text-end">
        <button class="btn btn-primary" id="btnAddClass">Add Class</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Classes Management</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="tblClass" class="table table-striped table-bordered align-middle">
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
  </div>

  <!-- Class Modal -->
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
            <label class="form-label">Class Code</label>
            <input type="text" id="class_code" class="form-control" required placeholder="e.g. CA211">
          </div>
          
          <div class="mb-2">
            <label class="form-label">Department</label>
            <select id="class_department_id" class="form-select"></select>
          </div>
          
          <div class="mb-2">
            <label class="form-label">Level / Study Phase (Optional)</label>
            <input type="text" id="class_level" class="form-control" placeholder="e.g. Undergraduate">
          </div>
          
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save Class</button>
        </div>
      </form>
    </div>
  </div>

</div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/classes.js"></script>
