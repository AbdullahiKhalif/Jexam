<?php
// apps/views/subject_upload.php
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">

    <div class="card mb-3">
      <div class="card-body row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Academic Year + Semester (active)</label>
          <select id="yearsem" class="form-select"></select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Term (active)</label>
          <select id="term" class="form-select"></select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Department</label>
          <select id="department" class="form-select"></select>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label">.xlsx file (sheets named like “CA24's”, “CNS23's” …)</label>
          <input type="file" id="file" class="form-control" accept=".xlsx">
          <div class="form-text">
            Each sheet applies to all classes of the cohort (e.g., “CA24” → CA241, CA242, …).<br>
            Columns: <b>Subject</b>, <b>Date</b> (YYYY-MM-DD or Excel serial), <b>Shift</b> (e.g., Shift I).
          </div>
        </div>
        <div class="col-md-2 d-none">
          <label class="form-label">Default duration (min)</label>
          <input type="number" id="duration" class="form-control" value="120" min="1">
        </div>
        <div class="col-md-4 text-end">
          <button id="btnUpload" class="btn btn-primary">Upload</button>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-body">
        <pre id="result" class="small mb-0" style="white-space:pre-wrap"></pre>
      </div>
    </div>

  </div>
</main>
<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/subject_upload.js?v=1"></script>