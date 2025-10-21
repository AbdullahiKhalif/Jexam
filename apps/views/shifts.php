<?php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">
    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <h4 class="mb-0">Shifts</h4>
          <select id="campus_sel" class="form-select form-select-sm" style="width:auto"></select>
        </div>
        <button class="btn btn-primary" id="btnAddShift">Add Shift</button>
      </div>
      <div class="card-body">
        <table class="table table-striped" id="tblShift">
          <thead><tr><th>#</th><th>Name</th><th>Start</th><th>End</th><th>Enabled</th><th style="width:200px">Actions</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<div class="modal fade" id="mdlShift" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="frmShift">
      <div class="modal-header">
        <h5 class="modal-title">Add / Edit Shift</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="shift_id">
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" id="shift_name" placeholder="Shift I" required></div>
        <div class="mb-2"><label class="form-label">Start Time</label><input type="time" class="form-control" id="start_time"></div>
        <div class="mb-2"><label class="form-label">End Time</label><input type="time" class="form-control" id="end_time"></div>
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
<script src="../js/shifts.js"></script>
