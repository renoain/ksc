            </div> <!-- Close container-fluid -->
        </div> <!-- Close page-content-wrapper -->
    </div> <!-- Close wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('wrapper').classList.toggle('toggled');
        });
    </script>
    <script src="<?= SITE_URL ?>assets/js/admin.js"></script>
</body>
</html>