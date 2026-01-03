<?php
// SPK SAW - Compare Alternatif
// File: compare.php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'koneksi.php';

// Load all data
function load_data($conn)
{
    $result = $conn->query("SELECT * FROM alternatif ORDER BY id");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

$data = load_data($conn);

// ini Alternatif yang masuk buat diseleksi
$selected_ids = [];
if (isset($_POST['compare'])) {
    $selected_ids = $_POST['selected'] ?? [];
}

$selected_data = [];
if (!empty($selected_ids)) {
    foreach ($data as $d) {
        if (in_array($d['id'], $selected_ids)) {
            $selected_data[] = $d;
        }
    }
}

// Perhitungan SAW nya buat data yang terpilih
$weights = [
    'rating' => 0.35,
    'ulasan' => 0.25,
    'harga' => 0.25,
    'fasilitas' => 0.15,
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
    
    $w_sum = array_sum($weights);
    if ($w_sum <= 0) $w_sum = 1;
    foreach ($weights as $k => $w) {
        $weights[$k] = $w / $w_sum;
    }
    
    $results = [];
    foreach (array_values($data) as $i => $d) {
        $score = 0;
        foreach ($weights as $k => $w) {
            $score += ($norm[$k][$i] ?? 0) * $w;
        }
        $r = $d;
        $r['score'] = round($score, 4);
        $r['norm_rating'] = round($norm['rating'][$i] ?? 0, 4);
        $r['norm_ulasan'] = round($norm['ulasan'][$i] ?? 0, 4);
        $r['norm_harga'] = round($norm['harga'][$i] ?? 0, 4);
        $r['norm_fasilitas'] = round($norm['fasilitas'][$i] ?? 0, 4);
        $results[] = $r;
    }
    
    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return $results;
}

$compared = [];
if (!empty($selected_data)) {
    $compared = saw_rank($selected_data, $weights, $types);
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Perbandingan Alternatif - Camping Bogor</title>
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
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
        }
        
        .card-header-modern {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border: none;
        }
        
        .comparison-card {
            height: 100%;
            position: relative;
        }
        
        .winner-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
            animation: rotate 3s linear infinite;
            z-index: 10;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 1rem auto;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
        }
        
        .score-circle .score {
            font-size: 1.8rem;
            font-weight: 800;
        }
        
        .score-circle .label {
            font-size: 0.7rem;
            opacity: 0.9;
        }
        
        .criterion-item {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
            background: #f8fafc;
        }
        
        .criterion-item:hover {
            background: #e2e8f0;
            transform: translateX(5px);
        }
        
        .best-value {
            color: var(--secondary);
            font-weight: 700;
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
        
        .checkbox-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            background: white;
        }
        
        .checkbox-card:hover {
            border-color: var(--primary);
            background: #f0f9ff;
            transform: translateY(-3px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .checkbox-card input:checked ~ * {
            color: var(--primary);
        }
        
        .checkbox-card input:checked ~ .card-body {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        }
        
        .table-comparison {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table-comparison thead {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .table-comparison tbody tr {
            transition: all 0.2s ease;
        }
        
        .table-comparison tbody tr:hover {
            background: #f1f5f9;
            transform: scale(1.01);
        }
        
        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .alert-info-modern {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
        }
        
        .alert-warning-modern {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }
        
        .recommendation-banner {
            background: linear-gradient(135deg, #d1fae5, #6ee7b7);
            border: 2px solid var(--secondary);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        
        .recommendation-banner h3 {
            color: #065f46;
            font-weight: 800;
        }
        
        .norm-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: var(--primary);
            color: white;
            font-size: 0.85rem;
            font-weight: 600;
        }
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Utama
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="compare.php">
                            <i class="fas fa-balance-scale"></i> Perbandingan
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1><i class="fas fa-balance-scale"></i> Perbandingan Alternatif</h1>
            <p class="text-muted mb-0">Bandingkan tempat camping secara detail berdasarkan kriteria dan perhitungan SAW</p>
        </div>

        <!-- Selection Card -->
        <div class="card-modern mb-4">
            <div class="card-header-modern">
                <h5 class="mb-0"><i class="fas fa-check-square"></i> Pilih Alternatif untuk Dibandingkan</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert-info-modern alert-modern mb-4">
                    <i class="fas fa-info-circle"></i> Pilih minimal <strong>2 alternatif</strong> untuk melihat perbandingan detail
                </div>
                
                <form method="post">
                    <div class="row">
                        <?php foreach ($data as $d): ?>
                            <div class="col-md-4 col-lg-3 mb-3">
                                <label class="checkbox-card">
                                    <input class="form-check-input" type="checkbox" name="selected[]" value="<?= $d['id'] ?>" 
                                           <?= in_array($d['id'], $selected_ids) ? 'checked' : '' ?> 
                                           style="float: left; margin-right: 10px;">
                                    <div class="card-body p-0">
                                        <h6 class="mb-2 fw-bold"><?= htmlspecialchars($d['nama']) ?></h6>
                                        <div class="small text-muted">
                                            <div><i class="fas fa-star text-warning"></i> <?= $d['rating'] ?></div>
                                            <div><i class="fas fa-comments"></i> <?= $d['ulasan'] ?> ulasan</div>
                                            <div><i class="fas fa-money-bill-wave text-success"></i> Rp <?= number_format($d['harga'], 0, ',', '.') ?></div>
                                            <div><i class="fas fa-building text-danger"></i> Fasilitas: <?= $d['fasilitas'] ?></div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="compare" class="btn btn-primary-modern btn-modern btn-lg">
                            <i class="fas fa-chart-bar"></i> Bandingkan Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($compared)): ?>
            <div class="alert-info-modern alert-modern mb-4">
                <i class="fas fa-chart-line"></i> Menampilkan perbandingan <strong><?= count($compared) ?> alternatif</strong> yang dipilih
            </div>

            <!-- Comparison Cards -->
            <div class="row mb-4">
                <?php foreach ($compared as $index => $alt): ?>
                    <div class="col-md-<?= count($compared) <= 2 ? '6' : (count($compared) == 3 ? '4' : '3') ?> mb-4">
                        <div class="card-modern comparison-card">
                            <?php if ($index === 0): ?>
                                <div class="winner-badge">🏆</div>
                            <?php endif; ?>
                            
                            <div class="card-header-modern text-center">
                                <h5 class="mb-0"><?= htmlspecialchars($alt['nama']) ?></h5>
                                <?php if ($index === 0): ?>
                                    <small class="d-block mt-2 opacity-75">✨ Rekomendasi Terbaik ✨</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body p-4">
                                <div class="score-circle">
                                    <div class="score"><?= $alt['score'] ?></div>
                                    <div class="label">SCORE SAW</div>
                                </div>
                                
                                <h6 class="text-center mb-3 mt-4">Data Asli</h6>
                                
                                <div class="criterion-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-star text-warning"></i> Rating</span>
                                        <strong class="text-primary"><?= $alt['rating'] ?></strong>
                                    </div>
                                </div>
                                
                                <div class="criterion-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-comments text-info"></i> Ulasan</span>
                                        <strong class="text-primary"><?= $alt['ulasan'] ?></strong>
                                    </div>
                                </div>
                                
                                <div class="criterion-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-money-bill-wave text-success"></i> Harga</span>
                                        <strong class="text-primary">Rp <?= number_format($alt['harga'], 0, ',', '.') ?></strong>
                                    </div>
                                </div>
                                
                                <div class="criterion-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-building text-danger"></i> Fasilitas</span>
                                        <strong class="text-primary"><?= $alt['fasilitas'] ?></strong>
                                    </div>
                                </div>
                                
                                <h6 class="text-center mb-3 mt-4">Nilai Normalisasi</h6>
                                
                                <div class="text-center">
                                    <div class="mb-2">
                                        <small class="text-muted">Rating:</small>
                                        <span class="norm-badge"><?= $alt['norm_rating'] ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Ulasan:</small>
                                        <span class="norm-badge"><?= $alt['norm_ulasan'] ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Harga:</small>
                                        <span class="norm-badge"><?= $alt['norm_harga'] ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Fasilitas:</small>
                                        <span class="norm-badge"><?= $alt['norm_fasilitas'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Detailed Comparison Table -->
            <div class="card-modern mb-4">
                <div class="card-header-modern">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Tabel Perbandingan Detail</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-comparison mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">Kriteria</th>
                                    <?php foreach ($compared as $alt): ?>
                                        <th class="text-center"><?= htmlspecialchars($alt['nama']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold"><i class="fas fa-star text-warning"></i> Rating</td>
                                    <?php 
                                    $max_rating = max(array_column($compared, 'rating'));
                                    foreach ($compared as $alt): 
                                    ?>
                                        <td class="text-center <?= $alt['rating'] == $max_rating ? 'best-value' : '' ?>">
                                            <?= $alt['rating'] ?>
                                            <?= $alt['rating'] == $max_rating ? ' ✓' : '' ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="fw-bold"><i class="fas fa-comments text-info"></i> Ulasan</td>
                                    <?php 
                                    $max_ulasan = max(array_column($compared, 'ulasan'));
                                    foreach ($compared as $alt): 
                                    ?>
                                        <td class="text-center <?= $alt['ulasan'] == $max_ulasan ? 'best-value' : '' ?>">
                                            <?= $alt['ulasan'] ?>
                                            <?= $alt['ulasan'] == $max_ulasan ? ' ✓' : '' ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="fw-bold"><i class="fas fa-money-bill-wave text-success"></i> Harga Tiket</td>
                                    <?php 
                                    $min_harga = min(array_column($compared, 'harga'));
                                    foreach ($compared as $alt): 
                                    ?>
                                        <td class="text-center <?= $alt['harga'] == $min_harga ? 'best-value' : '' ?>">
                                            Rp <?= number_format($alt['harga'], 0, ',', '.') ?>
                                            <?= $alt['harga'] == $min_harga ? ' ✓' : '' ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="fw-bold"><i class="fas fa-building text-danger"></i> Fasilitas</td>
                                    <?php 
                                    $max_fasilitas = max(array_column($compared, 'fasilitas'));
                                    foreach ($compared as $alt): 
                                    ?>
                                        <td class="text-center <?= $alt['fasilitas'] == $max_fasilitas ? 'best-value' : '' ?>">
                                            <?= $alt['fasilitas'] ?>
                                            <?= $alt['fasilitas'] == $max_fasilitas ? ' ✓' : '' ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                                    <td class="fw-bold"><i class="fas fa-trophy text-warning"></i> Score SAW</td>
                                    <?php foreach ($compared as $alt): ?>
                                        <td class="text-center">
                                            <strong style="color: var(--primary); font-size: 1.2rem;"><?= $alt['score'] ?></strong>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recommendation Banner -->
            <div class="recommendation-banner">
                <div class="display-1 mb-3">🏆</div>
                <h3 class="mb-3">Rekomendasi Terbaik</h3>
                <h2 class="mb-3"><?= htmlspecialchars($compared[0]['nama']) ?></h2>
                <p class="mb-0 lead">
                    Tempat wisata ini memiliki score SAW tertinggi <strong><?= $compared[0]['score'] ?></strong> 
                    berdasarkan perhitungan metode SAW dengan mempertimbangkan semua kriteria
                </p>
            </div>

        <?php elseif (isset($_POST['compare'])): ?>
            <div class="alert-warning-modern alert-modern">
                <i class="fas fa-exclamation-triangle"></i> <strong>Perhatian!</strong> Silakan pilih minimal 2 alternatif untuk membandingkan.
            </div>
        <?php endif; ?>

        <footer class="text-center mt-5 mb-3">
            <p class="text-white mb-0">
                <i class="fas fa-code"></i> Sistem Pendukung Keputusan • Metode SAW 
            </p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>