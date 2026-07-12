<?php
// admin/schedules.php — Kelola Jadwal
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();
$msg = ''; $msg_type = '';

// ---- HAPUS ----
if (isset($_GET['delete'])) {
    $db->prepare("UPDATE schedules SET is_active=0 WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = '✅ Jadwal berhasil dihapus.'; $msg_type = 'success';
}

// ---- SIMPAN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $id        = (int)($_POST['schedule_id'] ?? 0);
    $film_id   = (int)$_POST['film_id'];
    $show_date = $_POST['show_date'];
    $time      = $_POST['time'];
    $studio    = trim($_POST['studio']);
    $price     = (int)$_POST['price'];
    $quota     = (int)$_POST['quota'];

    if (!$film_id || !$show_date || !$time || !$studio || $price < 0 || $quota < 1) {
        $msg = '❌ Lengkapi semua field jadwal.'; $msg_type = 'error';
    } else {
        if ($id) {
            $db->prepare("UPDATE schedules SET film_id=?,show_date=?,time=?,studio=?,price=?,quota=? WHERE id=?")
               ->execute([$film_id,$show_date,$time,$studio,$price,$quota,$id]);
            $msg = '✅ Jadwal berhasil diperbarui.';
        } else {
            $db->prepare("INSERT INTO schedules (film_id,show_date,time,studio,price,quota) VALUES (?,?,?,?,?,?)")
               ->execute([$film_id,$show_date,$time,$studio,$price,$quota]);
            $msg = '✅ Jadwal berhasil ditambahkan.';
        }
        $msg_type = 'success';
    }
}

$films     = $db->query("SELECT id,title FROM films WHERE is_active=1 ORDER BY title")->fetchAll();
$schedules = $db->query("
    SELECT s.*, f.title AS film_title
    FROM schedules s JOIN films f ON f.id = s.film_id
    WHERE s.is_active = 1
    ORDER BY s.show_date DESC, s.time ASC
")->fetchAll();

$edit_sched = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM schedules WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_sched = $stmt->fetch();
}

$base_path  = '../';
$page_title = 'Kelola Jadwal';
include '../includes/header.php';
?>
<div class="admin-layout">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <h2>📅 KELOLA JADWAL</h2>

        <?php if ($msg): ?><div class="msg <?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

        <div class="admin-card">
            <h3><?= $edit_sched ? '✏️ EDIT JADWAL' : '➕ TAMBAH JADWAL' ?></h3>
            <form method="POST">
                <input type="hidden" name="schedule_id" value="<?= $edit_sched['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Film *</label>
                        <select name="film_id" required>
                            <option value="">-- Pilih Film --</option>
                            <?php foreach ($films as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= ($edit_sched['film_id'] ?? '') == $f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Tayang *</label>
                        <input type="date" name="show_date" required value="<?= $edit_sched['show_date'] ?? date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jam Tayang *</label>
                        <input type="time" name="time" required value="<?= $edit_sched['time'] ?? '10:00' ?>">
                    </div>
                    <div class="form-group">
                        <label>Studio *</label>
                        <select name="studio" required>
                            <?php foreach (['Studio A','Studio B','Studio C','Studio VIP','Studio IMAX'] as $st): ?>
                                <option value="<?= $st ?>" <?= ($edit_sched['studio'] ?? '') == $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Harga Tiket (Rp) *</label>
                        <input type="number" name="price" min="0" step="1000" required value="<?= $edit_sched['price'] ?? 50000 ?>">
                    </div>
                    <div class="form-group">
                        <label>Kuota Kursi *</label>
                        <input type="number" name="quota" min="1" max="200" required value="<?= $edit_sched['quota'] ?? 50 ?>">
                    </div>
                </div>
                <div style="display:flex;gap:1rem;">
                    <button type="submit" name="save_schedule" class="btn-primary"><i class="fas fa-save"></i> Simpan Jadwal</button>
                    <?php if ($edit_sched): ?><a href="schedules.php" class="btn-sm btn-delete" style="padding:0.6rem 1.2rem;text-decoration:none;border-radius:8px;">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-card">
            <h3>📋 DAFTAR JADWAL</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>FILM</th><th>TANGGAL</th><th>JAM</th><th>STUDIO</th><th>HARGA</th><th>KUOTA</th><th>SISA</th><th>AKSI</th></tr></thead>
                    <tbody>
                        <?php foreach ($schedules as $s):
                            $sisa = getRemainingQuota($s['id']);
                            $pct  = $s['quota'] > 0 ? round($sisa/$s['quota']*100) : 0;
                            $barC = $pct>60 ? 'high' : ($pct>30 ? 'mid' : 'low');
                        ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($s['film_title']) ?></td>
                            <td><?= date('d/m/Y', strtotime($s['show_date'])) ?></td>
                            <td><b><?= $s['time'] ?></b></td>
                            <td><span class="badge-studio"><?= $s['studio'] ?></span></td>
                            <td style="color:var(--gold);"><?= formatRupiah($s['price']) ?></td>
                            <td><?= $s['quota'] ?></td>
                            <td>
                                <div class="sisa-bar">
                                    <div class="bar-bg"><div class="bar-fill <?= $barC ?>" style="width:<?= $pct ?>%"></div></div>
                                    <span style="font-size:0.75rem;"><?= $sisa ?></span>
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?= $s['id'] ?>" class="btn-sm btn-edit">✏️ Edit</a>
                                &nbsp;
                                <a href="?delete=<?= $s['id'] ?>" class="btn-sm btn-delete"
                                   onclick="return confirm('Hapus jadwal ini?')">🗑 Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
