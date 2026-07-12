<?php
// admin/index.php — Admin Dashboard
session_start();
require_once '../config/database.php';
require_once '../config/whatsapp.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = getDB();

// Stats
$total_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetchColumn();
$total_revenue  = $db->query("SELECT COALESCE(SUM(grand_total),0) FROM bookings WHERE status='confirmed'")->fetchColumn();
$total_films    = $db->query("SELECT COUNT(*) FROM films WHERE is_active=1")->fetchColumn();
$total_users    = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();

// Bookings terbaru
$recent = $db->query("
    SELECT b.*, f.title AS film_title, s.studio, s.time
    FROM bookings b
    JOIN schedules s ON s.id = b.schedule_id
    JOIN films f ON f.id = s.film_id
    ORDER BY b.created_at DESC LIMIT 8
")->fetchAll();

// WA stats
$wa_success = $db->query("SELECT COUNT(*) FROM wa_logs WHERE status='success'")->fetchColumn();
$wa_failed  = $db->query("SELECT COUNT(*) FROM wa_logs WHERE status='failed'")->fetchColumn();

$base_path  = '../';
$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>

<div class="admin-layout">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <h2>⚡ ADMIN DASHBOARD</h2>

        <div class="stats" style="margin-bottom:2rem;">
            <div class="stat">
                <div class="stat-icon">🎟️</div>
                <div><div class="stat-num"><?= number_format($total_bookings) ?></div><div class="stat-label">TOTAL BOOKING</div></div>
            </div>
            <div class="stat">
                <div class="stat-icon">💰</div>
                <div><div class="stat-num" style="font-size:1.3rem;"><?= formatRupiah($total_revenue) ?></div><div class="stat-label">TOTAL REVENUE</div></div>
            </div>
            <div class="stat">
                <div class="stat-icon">🎬</div>
                <div><div class="stat-num"><?= $total_films ?></div><div class="stat-label">FILM AKTIF</div></div>
            </div>
            <div class="stat">
                <div class="stat-icon">👤</div>
                <div><div class="stat-num"><?= $total_users ?></div><div class="stat-label">PENGGUNA</div></div>
            </div>
        </div>

        <!-- WA Stats -->
        <div class="admin-card" style="border-color:var(--whatsapp);">
            <h3 style="color:var(--whatsapp);"><i class="fab fa-whatsapp"></i> STATISTIK WHATSAPP</h3>
            <div style="display:flex; gap:2rem; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="font-size:2rem; font-family:'Bebas Neue',sans-serif; color:#22c55e;"><?= $wa_success ?></div>
                    <div style="font-size:0.8rem; color:var(--muted);">Pesan Terkirim</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:2rem; font-family:'Bebas Neue',sans-serif; color:var(--accent);"><?= $wa_failed ?></div>
                    <div style="font-size:0.8rem; color:var(--muted);">Gagal Terkirim</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:2rem; font-family:'Bebas Neue',sans-serif; color:var(--gold);">
                        <?= ($wa_success + $wa_failed) > 0 ? round($wa_success / ($wa_success + $wa_failed) * 100) : 0 ?>%
                    </div>
                    <div style="font-size:0.8rem; color:var(--muted);">Success Rate</div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="admin-card">
            <h3>📋 BOOKING TERBARU</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>KODE</th><th>NAMA</th><th>FILM</th><th>STUDIO</th><th>JAM</th><th>QTY</th><th>TOTAL</th><th>STATUS</th><th>WA</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><code style="color:var(--gold); font-size:0.75rem;"><?= $r['booking_code'] ?></code></td>
                            <td><?= htmlspecialchars($r['nama']) ?></td>
                            <td><?= htmlspecialchars($r['film_title']) ?></td>
                            <td><span class="badge-studio"><?= $r['studio'] ?></span></td>
                            <td><?= $r['time'] ?></td>
                            <td><?= $r['qty'] ?></td>
                            <td style="color:var(--gold);"><?= formatRupiah($r['grand_total']) ?></td>
                            <td><span class="badge-status <?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span></td>
                            <td><?= $r['wa_sent'] ? '<span style="color:#22c55e">✓</span>' : '<span style="color:var(--muted)">–</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="bookings.php" style="color:var(--accent); font-size:0.85rem;">Lihat semua booking →</a>
        </div>
    </div>
</div>
</body>
</html>
