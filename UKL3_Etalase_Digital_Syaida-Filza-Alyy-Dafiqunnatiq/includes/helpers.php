<?php
/** Hanya izinkan link http/https (mencegah javascript: dsb). */
function valid_http_url(string $url): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

/**
 * Validasi & simpan gambar project. Mengembalikan nama file baru,
 * atau null (dan mengisi $error) jika gagal.
 */
function save_project_image(array $file, string $uploadDir, ?string &$error): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = "Pilih gambar karya digital terlebih dahulu!";
        return null;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        $error = "Ukuran berkas gambar tidak boleh lebih dari 2MB!";
        return null;
    }

    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $info    = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowed[$info[2]])) {
        $error = "Format gambar harus berupa JPG, JPEG, PNG, atau WEBP!";
        return null;
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newName = uniqid('project_', true) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], rtrim($uploadDir, '/\\') . '/' . $newName)) {
        $error = "Gagal mengunggah berkas gambar ke server.";
        return null;
    }
    return $newName;
}

function delete_project_image(string $uploadDir, string $fileName): void {
    $path = rtrim($uploadDir, '/\\') . '/' . basename($fileName);
    if ($fileName !== '' && is_file($path)) {
        unlink($path);
    }
}

/**
 * Hapus user (hanya jika rolenya ada di $allowedRoles) beserta file gambar project miliknya.
 */
function delete_user_with_files(PDO $pdo, int $id, array $allowedRoles): bool {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        return false;
    }

    $imgs = $pdo->prepare("SELECT gambar FROM projects WHERE user_id = :id");
    $imgs->execute([':id' => $id]);
    $files = $imgs->fetchAll(PDO::FETCH_COLUMN);

    $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $del->execute([':id' => $id]);

    foreach ($files as $f) {
        delete_project_image(UPLOAD_DIR, $f);
    }
    return true;
}

/** True jika error PDO adalah duplikat (email sudah terdaftar). */
function is_duplicate_error(PDOException $e): bool {
    return ($e->errorInfo[1] ?? 0) === 1062;
}

/* ---------- Reaksi & Ulasan ---------- */

/** Jenis reaksi. Kuncinya sama dengan nilai ENUM di kolom project_reactions.reaction. */
const REACTION_TYPES = [
    'suka'       => ['emoji' => '👍', 'label' => 'Suka'],
    'cinta'      => ['emoji' => '❤️', 'label' => 'Cinta'],
    'wow'        => ['emoji' => '😮', 'label' => 'Wow'],
    'keren'      => ['emoji' => '🔥', 'label' => 'Keren'],
    'inspiratif' => ['emoji' => '💡', 'label' => 'Inspiratif'],
];

/** Kirim jawaban JSON lalu hentikan script. */
function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Ambil data reaksi + ulasan satu karya, dilihat dari sudut pandang user yang login.
 */
function ambil_interaksi(PDO $pdo, int $projectId, int $userId, bool $isStaff): array {
    // --- Reaksi ---
    $jumlah = array_fill_keys(array_keys(REACTION_TYPES), 0);
    $stmt = $pdo->prepare("SELECT reaction, COUNT(*) AS jml FROM project_reactions
                           WHERE project_id = :p GROUP BY reaction");
    $stmt->execute([':p' => $projectId]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($jumlah[$row['reaction']])) {
            $jumlah[$row['reaction']] = (int) $row['jml'];
        }
    }

    $stmt = $pdo->prepare("SELECT reaction FROM project_reactions WHERE project_id = :p AND user_id = :u");
    $stmt->execute([':p' => $projectId, ':u' => $userId]);
    $saya = $stmt->fetchColumn();

    // --- Ulasan ---
    $stmt = $pdo->prepare("SELECT v.id, v.user_id, v.rating, v.komentar, v.created_at, u.nama
                           FROM project_reviews v
                           JOIN users u ON u.id = v.user_id
                           WHERE v.project_id = :p
                           ORDER BY v.created_at DESC, v.id DESC");
    $stmt->execute([':p' => $projectId]);

    $daftar = [];
    $totalRating = 0;
    $ulasanSaya = null;
    foreach ($stmt->fetchAll() as $row) {
        $milik = ((int) $row['user_id'] === $userId);
        $totalRating += (int) $row['rating'];
        if ($milik) {
            $ulasanSaya = ['rating' => (int) $row['rating'], 'komentar' => $row['komentar']];
        }
        $daftar[] = [
            'id'        => (int) $row['id'],
            'nama'      => $row['nama'],
            'rating'    => (int) $row['rating'],
            'komentar'  => $row['komentar'],
            'tanggal'   => date('d M Y, H:i', strtotime($row['created_at'])),
            'milik'     => $milik,
            'bisa_hapus' => $milik || $isStaff,
        ];
    }

    $jmlUlasan = count($daftar);
    return [
        'reaksi' => [
            'jumlah' => $jumlah,
            'saya'   => $saya === false ? null : $saya,
            'total'  => array_sum($jumlah),
        ],
        'ulasan' => [
            'daftar' => $daftar,
            'jumlah' => $jmlUlasan,
            'rata'   => $jmlUlasan > 0 ? round($totalRating / $jmlUlasan, 1) : null,
            'saya'   => $ulasanSaya,
        ],
    ];
}
