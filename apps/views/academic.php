<?php
// apps/views/academic.php
// ini_set('display_errors',1); error_reporting(E_ALL);

include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">
    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between">
        <div>
          <h4 class="mb-0">Academic Management</h4>
          <small class="text-muted">Create, edit, delete, and toggle active status</small>
        </div>
        <button class="btn btn-primary" id="btnAdd">Add Period</button>
      </div>

      <div class="card-body">
        <table class="table table-striped align-middle" id="tblAcademic">
          <thead>
            <tr>
              <th style="width: 25%">Year</th>
              <th style="width: 25%">Semester</th>
              <th style="width: 25%">Term</th>
              <th style="width: 15%">Active</th>
              <th style="width: 10%">Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Add/Edit Modal -->
<div class="modal fade" id="mdlAcademic" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="frmAcademic">
      <div class="modal-header">
        <h5 class="modal-title">Add / Edit Academic</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit_year_id">
        <input type="hidden" id="edit_sem_id">
        <input type="hidden" id="edit_term_id">
        <div class="mb-2">
          <label class="form-label">Year</label>
          <input type="text" class="form-control" id="year_name" placeholder="2025-2026" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Semester</label>
          <input type="text" class="form-control" id="sem_name" placeholder="Semester I" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Term</label>
          <input type="text" class="form-control" id="term_name" placeholder="Midterm" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/academic.js?v=20250921c"></script>
