/* ===== SCAN ABSENSI SISWA - JAVASCRIPT ===== */

$(document).ready(function() {
    const SCAN_ENDPOINT = '../pages/scan_proses.php';
    const OFFLINE_QUEUE_KEY = 'absensi_offline_queue';
    const MIN_SCAN_INTERVAL_MS = 2000;
    const DEFAULT_STUDENT_IMAGE = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22160%22 viewBox=%220 0 160 160%22%3E%3Crect width=%22160%22 height=%22160%22 fill=%22%23e5e7eb%22/%3E%3Ccircle cx=%2280%22 cy=%2260%22 r=%2235%22 fill=%22%239ca3af%22/%3E%3Crect x=%2240%22 y=%22105%22 width=%2280%22 height=%2240%22 rx=%2220%22 fill=%22%239ca3af%22/%3E%3C/svg%3E';

    const $input = $('#barcode-input');
    const audioSuccess = document.getElementById('sound-success');
    const audioError = document.getElementById('sound-error');
    let scannerInstance = null;
    let cameraActive = true;
    let lastScanAt = 0;
    let audioEnabled = false;
    let offlineSyncInProgress = false;

    setupEventListeners();
    initializeClock();
    initializeScanner(); // Initialize directly without waiting for Instascan
    checkSystemStatus();

    function initializeClock() {
        updateClockAndDate();
        setInterval(updateClockAndDate, 1000);
    }

    function updateClockAndDate() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID', {
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $('#clock').text(timeString);
        $('#date-display').text(`${days[now.getDay()]}, ${String(now.getDate()).padStart(2, '0')} ${months[now.getMonth()]} ${now.getFullYear()}`);
    }

    function setupEventListeners() {
        $input.focus();

        $(document).on('click touchstart', function() {
            if (cameraActive) {
                $input.focus();
            }
        });

        $input.on('keypress', function(e) {
            if (e.which === 13) {
                prosesScan();
            }
        });

        $('#btn-proses').on('click', function() {
            if (!audioEnabled) {
                audioEnabled = true;
                updateSystemStatus('audio', true, 'Audio aktif');
            }
            prosesScan();
        });

        $('#btn-camera').on('click', toggleCamera);
        $('#btn-refresh').on('click', refreshScanner);
        $('#btn-settings').on('click', openSettings);
        window.addEventListener('online', syncOfflineQueue);
    }

    function initializeScanner() {
        if (typeof JSQRScanner === 'undefined') {
            updateSystemStatus('scanner', false, 'QR Scanner library tidak tersedia');
            showCameraPlaceholder();
            return;
        }

        const videoElement = document.getElementById('preview');
        if (!videoElement) {
            updateSystemStatus('scanner', false, 'Elemen video tidak ditemukan');
            return;
        }

        if (scannerInstance) {
            scannerInstance.stop();
        }

        scannerInstance = new JSQRScanner(videoElement, function(content) {
            if (content && content.trim() !== '') {
                $input.val(content);
                // Auto scan tanpa delay untuk feedback instan
                prosesScan();
            }
        });

        scannerInstance.start().then(function() {
            cameraActive = true;
            updateSystemStatus('scanner', true, 'Scanner aktif & siap');
        }).catch(function(err) {
            console.error('Camera start failed', err);
            updateSystemStatus('scanner', false, 'Gagal memulai kamera');
            showCameraPlaceholder();
        });
    }

    function toggleCamera() {
        const $btn = $('#btn-camera');
        if (!cameraActive && scannerInstance) {
            initializeScanner();
            $btn.addClass('active');
            return;
        }

        if (scannerInstance) {
            scannerInstance.stop();
        }
        cameraActive = false;
        $btn.removeClass('active');
        updateSystemStatus('scanner', false, 'Scanner dihentikan sementara');
    }

    function refreshScanner() {
        if (scannerInstance) {
            scannerInstance.stop();
        }
        $input.val('').prop('disabled', false).css('opacity', 1).focus();
        showScanState('info', 'Scanner direset. Siap untuk scan berikutnya.');
        setTimeout(initializeScanner, 1200);
    }

    function openSettings() {
        Swal.fire({
            title: 'Pengaturan Scanner',
            html: `
                <div style="text-align: left;">
                    <p><strong>Delay anti-spam:</strong></p>
                    <p><small>Minimal 2 detik untuk mencegah double scan.</small></p>
                </div>
            `,
            confirmButtonText: 'Tutup'
        });
    }

    function prosesScan() {
        const value = $input.val().trim();
        if (value === '') {
            showScanState('error', 'Kode scan kosong. Silakan ulangi.');
            return;
        }

        const now = Date.now();
        if (now - lastScanAt < MIN_SCAN_INTERVAL_MS) {
            showScanState('error', 'Tunggu 2 detik sebelum scan ulang.');
            return;
        }
        lastScanAt = now;

        // Show processing state immediately
        showScanState('processing', 'Memproses scan...');

        const payload = { barcode: value };

        if (!navigator.onLine) {
            queueOfflineScan(payload);
            showScanState('offline', 'Offline: Data tersimpan & akan disinkronkan saat online');
            return;
        }

        $.ajax({
            url: SCAN_ENDPOINT,
            type: 'POST',
            data: payload,
            dataType: 'json',
            timeout: 5000, // Faster timeout for instant feedback
            beforeSend: function() {
                console.log('API Call:', SCAN_ENDPOINT, payload);
            },
            success: function(response) {
                console.log('API Response:', response);
                if (response.status === 'success') {
                    showScanState('success', response.data);
                } else {
                    showScanState('error', response.message || 'Scan gagal');
                }
            },
            error: function(xhr, status, error) {
                console.error('API Error:', status, error, xhr.responseText);
                const message = xhr.responseJSON?.message ||
                    (status === 'timeout' ? 'Timeout - Server tidak merespons' : 'Gagal menghubungi server');
                showScanState('error', message);
            }
        });
    }

    function showScanState(state, data) {
        const $resultDisplay = $('#result-display');
        let html = '';
        let autoResetDelay = 2500; // 2.5 detik default

        switch(state) {
            case 'processing':
                html = `
                    <div class="last-scan-card processing">
                        <div class="empty-state">
                            <i class="fas fa-spinner fa-spin" style="color: var(--primary-blue); font-size: 3rem;"></i>
                            <p style="font-weight: 500; margin-bottom: 4px; color: var(--primary-blue);">Memproses scan...</p>
                            <p style="font-size: 0.85rem; color: #6b7280;">Mohon tunggu sebentar</p>
                        </div>
                    </div>
                `;
                autoResetDelay = 3000; // Processing state lebih lama
                break;

            case 'success':
                // Update stat counters
                updateStatCounters(data.status_absen);

                // Determine notification style based on status
                let notificationStyle = {
                    icon: 'success',
                    background: '#d1fae5',
                    color: '#065f46',
                    title: data.nama
                };

                let statusBadgeClass = 'success';
                let statusIcon = '✅';

                if (data.status === 'terlambat') {
                    notificationStyle = {
                        icon: 'warning',
                        background: '#fef3c7',
                        color: '#92400e',
                        title: 'TERLAMBAT - ' + data.nama
                    };
                    statusBadgeClass = 'warning';
                    statusIcon = '⚠️';
                } else if (data.status === 'tepat_waktu') {
                    statusIcon = '⏰';
                }

                html = `
                    <div class="last-scan-card success-state">
                        <div class="last-scan-content">
                            <img src="${data.foto ? '../assets/img/siswa/' + data.foto : DEFAULT_STUDENT_IMAGE}"
                                 alt="${data.nama}" class="student-photo" onerror="this.src='${DEFAULT_STUDENT_IMAGE}'">
                            <div class="scan-details">
                                <h6>${statusIcon} SCAN BERHASIL</h6>
                                <strong>${data.nama}</strong>
                                <p><small>Kelas: ${data.kelas}</small></p>
                                <p><small>Waktu: ${data.jam}</small></p>
                                ${data.keterlambatan > 0 ? `<p><small>Terlambat: ${data.keterlambatan} menit</small></p>` : ''}
                                <span class="scan-badge ${statusBadgeClass}">${data.status_absen}</span>
                            </div>
                        </div>
                    </div>
                `;

                // Play success sound
                playAudio(audioSuccess);

                // Show SweetAlert with appropriate styling
                Swal.fire({
                    icon: notificationStyle.icon,
                    title: notificationStyle.title,
                    html: `${data.status_absen} pada ${data.jam}${data.keterlambatan > 0 ? '<br><small>Terlambat: ' + data.keterlambatan + ' menit</small>' : ''}`,
                    timer: data.status === 'terlambat' ? 4000 : 2500,
                    showConfirmButton: false,
                    position: 'center',
                    toast: true,
                    background: notificationStyle.background,
                    color: notificationStyle.color
                });
                break;

            case 'error':
                html = `
                    <div class="last-scan-card error-state">
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle" style="color: var(--error-red); font-size: 3rem;"></i>
                            <p style="font-weight: 500; margin-bottom: 4px; color: var(--error-red);">❌ SCAN GAGAL</p>
                            <p style="font-size: 0.85rem; color: #dc2626;">${data}</p>
                        </div>
                    </div>
                `;

                // Play error sound
                playAudio(audioError);

                // Show SweetAlert for error
                Swal.fire({
                    icon: 'error',
                    title: 'Scan Gagal',
                    text: data,
                    timer: 2000,
                    showConfirmButton: false,
                    position: 'center',
                    toast: true,
                    background: '#fef2f2',
                    color: '#dc2626'
                });
                break;

            case 'offline':
                html = `
                    <div class="last-scan-card offline-state">
                        <div class="empty-state">
                            <i class="fas fa-wifi-slash" style="color: #f59e0b; font-size: 3rem;"></i>
                            <p style="font-weight: 500; margin-bottom: 4px; color: #f59e0b;">📱 MODE OFFLINE</p>
                            <p style="font-size: 0.85rem; color: #d97706;">${data}</p>
                        </div>
                    </div>
                `;
                autoResetDelay = 4000; // Offline state lebih lama
                break;
        }

        // Apply the state immediately
        $resultDisplay.html(html);

        // Auto reset after delay
        setTimeout(() => {
            $resultDisplay.html(`
                <div class="last-scan-card">
                    <div class="empty-state">
                        <i class="fas fa-id-card"></i>
                        <p style="font-weight: 500; margin-bottom: 4px;">Menunggu Scan</p>
                        <p style="font-size: 0.85rem;">Arahkan QR/Barcode ke kamera</p>
                    </div>
                </div>
            `);
            // Reset input
            $input.val('').focus();
        }, autoResetDelay);
    }

    function updateStatCounters(statusAbsensi) {
        const hadirVal = parseInt($('#stat-hadir').text()) || 0;
        const telatVal = parseInt($('#stat-telat').text()) || 0;
        const belumVal = parseInt($('#stat-belum').text()) || 0;

        if (statusAbsensi === 'Hadir') {
            $('#stat-hadir').text(hadirVal + 1);
            $('#stat-belum').text(Math.max(0, belumVal - 1));
        } else if (statusAbsensi === 'Terlambat') {
            $('#stat-telat').text(telatVal + 1);
            $('#stat-belum').text(Math.max(0, belumVal - 1));
        }
    }

    function playAudio(audioElement) {
        if (!audioEnabled || !audioElement) {
            return;
        }
        audioElement.currentTime = 0;
        audioElement.play().catch(() => {});
    }

    function queueOfflineScan(payload) {
        if (!payload || !payload.barcode) {
            return;
        }
        const queue = getOfflineQueue();
        queue.push({ barcode: payload.barcode, created_at: new Date().toISOString() });
        setOfflineQueue(queue);
        updateSystemStatus('connection', false, 'Offline mode: data disimpan sementara.');
        syncOfflineQueue();
    }

    function getOfflineQueue() {
        try {
            const raw = localStorage.getItem(OFFLINE_QUEUE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (err) {
            return [];
        }
    }

    function setOfflineQueue(queue) {
        localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    }

    function syncOfflineQueue() {
        if (offlineSyncInProgress || !navigator.onLine) {
            return;
        }

        const queue = getOfflineQueue();
        if (!queue.length) {
            updateSystemStatus('connection', true, 'Koneksi normal');
            return;
        }

        offlineSyncInProgress = true;
        const item = queue[0];

        $.ajax({
            url: SCAN_ENDPOINT,
            type: 'POST',
            data: { barcode: item.barcode },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.status === 'success') {
                    queue.shift();
                    setOfflineQueue(queue);
                    updateSystemStatus('connection', true, 'Sinkronisasi offline berhasil');
                } else {
                    updateSystemStatus('connection', false, 'Sinkronisasi offline gagal: ' + (response.message || '')); 
                }
            },
            error: function(xhr, status) {
                updateSystemStatus('connection', false, 'Tidak dapat sinkronisasi offline sekarang');
            },
            complete: function() {
                offlineSyncInProgress = false;
                if (queue.length > 0 && navigator.onLine) {
                    setTimeout(syncOfflineQueue, 1200);
                }
            }
        });
    }

    function checkSystemStatus() {
        checkAudioPermission();
        checkConnection();
        // Try to sync any existing offline data
        if (navigator.onLine) {
            syncOfflineQueue();
        }
    }

    function checkAudioPermission() {
        if (!audioSuccess || !audioError) {
            updateSystemStatus('audio', false, 'File audio tidak ditemukan');
            return;
        }
        const playPromise = audioSuccess.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                audioSuccess.pause();
                audioSuccess.currentTime = 0;
                audioEnabled = true;
                updateSystemStatus('audio', true, 'Audio aktif');
            }).catch(() => {
                audioEnabled = false;
                updateSystemStatus('audio', false, 'Klik tombol scan untuk mengaktifkan audio');
            });
        }
    }

    function checkConnection() {
        $.ajax({
            url: SCAN_ENDPOINT,
            type: 'POST',
            data: { check: true },
            dataType: 'json',
            timeout: 5000,
            success: function() {
                updateSystemStatus('connection', true, 'Koneksi database OK');
                syncOfflineQueue();
            },
            error: function(xhr, status) {
                let message = 'Offline atau server tidak tersedia';
                if (xhr.status === 404) {
                    message = 'API endpoint tidak ditemukan';
                } else if (xhr.status === 500) {
                    message = 'Error server internal';
                } else if (status === 'timeout') {
                    message = 'Timeout koneksi';
                }
                updateSystemStatus('connection', false, message);
            }
        });
    }

    function updateSystemStatus(type, status, message) {
        const $icon = $(`#status-${type}-check`).prev('.status-icon');
        const $text = $(`#status-${type}-check`);
        if (status) {
            $icon.removeClass('error').addClass('success').html('<i class="fas fa-check"></i>');
            $text.removeClass('error').addClass('success').text(message);
        } else {
            $icon.removeClass('success').addClass('error').html('<i class="fas fa-times"></i>');
            $text.removeClass('success').addClass('error').text(message);
        }
    }

    function updateLastSync() {
        const now = new Date();
        $('#last-sync').text(now.toLocaleTimeString('id-ID', { hour12: false }));
    }

    function showCameraPlaceholder() {
        const $preview = $('#preview');
        $preview.html(`
            <div class="camera-placeholder">
                <i class="fas fa-camera" style="font-size: 3rem; color: #9ca3af;"></i>
                <p style="margin-top: 10px; color: #6b7280;">Kamera tidak tersedia</p>
            </div>
        `);
    }
