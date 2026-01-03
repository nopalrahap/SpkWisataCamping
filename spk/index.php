<?php
// SPK SAW - Pemilihan Tempat Wisata Camping kecamatan MegaMendung
session_start();

// ngecek buat  login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'koneksi.php';

// --- helper functions ---
function load_data($conn)
{
    $result = $conn->query("SELECT * FROM alternatif ORDER BY id");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// --- handle requests ---
$message = '';
$action = $_REQUEST['action'] ?? '';

if ($action === 'add') {
    $nama = trim($_POST['nama'] ?? '');
    $rating = floatval($_POST['rating'] ?? 0);
    $ulasan = intval($_POST['ulasan'] ?? 0);
    $harga = floatval($_POST['harga'] ?? 0);
    $fasilitas = intval($_POST['fasilitas'] ?? 0);
    
    if ($nama === '') {
        $message = 'Nama wajib diisi.';
    } else {
        $stmt = $conn->prepare("INSERT INTO alternatif (nama, rating, ulasan, harga, fasilitas) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdidi", $nama, $rating, $ulasan, $harga, $fasilitas);
        if ($stmt->execute()) {
            $message = 'Alternatif berhasil ditambahkan.';
        } else {
            $message = 'Gagal menambahkan alternatif.';
        }
        $stmt->close();
    }
}

if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM alternatif WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'Alternatif dihapus.';
    }
    $stmt->close();
}

if ($action === 'clear') {
    if ($conn->query("TRUNCATE TABLE alternatif")) {
        $message = 'Semua data dihapus.';
    }
}

if ($action === 'import_csv') {
    if (isset($_FILES['csv']) && $_FILES['csv']['error'] === 0) {
        $csv = file_get_contents($_FILES['csv']['tmp_name']);
        $rows = array_map('str_getcsv', explode("\n", $csv));
        array_shift($rows); // skip header
        
        $stmt = $conn->prepare("INSERT INTO alternatif (nama, rating, ulasan, harga, fasilitas) VALUES (?, ?, ?, ?, ?)");
        $count = 0;
        foreach ($rows as $r) {
            if (count($r) < 5) continue;
            $r = array_map('trim', $r);
            $nama = $r[0];
            $rating = floatval($r[1]);
            $ulasan = intval($r[2]);
            $harga = floatval($r[3]);
            $fasilitas = intval($r[4]);
            
            $stmt->bind_param("sdidi", $nama, $rating, $ulasan, $harga, $fasilitas);
            if ($stmt->execute()) $count++;
        }
        $stmt->close();
        $message = "CSV diimport ($count baris).";
    } else {
        $message = 'Gagal mengupload CSV.';
    }
}

if ($action === 'export_csv') {
    $data = load_data($conn);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="export_wisata.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['nama', 'rating', 'ulasan', 'harga', 'fasilitas']);
    foreach ($data as $d) {
        fputcsv($out, [$d['nama'], $d['rating'], $d['ulasan'], $d['harga'], $d['fasilitas']]);
    }
    fclose($out);
    exit;
}

$data = load_data($conn);

// perhitungan SAW nya

//bobot kriteria
$weights = [
    'rating' => floatval($_POST['w_rating'] ?? ($_GET['w_rating'] ?? 0.35)),
    'ulasan' => floatval($_POST['w_ulasan'] ?? ($_GET['w_ulasan'] ?? 0.25)),
    'harga' => floatval($_POST['w_harga'] ?? ($_GET['w_harga'] ?? 0.25)),
    'fasilitas' => floatval($_POST['w_fasilitas'] ?? ($_GET['w_fasilitas'] ?? 0.15)),
];

$types = ['rating' => 'benefit', 'ulasan' => 'benefit', 'harga' => 'cost', 'fasilitas' => 'benefit'];

function saw_rank($data, $weights, $types)
{
    if (count($data) === 0) return [];
    
    $crit_values = ['rating' => [], 'ulasan' => [], 'harga' => [], 'fasilitas' => []];
    foreach ($data as $d) {
        foreach ($crit_values as $k => $_) {
            $crit_values[$k][] = floatval($d[$k]);
        }
    }
    //normalisasi benefit & cost
    $norm = [];
    foreach ($crit_values as $k => $vals) {
        if ($types[$k] === 'benefit') {
            $max = max($vals);
            foreach ($vals as $i => $v) {
                $norm[$k][$i] = $max > 0 ? ($v / $max) : 0;
            }
        } else {
            $min = min($vals);
            foreach ($vals as $i => $v) {
                $norm[$k][$i] = $v > 0 ? ($min / $v) : 0;
            }
        }
    }

    // normalisasi matrik 
    $w_sum = array_sum($weights);
    if ($w_sum <= 0) $w_sum = 1;
    foreach ($weights as $k => $w) {
        $weights[$k] = $w / $w_sum;
    }
    
    // hitung skor akhir
    $results = [];
    foreach (array_values($data) as $i => $d) {
        $score = 0;
        foreach ($weights as $k => $w) {
            $score += ($norm[$k][$i] ?? 0) * $w;
        }
        $r = $d;
        $r['score'] = round($score, 4);
        $results[] = $r;
    }
    
    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return $results;
}

