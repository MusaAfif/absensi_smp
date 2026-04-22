README INSTALASI APLIKASI ABSENSI SMP
====================================

Nama aplikasi:
Sistem Absensi Siswa SMP berbasis PHP + MySQL

Ringkasan:
Aplikasi ini digunakan untuk login admin, pengelolaan data siswa, pengaturan jadwal absensi, scan absensi masuk/pulang, dan pembuatan laporan.

CATATAN PENTING SEBELUM INSTALASI
---------------------------------
1. Repo ini tidak menyertakan file dump database .sql bawaan.
2. Anda harus menyiapkan database dari backup SQL sekolah atau export database lama.
3. Konfigurasi database default saat ini ada di file:
   includes/config.php
4. Nama database default saat ini:
   db_absensi_smp
5. Timezone default saat ini:
   Asia/Jakarta

PERSYARATAN SERVER
------------------
Minimum offline / lokal sekolah:
- Windows 10/11 atau Linux
- PHP 8.1 sampai 8.3
- MySQL 5.7+ atau MariaDB 10.4+
- Apache / Nginx
- RAM minimum 4 GB
- SSD minimum 20 GB kosong

Rekomendasi online hosting / VPS:
- PHP 8.2 atau 8.3
- MySQL 8 / MariaDB 10.6+
- SSL aktif
- Backup harian otomatis
- Akses cron dan log server
- Storage SSD / NVMe

REKOMENDASI SPESIFIKASI BERDASARKAN JUMLAH SISWA
------------------------------------------------
1. Sampai 300 siswa
   - Shared hosting premium atau cloud hosting
   - CPU 1 vCore
   - RAM 1-2 GB
   - Storage 5-10 GB SSD
   - Database 1 GB cukup

2. 300 sampai 1000 siswa
   - VPS managed / unmanaged
   - CPU 2 vCore
   - RAM 2-4 GB
   - Storage 20-40 GB SSD
   - Database 2-5 GB

3. 1000 sampai 3000 siswa
   - VPS produksi
   - CPU 4 vCore
   - RAM 4-8 GB
   - Storage 40-80 GB NVMe
   - Database 5-10 GB
   - Backup harian + staging sangat disarankan

4. Lebih dari 3000 siswa atau multi-kampus
   - VPS high availability / cloud instance dedicated
   - CPU 4-8 vCore
   - RAM 8-16 GB
   - Storage 80-160 GB NVMe
   - Database terpisah atau managed database
   - Wajib monitoring, backup otomatis, dan audit log

INSTALASI OFFLINE DI LARAGON / XAMPP
------------------------------------
1. Copy folder project ke web root.
   Contoh Laragon:
   C:\laragon\www\absensi_smp

2. Pastikan Apache dan MySQL aktif.

3. Buat database baru dengan nama:
   db_absensi_smp

4. Import database dari file backup SQL sekolah.
   Jika menggunakan phpMyAdmin:
   - buka phpMyAdmin
   - pilih database db_absensi_smp
   - pilih Import
   - upload file .sql
   - jalankan import

5. Buka file includes/config.php lalu sesuaikan jika perlu:
   APP_DB_HOST
   APP_DB_USER
   APP_DB_PASS
   APP_DB_NAME

6. Pastikan folder berikut bisa ditulis server bila digunakan:
   logs/
   uploads/
   assets/img/logo_sekolah/
   assets/img/logo_pemda/
   assets/img/siswa/

7. Akses aplikasi dari browser:
   http://localhost/absensi_smp/

8. Login menggunakan akun admin yang tersedia di database.

INSTALASI ONLINE DI HOSTING / VPS
---------------------------------
1. Upload semua file project ke document root hosting.
   Contoh:
   public_html/absensi_smp/
   atau domain root langsung jika aplikasi menjadi root website.

2. Buat database MySQL dan user database.

3. Import file backup SQL ke database hosting.

4. Edit includes/config.php sesuai hosting:
   APP_DB_HOST
   APP_DB_USER
   APP_DB_PASS
   APP_DB_NAME

5. Jika aplikasi dipasang di subfolder, cek BASE_URL hasil deteksi pada includes/config.php.
   Pastikan URL yang terbentuk benar.

6. Aktifkan HTTPS / SSL.
   Sangat disarankan untuk produksi agar cookie session aman.

7. Set permission folder upload dan log.
   Linux umumnya:
   - folder 755
   - file 644
   - pastikan web server dapat menulis ke uploads/ dan logs/

8. Nonaktifkan directory listing di hosting.

9. Batasi akses file backup SQL dan file log dari publik.

10. Lakukan uji fungsi minimum setelah deploy:
   - login admin
   - buka dashboard
   - tambah siswa
   - scan masuk
   - scan pulang
   - buka laporan
   - ubah pengaturan
   - upload logo

CHECKLIST GO LIVE
-----------------
- Database sudah terimport lengkap
- Akun admin sudah diuji
- Zona waktu server benar
- SSL aktif
- Folder upload dan log bisa ditulis
- URL aplikasi benar
- Backup database dijadwalkan
- Password admin sudah diganti dari default
- Hanya akun resmi sekolah yang aktif

TROUBLESHOOTING UMUM
--------------------
1. Gagal koneksi database
   - cek host, user, password, nama database di includes/config.php
   - cek service MySQL hidup

2. Login gagal
   - cek data akun di tabel admin atau users
   - pastikan password di database valid

3. Logo tidak tampil
   - cek file upload berhasil
   - cek nama file tersimpan di tabel pengaturan
   - cek permission folder assets/img/logo_sekolah dan assets/img/logo_pemda

4. Scan tidak masuk
   - cek tabel siswa
   - cek kode QR / barcode / RFID yang dipakai
   - cek jadwal absensi di menu pengaturan
   - cek log server di folder logs/

5. Laporan kosong
   - cek data absensi ada di database
   - cek filter tanggal dan kelas

SARAN OPERASIONAL
-----------------
- Lakukan backup database harian
- Simpan export database mingguan di lokasi terpisah
- Ganti password admin secara berkala
- Audit data siswa lulus/pindah setiap semester
- Bersihkan file log secara berkala
- Gunakan HTTPS untuk server online

DOKUMEN INI DISUSUN BERDASARKAN KONFIGURASI REPO SAAT INI.
Jika struktur database sekolah berbeda, sesuaikan query dan data awal sebelum go live.
