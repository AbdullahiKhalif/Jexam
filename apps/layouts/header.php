<div class="main">
        <nav class="navbar navbar-expand navbar-light navbar-bg">
          <a class="sidebar-toggle">
            <i class="hamburger align-self-center"></i>
          </a>

          <form class="d-none d-sm-inline-block">
            <div class="input-group input-group-navbar">
              <input
                type="text"
                class="form-control"
                placeholder="Search projects…"
                aria-label="Search"
              />
              <button class="btn" type="button">
                <i class="align-middle" data-feather="search"></i>
              </button>
            </div>
          </form>

          

          <div class="navbar-collapse collapse">
            <ul class="navbar-nav navbar-align">

              <li class="nav-item dropdown">
                <a
                  class="nav-icon dropdown-toggle d-inline-block d-sm-none"
                  href="#"
                  data-bs-toggle="dropdown"
                >
                  <i class="align-middle" data-feather="settings"></i>
                </a>

                <a
                  class="nav-link dropdown-toggle d-none d-sm-inline-block"
                  href="#"
                  data-bs-toggle="dropdown"
                >
                  
                  <span class="text-dark fs-6 fw-bold" style="text-transform: uppercase;"><?php echo $_SESSION['name'] ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                  <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mdlChangePwd">
                    Change Password
                  </button>
                  <a class="dropdown-item" href="../api/logout.php">Sign out</a>
                </div>
              </li>
            </ul>
          </div>


          <!-- Change Password Modal -->


        </nav>

<div class="modal fade" id="mdlChangePwd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm"> <!-- 👈 Added modal-sm -->
    <form class="modal-content" id="frmChangePwd" autocomplete="off" action="javascript:void(0)" onsubmit="return false;">
      <div class="modal-header">
        <h4 class="modal-title">Change Password!</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-1">
          <label class="form-label fw-bold">Username</label>
          <input type="text" class="form-control text-transform-uppercase" value="<?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES); ?>" disabled>
          <div class="form-text">This is your current username and cannot be changed here.</div>
        </div>

        <div class="mb-1">
          <label class="form-label fw-bold" for="new_password">New Password</label>
          <input type="password" class="form-control border-2" id="new_password" minlength="3" required>
          <div class="form-text">At least 3 characters.</div>
        </div>

        <div class="mb-1">
          <label class="form-label fw-bold" for="confirm_password">Confirm New Password</label>
          <input type="password" class="form-control border-2" id="confirm_password" minlength="3" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-info" id="btnChangePwd">Update Password</button>
      </div>
    </form>
  </div>
</div>




<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
  // Run after DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  function init() {
    const modalEl = document.getElementById("mdlChangePwd");
    const form    = document.getElementById("frmChangePwd");
    const newPwd  = document.getElementById("new_password");
    const confPwd = document.getElementById("confirm_password");

    // Hard stop form navigation in every case
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const np = (newPwd.value || "").trim();
      const cp = (confPwd.value || "").trim();

      if (np.length < 3)  return notify("Oops", "Password must be at least 3 characters.", "warning");
      if (np !== cp)      return notify("Mismatch", "New password and confirm password do not match.", "error");

      // Build form body (x-www-form-urlencoded)
      const body = new URLSearchParams({
        action: "change_password",
        new_password: np,
        confirm_password: cp
      });

      fetch("../api/users.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body
      })
      .then(async (res) => {
        // If server ever sends HTML (login page/warning), this avoids a hard crash
        const ct = res.headers.get("content-type") || "";
        const text = await res.text();
        if (!ct.includes("application/json")) {
          console.error("Non-JSON response:", text.slice(0, 500));
          throw new Error("Server returned non-JSON (check Network tab for details).");
        }
        return JSON.parse(text);
      })
      .then((resp) => {
        if (resp && resp.status) {
          hideModal(modalEl);
          newPwd.value = "";
          confPwd.value = "";
          notify("Done", "Password updated successfully.", "success");
        } else {
          notify("Error", (resp && resp.data) ? resp.data : "Failed to update password.", "error");
        }
      })
      .catch((err) => {
        console.error("change_password error:", err);
        notify("Request failed", "Open DevTools → Network → users.php to inspect the response.", "error");
      });
    });

    // Clear fields when modal opens
    modalEl.addEventListener("show.bs.modal", function () {
      newPwd.value = "";
      confPwd.value = "";
    });
  }

  function hideModal(el) {
    const inst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    inst.hide();
  }

  function notify(title, msg, type) {
    if (window.Swal) Swal.fire(title, msg, type || "info");
    else alert((title ? title + ": " : "") + msg);
  }
})();
</script>

