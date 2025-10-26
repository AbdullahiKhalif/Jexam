<footer class="footer">
  <div class="container-fluid py-3 text-start">
    <h6 id="copyright" class="fs-6"></h6>
  </div>
</footer>

<script>
  // Dynamically set current year and organization name
  const currentYear = new Date().getFullYear();
  const organizationName = "JUST EXAM OFFICE";
  document.getElementById("copyright").innerHTML =
    `<span class="text-muted"> &copy; ${currentYear} ${organizationName} by </span> <span class="text-primary" style="font-weight: bold;"> <a href="../views/dashboard.php">Jamhuriya University</a></span>`;
</script>

<!-- JS Dependencies -->
<script src="../../assets/js/app.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
