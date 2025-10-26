<?php
// apps/views/report_halls_open.php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<link rel="stylesheet" href="../assets/reports/style.css">

<main class="content">
  <div class="container-fluid p-0">

    <div class="header bg-secondary m-2 p-3 print-hide" style="border-radius:8px;">
      <div class="container">
        <h1 class="mb-0 text-white">Open Halls Report</h1>
        <div class="text-white-50">Pick Year/Semester/Term, Campus and Shift to see halls with allocations.</div>
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

    <center>
      <div class="results-section" id="resultsSection">
        <div class="row">
          <div class="col-12">
            <div class="d-flex justify-content-center align-items-center mb-3 print-hide">
              <h2 class="text-center"><i class="fas fa-list-alt me-2"></i>Halls Open</h2>
            </div>
            <div id="summaryLine" class="mb-3"></div>
          </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="fw-bold">Report Halls Open!</h3>
      <!-- NEW: Add Hall button -->
    </div>
    <div class="card-body">
   <div id="hallCards">
            <div class="table-reponsive table-bordered table-striped table-hover">
                <table id="hallsTable" class="table table-striped" style="width:100%; text-align: center; border: ">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Hall Name</th>
                        <th>Capacity</th>
                        <th>Allocated Students</th>
                    </tr>
                    </thead>
                    <tbody style="font-size:12px;"></tbody>
                </table>
            </div>
        </div>
    </div>
        </div>

       

        <div class="row mt-5 print-hide">
          <div class="col-12 d-flex justify-content-center flex-wrap gap-3">
            <button id="btnExportExcel" class="btn btn-success">
              <i class="fas fa-file-excel me-2"></i> Export to Excel
            </button>
          </div>
        </div>
      </div>
    </center>

  </div>
</main>

<?php include '../layouts/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/report_halls_open.js?v=1"></script>
