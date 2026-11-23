<?php
require_once 'config/kuis_db.php';
require_once 'middleware/auth_check.php';
require_once 'includes/helpers.php';

check_login();

$cari = trim($_GET['cari'] ?? '');

// Angka ringkas untuk kartu: jumlah reaksi, jumlah ulasan, rata-rata rating
$kolomStat = "(SELECT COUNT(*) FROM project_reactions r WHERE r.project_id = projects.id) AS jml_reaksi,
              (SELECT COUNT(*) FROM project_reviews v WHERE v.project_id = projects.id) AS jml_ulasan,
              (SELECT AVG(v2.rating) FROM project_reviews v2 WHERE v2.project_id = projects.id) AS rata_rating";

if ($cari !== '') {
    $stmt = $pdo->prepare("SELECT projects.*, users.nama AS pembuat, $kolomStat
                           FROM projects
                           JOIN users ON projects.user_id = users.id
                           WHERE projects.nama_project LIKE :k1 OR users.nama LIKE :k2
                           ORDER BY projects.created_at DESC, projects.id DESC");
    $stmt->execute([':k1' => "%$cari%", ':k2' => "%$cari%"]);
} else {
    $stmt = $pdo->query("SELECT projects.*, users.nama AS pembuat, $kolomStat
                         FROM projects
                         JOIN users ON projects.user_id = users.id
                         ORDER BY projects.created_at DESC, projects.id DESC");
}
$projects = $stmt->fetchAll();

$base   = '';
$active = 'galeri';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Etalase Digital Stella</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/popup.css">
</head>
<body class="dashboard-page">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="container my-5">
    <?= render_flash() ?>

    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-5">
            <h2 class="fw-bold mb-1">Galeri Project Siswa</h2>
            <p class="text-muted mb-0">Kumpulan karya dan pameran digital siswa Stella</p>
        </div>
        <div class="col-md-7">
            <form method="GET" action="index.php" class="d-flex gap-2 justify-content-md-end align-items-center">
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="cari" class="form-control" placeholder="cari karya/nama project" value="<?= e($cari) ?>">
                    <?php if ($cari !== ''): ?>
                        <a href="index.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="project_tambah.php" class="btn btn-success rounded-pill px-4 text-nowrap"><i class="bi bi-plus-lg me-1"></i> Tambah Project</a>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <?php if (count($projects) > 0): ?>
            <?php foreach ($projects as $p): ?>
                <?php $canManage = is_staff() || $_SESSION['user_id'] == $p['user_id']; ?>
                <div class="col-md-6 col-lg-4">
                    <!-- Klik kartu => popup detail (data dibaca oleh JS di bawah) -->
                    <div class="card h-100 project-card" tabindex="0" role="button"
                         data-id="<?= (int) $p['id'] ?>"
                         data-judul="<?= e($p['nama_project']) ?>"
                         data-pembuat="<?= e($p['pembuat']) ?>"
                         data-deskripsi="<?= e($p['deskripsi']) ?>"
                         data-link="<?= e($p['link_project']) ?>"
                         data-gambar="uploads/<?= e($p['gambar']) ?>"
                         data-tanggal="<?= e(date('d M Y', strtotime($p['created_at']))) ?>"
                         data-milik="<?= $_SESSION['user_id'] == $p['user_id'] ? '1' : '0' ?>"
                         data-manage="<?= $canManage ? '1' : '0' ?>">
                        <img src="uploads/<?= e($p['gambar']) ?>" class="card-img-top project-img" alt="Gambar Project">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold"><?= e($p['nama_project']) ?></h5>
                            <p class="card-text text-muted small mb-2"><i class="bi bi-person-circle"></i> Oleh: <b><?= e($p['pembuat']) ?></b></p>
                            <p class="card-text flex-grow-1"><?= e(mb_strimwidth($p['deskripsi'], 0, 120, '...')) ?></p>
                            <div class="card-stat">
                                <span title="Jumlah reaksi"><i class="bi bi-emoji-smile"></i> <span class="stat-reaksi"><?= (int) $p['jml_reaksi'] ?></span></span>
                                <span title="Jumlah ulasan"><i class="bi bi-chat-dots"></i> <span class="stat-ulasan"><?= (int) $p['jml_ulasan'] ?></span></span>
                                <span title="Rata-rata rating"><i class="bi bi-star-fill"></i> <span class="stat-rating"><?= $p['rata_rating'] !== null ? number_format((float) $p['rata_rating'], 1) : '-' ?></span></span>
                            </div>
                            <div class="text-primary small fw-semibold mt-2">
                                <i class="bi bi-arrows-fullscreen"></i> Klik untuk lihat detail
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-folder-x text-muted display-1"></i>
                <p class="text-muted mt-3">Tidak ada project atau karya yang ditemukan.</p>
                <?php if ($cari !== ''): ?>
                    <a href="index.php" class="btn btn-sm btn-outline-primary mt-2">Reset Pencarian</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Popup detail project (gaya dari etalase_stella_lkpd) -->
<div id="projectPopup" class="popup-overlay" aria-hidden="true" data-csrf="<?= e(csrf_token()) ?>">
    <div class="popup-content" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
        <button type="button" class="popup-close" data-popup-close aria-label="Tutup">&times;</button>
        <img id="popupImg" class="popup-img" src="" alt="Detail Karya">
        <div class="popup-body">
            <h2 id="popupTitle" class="popup-title"></h2>
            <div id="popupAuthor" class="popup-meta"></div>
            <div id="popupDate" class="popup-meta"></div>
            <p id="popupDesc" class="popup-desc"></p>
            <a id="popupLink" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-primary w-100 fw-semibold">Buka Tautan Project &rarr;</a>

            <!-- Reaksi & ulasan (diisi oleh assets/js/interaksi.js) -->
            <div class="interaksi">
                <div class="interaksi-judul">Reaksi</div>
                <div class="reaksi-bar" id="reaksiBar">
                    <?php foreach (REACTION_TYPES as $kunci => $r): ?>
                        <button type="button" class="reaksi-btn" data-reaction="<?= e($kunci) ?>" title="<?= e($r['label']) ?>">
                            <span class="reaksi-emoji"><?= $r['emoji'] ?></span>
                            <span class="reaksi-label"><?= e($r['label']) ?></span>
                            <span class="reaksi-jml">0</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div id="reaksiCatatan" class="interaksi-catatan" style="display: none;">
                    Kamu tidak bisa memberi reaksi atau ulasan pada karyamu sendiri.
                </div>

                <div class="interaksi-judul mt-3">Ulasan <span id="ulasanRingkasan" class="ulasan-ringkasan"></span></div>
                <form id="ulasanForm" class="ulasan-form" autocomplete="off">
                    <div class="bintang-pilih" id="bintangPilih" role="radiogroup" aria-label="Rating bintang">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="bintang-btn" data-nilai="<?= $i ?>" aria-label="<?= $i ?> bintang">&#9733;</button>
                        <?php endfor; ?>
                    </div>
                    <textarea id="ulasanKomentar" class="form-control" rows="3" maxlength="500"
                              placeholder="Tulis ulasanmu tentang karya ini (3-500 karakter)"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted"><span id="ulasanHitung">0</span>/500</small>
                        <button type="submit" id="ulasanKirim" class="btn btn-primary btn-sm fw-semibold">Kirim Ulasan</button>
                    </div>
                    <div id="ulasanPesan" class="ulasan-pesan"></div>
                </form>
                <div id="ulasanList" class="ulasan-list"></div>
            </div>

            <div id="popupActions" class="popup-actions" style="display: none;">
                <a id="popupEdit" href="#" class="btn btn-warning btn-sm fw-semibold"><i class="bi bi-pencil-square"></i> Edit</a>
                <form id="popupDeleteForm" method="POST" action="project_hapus.php" onsubmit="return confirm('Hapus karya ini?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="popupDeleteId" value="">
                    <button type="submit" class="btn btn-danger btn-sm fw-semibold"><i class="bi bi-trash"></i> Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/popup.js"></script>
<script src="assets/js/interaksi.js"></script>
<script>
(function () {
    var popup = document.getElementById('projectPopup');

    function openProject(card) {
        var d = card.dataset;

        document.getElementById('popupImg').src = d.gambar;
        document.getElementById('popupTitle').textContent = d.judul;
        document.getElementById('popupDesc').textContent = d.deskripsi;

        var author = document.getElementById('popupAuthor');
        author.textContent = 'Oleh: ';
        var strong = document.createElement('b');
        strong.textContent = d.pembuat;
        author.appendChild(strong);

        document.getElementById('popupDate').textContent = 'Dibuat pada: ' + d.tanggal;
        document.getElementById('popupLink').href = d.link;

        var actions = document.getElementById('popupActions');
        if (d.manage === '1') {
            document.getElementById('popupEdit').href = 'project_edit.php?id=' + encodeURIComponent(d.id);
            document.getElementById('popupDeleteId').value = d.id;
            actions.style.display = 'flex';
        } else {
            actions.style.display = 'none';
        }

        StellaPopup.open(popup);
        StellaInteraksi.buka(card);   // muat reaksi & ulasan karya ini
    }

    document.querySelectorAll('.project-card').forEach(function (card) {
        card.addEventListener('click', function () { openProject(card); });
        card.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                openProject(card);
            }
        });
    });
})();
</script>

</body>
</html>
