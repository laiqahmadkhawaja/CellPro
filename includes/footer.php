<?php
// includes/footer.php
?>
        </main>
        
        <footer class="mt-auto py-3 bg-white border-top text-center text-muted small">
            <div class="container-fluid">
                CellPro ERP &copy; <?= date('Y') ?> &mdash; Multi-Branch Mobile Shop Management System
            </div>
        </footer>
    </div>
</div>

<!-- Slide-Out Side Panel Overlay Container (Zero-Modal Compliance) -->
<div id="side-panel-overlay" class="side-panel-overlay"></div>

<!-- Local Vendor Scripts -->
<script src="<?= BASE_URL ?>assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/select2/select2.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>

</body>
</html>
<?php
// Flush output buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
