<?php
// apps/views/upload.php
// Uncomment while debugging:
// ini_set('display_errors',1); error_reporting(E_ALL);

include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">
    <div class="card mt-3">
      <div class="card-header">
        <h4 class="mb-0">Upload Exams by Period &amp; Location</h4>
      </div>

      <div class="card-body">
        <div class="row g-3">
          <!-- Combined Semester + Year -->
          <div class="col-md-4">
            <label class="form-label">Semester + Year</label>
            <select id="yearsem" class="form-select">
              <option value="">Loading…</option>
            </select>
          </div>

          <!-- Term (Midterm / Final) -->
          <div class="col-md-3">
            <label class="form-label">Term</label>
            <select id="term" class="form-select">
              <option value="">--</option>
            </select>
          </div>

          <!-- Campus -->
          <div class="col-md-3">
            <label class="form-label">Campus</label>
            <select id="campus" class="form-select">
              <option value="">Loading…</option>
            </select>
          </div>

          <!-- Shift -->
          <div class="col-md-2">
            <label class="form-label">Shift</label>
            <select id="shift" class="form-select">
              <option value="">--</option>
            </select>
          </div>

          <!-- File -->
          <div class="col-md-6">
            <label class="form-label">.xlsx file (sheet: <b>MasterUpload</b>)</label>
            <input type="file" class="form-control" id="excel" accept=".xlsx">
          </div>
        </div>

        <button id="btnUpload" class="btn btn-primary mt-3">Upload</button>

        <div class="alert alert-success d-none mt-3" id="ok"></div>
        <div class="alert alert-danger d-none mt-3" id="err"></div>

        <p class="mt-3 text-muted">
          Required sheet: <b>MasterUpload</b> with columns:
          <code>HallName, HallCapacity, ClassCode, StudentCount</code><br>
          Optional sheet: <b>DepartmentRules</b> with:
          <code>Prefix, Department</code>
        </p>
      </div>
    </div>
  </div>
</main>

<?php include '../layouts/footer.php'; ?>
<!-- jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Page JS (cache-busted) -->
<script src="../js/upload_periodic.js?v=20250921a"></script>
