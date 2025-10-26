<?php
// apps/views/report_subjects_assigned.php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<link rel="stylesheet" href="../assets/reports/style.css">

<main class="content">
  <div class="container-fluid p-0">

     <div class="header bg-secondary m-2 p-3 print-hide" style="border-radius:8px;">
      <div class="container">
        <h1 class="mb-0 text-white">Subjects Assigned (By Date)</h1>
        <div class="text-white-50">Pick Year/Semester/Term, Department (optional), and Date.</div>
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
          <label class="form-label">Department (optional)</label>
          <select id="department" class="form-select"></select>
          <div class="form-text">Leave as “All Departments” to include every department.</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">Date</label>
          <input type="date" id="date" class="form-control">
        </div>

        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
          <button id="btnGenerate" class="btn btn-info">
            <i class="fa fa-refresh text-white"></i> Generate
          </button>

        </div>
      </div>

      <div id="errorBox" class="error-details"></div>
    </div>

    <div class="loading" id="loading">
      <div class="spinner"></div>
      <div>Loading…</div>
    </div>

    <!-- RESULTS CARD -->
    <div class="card results-section" id="resultsSection" style="display:none;">
      <div class="card-body" id="printArea">
        <div class="row">
          <div class="col-12">
            <div class="d-flex justify-content-center align-items-center mb-3 print-hide">
              <h2 class="text-center"><i class="fas fa-list-alt me-2"></i>Subjects Assigned</h2>
            </div>
            <div id="summaryLine" class="mb-3 d-none"></div>
          </div>
        </div>

        <div id="deptBlocks" class="w-100">
          <!-- Filled by JS -->
        </div>
      </div>

      <!-- Bottom toolbar (only visible on screen) -->
      <div class="card-footer d-flex justify-content-center gap-2 print-hide">
        <button id="btnPrintBottom" class="btn btn-success">
          <i class="fa fa-print text-white"></i> Print
        </button>
        <button id="btnExportExcel" class="btn btn-info">
          <i class="fas fa-file-excel me-2"></i> Export to Excel
        </button>
      </div>
    </div>

  </div>
</main>

<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../js/report_subjects_assigned.js?v=2"></script>
