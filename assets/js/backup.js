document.addEventListener('DOMContentLoaded', function() {
    const backupBtn = document.getElementById('backupBtn');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const statusText = document.getElementById('statusText');

    if (backupBtn) {
        backupBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Show confirmation
            Swal.fire({
                title: 'Mulai Backup',
                text: 'Proses backup akan memakan waktu beberapa saat. Pastikan koneksi internet stabil.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Backup Sekarang',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#0d6efd'
            }).then((result) => {
                if (result.isConfirmed) {
                    startBackup();
                }
            });
        });
    }

    function startBackup() {
        // Disable button and show progress
        backupBtn.disabled = true;
        backupBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Membuat Backup...';

        // Show progress container
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        statusText.textContent = 'Memulai proses backup...';

        // Simulate progress (in real implementation, this would be handled by server)
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 100) progress = 100;

            progressBar.style.width = progress + '%';

            if (progress < 30) {
                statusText.textContent = 'Mengumpulkan data...';
            } else if (progress < 70) {
                statusText.textContent = 'Memproses tabel database...';
            } else if (progress < 90) {
                statusText.textContent = 'Membuat file backup...';
            } else {
                statusText.textContent = 'Menyelesaikan backup...';
            }

            if (progress >= 100) {
                clearInterval(interval);
                setTimeout(() => {
                    statusText.textContent = 'Backup selesai! Mengunduh file...';
                    // In real implementation, trigger download here
                    setTimeout(() => {
                        window.location.href = 'api/backup.php';
                    }, 1000);
                }, 500);
            }
        }, 300);
    }
});