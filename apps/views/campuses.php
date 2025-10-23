<?php
#include '../layouts/protectedPage.php';
include '../layouts/sidebar.php';
include '../layouts/header.php';
?>
<main class="content">
  <div class="container-fluid p-0">
    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between">
        <h4 class="mb-0">Campuses</h4>
        <button class="btn btn-primary" id="btnAddCampus">Add Campus</button>
      </div>
      <div class="card-body">
        <table class="table table-striped" id="tblCampus">
          <thead><tr><th>#</th><th>Name</th><th>Code</th> <th>Campus Cordinatores</th><th style="width:120px">Actions</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<div class="modal fade" id="mdlCampus" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="frmCampus">
      <div class="modal-header">
        <h5 class="modal-title">Add / Edit Campus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="campus_id">
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" id="campus_name" required></div>
        <div class="mb-2"><label class="form-label">Code</label><input class="form-control" id="campus_code" required></div>
        <div class="mb-2"><label class="form-label">Campus Cordinatores</label><input class="form-control" id="leaders" required></div>
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
<script src="../js/campuses.js"></script>
