document.addEventListener('DOMContentLoaded', function() {
    var btnBackup = document.getElementById('btn-backup');
    var btnRestore = document.getElementById('btn-restore');

    if (btnBackup) {
        btnBackup.addEventListener('click', function() {
            Swal.fire({
                title: 'Backup Database',
                text: 'Buat salinan data sistem absensi untuk keamanan?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Backup',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#212529'
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = 'backup.php';
                }
            });
        });
    }

    if (btnRestore) {
        btnRestore.addEventListener('click', function(e) {
            e.preventDefault();

            Swal.fire({
                title: 'Restore Database',
                text: 'PERINGATAN: Proses restore akan MENGHAPUS semua data saat ini. Pastikan Anda memiliki backup yang valid.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Lanjut ke Restore',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545'
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = 'restore.php';
                }
            });
        });
    }
});
