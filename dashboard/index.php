<?php
/**
 * Dashboard Monitoring & Rekapitulasi Analitik Manajemen
 * SIGAP - Sistem Informasi Gelar Alat & Perlengkapan K3
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();

// ===== Filter Periode Analitik =====
$filter_periode = isset($_GET['periode']) ? trim($_GET['periode']) : 'semua';
$filter_bulan   = isset($_GET['bulan']) && !empty($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m');

$where_header_clause = "";
$where_detail_clause = "";
$params_filter = [];

if ($filter_periode === 'bulan_ini') {
    $where_header_clause = "WHERE DATE_FORMAT(tanggal_inspeksi, '%Y-%m') = :bln";
    $where_detail_clause = "WHERE DATE_FORMAT(h.tanggal_inspeksi, '%Y-%m') = :bln";
    $params_filter[':bln'] = $filter_bulan;
} elseif ($filter_periode === 'tahun_ini') {
    $where_header_clause = "WHERE DATE_FORMAT(tanggal_inspeksi, '%Y') = :thn";
    $where_detail_clause = "WHERE DATE_FORMAT(h.tanggal_inspeksi, '%Y') = :thn";
    $params_filter[':thn'] = date('Y');
}

// ===== KPI Summary =====
$bulan_ini_str = date('Y-m');

// Total gelar alat bulan ini
$stmt_bln = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_header WHERE DATE_FORMAT(tanggal_inspeksi, '%Y-%m') = :bln");
$stmt_bln->execute([':bln' => $bulan_ini_str]);
$total_bulan = $stmt_bln->fetchColumn() ?: 0;

// Menunggu approval (submitted)
$stmt_sub = $pdo->query("SELECT COUNT(*) FROM gelar_alat_header WHERE status = 'submitted'");
$pending = $stmt_sub->fetchColumn() ?: 0;

// Sudah disetujui bulan ini
$stmt_app = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_header WHERE status = 'approved' AND DATE_FORMAT(tanggal_inspeksi, '%Y-%m') = :bln");
$stmt_app->execute([':bln' => $bulan_ini_str]);
$approved_bln = $stmt_app->fetchColumn() ?: 0;

// ===== 1. REKAPITULASI ANALITIK KELAIKAN ASET (GLOBAL) =====
$sql_global = "
    SELECT 
        COUNT(*) as total_item,
        SUM(CASE WHEN d.kondisi IN ('baik', 'ada') THEN 1 ELSE 0 END) as total_baik,
        SUM(CASE WHEN d.kondisi = 'waktu_ganti' THEN 1 ELSE 0 END) as total_waktu_ganti,
        SUM(CASE WHEN d.kondisi IN ('rusak', 'hilang', 'tidak_ada') THEN 1 ELSE 0 END) as total_rusak,
        SUM(CASE WHEN d.kondisi IN ('rusak', 'waktu_ganti', 'hilang') AND d.sudah_diperbaiki = 0 THEN 1 ELSE 0 END) as total_unresolved,
        -- Khusus APD K3
        SUM(CASE WHEN d.kategori_snapshot = 'k3_safety' THEN 1 ELSE 0 END) as total_k3,
        SUM(CASE WHEN d.kategori_snapshot = 'k3_safety' AND d.kondisi IN ('baik', 'ada') THEN 1 ELSE 0 END) as k3_baik,
        SUM(CASE WHEN d.kategori_snapshot = 'k3_safety' AND d.kondisi IN ('rusak', 'waktu_ganti', 'hilang') AND d.sudah_diperbaiki = 0 THEN 1 ELSE 0 END) as k3_rusak_aktif,
        -- Khusus Perkakas / Alat Kerja
        SUM(CASE WHEN d.kategori_snapshot = 'alat_kerja' THEN 1 ELSE 0 END) as total_alat,
        SUM(CASE WHEN d.kategori_snapshot = 'alat_kerja' AND d.kondisi IN ('baik', 'ada') THEN 1 ELSE 0 END) as alat_baik,
        SUM(CASE WHEN d.kategori_snapshot = 'alat_kerja' AND d.kondisi IN ('rusak', 'waktu_ganti', 'hilang') AND d.sudah_diperbaiki = 0 THEN 1 ELSE 0 END) as alat_rusak_aktif
    FROM gelar_alat_detail d
    JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
    $where_detail_clause
";
$stmt_global = $pdo->prepare($sql_global);
$stmt_global->execute($params_filter);
$rekap_global = $stmt_global->fetch(PDO::FETCH_ASSOC) ?: [
    'total_item' => 0, 'total_baik' => 0, 'total_waktu_ganti' => 0, 'total_rusak' => 0, 'total_unresolved' => 0,
    'total_k3' => 0, 'k3_baik' => 0, 'k3_rusak_aktif' => 0,
    'total_alat' => 0, 'alat_baik' => 0, 'alat_rusak_aktif' => 0
];

$pct_laik_global = $rekap_global['total_item'] > 0 ? round(($rekap_global['total_baik'] / $rekap_global['total_item']) * 100, 1) : 100;
$pct_laik_k3     = $rekap_global['total_k3'] > 0 ? round(($rekap_global['k3_baik'] / $rekap_global['total_k3']) * 100, 1) : 100;
$pct_laik_alat   = $rekap_global['total_alat'] > 0 ? round(($rekap_global['alat_baik'] / $rekap_global['total_alat']) * 100, 1) : 100;

// ===== 2. KELAIKAN PER KATEGORI ASET =====
$sql_kat = "
    SELECT 
        d.kategori_snapshot,
        COUNT(*) as total,
        SUM(CASE WHEN d.kondisi IN ('baik', 'ada') THEN 1 ELSE 0 END) as baik,
        SUM(CASE WHEN d.kondisi = 'waktu_ganti' THEN 1 ELSE 0 END) as waktu_ganti,
        SUM(CASE WHEN d.kondisi IN ('rusak', 'hilang', 'tidak_ada') THEN 1 ELSE 0 END) as rusak,
        SUM(CASE WHEN d.kondisi IN ('rusak', 'waktu_ganti', 'hilang') AND d.sudah_diperbaiki = 0 THEN 1 ELSE 0 END) as unresolved
    FROM gelar_alat_detail d
    JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
    $where_detail_clause
    GROUP BY d.kategori_snapshot
";
$stmt_kat = $pdo->prepare($sql_kat);
$stmt_kat->execute($params_filter);
$kat_raw = $stmt_kat->fetchAll(PDO::FETCH_ASSOC);

$kategori_labels = [
    'k3_safety' => 'APD / K3',
    'alat_kerja' => 'Perkakas Kerja',
    'kendaraan_pendukung' => 'Armada Kendaraan',
    'administrasi' => 'Administrasi & SOP'
];
$chart_kategori_data = [];
foreach ($kategori_labels as $key => $lbl) {
    $found = false;
    foreach ($kat_raw as $k) {
        if ($k['kategori_snapshot'] === $key) {
            $chart_kategori_data[$key] = [
                'label'       => $lbl,
                'total'       => intval($k['total']),
                'baik'        => intval($k['baik']),
                'waktu_ganti' => intval($k['waktu_ganti']),
                'rusak'       => intval($k['rusak']),
                'unresolved'  => intval($k['unresolved'])
            ];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $chart_kategori_data[$key] = [
            'label'       => $lbl,
            'total'       => 0,
            'baik'        => 0,
            'waktu_ganti' => 0,
            'rusak'       => 0,
            'unresolved'  => 0
        ];
    }
}

// ===== 3. KELAIKAN PER JENIS PEKERJAAN =====
$sql_jp = "
    SELECT 
        h.jenis_pekerjaan,
        COUNT(*) as total,
        SUM(CASE WHEN d.kondisi IN ('baik', 'ada') THEN 1 ELSE 0 END) as baik,
        SUM(CASE WHEN d.kondisi IN ('rusak', 'waktu_ganti', 'hilang') THEN 1 ELSE 0 END) as bermasalah
    FROM gelar_alat_detail d
    JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
    $where_detail_clause
    GROUP BY h.jenis_pekerjaan
";
$stmt_jp = $pdo->prepare($sql_jp);
$stmt_jp->execute($params_filter);
$jp_raw = $stmt_jp->fetchAll(PDO::FETCH_ASSOC);

$jp_labels = [
    'p2tl'   => 'P2TL',
    'sr_app' => 'SR & APP',
    'yandal' => 'YANDAL',
    'har'    => 'HAR'
];
$chart_jp_data = [];
foreach ($jp_labels as $key => $lbl) {
    $found = false;
    foreach ($jp_raw as $j) {
        if ($j['jenis_pekerjaan'] === $key) {
            $chart_jp_data[$key] = [
                'label'      => $lbl,
                'total'      => intval($j['total']),
                'baik'       => intval($j['baik']),
                'bermasalah' => intval($j['bermasalah'])
            ];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $chart_jp_data[$key] = [
            'label'      => $lbl,
            'total'      => 0,
            'baik'       => 0,
            'bermasalah' => 0
        ];
    }
}

// ===== 4. REKAPITULASI KEBUTUHAN PENGADAAN & PERBAIKAN ASET =====
$sql_procure = "
    SELECT 
        d.nama_barang_snapshot,
        d.kategori_snapshot,
        MAX(d.satuan) as satuan,
        COUNT(*) as total_laporan,
        SUM(CASE WHEN d.kondisi = 'rusak' THEN 1 ELSE 0 END) as jml_rusak,
        SUM(CASE WHEN d.kondisi = 'waktu_ganti' THEN 1 ELSE 0 END) as jml_waktu_ganti,
        SUM(CASE WHEN d.sudah_diperbaiki = 0 THEN 1 ELSE 0 END) as belum_selesai,
        SUM(CASE WHEN d.sudah_diperbaiki = 1 THEN 1 ELSE 0 END) as sudah_selesai,
        MAX(h.tanggal_inspeksi) as tgl_terakhir,
        GROUP_CONCAT(DISTINCT r.nama_regu ORDER BY r.nama_regu SEPARATOR ', ') as regu_terdampak,
        MIN(CASE WHEN d.sudah_diperbaiki = 0 THEN d.id ELSE NULL END) as active_detail_id
    FROM gelar_alat_detail d
    JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
    JOIN regu r ON h.regu_id = r.id
    WHERE d.kondisi IN ('rusak', 'waktu_ganti', 'hilang')
    " . ($where_detail_clause ? "AND " . substr($where_detail_clause, 6) : "") . "
    GROUP BY d.nama_barang_snapshot, d.kategori_snapshot
    ORDER BY belum_selesai DESC, jml_rusak DESC, total_laporan DESC
";
$stmt_procure = $pdo->prepare($sql_procure);
$stmt_procure->execute($params_filter);
$rekap_pengadaan = $stmt_procure->fetchAll(PDO::FETCH_ASSOC);

// ===== 5. Antrean Persetujuan (Submitted) =====
$stmt_pending = $pdo->query("
    SELECT h.*, r.nama_regu, r.kendaraan, COALESCE(NULLIF(h.nopol,''), r.nopol) AS nopol, u.nama_lengkap AS nama_petugas, u.jabatan AS jabatan_petugas,
        (SELECT COUNT(*) FROM gelar_alat_detail d WHERE d.gelar_alat_id = h.id AND d.kondisi IN ('rusak','waktu_ganti')) AS jumlah_temuan
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    WHERE h.status = 'submitted'
    ORDER BY h.created_at ASC
");
$pending_list = $stmt_pending->fetchAll();

// ===== 6. Red Flag: Feed Temuan Kerusakan Terbaru =====
$stmt_rf = $pdo->query("
    SELECT d.id AS detail_id, d.nama_barang_snapshot, d.kondisi, d.keterangan, d.foto_temuan,
           d.sudah_diperbaiki, d.foto_perbaikan, d.catatan_perbaikan, d.tgl_diperbaiki,
           h.tanggal_inspeksi, h.jenis_pekerjaan, h.id AS header_id,
           r.nama_regu
    FROM gelar_alat_detail d
    JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
    JOIN regu r ON h.regu_id = r.id
    WHERE d.kondisi IN ('rusak','waktu_ganti')
    ORDER BY d.sudah_diperbaiki ASC, h.created_at DESC
    LIMIT 30
");
$red_flags = $stmt_rf->fetchAll();

// ===== 7. Riwayat Semua Laporan (Recent) =====
$stmt_hist = $pdo->query("
    SELECT h.*, r.nama_regu, COALESCE(NULLIF(h.nopol,''), r.nopol) AS nopol, u.nama_lengkap AS nama_petugas
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    ORDER BY h.created_at DESC
    LIMIT 10
");
$recent_all = $stmt_hist->fetchAll();

$page_title = "Dashboard K3 & Rekapitulasi Gelar Alat";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header & Toolbar -->
<div class="dash-header-wrap" style="align-items:center;">
    <div>
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
            <span class="badge" style="background:linear-gradient(135deg, #0072ce 0%, #0284c7 100%); color:#ffffff; font-weight:700; font-size:0.725rem; padding:2px 9px; border-radius:20px;">
                <i class="fa-solid fa-chart-line"></i> Analitik Operasional &amp; K3
            </span>
            <span style="font-size:0.785rem; color:var(--text-muted);">&bull; Monitoring Kelaikan Alat &amp; Perlengkapan</span>
        </div>
        <h1 style="font-size:1.5rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:2px; color:var(--text-main);">
            Dashboard Rekapitulasi &amp; Analitik K3
        </h1>
        <p style="color:var(--text-muted); font-size:0.825rem; margin:0;">
            Selamat datang, <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong> &mdash; <?= htmlspecialchars($user['jabatan']) ?>
        </p>
    </div>

    <!-- Filter Periode & Action Toolbar -->
    <div class="dash-action-toolbar no-print">
        <form method="GET" action="" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); padding:5px 10px; box-shadow:var(--shadow-xs);">
                <i class="fa-solid fa-filter" style="color:var(--text-muted); font-size:0.8rem; margin-right:6px;"></i>
                <select name="periode" class="form-select-sm" onchange="toggleBulanInput(this.value); this.form.submit();" style="border:none; outline:none; font-size:0.825rem; font-weight:600; color:var(--text-main); background:transparent; cursor:pointer;">
                    <option value="semua" <?= $filter_periode === 'semua' ? 'selected' : '' ?>>Semua Data (Rekap Global)</option>
                    <option value="bulan_ini" <?= $filter_periode === 'bulan_ini' ? 'selected' : '' ?>>Filter Per Bulan</option>
                    <option value="tahun_ini" <?= $filter_periode === 'tahun_ini' ? 'selected' : '' ?>>Tahun Ini (<?= date('Y') ?>)</option>
                </select>
                <div id="wrapBulanInput" style="display:<?= $filter_periode === 'bulan_ini' ? 'inline-block' : 'none' ?>; margin-left:6px;">
                    <input type="month" name="bulan" value="<?= htmlspecialchars($filter_bulan) ?>" onchange="this.form.submit()" style="border:1px solid #cbd5e1; border-radius:4px; padding:2px 6px; font-size:0.8rem; color:var(--text-main);">
                </div>
            </div>

            <button type="button" onclick="window.print()" class="btn btn-outline btn-sm" title="Cetak Rekapitulasi Analitik">
                <i class="fa-solid fa-print"></i> Cetak Laporan
            </button>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- NOTIFIKASI / ALERT MENDESAK K3 & PERKAKAS RUSAK                           -->
<!-- ========================================================================= -->
<?php 
$total_unresolved = intval($rekap_global['total_unresolved']);
$k3_rusak_aktif   = intval($rekap_global['k3_rusak_aktif']);
$alat_rusak_aktif = intval($rekap_global['alat_rusak_aktif']);
?>

<?php if ($total_unresolved > 0): ?>
    <div class="alert-k3-banner">
        <div class="alert-k3-icon pulse-alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div style="flex:1; min-width:0;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span class="badge" style="background:#dc2626; color:#ffffff; font-weight:700; font-size:0.7rem; letter-spacing:0.03em; padding:2px 8px;">
                            PERHATIAN K3
                        </span>
                        <strong style="font-size:0.925rem; color:#991b1b;">
                            Terdeteksi <?= $total_unresolved ?> Item Rusak / Perlu Penggantian yang Belum Selesai!
                        </strong>
                    </div>
                    <p style="margin:3px 0 0 0; font-size:0.8rem; color:#7f1d1d; line-height:1.45;">
                        Data checklist masuk mengindikasikan adanya alat pelindung diri atau perkakas kerja yang rusak. Kondisi ini memerlukan perbaikan atau pengadaan unit baru segera.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <?php if ($k3_rusak_aktif > 0): ?>
                        <span class="badge badge-rejected" style="font-size:0.72rem; padding:3px 8px;">
                            <i class="fa-solid fa-shield-halved"></i> <strong><?= $k3_rusak_aktif ?> APD Rusak</strong>
                        </span>
                    <?php endif; ?>
                    <?php if ($alat_rusak_aktif > 0): ?>
                        <span class="badge" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:0.72rem; padding:3px 8px;">
                            <i class="fa-solid fa-wrench"></i> <strong><?= $alat_rusak_aktif ?> Perkakas Rusak</strong>
                        </span>
                    <?php endif; ?>
                    <a href="#sectionPeralatanRusak" class="btn btn-sm" style="background:#dc2626; color:#ffffff; font-size:0.75rem; font-weight:700; padding:5px 12px; border-radius:6px; box-shadow:0 2px 4px rgba(220,38,38,0.25); text-decoration:none;">
                        <i class="fa-solid fa-arrow-down"></i> Tindak Lanjut
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert-k3-safe">
        <div style="width:36px; height:36px; border-radius:50%; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex-shrink:0;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div style="flex:1;">
            <div style="font-weight:700; color:#166534; font-size:0.9rem;">
                Kondisi Perlengkapan K3 &amp; Perkakas Prima
            </div>
            <div style="font-size:0.8rem; color:#15803d; margin-top:2px;">
                Seluruh data checklist yang masuk berada dalam kondisi laik pakai. Tidak ada temuan kerusakan alat yang tertunda.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- KARTU INDEKS KELAIKAN ALAT (HEALTH SCORE & READINESS)                     -->
<!-- ========================================================================= -->
<div class="readiness-grid">
    <!-- Health Index Global -->
    <div class="readiness-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <span class="readiness-title">Indeks Kelaikan Global</span>
                <div class="readiness-value" style="color:<?= $pct_laik_global >= 90 ? '#16a34a' : ($pct_laik_global >= 75 ? '#d97706' : '#dc2626') ?>;">
                    <?= $pct_laik_global ?>%
                </div>
            </div>
            <div class="readiness-icon" style="background:#f0fdf4; color:#16a34a;">
                <i class="fa-solid fa-shield-heart"></i>
            </div>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?= min(max($pct_laik_global, 0), 100) ?>%; background:<?= $pct_laik_global >= 90 ? '#16a34a' : ($pct_laik_global >= 75 ? '#d97706' : '#dc2626') ?>;"></div>
        </div>
        <div class="readiness-meta">
            <span><strong><?= $rekap_global['total_baik'] ?></strong> Laik dari <strong><?= $rekap_global['total_item'] ?></strong> item</span>
        </div>
    </div>

    <!-- Kelaikan APD K3 -->
    <div class="readiness-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <span class="readiness-title">Kelaikan APD (Safety K3)</span>
                <div class="readiness-value" style="color:<?= $pct_laik_k3 >= 90 ? '#16a34a' : ($pct_laik_k3 >= 75 ? '#d97706' : '#dc2626') ?>;">
                    <?= $pct_laik_k3 ?>%
                </div>
            </div>
            <div class="readiness-icon" style="background:#eff6ff; color:#0284c7;">
                <i class="fa-solid fa-helmet-safety"></i>
            </div>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?= min(max($pct_laik_k3, 0), 100) ?>%; background:<?= $pct_laik_k3 >= 90 ? '#0284c7' : ($pct_laik_k3 >= 75 ? '#d97706' : '#dc2626') ?>;"></div>
        </div>
        <div class="readiness-meta">
            <span>
                <?php if ($k3_rusak_aktif > 0): ?>
                    <strong style="color:#dc2626;"><?= $k3_rusak_aktif ?> APD butuh ganti</strong>
                <?php else: ?>
                    <span style="color:#16a34a;"><i class="fa-solid fa-check"></i> 100% Siap</span>
                <?php endif; ?>
                &bull; Total <?= $rekap_global['total_k3'] ?> item
            </span>
        </div>
    </div>

    <!-- Kelaikan Perkakas & Alat Kerja -->
    <div class="readiness-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <span class="readiness-title">Kelaikan Perkakas Kerja</span>
                <div class="readiness-value" style="color:<?= $pct_laik_alat >= 90 ? '#16a34a' : ($pct_laik_alat >= 75 ? '#d97706' : '#dc2626') ?>;">
                    <?= $pct_laik_alat ?>%
                </div>
            </div>
            <div class="readiness-icon" style="background:#fef3c7; color:#d97706;">
                <i class="fa-solid fa-toolbox"></i>
            </div>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?= min(max($pct_laik_alat, 0), 100) ?>%; background:<?= $pct_laik_alat >= 90 ? '#16a34a' : ($pct_laik_alat >= 75 ? '#d97706' : '#dc2626') ?>;"></div>
        </div>
        <div class="readiness-meta">
            <span>
                <?php if ($alat_rusak_aktif > 0): ?>
                    <strong style="color:#b45309;"><?= $alat_rusak_aktif ?> perkakas rusak</strong>
                <?php else: ?>
                    <span style="color:#16a34a;"><i class="fa-solid fa-check"></i> Kondisi Baik</span>
                <?php endif; ?>
                &bull; Total <?= $rekap_global['total_alat'] ?> item
            </span>
        </div>
    </div>

    <!-- Kebutuhan Pengadaan / Servis Aktif -->
    <div class="readiness-card" style="border-left:4px solid <?= $total_unresolved > 0 ? '#dc2626' : '#16a34a' ?>;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <span class="readiness-title">Kebutuhan Servis / Ganti</span>
                <div class="readiness-value" style="color:<?= $total_unresolved > 0 ? '#dc2626' : '#16a34a' ?>;">
                    <?= $total_unresolved ?> <span style="font-size:0.9rem; font-weight:600; color:var(--text-muted);">item</span>
                </div>
            </div>
            <div class="readiness-icon" style="background:<?= $total_unresolved > 0 ? '#fee2e2' : '#f0fdf4' ?>; color:<?= $total_unresolved > 0 ? '#dc2626' : '#16a34a' ?>;">
                <i class="fa-solid fa-cart-flatbed"></i>
            </div>
        </div>
        <div class="readiness-meta" style="margin-top:12px;">
            <span style="color:<?= $total_unresolved > 0 ? '#b91c1c' : '#16a34a' ?>; font-weight:600;">
                <?= $total_unresolved > 0 ? 'Menunggu perbaikan/pengadaan' : 'Semua kebutuhan terpenuhi' ?>
            </span>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- GRAFIK KELAYAKAN ALAT (VISUAL ANALYTICS BERSIH & LEGA)                     -->
<!-- ========================================================================= -->
<div class="charts-grid-top">
    <!-- Chart 1: Donut Distribusi Kondisi Aset -->
    <div class="card chart-card">
        <div class="card-header" style="padding-bottom:10px;">
            <div>
                <h2 class="card-title" style="font-size:0.95rem;">
                    <i class="fa-solid fa-chart-pie" style="color:var(--primary);"></i> Proporsi Status Kondisi Aset
                </h2>
                <span style="font-size:0.75rem; color:var(--text-muted);">Persentase kelaikan seluruh perlengkapan checklist</span>
            </div>
        </div>
        <div style="position:relative; height:220px; display:flex; align-items:center; justify-content:center;">
            <canvas id="chartKondisiDonut"></canvas>
        </div>
        <div style="display:flex; justify-content:space-around; padding-top:12px; border-top:1px solid var(--border-light); margin-top:8px;">
            <div style="text-align:center;">
                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#16a34a; margin-right:4px;"></span>
                <span style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Baik</span>
                <div style="font-size:0.95rem; font-weight:800; color:#16a34a;"><?= $rekap_global['total_baik'] ?></div>
            </div>
            <div style="text-align:center;">
                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#eab308; margin-right:4px;"></span>
                <span style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Waktu Ganti</span>
                <div style="font-size:0.95rem; font-weight:800; color:#ca8a04;"><?= $rekap_global['total_waktu_ganti'] ?></div>
            </div>
            <div style="text-align:center;">
                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#dc2626; margin-right:4px;"></span>
                <span style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Rusak / Hilang</span>
                <div style="font-size:0.95rem; font-weight:800; color:#dc2626;"><?= $rekap_global['total_rusak'] ?></div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Bar Chart Kelaikan per Kategori Aset -->
    <div class="card chart-card">
        <div class="card-header" style="padding-bottom:10px;">
            <div>
                <h2 class="card-title" style="font-size:0.95rem;">
                    <i class="fa-solid fa-layer-group" style="color:var(--primary);"></i> Kelaikan per Kategori Aset
                </h2>
                <span style="font-size:0.75rem; color:var(--text-muted);">Perbandingan item Baik vs Rusak / Waktu Ganti</span>
            </div>
        </div>
        <div style="position:relative; height:260px;">
            <canvas id="chartKategoriBar"></canvas>
        </div>
    </div>
</div>

<!-- Chart 3: Bar Chart Kelaikan per Jenis Pekerjaan (Full Width Lega) -->
<div class="card chart-card" style="margin-bottom:1.5rem;">
    <div class="card-header" style="padding-bottom:10px;">
        <div>
            <h2 class="card-title" style="font-size:0.95rem;">
                <i class="fa-solid fa-users-gear" style="color:var(--primary);"></i> Kelaikan per Jenis Pekerjaan Regu
            </h2>
            <span style="font-size:0.75rem; color:var(--text-muted);">Tingkat kesiapan perlengkapan di masing-masing jenis pekerjaan lapangan</span>
        </div>
    </div>
    <div style="position:relative; height:240px;">
        <canvas id="chartJenisPekerjaanBar"></canvas>
    </div>
</div>

<!-- ========================================================================= -->
<!-- DAFTAR MONITORING PERALATAN RUSAK & TINDAK LANJUT PERBAIKAN               -->
<!-- ========================================================================= -->
<?php
$rusak_unresolved_count = 0;
foreach ($red_flags as $rf) {
    if (!$rf['sudah_diperbaiki']) $rusak_unresolved_count++;
}
?>
<div class="card" id="sectionPeralatanRusak" style="margin-bottom:1.5rem;">
    <div class="card-header" style="flex-wrap:wrap; gap:12px; align-items:center;">
        <div>
            <div style="display:flex; align-items:center; gap:8px;">
                <h2 class="card-title">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i> Monitoring Peralatan Rusak &amp; Tindak Lanjut Perbaikan
                </h2>
                <?php if ($rusak_unresolved_count > 0): ?>
                    <span class="badge" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-weight:700; animation:pulseAlert 2s infinite;">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= $rusak_unresolved_count ?> Perlu Segera Diperbaiki!
                    </span>
                <?php else: ?>
                    <span class="badge badge-approved" style="font-size:0.75rem;">
                        <i class="fa-solid fa-circle-check"></i> Seluruh Alat Siap / Baik
                    </span>
                <?php endif; ?>
            </div>
            <span style="font-size:0.78rem; color:var(--text-muted); margin-top:2px; display:block;">
                Daftar peralatan kerja dan APD yang dilaporkan rusak / waktu ganti beserta kontrol status perbaikan
            </span>
        </div>

        <!-- Filter Tabs & Quick Search -->
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <div style="display:flex; gap:4px; background:#f1f5f9; padding:3px; border-radius:8px;">
                <button type="button" class="btn btn-sm procure-tab active" id="tabRfAll" onclick="filterRusakStatus('all')">
                    Semua (<?= count($red_flags) ?>)
                </button>
                <button type="button" class="btn btn-sm procure-tab" id="tabRfPending" onclick="filterRusakStatus('pending')">
                    <span style="color:#b91c1c; font-weight:700;">● Belum Diperbaiki (<?= $rusak_unresolved_count ?>)</span>
                </button>
                <button type="button" class="btn btn-sm procure-tab" id="tabRfDone" onclick="filterRusakStatus('done')">
                    <span style="color:#15803d; font-weight:600;">✓ Sudah Diperbaiki (<?= count($red_flags) - $rusak_unresolved_count ?>)</span>
                </button>
            </div>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.75rem;"></i>
                <input type="text" id="searchRusakInput" oninput="searchRusakTable()" placeholder="Cari alat / regu..." class="form-control" style="padding-left:28px; font-size:0.8rem; height:32px; width:180px;">
            </div>
        </div>
    </div>

    <?php if (empty($red_flags)): ?>
        <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
            <div style="width:54px; height:54px; border-radius:50%; background:#f0fdf4; color:#16a34a; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:1.5rem;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <strong style="color:var(--text-main); font-size:0.95rem;">Kondisi Aman &amp; Baik</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Seluruh peralatan kerja dan APD yang dilaporkan saat ini berada dalam kondisi siap pakai.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table" id="tableRusak">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">#</th>
                        <th>Nama Alat / Perlengkapan</th>
                        <th>Regu &amp; Tanggal Gelar</th>
                        <th>Kondisi Temuan</th>
                        <th>Status Perbaikan</th>
                        <th style="text-align:center;">Ubah Status / Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($red_flags as $rf): 
                        $is_done = !empty($rf['sudah_diperbaiki']);
                        $is_rusak = ($rf['kondisi'] === 'rusak');
                        $search_text = strtolower($rf['nama_barang_snapshot'] . ' ' . $rf['nama_regu'] . ' ' . (isset($rf['keterangan']) ? $rf['keterangan'] : ''));
                    ?>
                        <tr class="row-rusak" 
                            id="rf-<?= $rf['detail_id'] ?>"
                            data-status="<?= $is_done ? 'done' : 'pending' ?>"
                            data-search="<?= htmlspecialchars($search_text) ?>"
                            style="<?= !$is_done ? 'background:#fffbfb;' : '' ?>">
                            <td style="text-align:center; font-weight:600; color:var(--text-muted);"><?= $no++ ?></td>
                            <td>
                                <div style="font-weight:700; color:var(--text-main); font-size:0.885rem;">
                                    <?= htmlspecialchars($rf['nama_barang_snapshot']) ?>
                                </div>
                                <?php if (!empty($rf['keterangan'])): ?>
                                    <div style="font-size:0.775rem; color:var(--text-muted); margin-top:2px; font-style:italic;">
                                        &ldquo;<?= htmlspecialchars($rf['keterangan']) ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text-main); font-size:0.85rem;">
                                    <?= htmlspecialchars($rf['nama_regu']) ?>
                                </div>
                                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                    <span class="badge" style="background:#e2e8f0; color:#475569; font-size:0.65rem; padding:1px 5px;">
                                        <?= strtoupper($rf['jenis_pekerjaan']) ?>
                                    </span>
                                    &bull; <?= date('d/m/Y', strtotime($rf['tanggal_inspeksi'])) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($is_rusak): ?>
                                    <span class="badge" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-weight:700; font-size:0.75rem;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> RUSAK
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:#fffbeb; color:#d97706; border:1px solid #fde68a; font-weight:700; font-size:0.75rem;">
                                        <i class="fa-solid fa-clock-rotate-left"></i> WAKTU GANTI
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_done): ?>
                                    <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-weight:700; font-size:0.75rem;">
                                        <i class="fa-solid fa-circle-check"></i> Sudah Diperbaiki
                                    </span>
                                    <?php if (!empty($rf['tgl_diperbaiki'])): ?>
                                        <div style="font-size:0.72rem; color:var(--text-muted); margin-top:3px;">
                                            <?= date('d/m/Y H:i', strtotime($rf['tgl_diperbaiki'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($rf['catatan_perbaikan'])): ?>
                                        <div style="font-size:0.72rem; color:#15803d; margin-top:1px;">
                                            Catatan: <?= htmlspecialchars($rf['catatan_perbaikan']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge" style="background:#fef2f2; color:#dc2626; border:1px solid #f87171; font-weight:700; font-size:0.75rem;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Belum Diperbaiki
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <div style="display:inline-flex; gap:8px; align-items:center; justify-content:center;">
                                    <?php if (!$is_done): ?>
                                        <button type="button" 
                                            onclick="showDoneModal(<?= $rf['detail_id'] ?>, '<?= addslashes(htmlspecialchars($rf['nama_barang_snapshot'])) ?>')"
                                            class="btn btn-sm btn-success" 
                                            style="padding:4px 10px; font-size:0.75rem; font-weight:700;"
                                            title="Tandai Sudah Diperbaiki">
                                            <i class="fa-solid fa-check"></i> Tandai Diperbaiki
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                            onclick="toggleBelum(<?= $rf['detail_id'] ?>)" 
                                            class="btn btn-sm btn-outline" 
                                            style="padding:4px 8px; font-size:0.75rem; color:#dc2626; border-color:#fecaca;"
                                            title="Ubah kembali status ke Belum Diperbaiki">
                                            <i class="fa-solid fa-rotate-left"></i> Set Belum
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?= base_url('riwayat/detail.php?id=' . $rf['header_id']) ?>" class="btn-icon btn-icon-detail" title="Lihat Dokumen Pemeriksaan">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- MODAL KONFIRMASI SELESAI DIPERBAIKI / DIGANTI                             -->
<!-- ========================================================================= -->
<div id="doneModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:460px; width:90%; box-shadow:var(--shadow-xl); animation:modalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="text-align:center; margin-bottom:1.25rem;">
            <div style="width:52px; height:52px; background:#f0fdf4; border-radius:var(--radius-lg); display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.4rem; color:#16a34a; box-shadow:0 4px 12px rgba(22,163,74,0.15);">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
            <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-main); margin-bottom:4px;">Tandai Selesai Diperbaiki / Diganti</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">Alat: <strong id="doneItemName" style="color:var(--text-main);"></strong></p>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" for="catatanPerbaikan">Catatan Tindakan Perbaikan / Pengadaan <span style="font-weight:400; color:var(--text-muted);">(opsional)</span></label>
            <textarea id="catatanPerbaikan" class="form-control" rows="2" placeholder="Contoh: Diganti unit baru dari gudang K3, sudah diuji kelaikannya..."></textarea>
        </div>

        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label" for="fotoPerbaikanInput">Foto Bukti Perbaikan / Penggantian <span style="font-weight:400; color:var(--text-muted);">(opsional)</span></label>
            <input type="file" id="fotoPerbaikanInput" class="form-control" accept="image/*">
            <small style="color:var(--text-muted); font-size:0.75rem;">Unggah foto fisik alat yang baru / sudah selesai diservis (JPG/PNG/WEBP).</small>
            <div id="previewFotoPerbaikan" style="display:none; margin-top:8px;">
                <img id="imgFotoPerbaikan" style="max-height:65px; border-radius:6px; border:2px solid #10b981;">
                <span id="badgeFotoPerbaikan" style="font-size:0.75rem; color:#047857; margin-left:8px;"></span>
            </div>
        </div>

        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:var(--radius-md); padding:8px 12px; font-size:0.78rem; color:#b45309; margin-bottom:1.25rem; display:flex; align-items:flex-start; gap:8px;">
            <i class="fa-solid fa-bell" style="margin-top:2px;"></i>
            <span>Notifikasi otomatis akan dikirim ke <strong>petugas pemeriksa</strong> yang melaporkan temuan ini.</span>
        </div>

        <div style="display:flex; gap:10px;">
            <button onclick="closeDoneModal()" class="btn btn-outline" style="flex:1;">Batal</button>
            <button onclick="submitDone()" class="btn btn-success" style="flex:1;" id="btnSubmitDone">
                <i class="fa-solid fa-check"></i> Simpan Selesai
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- STYLES KHUSUS DASHBOARD ANALITIK & REKAPITULASI                           -->
<!-- ========================================================================= -->
<style>
.dash-header-wrap {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:1.5rem;
    flex-wrap:wrap;
    gap:1rem;
}
.dash-action-toolbar {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

/* Alert K3 Banner */
.alert-k3-banner {
    display:flex;
    align-items:flex-start;
    gap:14px;
    background:linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
    border:1.5px solid #f87171;
    border-radius:var(--radius-lg);
    padding:16px 20px;
    margin-bottom:1.5rem;
    box-shadow:0 4px 16px rgba(239,68,68,0.12);
}
.alert-k3-icon {
    width:46px;
    height:46px;
    border-radius:12px;
    background:#dc2626;
    color:#ffffff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.35rem;
    flex-shrink:0;
    box-shadow:0 4px 10px rgba(220,38,38,0.3);
}
.pulse-alert {
    animation:pulseAlert 2s infinite;
}
@keyframes pulseAlert {
    0% { transform:scale(1); box-shadow:0 0 0 0 rgba(220,38,38,0.4); }
    70% { transform:scale(1.05); box-shadow:0 0 0 10px rgba(220,38,38,0); }
    100% { transform:scale(1); box-shadow:0 0 0 0 rgba(220,38,38,0); }
}

