<?php
/**
 * Endpoint JSON untuk reaksi & ulasan sebuah karya.
 *   GET  ?id=KARYA                          -> data reaksi + ulasan karya itu
 *   POST aksi=reaksi         id, reaction   -> pasang / ganti / batalkan reaksi
 *   POST aksi=ulasan_simpan  id, rating, komentar -> tambah / perbarui ulasan
 *   POST aksi=ulasan_hapus   review_id      -> hapus ulasan (milik sendiri, atau staff)
 */
require_once 'config/kuis_db.php';
require_once 'middleware/auth_check.php';
require_once 'includes/helpers.php';

if (!isset($_SESSION['user_id'])) {
    json_out(['ok' => false, 'pesan' => 'Silakan login terlebih dahulu.'], 401);
}

$userId  = (int) $_SESSION['user_id'];
$isStaff = is_staff();

/** Cari karya; hentikan dengan JSON error jika tidak ada. */
function cari_karya(PDO $pdo, int $id): array {
    $stmt = $pdo->prepare("SELECT id, user_id FROM projects WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $karya = $stmt->fetch();
    if (!$karya) {
        json_out(['ok' => false, 'pesan' => 'Karya tidak ditemukan.'], 404);
    }
    return $karya;
}

/** Jawaban sukses: data terbaru karya + penanda apakah ini karya milik sendiri. */
function jawab_data(PDO $pdo, array $karya, int $userId, bool $isStaff): void {
    $data = ambil_interaksi($pdo, (int) $karya['id'], $userId, $isStaff);
    $data['ok']      = true;
    $data['id']      = (int) $karya['id'];
    $data['pemilik'] = ((int) $karya['user_id'] === $userId);
    json_out($data);
}

/* ---------- GET: ambil data ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $karya = cari_karya($pdo, (int) ($_GET['id'] ?? 0));
    jawab_data($pdo, $karya, $userId, $isStaff);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'pesan' => 'Metode tidak didukung.'], 405);
}

/* ---------- POST: cek token CSRF ---------- */
$kirim = $_POST['csrf_token'] ?? '';
if (!is_string($kirim) || !hash_equals(csrf_token(), $kirim)) {
    json_out(['ok' => false, 'pesan' => 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.'], 419);
}

$aksi = $_POST['aksi'] ?? '';

/* ---------- Hapus ulasan ---------- */
if ($aksi === 'ulasan_hapus') {
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, project_id, user_id FROM project_reviews WHERE id = :id");
    $stmt->execute([':id' => $reviewId]);
    $ulasan = $stmt->fetch();
    if (!$ulasan) {
        json_out(['ok' => false, 'pesan' => 'Ulasan tidak ditemukan.'], 404);
    }
    if (!$isStaff && (int) $ulasan['user_id'] !== $userId) {
        json_out(['ok' => false, 'pesan' => 'Kamu hanya boleh menghapus ulasanmu sendiri.'], 403);
    }
    $pdo->prepare("DELETE FROM project_reviews WHERE id = :id")->execute([':id' => $reviewId]);
    jawab_data($pdo, cari_karya($pdo, (int) $ulasan['project_id']), $userId, $isStaff);
}

/* ---------- Reaksi & simpan ulasan: butuh karya, dan bukan karya sendiri ---------- */
if ($aksi !== 'reaksi' && $aksi !== 'ulasan_simpan') {
    json_out(['ok' => false, 'pesan' => 'Aksi tidak dikenal.'], 400);
}

$karya = cari_karya($pdo, (int) ($_POST['id'] ?? 0));
if ((int) $karya['user_id'] === $userId) {
    json_out(['ok' => false, 'pesan' => 'Kamu tidak bisa memberi reaksi atau ulasan pada karyamu sendiri.'], 403);
}

if ($aksi === 'reaksi') {
    $jenis = $_POST['reaction'] ?? '';
    if (!is_string($jenis) || !isset(REACTION_TYPES[$jenis])) {
        json_out(['ok' => false, 'pesan' => 'Jenis reaksi tidak valid.'], 422);
    }

    $stmt = $pdo->prepare("SELECT reaction FROM project_reactions WHERE project_id = :p AND user_id = :u");
    $stmt->execute([':p' => $karya['id'], ':u' => $userId]);
    $lama = $stmt->fetchColumn();

    if ($lama === $jenis) {
        // Menekan reaksi yang sama sekali lagi = membatalkan reaksi
        $pdo->prepare("DELETE FROM project_reactions WHERE project_id = :p AND user_id = :u")
            ->execute([':p' => $karya['id'], ':u' => $userId]);
    } else {
        // Reaksi baru, atau ganti ke jenis lain
        $pdo->prepare("INSERT INTO project_reactions (project_id, user_id, reaction)
                       VALUES (:p, :u, :r)
                       ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)")
            ->execute([':p' => $karya['id'], ':u' => $userId, ':r' => $jenis]);
    }
    jawab_data($pdo, $karya, $userId, $isStaff);
}

/* aksi === 'ulasan_simpan' */
$rating   = (int) ($_POST['rating'] ?? 0);
$komentar = trim((string) ($_POST['komentar'] ?? ''));
$panjang  = mb_strlen($komentar, 'UTF-8');

if ($rating < 1 || $rating > 5) {
    json_out(['ok' => false, 'pesan' => 'Pilih rating bintang 1 sampai 5.'], 422);
}
if ($panjang < 3 || $panjang > 500) {
    json_out(['ok' => false, 'pesan' => 'Ulasan harus 3 sampai 500 karakter.'], 422);
}

$pdo->prepare("INSERT INTO project_reviews (project_id, user_id, rating, komentar)
               VALUES (:p, :u, :r, :k)
               ON DUPLICATE KEY UPDATE rating = VALUES(rating), komentar = VALUES(komentar)")
    ->execute([':p' => $karya['id'], ':u' => $userId, ':r' => $rating, ':k' => $komentar]);

jawab_data($pdo, $karya, $userId, $isStaff);