// mendapatkan hasil ranking
$ranked = saw_rank($data, $weights, $types);

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Camping Bogor - SPK SAW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --accent: #f59e0b;
            --dark: #1e293b;
            --light: #f8fafc;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 3rem;
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .nav-link {
            color: var(--dark) !important;
            font-weight: 500;
            padding: 0.5rem 1.5rem !important;
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-link:hover {
            background: var(--light);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            color: white !important;
            background: var(--primary);
        }
        
        .hero-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        
        .hero-section h1 {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .card-modern {
            background: white;
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        .card-modern:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border: none;
        }
        
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.2);
            transition: left 0.3s ease;
        }
        
        .btn-modern:hover::before {
            left: 100%;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, var(--secondary), #059669);
            color: white;
        }
        
        .btn-danger-modern {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .form-control-modern {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .table-modern {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table-modern thead {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .table-modern tbody tr {
            transition: all 0.2s ease;
        }
        
        .table-modern tbody tr:hover {
            background: #f1f5f9;
            transform: scale(1.01);
        }
        
        .rank-badge {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #ea580c);
            color: white;
            font-weight: 700;
            line-height: 40px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .rank-badge.first {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .score-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-upload-label {
            display: block;
            padding: 0.75rem 1rem;
            background: white;
            border: 2px dashed var(--primary);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .file-upload-label:hover {
            background: #f0f9ff;
            border-color: var(--primary-dark);
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .icon-primary { background: linear-gradient(135deg, #dbeafe, #93c5fd); color: var(--primary); }
        .icon-success { background: linear-gradient(135deg, #d1fae5, #6ee7b7); color: var(--secondary); }
        .icon-warning { background: linear-gradient(135deg, #fef3c7, #fcd34d); color: var(--accent); }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-campground text-primary"></i> Sistem Pendukung Keputusan
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home"></i> Utama
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="compare.php">
                            <i class="fas fa-balance-scale"></i> Perbandingan
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1><i class="fas fa-mountain"></i> Wisata Camping </h1>
            <p class="text-muted mb-0">Sistem pendukung keputusan pemilihan tempat camping terbaik menggunakan metode SAW</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-modern mb-4">
                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon icon-primary">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3 class="mb-0"><?= count($data) ?></h3>
                    <p class="text-muted mb-0">Total Alternatif</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon icon-success">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="mb-0"><?= count($ranked) > 0 ? htmlspecialchars($ranked[0]['nama']) : '-' ?></h3>
                    <p class="text-muted mb-0">Rekomendasi Terbaik</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon icon-warning">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h3 class="mb-0">SAW</h3>
                    <p class="text-muted mb-0">Metode Perhitungan</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card-modern mb-4">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Tambah Alternatif Baru</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="post" action="?action=add">
                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="fas fa-campground text-primary"></i> Nama Tempat</label>
                                <input name="nama" class="form-control form-control-modern" placeholder="Contoh: Gunung Pancar Camping" required>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label fw-semibold"><i class="fas fa-star text-warning"></i> Rating</label>
                                    <input type="number" step="0.1" min="0" max="5" name="rating" class="form-control form-control-modern" value="4.0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold"><i class="fas fa-comments text-info"></i> Ulasan</label>
                                    <input type="number" name="ulasan" class="form-control form-control-modern" value="0">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold"><i class="fas fa-money-bill-wave text-success"></i> Harga (Rp)</label>
                                    <input type="number" name="harga" class="form-control form-control-modern" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold"><i class="fas fa-building text-danger"></i> Fasilitas</label>
                                    <input type="number" min="1" max="100" name="fasilitas" class="form-control form-control-modern" value="3">
                                </div>
                            </div>
                            <div class="mt-4">
                                <button class="btn btn-primary-modern btn-modern w-100">
                                    <i class="fas fa-save"></i> Simpan Alternatif
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-modern mb-4">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-sliders-h"></i> Pengaturan Bobot</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="post">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Rating</label>
                                    <input type="number" step="0.01" name="w_rating" class="form-control form-control-modern" value="<?= htmlspecialchars($weights['rating']) ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Ulasan</label>
                                    <input type="number" step="0.01" name="w_ulasan" class="form-control form-control-modern" value="<?= htmlspecialchars($weights['ulasan']) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Harga</label>
                                    <input type="number" step="0.01" name="w_harga" class="form-control form-control-modern" value="<?= htmlspecialchars($weights['harga']) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Fasilitas</label>
                                    <input type="number" step="0.01" name="w_fasilitas" class="form-control form-control-modern" value="<?= htmlspecialchars($weights['fasilitas']) ?>">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-success-modern btn-modern w-100">
                                    <i class="fas fa-sync-alt"></i> Hitung Ulang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-file-import"></i> Import / Export</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="post" enctype="multipart/form-data">
                            <div class="file-upload-wrapper mb-3">
                                <input type="file" name="csv" id="csvFile" accept=".csv">
                                <label for="csvFile" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i> Pilih File CSV
                                </label>
                            </div>
                            <div class="d-grid gap-2">
                                <button formaction="?action=import_csv" class="btn btn-success-modern btn-modern">
                                    <i class="fas fa-file-upload"></i> Import CSV
                                </button>
                                <a href="?action=export_csv" class="btn btn-primary-modern btn-modern">
                                    <i class="fas fa-file-download"></i> Export CSV
                                </a>
                                <button type="button" formaction="?action=clear" onclick="if(confirm('Hapus semua data?')) window.location='?action=clear'" class="btn btn-danger-modern btn-modern">
                                    <i class="fas fa-trash-alt"></i> Hapus Semua Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card-modern mb-4">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Alternatif (<?= count($data) ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Nama Tempat</th>
                                        <th class="text-center"><i class="fas fa-star text-warning"></i></th>
                                        <th class="text-center"><i class="fas fa-comments"></i></th>
                                        <th>Harga</th>
                                        <th class="text-center"><i class="fas fa-building"></i></th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $d): ?>
                                        <tr>
                                            <td class="text-center fw-bold"><?= htmlspecialchars($d['id']) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($d['nama']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars($d['rating']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars($d['ulasan']) ?></td>
                                            <td>Rp <?= number_format($d['harga'], 0, ',', '.') ?></td>
                                            <td class="text-center"><?= htmlspecialchars($d['fasilitas']) ?></td>
                                            <td class="text-center">
                                                <a href="?action=delete&id=<?= $d['id'] ?>" class="btn btn-danger-modern btn-modern btn-sm" onclick="return confirm('Hapus alternatif ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card-modern">
                    <div class="card-header-modern">
                        <h5 class="mb-0"><i class="fas fa-trophy"></i> Hasil Perangkingan SAW</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (count($ranked) === 0): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada data alternatif</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Rank</th>
                                            <th>Nama Tempat</th>
                                            <th class="text-center">Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ranked as $i => $r): ?>
                                            <tr style="<?= $i === 0 ? 'background: linear-gradient(135deg, #fef3c7, #fde68a);' : '' ?>">
                                                <td class="text-center">
                                                    <span class="rank-badge <?= $i === 0 ? 'first' : '' ?>">
                                                        <?= $i === 0 ? '👑' : ($i + 1) ?>
                                                    </span>
                                                </td>
                                                <td class="fw-semibold">
                                                    <?= htmlspecialchars($r['nama']) ?>
                                                    <?= $i === 0 ? '<span class="badge bg-warning text-dark ms-2"><i class="fas fa-star"></i> Terbaik</span>' : '' ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="score-display"><?= htmlspecialchars($r['score']) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-light mt-3 mb-0">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Perhitungan menggunakan metode SAW dengan normalisasi benefit & cost
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="card-modern mt-4">
            <div class="card-body p-4">
                <h5 class="mb-3"><i class="fas fa-question-circle text-primary"></i> Panduan Penggunaan</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <div class="me-3">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">1</div>
                            </div>
                            <div>
                                <h6 class="mb-1">Tambah Data</h6>
                                <small class="text-muted">Masukkan alternatif tempat camping dengan semua kriteria</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <div class="me-3">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--secondary), #059669); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">2</div>
                            </div>
                            <div>
                                <h6 class="mb-1">Atur Bobot</h6>
                                <small class="text-muted">Sesuaikan bobot kriteria sesuai preferensi Anda</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex">
                            <div class="me-3">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--accent), #ea580c); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">3</div>
                            </div>
                            <div>
                                <h6 class="mb-1">Lihat Hasil</h6>
                                <small class="text-muted">Sistem akan otomatis menghitung ranking terbaik</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="text-center mt-5 mb-3">
            <p class="text-white mb-0">
                <i class="fas fa-code"></i> Sistem Pendukung Keputusan • Metode SAW
            </p>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload label update
        document.getElementById('csvFile')?.addEventListener('change', function(e) {
            const label = document.querySelector('.file-upload-label');
            if (e.target.files.length > 0) {
                label.innerHTML = '<i class="fas fa-check-circle"></i> ' + e.target.files[0].name;
            }
        });
    </script>
</body>
</html>