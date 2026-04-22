# Setup Aplikasi Absensi SMP

## Persyaratan
- PHP 7.4+
- MySQL/MariaDB
- Laragon (atau server lokal lainnya)
- Git

## Langkah-Langkah Setup

### 1. Clone Repository
```bash
git clone https://github.com/MusaAfif/absensi_smp.git
cd absensi_smp
```

### 2. Setup Database

#### Opsi A: Import Database dari File Backup
```bash
# Pastikan MySQL sudah running
mysql -u root -h localhost < db_absensi_smp.sql
```

#### Opsi B: Buat Database Manual
```bash
mysql -u root -h localhost
CREATE DATABASE db_absensi_smp;
USE db_absensi_smp;
SOURCE db_absensi_smp.sql;
EXIT;
```

### 3. Konfigurasi
- Database config sudah tersetting di `includes/config.php`
- Default: 
  - **Host**: localhost
  - **User**: root
  - **Password**: (kosong)
  - **Database**: db_absensi_smp

Sesuaikan jika berbeda dengan setup lokal Anda.

### 4. Jalankan Aplikasi

#### Via Laragon:
1. Buka Laragon
2. Klik **Start All**
3. Akses: `http://localhost/absensi_smp/`

#### Via PHP Development Server:
```bash
php -S localhost:8000
# Akses: http://localhost:8000
```

### 5. Login
- **URL**: http://localhost/absensi_smp/login.php
- Gunakan akun yang sudah ada di database atau buat akun admin baru

---

## Struktur Folder
```
absensi_smp/
├── api/               # REST API endpoints
├── ajax/              # AJAX handlers
├── admin/             # Admin panel
├── assets/            # CSS, JS, Images
├── includes/          # Core files & config
├── pages/             # Main pages
├── config/            # Configuration
├── logs/              # Application logs
├── uploads/           # User file uploads
└── db_absensi_smp.sql # Database backup
```

---

## Fitur Utama
- 📋 Manajemen Siswa
- ⏱️ Absensi Masuk/Pulang (QR Code)
- 📊 Laporan Absensi
- 👤 Manajemen User & Role
- 🔐 Security (CSRF, Rate Limiting, Input Validation)

---

## Troubleshooting

### Database Connection Error
- Pastikan MySQL sudah running
- Cek konfigurasi di `includes/config.php`
- Pastikan database sudah ter-import

### Port Conflict
- Ubah port di `.htaccess` atau `php.ini` jika diperlukan

### Permission Denied
- Pastikan folder `logs/` dan `uploads/` punya write permission

---

## Support
Untuk issues atau pertanyaan, buka GitHub Issues di repository ini.
