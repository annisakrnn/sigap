<?php
/**
 * Pemilihan Jenis Gelar Alat & Dashboard Petugas
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('petugas');

$pdo = get_db_connection();
if (!$pdo) {
    header("Location: ../setup.php");
    exit;
}

$user = get_logged_user();

// Ambil daftar regu dari database
$stmt_regu = $pdo->query("SELECT * FROM regu ORDER BY jenis_pekerjaan, nama_regu");
$all_regus = $stmt_regu->fetchAll();

// Kelompokkan regu per jenis pekerjaan
$regu_by_jenis = [];
foreach ($all_regus as $r) {
    $regu_by_jenis[$r['jenis_pekerjaan']][] = $r;
}

// Ambil riwayat gelar alat terakhir oleh petugas ini
$stmt_hist = $pdo->prepare("SELECT h.*, r.nama_regu, r.nopol 
    FROM gelar_alat_header h 
    JOIN regu r ON h.regu_id = r.id 
    WHERE h.petugas_id = :pid 
    ORDER BY h.created_at DESC LIMIT 5");
$stmt_hist->execute([':pid' => $user['id']]);
$recent_inspections = $stmt_hist->fetchAll();

$page_title = "Form Gelar Alat";
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 2rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size: 1.65rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px; color: var(--text-main);">Pemeriksaan Gelar Alat & K3</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Pilih unit atau regu yang akan melaksanakan inspeksi apel peralatan operasional.</p>
    </div>
    <div class="topbar-date">
        <i class="fa-regular fa-calendar"></i>
        <span><?= date('d F Y') ?></span>
    </div>
</div>

<!-- Kartu Pilihan Jenis Pekerjaan -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    
    <!-- 1. P2TL -->
    <div class="card card-hover" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 3px solid #2563eb;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700;">Gelar Alat P2TL</h3>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Penertiban Tenaga Listrik</span>
                </div>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
                Checklist APD, Tang Ampere 3 Phasa, Tangga Telescopic, Spy Cam pulpen, Laptop & sarana administrasi P2TL.
            </p>
        </div>
        <div>
            <form action="form.php" method="GET">
                <input type="hidden" name="jenis" value="p2tl">
                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size:0.8rem;">Pilih Regu:</label>
                    <select name="regu_id" class="form-control" required style="font-size: 0.85rem;">
                        <?php foreach (($regu_by_jenis['p2tl'] ?? []) as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_regu']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fa-solid fa-pen-to-square"></i> Mulai Ceklis P2TL
                </button>
            </form>
        </div>
    </div>

    <!-- 2. SR APP 1 Phasa -->
    <div class="card card-hover" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 3px solid #16a34a;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-plug-circle-bolt"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700;">SR APP 1 Phasa</h3>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Penyambungan & Bongkar</span>
                </div>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
                Checklist Power Meter Clamp On, Hidrolik Dies, Tang Kombinasi, Helm, Rompi, Body Harness & Kendaraan R2/R3.
            </p>
        </div>
        <div>
            <form action="form.php" method="GET">
                <input type="hidden" name="jenis" value="sr_app">
                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size:0.8rem;">Pilih Regu:</label>
                    <select name="regu_id" class="form-control" required style="font-size: 0.85rem;">
                        <?php foreach (($regu_by_jenis['sr_app'] ?? []) as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_regu']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="width: 100%;">
                    <i class="fa-solid fa-pen-to-square"></i> Mulai Ceklis SR APP
                </button>
            </form>
        </div>
    </div>

    <!-- 3. YANDAL & ULC -->
    <div class="card card-hover" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 3px solid #d97706;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: #fffbeb; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700;">YANDAL & ULC</h3>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Pelayanan Gangguan 24 Jam</span>
                </div>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
                Telescopic Hotstick 20kV, Groundcluster, Sarung Tangan 30kV, Tang Scoon, APAR, dan armada Hilux/Verza.
            </p>
        </div>
        <div>
            <form action="form.php" method="GET">
                <input type="hidden" name="jenis" value="yandal">
                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size:0.8rem;">Pilih Regu:</label>
                    <select name="regu_id" class="form-control" required style="font-size: 0.85rem;">
                        <?php foreach (($regu_by_jenis['yandal'] ?? []) as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_regu']) ?> (<?= htmlspecialchars($r['nopol'] ?? '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="background:#d97706; color:white; width:100%; box-shadow:0 1px 2px rgba(217,119,6,0.2);">
                    <i class="fa-solid fa-pen-to-square"></i> Mulai Ceklis YANDAL
                </button>
            </form>
        </div>
    </div>

    <!-- 4. HAR (Pemeliharaan) -->
    <div class="card card-hover" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 3px solid #7c3aed;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700;">Pemeliharaan (HAR)</h3>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">HAR A Truk Hino & HAR B Carry</span>
                </div>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
                Chainsaw 14", Insulation Tester Digital, Kunci Moment, Compression Dies, Tali Seling, Sosrok Tiang, dll.
            </p>
        </div>
        <div>
            <form action="form.php" method="GET">
                <input type="hidden" name="jenis" value="har">
                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size:0.8rem;">Pilih Regu:</label>
                    <select name="regu_id" class="form-control" required style="font-size: 0.85rem;">
                        <?php foreach (($regu_by_jenis['har'] ?? []) as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_regu']) ?> (<?= htmlspecialchars($r['nopol'] ?? '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="background:#7c3aed; color:white; width: 100%; box-shadow:0 1px 2px rgba(124,58,237,0.2);">
                    <i class="fa-solid fa-pen-to-square"></i> Mulai Ceklis HAR
                </button>
            </form>
        </div>
    </div>

</div>

<!-- Riwayat Terakhir yang Diinput Petugas Ini -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);"></i> Riwayat Gelar Alat Terakhir Anda
        </h2>
        <a href="<?= base_url('riwayat/index.php') ?>" class="btn btn-outline btn-sm">
            Lihat Semua &rarr;
        </a>
    </div>

    <?php if (empty($recent_inspections)): ?>
        <div style="color: var(--text-muted); text-align: center; padding: 2.5rem 1rem;">
            <div style="width:48px; height:48px; border-radius:50%; background:#f1f5f9; color:#64748b; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.3rem;">
                <i class="fa-regular fa-folder-open"></i>
            </div>
            <strong style="color:var(--text-main);">Belum Ada Riwayat</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Silakan pilih salah satu jenis pekerjaan di atas untuk memulai gelar alat hari ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis Pekerjaan</th>
                        <th>Regu & Armada</th>
                        <th>Pelaksana</th>
                        <th>Status</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_inspections as $row): ?>
                        <tr>
                            <td><strong style="color:var(--text-main);"><?= date('d/m/Y', strtotime($row['tanggal_inspeksi'])) ?></strong></td>
                            <td>
                                <span class="badge badge-submitted" style="font-size:0.68rem;">
                                    <?= strtoupper($row['jenis_pekerjaan']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight:600; color:var(--text-main);"><?= htmlspecialchars($row['nama_regu']) ?></span>
                                <?php if (!empty($row['nopol'])): ?>
                                    <span style="color:var(--text-muted); font-size:0.785rem;"> (<?= htmlspecialchars($row['nopol']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['nama_pelaksana_1']) ?></td>
                            <td>
                                <span class="badge badge-<?= $row['status'] ?>">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="<?= base_url('riwayat/detail.php?id=' . $row['id']) ?>" class="btn btn-outline btn-sm">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </a>
                                    <?php if ($row['status'] === 'approved'): ?>
                                        <a href="<?= base_url('cetak/berita_acara.php?id=' . $row['id']) ?>" target="_blank" class="btn btn-primary btn-sm" title="Cetak Berita Acara Resmi">
                                            <i class="fa-solid fa-print"></i> Cetak
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
