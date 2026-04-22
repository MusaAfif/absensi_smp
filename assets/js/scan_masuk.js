/* ===== SCAN ABSEN MASUK - JAVASCRIPT ===== */

$(document).ready(function() {
    const BASE_PATH = window.BASE_PATH || '';
    // Route through index.php for PHP dev server compatibility
    const SCAN_ENDPOINT = '/' + BASE_PATH.replace(/^\/+|\/+$/g, '') + (BASE_PATH ? '/' : '') + '?api=attendance';
    const OFFLINE_QUEUE_KEY = 'absensi_masuk_offline_queue';
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
    let scanning = false;
    let autoReloadScheduled = false;
    const AUTO_RELOAD_DELAY_MS = 2500;

    setupEventListeners();
    initializeClock();
    initializeScanner();
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
                handleScan();
            }
        });

        $('#btn-proses').on('click', function() {
            if (!audioEnabled) {
                audioEnabled = true;
                updateSystemStatus('audio', true, 'Audio aktif');
            }
            handleScan();
        });

        $('#btn-camera').on('click', toggleCamera);
        $('#btn-refresh').on('click', refreshScanner);
        $('#btn-settings').on('click', openSettings);
        window.addEventListener('online', syncOfflineQueue);
    }

    function initializeScanner() {
        if (typeof JSQRScanner === 'undefined') {
            console.warn('QR Scanner library tidak tersedia');
            updateSystemStatus('scanner', false, 'QR Scanner tidak tersedia');
            return; // Continue without camera
        }

        const videoElement = document.getElementById('preview');
        if (!videoElement) {
            console.warn('Elemen video tidak ditemukan');
            updateSystemStatus('scanner', false, 'Video element not found');
            return;
        }

        if (scannerInstance) {
            scannerInstance.stop();
        }

        scannerInstance = new JSQRScanner(videoElement, function(content) {
            if (content && content.trim() !== '') {
                $input.val(content);
                handleScan();
            }
        });

        scannerInstance.start().then(function() {
            cameraActive = true;
            console.log('✓ Camera started successfully');
            updateSystemStatus('scanner', true, 'Scanner aktif & siap');
        }).catch(function(err) {
            console.warn('Camera start error:', err);
            cameraActive = false;
            
            let errorMessage = 'Kamera tidak tersedia - gunakan manual input';
            if (err.name === 'NotReadableError') {
                errorMessage = 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi lain yang menggunakan kamera.';
            } else if (err.name === 'NotAllowedError') {
                errorMessage = 'Akses kamera ditolak. Izinkan akses kamera di browser settings.';
            } else if (err.name === 'NotFoundError') {
                errorMessage = 'Kamera tidak ditemukan. Pastikan kamera terhubung.';
            }
            
            updateSystemStatus('scanner', false, errorMessage);
            showScanState('error', errorMessage + ' Anda masih bisa input manual.');
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

    async function handleScan() {
        if (scanning) return;
        scanning = true;

        const value = $input.val().trim();
        if (value === '' || value.length < 2) {
            showScanState('error', 'Kode scan kosong atau tidak valid. Silakan ulangi.');
            scanning = false;
            return;
        }

        // Validasi format identifier (harus alphanumeric atau dengan karakter tertentu)
        if (!/^[a-zA-Z0-9\-_\.]+$/.test(value)) {
            showScanState('error', 'Format kode tidak valid. Gunakan huruf, angka, -, _, atau .');
            scanning = false;
            return;
        }

        const now = Date.now();
        if (now - lastScanAt < MIN_SCAN_INTERVAL_MS) {
            showScanState('error', 'Tunggu 2 detik sebelum scan ulang.');
            scanning = false;
            return;
        }
        lastScanAt = now;

        showScanState('processing', 'Memproses scan...');

        const payload = { identifier: value };

        if (!navigator.onLine) {
            queueOfflineScan(payload);
            showScanState('offline', 'Offline: Data tersimpan & akan disinkronkan saat online');
            scanning = false;
            return;
        }

        try {
            const response = await fetch(SCAN_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            let data = null;
            const responseText = await response.text();
            try {
                data = responseText ? JSON.parse(responseText) : null;
            } catch (parseError) {
                console.error('Failed to parse API response:', parseError, responseText);
                if (response.status === 422) {
                    showScanState('error', 'Absensi tidak dapat diproses. Kemungkinan sudah absen atau di luar jadwal.');
                } else if (response.status === 404) {
                    showScanState('error', 'Data siswa tidak ditemukan. Periksa kode QR/barcode.');
                } else {
                    showScanState('error', 'Server error: ' + response.status);
                }
                return;
            }

            if (!response.ok) {
                console.error('API Response Error:', response.status, data);
                if (data?.message) {
                    showScanState('error', data.message);
                } else if (response.status === 422) {
                    showScanState('error', 'Absensi tidak dapat diproses. Kemungkinan sudah absen atau di luar jadwal.');
                } else if (response.status === 404) {
                    showScanState('error', 'Data siswa tidak ditemukan. Periksa kode QR/barcode.');
                } else {
                    showScanState('error', 'Server error: ' + response.status);
                }
                return;
            }

            if (data && data.status === 'success') {
                showScanState('success', data.data || data);
                updateStatCounters();
            } else {
                showScanState('error', data?.message || 'Scan gagal');
            }
        } catch (error) {
            console.error('API Error:', error.message);
            showScanState('error', 'Gagal menghubungi server: ' + error.message);
        } finally {
            setTimeout(() => scanning = false, 2000);
        }
    }

    function showScanState(state, data) {
        const $resultDisplay = $('#result-display');
        let html = '';
        let autoResetDelay = 2500;

        switch(state) {
            case 'processing':
                html = `
                    <div class="last-scan-card processing">
                        <div class="empty-state">
                            <i class="fas fa-spinner fa-spin" style="color: #10b981; font-size: 3rem;"></i>
                            <p style="font-weight: 500; margin-bottom: 4px; color: #10b981;">Memproses scan masuk...</p>
                            <p style="font-size: 0.85rem; color: #6b7280;">Mohon tunggu sebentar</p>
                        </div>
                    </div>
                `;
                autoResetDelay = 3000;
                break;

            case 'success':
                const phase = data.scan_phase || 'Masuk';
                const statusClass = data.status_absen === 'Terlambat' ? 'warning' : 'success';
                const statusIcon = phase === 'Masuk' ? 'fa-sign-in-alt' : 'fa-sign-out-alt';
                const statusText = phase === 'Masuk' ? 'MASUK' : 'PULANG';
                const message = data.message || (phase === 'Masuk' ? 'Absensi masuk berhasil.' : 'Absensi pulang berhasil.');
                
                html = `
                    <div class="last-scan-card success-state">
                        <div class="last-scan-content">
                            <img src="${data.foto ? '../assets/img/siswa/' + data.foto : DEFAULT_STUDENT_IMAGE}"
                                 alt="${data.nama}" class="student-photo" onerror="this.src='${DEFAULT_STUDENT_IMAGE}'">
                            <div class="scan-details">
                                <h6>✅ ${message}</h6>
                                <strong>${data.nama}</strong>
                                <p><small>Kelas: ${data.kelas}</small></p>
                                <p><small>Waktu: ${data.jam}</small></p>
                                <span class="scan-badge ${statusClass}"><i class="fas ${statusIcon} me-1"></i>${statusText} - ${data.status_absen}</span>
                            </div>
                        </div>
                    </div>
                `;

                playAudio(audioSuccess);

                Swal.fire({
                    icon: 'success',
                    title: data.nama,
                    text: 'Absen masuk berhasil pada ' + data.jam,
                    timer: 2000,
                    showConfirmButton: false,
                    position: 'center',
                    toast: true,
                    background: '#d1fae5',
                    color: '#065f46'
                });

                if (!autoReloadScheduled) {
                    autoReloadScheduled = true;
                    setTimeout(() => {
                        window.location.reload();
                    }, AUTO_RELOAD_DELAY_MS);
                }
                break;

            case 'error':
                html = `
                    <div class="last-scan-card error-state">
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle" style="color: #dc2626; font-size: 3rem;"></i>
                            <p style="font-weight: 500; margin-bottom: 4px; color: #dc2626;">❌ SCAN MASUK GAGAL</p>
                            <p style="font-size: 0.85rem; color: #dc2626;">${data}</p>
                        </div>
                    </div>
                `;

                playAudio(audioError);

                Swal.fire({
                    icon: 'error',
                    title: 'Scan Masuk Gagal',
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
                autoResetDelay = 4000;
                break;
        }

        $resultDisplay.html(html);

        setTimeout(() => {
            $resultDisplay.html(`
                <div class="last-scan-card">
                    <div class="empty-state">
                        <i class="fas fa-id-card"></i>
                        <p style="font-weight: 500; margin-bottom: 4px;">Menunggu Scan Masuk</p>
                        <p style="font-size: 0.85rem;">Arahkan QR Code atau scan dengan barcode scanner</p>
                    </div>
                </div>
            `);
            $input.val('').focus();
        }, autoResetDelay);
    }

    function updateStatCounters() {
        const basePath = (BASE_PATH || '').replace(/\/+$/, '');
        const statsUrl = '/' + basePath.replace(/^\/+/, '') + (basePath ? '/' : '') + 'api/get_stats.php';
        fetch(statsUrl, { credentials: 'same-origin' })
            .then(function(res) { return res.ok ? res.json() : null; })
            .then(function(data) {
                if (!data) return;
                var $masuk  = $('#stat-masuk');
                var $pulang = $('#stat-pulang');
                var $belum  = $('#stat-belum');
                if ($masuk.length)  $masuk.text(data.hadir);
                if ($pulang.length) $pulang.text(data.hadir - data.belum >= 0 ? data.hadir - data.belum : 0);
                if ($belum.length)  $belum.text(data.belum);
            })
            .catch(function() { /* silent fail */ });
    }

    function queueOfflineScan(payload) {
        let queue = JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
        queue.push({
            payload: payload,
            timestamp: Date.now(),
            endpoint: SCAN_ENDPOINT
        });
        localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    }

    function syncOfflineQueue() {
        if (offlineSyncInProgress) return;

        let queue = JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
        if (queue.length === 0) return;

        offlineSyncInProgress = true;

        // Process queue items
        const processQueue = async () => {
            for (let i = 0; i < queue.length; i++) {
                const item = queue[i];
                try {
                    const response = await fetch(item.endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(item.payload)
                    });

                    if (response.ok) {
                        queue.splice(i, 1);
                        i--;
                    }
                } catch (error) {
                    console.error('Offline sync failed for item:', item, error);
                }
            }

            localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
            offlineSyncInProgress = false;

            if (queue.length > 0) {
                showScanState('offline', `Belum tersinkron: ${queue.length} data`);
            }
        };

        processQueue();
    }

    function checkSystemStatus() {
        setInterval(() => {
            updateSystemStatus('connection', navigator.onLine, navigator.onLine ? 'Data tersimpan' : 'Offline mode');
            $('#last-sync').text(new Date().toLocaleTimeString('id-ID', { hour12: false }));
        }, 30000);
    }

    function updateSystemStatus(type, isSuccess, message) {
        const $element = $(`#status-${type}-check`);
        const $icon = $element.prev('.status-icon');

        if (isSuccess) {
            $element.removeClass('error').addClass('success').text(message);
            $icon.removeClass('error').addClass('success').html('<i class="fas fa-check"></i>');
        } else {
            $element.removeClass('success').addClass('error').text(message);
            $icon.removeClass('success').addClass('error').html('<i class="fas fa-times"></i>');
        }
    }

    function showCameraPlaceholder() {
        $('#preview').hide();
        $('#camera-placeholder').show();
    }

    function playAudio(audioElement) {
        if (audioEnabled && audioElement) {
            audioElement.currentTime = 0;
            audioElement.play().catch(e => console.log('Audio play failed:', e));
        }
    }
});