.alert-k3-safe {
    display:flex;
    align-items:center;
    gap:14px;
    background:linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border:1.5px solid #86efac;
    border-radius:var(--radius-lg);
    padding:14px 18px;
    margin-bottom:1.5rem;
    box-shadow:0 2px 8px rgba(22,163,74,0.08);
}

/* Readiness & KPI Grid */
.readiness-grid {
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:1rem;
    margin-bottom:1.5rem;
}
.readiness-card {
    background:#ffffff;
    border:1px solid var(--border-color);
    border-radius:var(--radius-lg);
    padding:16px;
    box-shadow:var(--shadow-sm);
    transition:transform 0.18s ease, box-shadow 0.18s ease;
}
.readiness-card:hover {
    transform:translateY(-2px);
    box-shadow:var(--shadow-md);
}
.readiness-title {
    font-size:0.75rem;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:0.04em;
    color:var(--text-muted);
}
.readiness-value {
    font-size:1.75rem;
    font-weight:800;
    letter-spacing:-0.03em;
    margin-top:2px;
}
.readiness-icon {
    width:38px;
    height:38px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.15rem;
    flex-shrink:0;
}
.progress-bar-wrap {
    height:6px;
    background:#e2e8f0;
    border-radius:10px;
    overflow:hidden;
    margin-top:10px;
}
.progress-bar-fill {
    height:100%;
    border-radius:10px;
    transition:width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.readiness-meta {
    font-size:0.75rem;
    color:var(--text-muted);
    margin-top:8px;
}

/* Charts Grid */
.charts-grid-top {
    display:grid;
    grid-template-columns:1fr 1.25fr;
    gap:1.25rem;
    margin-bottom:1.25rem;
}
.chart-card {
    padding:16px 20px;
}

/* Procurement Table Tabs */
.procure-tab {
    background:transparent;
    color:var(--text-muted);
    border:none;
    font-size:0.75rem;
    font-weight:600;
    padding:4px 10px;
    border-radius:6px;
    cursor:pointer;
    transition:all 0.15s ease;
}
.procure-tab.active {
    background:#ffffff;
    color:var(--primary);
    box-shadow:0 1px 3px rgba(0,0,0,0.1);
}

@keyframes modalIn {
    from { opacity:0; transform:scale(0.96) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
.rf-done { opacity:0.85; }
#doneModal.show { display:flex !important; }
.rf-tab {
    background:#f1f5f9;
    color:var(--text-muted);
    border:1px solid var(--border-color);
}
.rf-tab.active {
    background:var(--primary);
    color:white !important;
    border-color:var(--primary);
}

/* Print Optimization */
@media print {
    .no-print, .dash-action-toolbar, .sidebar, .sidebar-overlay, .sidebar-close-btn, #doneModal, .btn, .procure-tab, #searchProcureInput, #searchRfInput, .rf-tab {
        display:none !important;
    }
    body { background:#ffffff; color:#000000; font-size:11pt; }
    .main-content { margin-left:0 !important; padding:0 !important; }
    .card { box-shadow:none !important; border:1px solid #ccc !important; break-inside:avoid; }
    .charts-grid-top { grid-template-columns:1fr 1fr; }
}

/* Responsive */
@media (max-width: 1024px) {
    .readiness-grid { grid-template-columns:repeat(2, 1fr); }
    .charts-grid-top { grid-template-columns:1fr; }
}
@media (max-width: 640px) {
    .readiness-grid { grid-template-columns:1fr; }
}
</style>

<!-- Chart.js Offline First with CDN Fallback -->
<script src="<?= base_url('assets/js/chart.umd.min.js') ?>"></script>
<script>
if (typeof Chart === 'undefined') {
    // CDN Fallback if local file fails
    document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
}
</script>

<script src="<?= base_url('assets/js/image-compress.js') ?>"></script>

<script>
// Toggle Input Bulan jika periode bulan_ini
function toggleBulanInput(val) {
    const el = document.getElementById('wrapBulanInput');
    if (el) el.style.display = (val === 'bulan_ini') ? 'inline-block' : 'none';
}

// ============================================================================
// INISIALISASI GRAFIK KELAYAKAN ALAT (CHART.JS)
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js belum siap.');
        return;
    }

    Chart.defaults.font.family = "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Chart Donut Status Kondisi Aset
    const ctxDonut = document.getElementById('chartKondisiDonut');
    if (ctxDonut) {
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Baik / Laik', 'Waktu Ganti', 'Rusak / Hilang'],
                datasets: [{
                    data: [
                        <?= intval($rekap_global['total_baik']) ?>,
                        <?= intval($rekap_global['total_waktu_ganti']) ?>,
                        <?= intval($rekap_global['total_rusak']) ?>
                    ],
                    backgroundColor: ['#16a34a', '#eab308', '#dc2626'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = <?= max(intval($rekap_global['total_item']), 1) ?>;
                                const val = context.raw || 0;
                                const pct = ((val / total) * 100).toFixed(1);
                                return ` ${context.label}: ${val} item (${pct}%)`;
                            }
                        }
                    }
                },
                cutout: '72%'
            }
        });
    }

    // 2. Chart Bar Kelaikan per Kategori Aset
    const ctxKat = document.getElementById('chartKategoriBar');
    if (ctxKat) {
        const labelsKat = <?= json_encode(array_column($chart_kategori_data, 'label')) ?>;
        const dataBaikKat = <?= json_encode(array_column($chart_kategori_data, 'baik')) ?>;
        const dataRusakKat = <?= json_encode(array_map(function($item) {
            return $item['rusak'] + $item['waktu_ganti'];
        }, $chart_kategori_data)) ?>;

        new Chart(ctxKat, {
            type: 'bar',
            data: {
                labels: labelsKat,
                datasets: [
                    {
                        label: 'Laik / Baik',
                        data: dataBaikKat,
                        backgroundColor: '#16a34a',
                        borderRadius: 6
                    },
                    {
                        label: 'Rusak / Perlu Ganti',
                        data: dataRusakKat,
                        backgroundColor: '#dc2626',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });
    }

    // 3. Chart Bar Kelaikan per Jenis Pekerjaan
    const ctxJp = document.getElementById('chartJenisPekerjaanBar');
    if (ctxJp) {
        const labelsJp = <?= json_encode(array_column($chart_jp_data, 'label')) ?>;
        const dataBaikJp = <?= json_encode(array_column($chart_jp_data, 'baik')) ?>;
        const dataBermasalahJp = <?= json_encode(array_column($chart_jp_data, 'bermasalah')) ?>;

        new Chart(ctxJp, {
            type: 'bar',
            data: {
                labels: labelsJp,
                datasets: [
                    {
                        label: 'Kondisi Baik',
                        data: dataBaikJp,
                        backgroundColor: '#0284c7',
                        borderRadius: 6
                    },
                    {
                        label: 'Ada Temuan Rusak',
                        data: dataBermasalahJp,
                        backgroundColor: '#f59e0b',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });
    }
});

// ============================================================================
// FILTER & SEARCH TABEL REKAPITULASI PENGADAAN
// ============================================================================
let _activeProcureFilter = 'all';

function filterProcure(type) {
    _activeProcureFilter = type;
    document.querySelectorAll('.procure-tab').forEach(b => b.classList.remove('active'));
    if (type === 'all') document.getElementById('tabProcureAll').classList.add('active');
    if (type === 'k3_safety') document.getElementById('tabProcureK3').classList.add('active');
    if (type === 'alat_kerja') document.getElementById('tabProcureAlat').classList.add('active');
    if (type === 'unresolved') document.getElementById('tabProcureUnresolved').classList.add('active');

    searchProcureTable();
}

function searchProcureTable() {
    const query = document.getElementById('searchProcureInput')?.value.toLowerCase().trim() || '';
    const rows = document.querySelectorAll('#tableProcure tbody tr.procure-row');

    rows.forEach(r => {
        const kat = r.dataset.kategori;
        const status = r.dataset.status;
        const search = r.dataset.search || '';

        let matchType = false;
        if (_activeProcureFilter === 'all') {
            matchType = true;
        } else if (_activeProcureFilter === 'k3_safety') {
            matchType = (kat === 'k3_safety');
        } else if (_activeProcureFilter === 'alat_kerja') {
            matchType = (kat === 'alat_kerja');
        } else if (_activeProcureFilter === 'unresolved') {
            matchType = (status === 'unresolved');
        }

        const matchQuery = (!query || search.includes(query));
        r.style.display = (matchType && matchQuery) ? '' : 'none';
    });
}

// ============================================================================
// MONITORING PERALATAN RUSAK - LIVE SEARCH & FILTER
// ============================================================================
let _doneDetailId = null;
let _currentRusakFilter = 'all';

function filterRusakStatus(status) {
    _currentRusakFilter = status;
    document.querySelectorAll('.procure-tab').forEach(b => b.classList.remove('active'));
    if (status === 'all') document.getElementById('tabRfAll')?.classList.add('active');
    if (status === 'pending') document.getElementById('tabRfPending')?.classList.add('active');
    if (status === 'done') document.getElementById('tabRfDone')?.classList.add('active');
    applyRusakTableFilter();
}

function searchRusakTable() {
    applyRusakTableFilter();
}

function applyRusakTableFilter() {
    const query = (document.getElementById('searchRusakInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#tableRusak .row-rusak');
    rows.forEach(r => {
        const status = r.dataset.status;
        const search = r.dataset.search || '';
        const matchStatus = (_currentRusakFilter === 'all' || status === _currentRusakFilter);
        const matchQuery  = (!query || search.includes(query));
        r.style.display = (matchStatus && matchQuery) ? '' : 'none';
    });
}

async function toggleBelum(detailId) {
    if (!confirm('Apakah Anda yakin ingin mengubah status alat ini kembali menjadi BELUM DIPERBAIKI?')) return;
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    formData.append('detail_id', detailId);
    formData.append('status', 'pending');

    try {
        const resp = await fetch('<?= base_url('dashboard/mark_done.php') ?>', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        if (data.ok) {
            showToast('✅ ' + data.pesan, 'sukses');
            setTimeout(() => { location.reload(); }, 600);
        } else {
            showToast('⚠ ' + data.pesan, 'peringatan');
        }
    } catch(e) {
        showToast('❌ Gagal terhubung ke server.', 'error');
    }
}

// Modal Done Handling
function showDoneModal(detailId, namaBarang) {
    _doneDetailId = detailId;
    document.getElementById('doneItemName').textContent = namaBarang;
    document.getElementById('catatanPerbaikan').value = '';
    const fileInput = document.getElementById('fotoPerbaikanInput');
    if (fileInput) fileInput.value = '';
    document.getElementById('previewFotoPerbaikan').style.display = 'none';
    document.getElementById('doneModal').classList.add('show');
}

function closeDoneModal() {
    document.getElementById('doneModal').classList.remove('show');
    _doneDetailId = null;
}

// Image preview & compression for perbaikan
document.getElementById('fotoPerbaikanInput')?.addEventListener('change', async function() {
    if (this.files[0]) {
        const file = this.files[0];
        try {
            const compressed = await window.ImageCompressor.compress(file, { maxDimension: 1280, quality: 0.8 });
            const dt = new DataTransfer();
            dt.items.add(compressed);
            this.files = dt.files;
            document.getElementById('imgFotoPerbaikan').src = URL.createObjectURL(compressed);
            document.getElementById('badgeFotoPerbaikan').textContent = 'Foto siap (' + Math.round(compressed.size/1024) + ' KB)';
            document.getElementById('previewFotoPerbaikan').style.display = 'flex';
        } catch(e) {
            document.getElementById('imgFotoPerbaikan').src = URL.createObjectURL(file);
            document.getElementById('previewFotoPerbaikan').style.display = 'flex';
        }
    }
});

async function submitDone() {
    if (!_doneDetailId) return;
    const btn = document.getElementById('btnSubmitDone');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    const catatan = document.getElementById('catatanPerbaikan').value.trim();
    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    formData.append('detail_id', _doneDetailId);
    formData.append('catatan_perbaikan', catatan);

    const fileInput = document.getElementById('fotoPerbaikanInput');
    if (fileInput && fileInput.files[0]) {
        formData.append('foto_perbaikan', fileInput.files[0]);
    }

    try {
        const resp = await fetch('<?= base_url('dashboard/mark_done.php') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await resp.json();

        if (data.ok) {
            closeDoneModal();
            showToast('✅ ' + data.pesan, 'sukses');
            setTimeout(() => { location.reload(); }, 700);
        } else {
            showToast('⚠ ' + data.pesan, 'peringatan');
        }
    } catch (e) {
        showToast('❌ Gagal terhubung ke server.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Simpan Selesai';
    }
}

function showToast(pesan, tipe = 'sukses') {
    const colors = {
        sukses: { bg:'#d1fae5', border:'#a7f3d0', color:'#065f46' },
        peringatan: { bg:'#fef3c7', border:'#fde68a', color:'#92400e' },
        error: { bg:'#fee2e2', border:'#fecaca', color:'#991b1b' },
    };
    const c = colors[tipe] || colors.sukses;
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:99999;padding:14px 20px;border-radius:12px;
        background:${c.bg};border:1px solid ${c.border};color:${c.color};
        font-size:0.9rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.12);
        animation:modalIn 0.25s ease;max-width:340px;font-family:'Plus Jakarta Sans',sans-serif;`;
    toast.innerHTML = pesan;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// Tutup modal jika klik backdrop
document.getElementById('doneModal').addEventListener('click', function(e) {
    if (e.target === this) closeDoneModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
