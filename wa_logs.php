<?php
// admin/wa_logs.php — Log WhatsApp
session_start();
require_once '../config/database.php';
require_once '../config/whatsapp.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db  = getDB();
$msg = ''; $msg_type = '';

// ---- BERSIHKAN LOG ----
if (isset($_GET['clear']) && $_GET['clear'] === 'all') {
    $db->exec("DELETE FROM wa_logs");
    $msg = '✅ Semua log WhatsApp berhasil dihapus.'; $msg_type = 'success';
}

// ---- TEST KIRIM WA ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_wa'])) {
    $phone   = trim($_POST['test_phone']);
    $message = trim($_POST['test_message']);
    if ($phone && $message) {
        $result = sendWhatsApp($phone, $message);
        logWA($phone, 'TEST', $message, $result['success'] ? 'success' : 'failed');
        $msg = $result['success']
            ? "✅ Pesan berhasil dikirim ke $phone"
            : "❌ Gagal kirim: " . ($result['error'] ?? 'Unknown error');
        $msg_type = $result['success'] ? 'success' : 'error';
    }
}

// ---- FILTER ----
$status_filter = $_GET['status'] ?? '';
$allowed_status = ['success', 'failed'];
$status_filter = in_array($status_filter, $allowed_status) ? $status_filter : '';

$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

// Hitung total dengan prepared statement (tanpa LIMIT)
if ($status_filter) {
    $total = $db->prepare("SELECT COUNT(*) FROM wa_logs WHERE status = ?");
    $total->execute([$status_filter]);
    $total_count = $total->fetchColumn();
} else {
    $total_count = $db->query("SELECT COUNT(*) FROM wa_logs")->fetchColumn();
}

$total_pages = max(1, ceil($total_count / $per_page));

// ========== PERBAIKAN UTAMA: LIMIT dan OFFSET dimasukkan langsung ke SQL ==========
$limit = (int)$per_page;
$offset_val = (int)$offset;

if ($status_filter) {
    // Gunakan sprintf atau langsung concatenate untuk LIMIT/OFFSET
    $sql = "SELECT * FROM wa_logs WHERE status = :status ORDER BY sent_at DESC LIMIT $limit OFFSET $offset_val";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':status', $status_filter);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} else {
    $sql = "SELECT * FROM wa_logs ORDER BY sent_at DESC LIMIT $limit OFFSET $offset_val";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll();
}

// Stats
$total_success = $db->query("SELECT COUNT(*) FROM wa_logs WHERE status='success'")->fetchColumn();
$total_failed  = $db->query("SELECT COUNT(*) FROM wa_logs WHERE status='failed'")->fetchColumn();
$total_all     = $total_success + $total_failed;
$success_rate  = $total_all > 0 ? round($total_success / $total_all * 100) : 0;

