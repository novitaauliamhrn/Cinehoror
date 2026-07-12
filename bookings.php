<?php
// admin/bookings.php — Kelola Booking
session_start();
require_once '../config/database.php';
require_once '../config/whatsapp.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();
$msg = ''; $msg_type = '';

// ---- CANCEL ----
if (isset($_GET['cancel'])) {
    $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([(int)$_GET['cancel']]);
    $msg = '✅ Booking dibatalkan.'; $msg_type = 'success';
}

// ---- RESEND WA (FIXED) ----
if (isset($_GET['resend_wa'])) {
    $booking = getBookingByCode($_GET['resend_wa']);
    if ($booking) {
        // FIX: Query yang benar untuk mendapatkan film_id
        $stmt = $db->prepare("SELECT film_id FROM schedules WHERE id = ?");
        $stmt->execute([$booking['schedule_id']]);
        $film_id = $stmt->fetchColumn();
        
        if ($film_id) {
            $film_data = getFilmById($film_id);
            
            $wa_message = "🎬 *CINEHOROR BIOSKOP*\n\n";
            $wa_message .= "Reminder booking kamu:\n";
            $wa_message .= "🎫 Kode: *{$booking['booking_code']}*\n";
            $wa_message .= "🎬 Film: {$booking['film_title']}\n";
            $wa_message .= "🕐 Jam: {$booking['time']} WIB\n";
            $wa_message .= "🏢 Studio: {$booking['studio']}\n";
            $wa_message .= "💺 Kursi: {$booking['seats']}\n";
            $wa_message .= "💰 Total: Rp " . number_format($booking['grand_total'],0,',','.') . "\n";
            
            $result = sendWhatsApp($booking['phone'], $wa_message);
            logWA($booking['phone'], $booking['booking_code'], $wa_message, $result['success'] ? 'success' : 'failed');
            
            if ($result['success']) {
                $db->prepare("UPDATE bookings SET wa_sent=1 WHERE booking_code=?")->execute([$booking['booking_code']]);
            }
            $msg = $result['success'] ? '✅ WhatsApp berhasil dikirim ulang.' : '❌ Gagal kirim WA: ' . ($result['error'] ?? '');
            $msg_type = $result['success'] ? 'success' : 'error';
        } else {
            $msg = '❌ Film tidak ditemukan.'; $msg_type = 'error';
        }
    } else {
        $msg = '❌ Booking tidak ditemukan.'; $msg_type = 'error';
    }
}

