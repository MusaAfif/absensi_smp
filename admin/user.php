<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/attendance_service.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
cek_role(['super_admin']);

require_once __DIR__ . '/service/recovery_service.php';
require_once __DIR__ . '/service/user_service.php';

CSRFProtection::init();

$recoveryService = new RecoveryService($conn);
$userService = new UserService($conn, $recoveryService);
$userService->ensureSchema();

$currentUserId = $_SESSION['id_user'] ?? null;
$admins = $userService->getAllAdmins($currentUserId);
$allCodes = [];
foreach ($admins as $admin) {
    $allCodes[$admin['id_user']] = $recoveryService->getCodesByUser((int)$admin['id_user']);
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
$generatedCodes = $_SESSION['generated_codes'] ?? null;
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['generated_codes']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management | Absensi SMP</title>
    <link href="<?= BASE_URL ?>assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/site.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<?php include __DIR__ . '/../includes/navbar.php'; ?>
    <div class="container-fluid px-4 py-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold">User Management</h1>
                <p class="text-muted mb-0">Kelola admin dan recovery code terpisah dari pengaturan.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button id="btnAddAdmin" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminModal">
                    <i class="fas fa-user-plus me-2"></i>Tambah Admin
                </button>
                <button id="btnResetAll" class="btn btn-outline-danger">Reset Semua Recovery Code</button>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Daftar Admin</h5>
                    <small class="text-muted">Tabel menampilkan username, nama, role, dan recovery code tersisa.</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="text-uppercase small text-secondary">
                                <th>Username</th>
                                <th>Nama Admin</th>
                                <th>Role</th>
                                <th class="text-center">Recovery Tersisa</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($admin['username']) ?></td>
                                    <td><?= htmlspecialchars($admin['nama_admin']) ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $admin['role']))) ?></td>
                                    <td class="text-center"><?= (int)$admin['recovery_left'] ?></td>
                                    <td class="text-center">
                                        <?php if ($admin['is_current_user']): ?>
                                            <span class="badge bg-success">Sedang Login</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-view-codes" data-user-id="<?= $admin['id_user'] ?>" title="Lihat recovery code">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-success me-1 btn-regenerate-codes" data-user-id="<?= $admin['id_user'] ?>" title="Generate kode baru">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning me-1 btn-edit-user"
                                            data-user-id="<?= $admin['id_user'] ?>"
                                            data-username="<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>"
                                            data-nama="<?= htmlspecialchars($admin['nama_admin'], ENT_QUOTES) ?>"
                                            data-role="<?= htmlspecialchars($admin['role'], ENT_QUOTES) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!$admin['is_current_user']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-user" data-user-id="<?= $admin['id_user'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada admin terdaftar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <form id="actionForm" method="POST" action="<?= BASE_URL ?>admin/process_user.php" class="d-none">
        <?= CSRFProtection::getTokenField() ?>
        <input type="hidden" name="action" id="actionField">
        <input type="hidden" name="id_user" id="idUserField">
    </form>

    <div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="adminForm" method="POST" action="<?= BASE_URL ?>admin/process_user.php">
                    <?= CSRFProtection::getTokenField() ?>
                    <div class="modal-header border-0">
                        <div>
                            <h5 class="modal-title" id="adminModalLabel">Tambah Admin</h5>
                            <p class="text-muted mb-0">Gunakan form untuk menambah atau mengubah data admin.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add" id="modalActionField">
                        <input type="hidden" name="id_user" id="modalIdField">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" id="usernameField" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select name="role" id="roleField" class="form-select" required>
                                    <option value="admin">Admin</option>
                                    <option value="super_admin">Super Admin</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Nama Admin</label>
                                <input type="text" name="nama_admin" id="namaField" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" id="passwordField" class="form-control" placeholder="Biarkan kosong jika tidak ingin mengubah">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Konfirmasi Password</label>
                                <input type="password" name="password_confirm" id="passwordConfirmField" class="form-control" placeholder="Biarkan kosong jika tidak ingin mengubah">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="codesModal" tabindex="-1" aria-labelledby="codesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="codesModalLabel">Recovery Codes</h5>
                        <p class="text-muted mb-0" id="codesModalSubtitle">Daftar kode cadangan admin.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th>Kode</th>
                                    <th>Status</th>
                                    <th>Dibuat</th>
                                </tr>
                            </thead>
                            <tbody id="codesTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const admins = <?= json_encode($admins, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const allCodes = <?= json_encode($allCodes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const currentUserId = <?= json_encode($currentUserId) ?>;

        document.addEventListener('DOMContentLoaded', function () {
            const adminModal = new bootstrap.Modal(document.getElementById('adminModal'));
            const codesModal = new bootstrap.Modal(document.getElementById('codesModal'));

            document.querySelectorAll('.btn-edit-user').forEach(button => {
                button.addEventListener('click', function () {
                    const userId = this.dataset.userId;
                    const username = this.dataset.username;
                    const nama = this.dataset.nama;
                    const role = this.dataset.role;

                    document.getElementById('adminModalLabel').textContent = 'Edit Admin';
                    document.getElementById('modalActionField').value = 'edit';
                    document.getElementById('modalIdField').value = userId;
                    document.getElementById('usernameField').value = username;
                    document.getElementById('usernameField').setAttribute('readonly', 'readonly');
                    document.getElementById('roleField').value = role;
                    document.getElementById('namaField').value = nama;
                    document.getElementById('passwordField').value = '';
                    document.getElementById('passwordConfirmField').value = '';
                    adminModal.show();
                });
            });

            document.getElementById('btnAddAdmin').addEventListener('click', function () {
                document.getElementById('adminModalLabel').textContent = 'Tambah Admin';
                document.getElementById('modalActionField').value = 'add';
                document.getElementById('modalIdField').value = '';
                document.getElementById('usernameField').removeAttribute('readonly');
                document.getElementById('usernameField').value = '';
                document.getElementById('roleField').value = 'admin';
                document.getElementById('namaField').value = '';
                document.getElementById('passwordField').value = '';
                document.getElementById('passwordConfirmField').value = '';
            });

            document.getElementById('adminForm').addEventListener('submit', function (e) {
                const password = document.getElementById('passwordField').value;
                const confirm = document.getElementById('passwordConfirmField').value;
                const action = document.getElementById('modalActionField').value;

                if (action === 'add') {
                    if (!password) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Password wajib diisi',
                            text: 'Password tidak boleh kosong ketika menambah admin.'
                        });
                    }
                    if (password && password !== confirm) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Konfirmasi tidak cocok',
                            text: 'Password dan konfirmasi harus sama.'
                        });
                    }
                }

                if (action === 'edit' && password && password !== confirm) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Konfirmasi tidak cocok',
                        text: 'Password dan konfirmasi harus sama.'
                    });
                }
            });

            document.querySelectorAll('.btn-delete-user').forEach(button => {
                button.addEventListener('click', function () {
                    const userId = this.dataset.userId;
                    Swal.fire({
                        title: 'Hapus admin?',
                        text: 'Aksi ini akan menghapus admin beserta recovery code-nya.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Hapus',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#d33'
                    }).then(result => {
                        if (result.isConfirmed) {
                            document.getElementById('actionField').value = 'delete';
                            document.getElementById('idUserField').value = userId;
                            document.getElementById('actionForm').submit();
                        }
                    });
                });
            });

            document.querySelectorAll('.btn-view-codes').forEach(button => {
                button.addEventListener('click', function () {
                    const userId = this.dataset.userId;
                    const codes = allCodes[userId] || [];
                    const tbody = document.getElementById('codesTableBody');
                    tbody.innerHTML = '';
                    codes.forEach(code => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><code>${code.code}</code></td>
                            <td>${code.used ? '<span class="badge bg-danger">Used</span>' : '<span class="badge bg-success">Available</span>'}</td>
                            <td>${code.created_at}</td>
                        `;
                        tbody.appendChild(row);
                    });
                    const selected = admins.find(a => a.id_user == userId);
                    document.getElementById('codesModalSubtitle').textContent = `Kode untuk admin: ${selected ? selected.username : ''}`;
                    codesModal.show();
                });
            });

            document.querySelectorAll('.btn-regenerate-codes').forEach(button => {
                button.addEventListener('click', function () {
                    const userId = this.dataset.userId;
                    Swal.fire({
                        title: 'Generate recovery code baru?',
                        text: 'Kode baru akan ditambahkan ke akun admin.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Generate',
                        cancelButtonText: 'Batal'
                    }).then(result => {
                        if (result.isConfirmed) {
                            document.getElementById('actionField').value = 'regenerate_codes';
                            document.getElementById('idUserField').value = userId;
                            document.getElementById('actionForm').submit();
                        }
                    });
                });
            });

            document.getElementById('btnResetAll').addEventListener('click', function () {
                Swal.fire({
                    title: 'Reset semua recovery code?',
                    text: 'Semua kode recovery akan ditandai belum digunakan.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Reset Semua',
                    cancelButtonText: 'Batal'
                }).then(result => {
                    if (result.isConfirmed) {
                        document.getElementById('actionField').value = 'reset_all_codes';
                        document.getElementById('idUserField').value = '';
                        document.getElementById('actionForm').submit();
                    }
                });
            });

            <?php if ($success): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: <?= json_encode($success) ?>,
                    timer: 4000,
                    timerProgressBar: true
                });
            <?php endif; ?>

            <?php if ($error): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: <?= json_encode($error) ?>
                });
            <?php endif; ?>

            <?php if (!empty($generatedCodes)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Recovery Code Dibuat',
                    html: `<?= addslashes(implode(', ', $generatedCodes)) ?>`,
                    width: 600,
                    confirmButtonText: 'Tutup'
                });
            <?php endif; ?>
        });
    </script>
    <script src="<?= BASE_URL ?>assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
