<?php
// apps/views/report_subject_halls.php
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<link rel="stylesheet" href="../assets/reports/style.css">

<main class="content">
  <div class="container-fluid p-0">

    <div class="header bg-secondary m-2 p-3 print-hide" style="border-radius:8px;">
      <div class="container">
        <h1 class="mb-0 text-white"><i class="fa-solid fa-book-open me-2"></i>Subject → Classes & Halls</h1>
        <div class="text-white-50">Pick period & location; subjects auto-load for that campus & shift.</div>
      </div>
    </div>

    <div class="upload-container print-hide">
      <div class="row g-3 exam-details-form">
        <div class="col-md-4">
          <label class="form-label">Academic Year + Semester (active)</label>
          <select id="yearsem" class="form-select"></select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Term</label>
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

        <div class="col-md-12">
          <label class="form-label">Subject</label>
          <select id="subject" class="form-select"></select>
          <div class="form-text">Subjects listed here exist for the chosen campus & shift in the selected period.</div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <button id="btnGenerate" class="btn btn-info">
            <i class="fa-solid fa-list me-1"></i> Generate Report
          </button>
          
          <button id="btnExport" class="btn btn-success">
            <i class="fa-solid fa-file-excel me-1"></i> Export Excel
          </button>
        </div>
      </div>

      <div id="errorBox" class="error-details" style="display:none;"></div>
    </div>

    <div class="loading" id="loading" style="display:none;">
      <div class="spinner"></div>
      <div>Loading…</div>
    </div>

    <div class="results-section" id="resultsSection" style="display:none;">
      <div id="summaryLine" class="mb-3"></div>
      <div class="card">
        <div class="card-body" id="printArea">
          <div class="table-responsive">
            <table id="tblMain" class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th>Department</th>
                  <th>Cohort</th>
                  <th>Class</th>
                  <th>Hall</th>
                  <th>Capacity</th>
                  <th>Allocated</th>
                  <th>Campus</th>
                  <th>Shift</th>
                </tr>
              </thead>
              <tbody id="tblRows"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 (searchable select) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- DataTables -->
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet"/>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<!-- XLSX -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script src="../js/report_subject_halls.js?v=2"></script>
