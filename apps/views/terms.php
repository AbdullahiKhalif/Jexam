<?php
// apps/views/terms.php
// ini_set('display_errors',1); error_reporting(E_ALL);

include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">
    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-0">Terms</h4>
          <small class="text-muted">Create, edit, delete, enable/disable</small>
        </div>
        <button class="btn btn-primary" id="btnAdd">Add Term</button>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-2">
          <div class="col-md-4">
            <label class="form-label">Filter by Semester</label>
            <select id="filterSemester" class="form-select">
              <option value="">All Semesters</option>
            </select>
          </div>
        </div>
        <table class="table table-striped align-middle" id="tblTerm">
          <thead>
            <tr>
              <th>Academic Year</th>
              <th>Semester</th>
              <th>Term</th>
              <th>Start date</th>
              <th>End date</th>
              <th>Active</th>
              <th style="width: 210px;">Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Modal -->
<div class="modal fade" id="mdlTerm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="frmTerm">
      <div class="modal-header">
        <h5 class="modal-title">Add / Edit Term</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="term_id">
        <div class="mb-2">
          <label class="form-label">Semester</label>
          <select id="term_semester_id" class="form-select" required></select>
        </div>
        <div class="mb-2">
          <label class="form-label">Term Name</label>
          <input type="text" class="form-control" id="term_name" placeholder="Midterm" required>
        </div>
        <div class="mb-2">
           <label class="form-label">Start date</label>
          <input type="date" id="start_date" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">End date</label>
          <input type="date" id="end_date" class="form-control">
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
<script src="../js/terms.js?v=20250921"></script>