$base_path  = '../';
$page_title = 'Log WhatsApp';
include '../includes/header.php';
?>
<div class="admin-layout">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <h2 style="color:var(--whatsapp);"><i class="fab fa-whatsapp"></i> LOG WHATSAPP</h2>

        <?php if ($msg): ?><div class="msg <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <!-- Stats -->
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem;">
            <div class="admin-card" style="margin-bottom:0; text-align:center;">
                <div style="font-size:2rem; font-family:'Bebas Neue',sans-serif; color:#22c55e;"><?= $total_success ?></div>
                <div style="font-size:0.78rem; color:var(--muted);">✅ Terkirim</div>
            </div>
            <div class="admin-card" style="margin-bottom:0; text-align:center;">
                <div style="font-size:2rem; font-family:'Bebas Neue',sans-serif; color:var(--accent);"><?= $total_failed ?></div>
                <div style="font-size:0.78rem; color:var(--muted);">❌ Gagal</div>
            </div>
            <div class="admin-card" style="margin-bottom:0; text-align:center;">
                <div style="font-size:2rem; font-family:'Bebas Neue',sans-serif; color:var(--gold);"><?= $success_rate ?>%</div>
                <div style="font-size:0.78rem; color:var(--muted);">📊 Success Rate</div>
            </div>
        </div>

        <!-- Test WA -->
        <div class="admin-card" style="border-color:var(--whatsapp);">
            <h3 style="color:var(--whatsapp);">📤 TEST KIRIM WHATSAPP</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nomor Tujuan</label>
                        <input type="tel" name="test_phone" placeholder="628123456789" required>
                    </div>
                    <div class="form-group">
                        <label>Pesan</label>
                        <input type="text" name="test_message" value="🧪 Test dari CineHoror Admin Panel — sistem WhatsApp berjalan!" required>
                    </div>
                </div>
                <button type="submit" name="test_wa" class="btn-wa" style="background:var(--whatsapp); color:white; border:none; padding:0.6rem 1.4rem; border-radius:8px; cursor:pointer; font-weight:600;">
                    <i class="fab fa-whatsapp"></i> Kirim Test
                </button>
            </form>
        </div>

        <!-- Log Table -->
        <div class="admin-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                <h3 style="margin-bottom:0;">📋 RIWAYAT LOG (<?= $total_count ?> entri)</h3>
                <div style="display:flex; gap:0.8rem; flex-wrap:wrap; align-items:center;">
                    <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
                        <select name="status" onchange="this.form.submit()" style="width:auto; padding:0.4rem 0.8rem;">
                            <option value="">Semua Status</option>
                            <option value="success" <?= $status_filter === 'success' ? 'selected' : '' ?>>✅ Success</option>
                            <option value="failed"  <?= $status_filter === 'failed' ? 'selected' : '' ?>>❌ Failed</option>
                        </select>
                    </form>
                    <a href="?clear=all" class="btn-sm btn-delete"
                       onclick="return confirm('Hapus semua log WhatsApp? Tindakan ini tidak bisa dibatalkan.')"
                       style="padding:0.5rem 1rem; border-radius:8px; text-decoration:none;">🗑 Bersihkan Log</a>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>WAKTU</th><th>NOMOR</th><th>KODE BOOKING</th><th>PESAN</th><th>STATUS</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-size:0.78rem; white-space:nowrap; color:var(--muted);">
                                <?= date('d/m/Y H:i:s', strtotime($log['sent_at'])) ?>
                            </td>
                            <td>
                                <span style="font-family:monospace; color:var(--text);"><?= htmlspecialchars($log['phone']) ?></span>
                            </td>
                            <td>
                                <?php if ($log['booking_code'] && $log['booking_code'] !== 'TEST'): ?>
                                    <code style="color:var(--gold); font-size:0.75rem;"><?= htmlspecialchars($log['booking_code']) ?></code>
                                <?php else: ?>
                                    <span style="color:var(--muted); font-size:0.75rem; font-style:italic;"><?= htmlspecialchars($log['booking_code']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:300px; font-size:0.78rem; color:var(--muted);">
                                <?= htmlspecialchars(mb_substr($log['message'], 0, 80)) ?><?= mb_strlen($log['message']) > 80 ? '…' : '' ?>
                            </td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    <span style="color:#22c55e; font-weight:600;">✅ Success</span>
                                <?php else: ?>
                                    <span style="color:var(--accent); font-weight:600;">❌ Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:2rem;">Belum ada log WhatsApp.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex; gap:0.5rem; justify-content:center; margin-top:1rem; flex-wrap:wrap;">
                <?php for ($p=1; $p<=$total_pages; $p++): ?>
                    <a href="?page=<?= $p ?>&status=<?= urlencode($status_filter) ?>"
                       style="padding:0.4rem 0.8rem; border-radius:6px; text-decoration:none; font-size:0.82rem;
                              background:<?= $p===$page ? 'var(--accent)' : 'var(--card2)' ?>;
                              color:<?= $p===$page ? '#fff' : 'var(--muted)' ?>;"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</body>
</html>