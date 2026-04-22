<footer class="text-center py-4 text-muted site-footer">
        <small>&copy; <?php echo date('Y'); ?> E-Absensi SMP - Developed by Mas Hari</small>
    </footer>

    <?php
    $prefix = basename(dirname($_SERVER['PHP_SELF'])) === 'pages' ? '../' : '';
    ?>
    <script src="<?= $prefix ?>assets/js/site.js"></script>
</body>
</html>