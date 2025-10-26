<?php
include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">
    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Campuses</h4>
        <button class="btn btn-primary" id="btnAddCampus">Add Campus</button>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped align-middle" id="tblCampus">
            <thead>
              <tr>
                <th style="width:60px">#</th>
                <th>Name</th>
                <th>Code</th>
                <th>Campus Coordinators</th>
                <th style="width:160px">Status</th>
                <th style="width:160px">Actions</th>
              </tr>
            </thead>
            <tbody><!-- filled by campuses.js --></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Add/Edit Modal -->
<div class="modal fade" id="mdlCampus" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="frmCampus">
      <div class="modal-header">
        <h5 class="modal-title">Add / Edit Campus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="campus_id">
        <div class="mb-2">
          <label class="form-label" for="campus_name">Name</label>
          <input class="form-control" id="campus_name" required>
        </div>
        <div class="mb-2">
          <label class="form-label" for="campus_code">Code</label>
          <input class="form-control" id="campus_code" required>
        </div>
        <div class="mb-2">
          <label class="form-label" for="leaders">Campus Coordinators</label>
          <input class="form-control" id="leaders" required>
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

<!-- Vendor scripts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-6X3dj+e0X+..." crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Page script (expects the toggle + list logic inside) -->
<script src="../js/campuses.js"></script>
