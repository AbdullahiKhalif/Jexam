<?php
// apps/views/academic.php
// ini_set('display_errors',1); error_reporting(E_ALL);
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
      <div class="col-md-3">
        <label class="form-label">Campus</label>
        <select id="campus" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Shift</label>
        <select id="shift" class="form-select"></select>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="fw-semibold">Halls availability for this period</div>
      <!-- NEW: Add Hall button -->
      <button class="btn btn-primary btn-sm" id="btnAddHall">Add Hall</button>
    </div>
    <div class="card-body">
      <table id="tblHallAvail" class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Hall</th>
            <th>Capacity</th>
            <th>Status (this period)</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody><!-- filled by JS --></tbody>
      </table>
    </div>
  </div>
</div>

<!-- NEW: Hall Modal -->
<div class="modal fade" id="mdlHall" tabindex="-1">
  <div class="modal-dialog">
    <form id="frmHall" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Hall</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="hall_id">
        <div class="mb-2">
          <label class="form-label">Campus</label>
          <select id="hall_campus_id" class="form-select" required></select>
          <div class="form-text">Defaults to the campus currently selected above.</div>
        </div>
        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" id="hall_name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Capacity</label>
          <input type="number" id="hall_capacity" min="0" class="form-control" required>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="hall_is_active" checked>
          <label class="form-check-label" for="hall_is_active">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="../js/halls_periodic.js"></script>

