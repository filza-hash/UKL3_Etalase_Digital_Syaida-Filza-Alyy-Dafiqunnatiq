# UKK Lvl 3 – Etalase Digital Stella

Tema warna: merah maroon, abu-abu, putih (`assets/css/theme.css`). Logo Telkom Schools di navbar, halaman login/registrasi, dan tab browser (`assets/img/logo.png`).

Gabungan dua proyek:

| Bagian | Sumber |
|---|---|
| Tampilan (Bootstrap 5, navbar, kartu galeri, form, halaman kelola siswa) | `etalase_stella` |
| Backend (PDO + prepared statement, middleware role, tabel `users`/`projects`, login email) | `etalase_stella_lkpd` |
| Popup (detail project & form tambah admin) | `etalase_stella_lkpd` |
| **Baru:** halaman *Kelola User* khusus Super Admin untuk menambah admin | – |

## Instalasi (XAMPP)
1. Salin folder `ukk_lvl3` ke `htdocs/`.
2. Buka phpMyAdmin → **Import** → pilih `database.sql` (otomatis membuat database `ukk_lvl3`).
3. Cek `config/kuis_db.php` (host/user/password MySQL).
4. Buka `http://localhost/ukk_lvl3/auth/login.php`.

## Akun default (password: `password123`)
| Email | Role |
|---|---|
| superadmin@stella.sch.id | super_admin |
| admin@stella.sch.id | admin |
| siswa@stella.sch.id | siswa |

Segera ganti password akun default setelah instalasi.

## Hak akses
| Fitur | Siswa | Admin | Super Admin |
|---|:-:|:-:|:-:|
| Lihat galeri, tambah project | ✔ | ✔ | ✔ |
| Edit/hapus project | milik sendiri | semua | semua |
| Kelola Siswa (`admin/siswa.php`) | – | ✔ | ✔ |
| Kelola User + tambah admin (`admin/users.php`) | – | – | ✔ |

## Catatan perubahan
- Login memakai **email** (backend lkpd), bukan username.
- Project memakai satu kolom `link_project` (skema lkpd); kolom `link_pendukung`/`link_demo` dari versi lama tidak dipakai.
- `reset.php` dihapus (bisa dipakai siapa saja tanpa login untuk mereset password).
- Hapus/reset sekarang lewat POST + token CSRF; upload gambar divalidasi (JPG/PNG/WEBP, maks. 2MB) baik saat tambah maupun edit.
- Reset password siswa oleh admin menjadi `654321` (ubah di `DEFAULT_RESET_PASSWORD`, `middleware/auth_check.php`).

## Fitur Reaksi & Ulasan
- Klik kartu karya -> popup detail -> ada bagian **Reaksi** (Suka, Cinta, Wow, Keren, Inspiratif) dan **Ulasan** (bintang 1-5 + komentar).
- Satu pengguna = satu reaksi per karya (tekan reaksi yang sama lagi untuk membatalkan) dan satu ulasan per karya (kirim lagi = memperbarui).
- Pemilik karya tidak bisa memberi reaksi/ulasan pada karyanya sendiri.
- Ulasan hanya bisa dihapus oleh penulisnya, atau oleh admin / super admin.
- Kartu di dashboard menampilkan jumlah reaksi, jumlah ulasan, dan rata-rata rating.
- Tabel baru: `project_reactions` dan `project_reviews` (ada di `database.sql`, aman di-import ulang).
- File baru: `project_interaksi.php`, `assets/js/interaksi.js`.