// ---- FILTER ----
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$film_id = (int)($_GET['film_id'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset  = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(b.booking_code LIKE ? OR b.nama LIKE ? OR b.phone LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status) { $where[] = 'b.status=?'; $params[] = $status; }
if ($film_id) { $where[] = 's.film_id=?'; $params[] = $film_id; }

$whereStr = implode(' AND ', $where);
$total_rows = $db->prepare("SELECT COUNT(*) FROM bookings b JOIN schedules s ON s.id=b.schedule_id WHERE $whereStr");
$total_rows->execute($params);
$total_count = $total_rows->fetchColumn();
$total_pages = max(1, ceil($total_count / $per_page));

$stmt = $db->prepare("
    SELECT b.*, f.title AS film_title, s.studio, s.time, s.show_date
    FROM bookings b
    JOIN schedules s ON s.id = b.schedule_id
    JOIN films f ON f.id = s.film_id
    WHERE $whereStr
    ORDER BY b.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$films_list = $db->query("SELECT id,title FROM films WHERE is_active=1 ORDER BY title")->fetchAll();

$total_revenue = $db->query("SELECT COALESCE(SUM(grand_total),0) FROM bookings WHERE status='confirmed'")->fetchColumn();

$base_path  = '../';
$page_title = 'Kelola Booking';
include '../includes/header.php';
?>
<div class="admin-layout">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <h2>🎟️ KELOLA BOOKING</h2>

        <?php if ($msg): ?><div class="msg <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Stats mini -->
        <div style="display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
            <div class="admin-card" style="flex:1; min-width:150px; margin-bottom:0; padding:1rem;">
                <div style="font-size:1.8rem; font-family:'Bebas Neue',sans-serif; color:var(--accent);"><?= $total_count ?></div>
                <div style="font-size:0.75rem; color:var(--muted);">Total Booking</div>
            </div>
            <div class="admin-card" style="flex:2; min-width:200px; margin-bottom:0; padding:1rem;">
                <div style="font-size:1.4rem; font-family:'Bebas Neue',sans-serif; color:var(--gold);"><?= formatRupiah($total_revenue) ?></div>
                <div style="font-size:0.75rem; color:var(--muted);">Total Revenue</div>
            </div>
        </div>

        <!-- Filter -->
        <div class="admin-card" style="padding:1rem;">
            <form method="GET" style="display:flex; gap:0.8rem; flex-wrap:wrap; align-items:flex-end;">
                <div class="form-group" style="flex:2; min-width:180px; margin:0;">
                    <label>Cari (nama / kode / no HP)</label>
                    <input type="text" name="search" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group" style="flex:1; min-width:140px; margin:0;">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="confirmed" <?= $status==='confirmed'?'selected':'' ?>>Confirmed</option>
                        <option value="pending"   <?= $status==='pending'  ?'selected':'' ?>>Pending</option>
                        <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1; min-width:160px; margin:0;">
                    <label>Film</label>
                    <select name="film_id">
                        <option value="">Semua Film</option>
                        <?php foreach ($films_list as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $film_id==$f['id']?'selected':'' ?>><?= htmlspecialchars($f['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="margin-bottom:0; height:38px;">🔍 Filter</button>
                <a href="bookings.php" class="btn-sm btn-edit" style="padding:0.6rem 1rem; border-radius:8px; text-decoration:none; height:38px; line-height:1.5;">Reset</a>
            </form>
        </div>

        <!-- Tabel -->
        <div class="admin-card">
            <h3>📋 DAFTAR BOOKING (<?= $total_count ?> data)</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>KODE</th><th>NAMA</th><th>FILM</th><th>TGL TAYANG</th><th>STUDIO</th>
                            <th>KURSI</th><th>QTY</th><th>TOTAL</th><th>STATUS</th><th>WA</th><th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td>
                                <code style="color:var(--gold); font-size:0.72rem; cursor:pointer;" onclick="showDetail('<?= htmlspecialchars($b['booking_code']) ?>')">
                                    <?= htmlspecialchars($b['booking_code']) ?>
                                </code>
                            </td>
                            <td>
                                <div style="font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($b['nama']) ?></div>
                                <div style="font-size:0.7rem; color:var(--muted);"><?= htmlspecialchars($b['phone']) ?></div>
                            </td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($b['film_title']) ?></td>
                            <td style="font-size:0.82rem;"><?= date('d/m/Y', strtotime($b['show_date'])) ?><br><span style="color:var(--muted);"><?= $b['time'] ?></span></td>
                            <td><span class="badge-studio"><?= htmlspecialchars($b['studio']) ?></span></td>
                            <td style="font-size:0.8rem;"><?= htmlspecialchars($b['seats']) ?></td>
                            <td style="text-align:center;"><?= $b['qty'] ?></td>
                            <td style="color:var(--gold); font-weight:600;"><?= formatRupiah($b['grand_total']) ?></td>
                            <td><span class="badge-status <?= $b['status'] ?>"><?= strtoupper($b['status']) ?></span></td>
                            <td style="text-align:center;">
                                <?= $b['wa_sent'] ? '<span style="color:#22c55e;" title="WA Terkirim">✓</span>' : '<span style="color:var(--muted);" title="Belum dikirim">–</span>' ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="?resend_wa=<?= urlencode($b['booking_code']) ?>&<?= http_build_query(['search'=>$search,'status'=>$status,'film_id'=>$film_id,'page'=>$page]) ?>"
                                   class="btn-sm btn-edit" title="Kirim ulang WA">📱 WA</a>
                                <?php if ($b['status'] !== 'cancelled'): ?>
                                &nbsp;
                                <a href="?cancel=<?= $b['id'] ?>&<?= http_build_query(['search'=>$search,'status'=>$status,'film_id'=>$film_id,'page'=>$page]) ?>"
                                   class="btn-sm btn-delete" onclick="return confirm('Batalkan booking ini?')">✕ Batal</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bookings)): ?>
                        <tr><td colspan="11" style="text-align:center; color:var(--muted); padding:2rem;">Tidak ada data booking.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex; gap:0.5rem; justify-content:center; margin-top:1rem; flex-wrap:wrap;">
                <?php for ($p=1; $p<=$total_pages; $p++): ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&film_id=<?= $film_id ?>"
                       style="padding:0.4rem 0.8rem; border-radius:6px; text-decoration:none;
                              background:<?= $p===$page ? 'var(--accent)' : 'var(--card2)' ?>;
                              color:<?= $p===$page ? '#fff' : 'var(--muted)' ?>;
                              font-size:0.82rem;"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal-overlay" id="detailModal">
    <div class="modal modal-medium">
        <button class="btn-close" onclick="document.getElementById('detailModal').classList.remove('open')">✕</button>
        <h3>📋 DETAIL BOOKING</h3>
        <div id="detailContent" style="font-size:0.88rem; line-height:1.8;"></div>
    </div>
</div>

<script>
const bookingsData = <?= json_encode($bookings) ?>;

function showDetail(code) {
    const b = bookingsData.find(x => x.booking_code === code);
    if (!b) return;
    document.getElementById('detailContent').innerHTML = `
        <div class="detail-row"><div class="detail-label">Kode Booking</div><div class="detail-value"><b style="color:var(--gold)">${escapeHtml(b.booking_code)}</b></div></div>
        <div class="detail-row"><div class="detail-label">Nama</div><div class="detail-value">${escapeHtml(b.nama)}</div></div>
        <div class="detail-row"><div class="detail-label">Phone</div><div class="detail-value">${escapeHtml(b.phone)}</div></div>
        <div class="detail-row"><div class="detail-label">Email</div><div class="detail-value">${escapeHtml(b.email)}</div></div>
        <div class="detail-row"><div class="detail-label">Film</div><div class="detail-value">${escapeHtml(b.film_title)}</div></div>
        <div class="detail-row"><div class="detail-label">Tanggal</div><div class="detail-value">${escapeHtml(b.show_date)}</div></div>
        <div class="detail-row"><div class="detail-label">Jam</div><div class="detail-value">${escapeHtml(b.time)}</div></div>
        <div class="detail-row"><div class="detail-label">Studio</div><div class="detail-value">${escapeHtml(b.studio)}</div></div>
        <div class="detail-row"><div class="detail-label">Kursi</div><div class="detail-value"><b>${escapeHtml(b.seats)}</b></div></div>
        <div class="detail-row"><div class="detail-label">Qty Tiket</div><div class="detail-value">${b.qty}</div></div>
        <div class="detail-row"><div class="detail-label">Tiket Total</div><div class="detail-value">Rp ${parseInt(b.ticket_total).toLocaleString('id-ID')}</div></div>
        <div class="detail-row"><div class="detail-label">Food Total</div><div class="detail-value">Rp ${parseInt(b.food_total).toLocaleString('id-ID')}</div></div>
        <div class="detail-row"><div class="detail-label">Grand Total</div><div class="detail-value"><b style="color:var(--gold)">Rp ${parseInt(b.grand_total).toLocaleString('id-ID')}</b></div></div>
        <div class="detail-row"><div class="detail-label">Status</div><div class="detail-value"><span class="badge-status ${b.status}">${b.status.toUpperCase()}</span></div></div>
        <div class="detail-row"><div class="detail-label">WA Terkirim</div><div class="detail-value">${b.wa_sent=='1' ? '✅ Ya' : '❌ Belum'}</div></div>
        <div class="detail-row"><div class="detail-label">Tanggal Booking</div><div class="detail-value">${escapeHtml(b.created_at)}</div></div>
    `;
    document.getElementById('detailModal').classList.add('open');
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

document.getElementById('detailModal').addEventListener('click', e => { if (e.target===e.currentTarget) e.currentTarget.classList.remove('open'); });
</script>
</body>
</html>