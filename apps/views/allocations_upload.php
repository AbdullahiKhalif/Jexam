<?php
// apps/views/allocations_upload.php
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
      <div>
        <strong>Upload Allocation Excel (.xlsx only)</strong>
        <div class="small text-muted">Row 1: <b>Class</b>, <b># of Std.</b>, then <b>Hall names</b> · Row 2: <b>Size</b> + capacities · Then class rows.</div>
      </div>
      
    </div>
    <div class="card-body">
      <form id="frmUpload" enctype="multipart/form-data">
        <input type="file" name="file" id="file" class="form-control mb-3" accept=".xlsx" required>
        <button class="btn btn-primary" type="submit" id="btnUpload">Upload & Register</button>
      </form>
      <div class="mt-3" id="result" style="display:none;"></div>
    </div>
  </div>

</div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/allocations_upload.js"></script>
