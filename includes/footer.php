        </div> <!-- /.container -->
    </main> <!-- /.main-content -->
</div> <!-- /.app-layout -->

<footer class="footer no-print">
    <div class="footer-inner">
        <div><strong>SIGAP</strong> &copy; <?= date('Y') ?> &middot; Sistem Informasi Gelar Alat &amp; Perlengkapan</div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Standar K3 &amp; Kesiapan Operasional Regu</div>
    </div>
</footer>

<script src="<?= base_url('assets/js/signature.js') ?>"></script>
<script>
// ===== Sidebar Toggle =====
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('show');
    document.body.classList.add('sidebar-open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
    document.body.classList.remove('sidebar-open');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeSidebar(); closeNotifPanel(); }
});

// ===== Notifikasi =====
const NOTIF_BASE = '<?= base_url('includes/get_notifikasi.php') ?>';
const BACA_BASE  = '<?= base_url('includes/baca_notifikasi.php') ?>';

const tipeIcon = {
    perbaikan: '<i class="fa-solid fa-screwdriver-wrench"></i>',
    info:      '<i class="fa-solid fa-circle-info"></i>',
    peringatan:'<i class="fa-solid fa-triangle-exclamation"></i>',
    sukses:    '<i class="fa-solid fa-circle-check"></i>',
};

let notifPanelOpen = false;

async function pollNotifCount() {
    try {
        const r = await fetch(NOTIF_BASE + '?jumlah=1');
        const d = await r.json();
        if (!d.ok) return;
        const badge = document.getElementById('notifCount');
        if (!badge) return;
        if (d.count > 0) {
            badge.textContent = d.count > 99 ? '99+' : d.count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    } catch(e) {}
}

async function loadNotifList() {
    const list = document.getElementById('notifList');
    const bacaBtn = document.getElementById('notifBacaSemua');
    if (!list) return;
    list.innerHTML = '<div class="notif-empty"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    try {
        const r = await fetch(NOTIF_BASE);
        const d = await r.json();
        if (!d.ok || d.items.length === 0) {
            list.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i><br>Belum ada notifikasi</div>';
            if (bacaBtn) bacaBtn.style.display = 'none';
            return;
        }
        if (bacaBtn) bacaBtn.style.display = d.count > 0 ? 'block' : 'none';
        list.innerHTML = d.items.map(n => `
            <a class="notif-item ${n.sudah_dibaca == 0 ? 'unread' : ''}"
               href="${n.url_aksi || '#'}"
               onclick="bacaNotif(${n.id}, this)">
                <div class="notif-icon tipe-${n.tipe}">${tipeIcon[n.tipe] || tipeIcon.info}</div>
                <div class="notif-body">
                    <div class="notif-judul">${n.judul}</div>
                    <div class="notif-waktu">${n.waktu}</div>
                </div>
                ${n.sudah_dibaca == 0 ? '<div class="notif-dot"></div>' : ''}
            </a>
        `).join('');
    } catch(e) {
        list.innerHTML = '<div class="notif-empty">Gagal memuat notifikasi</div>';
    }
}

function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    if (!panel) return;
    notifPanelOpen = !notifPanelOpen;
    panel.style.display = notifPanelOpen ? 'block' : 'none';
    if (notifPanelOpen) loadNotifList();
}
function closeNotifPanel() {
    const panel = document.getElementById('notifPanel');
    if (panel) panel.style.display = 'none';
    notifPanelOpen = false;
}

async function bacaNotif(id, el) {
    try {
        const fd = new FormData(); fd.append('id', id);
        await fetch(BACA_BASE, { method:'POST', body: fd });
        el.classList.remove('unread');
        const dot = el.querySelector('.notif-dot');
        if (dot) dot.remove();
        await pollNotifCount();
    } catch(e) {}
}

async function bacaSemuaNotif() {
    try {
        await fetch(BACA_BASE, { method:'POST', body: new FormData() });
        document.querySelectorAll('.notif-item.unread').forEach(el => {
            el.classList.remove('unread');
            const dot = el.querySelector('.notif-dot');
            if (dot) dot.remove();
        });
        const bacaBtn = document.getElementById('notifBacaSemua');
        if (bacaBtn) bacaBtn.style.display = 'none';
        await pollNotifCount();
    } catch(e) {}
}

// Tutup panel jika klik di luar
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('notifWrap');
    if (wrap && !wrap.contains(e.target)) closeNotifPanel();
});

// Poll count setiap 30 detik
pollNotifCount();
setInterval(pollNotifCount, 30000);
</script>
</body>
</html>
