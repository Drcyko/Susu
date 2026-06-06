</div> <!-- end container from header.php -->

<footer class="bg-dark text-white text-center py-3 mt-5">
  <div class="container">
    <p class="mb-0">&copy; <?= date('Y') ?> S&L Manager. All rights reserved.</p>
    <small class="text-muted">Logged in as: <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest' ?> | Role: <?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'N/A' ?></small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Enable Bootstrap tooltips everywhere
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

// Enable Bootstrap popovers everywhere
const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

// Auto-hide alerts after 4 seconds
setTimeout(() => {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    const bsAlert = new bootstrap.Alert(alert);
    bsAlert.close();
  });
}, 4000);
</script>

</body>
</html>
