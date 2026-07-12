<?php
// admin/foods.php — Kelola Makanan & Minuman
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();
$msg = ''; $msg_type = '';

// ---- HAPUS ----
if (isset($_GET['delete'])) {
    $db->prepare("UPDATE foods SET is_active=0 WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = '✅ Menu berhasil dihapus.'; $msg_type = 'success';
}

// ---- SIMPAN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_food'])) {
    $id       = (int)($_POST['food_id'] ?? 0);
    $name     = trim($_POST['name']);
    $price    = (int)$_POST['price'];
    $icon     = trim($_POST['icon']) ?: 'fa-utensils';
    $category = $_POST['category'];

    if (!$name || $price < 0) {
        $msg = '❌ Nama dan harga wajib diisi.'; $msg_type = 'error';
    } else {
        if ($id) {
            $db->prepare("UPDATE foods SET name=?,price=?,icon=?,category=? WHERE id=?")
               ->execute([$name,$price,$icon,$category,$id]);
            $msg = '✅ Menu berhasil diperbarui.';
        } else {
            $db->prepare("INSERT INTO foods (name,price,icon,category) VALUES (?,?,?,?)")
               ->execute([$name,$price,$icon,$category]);
            $msg = '✅ Menu berhasil ditambahkan.';
        }
        $msg_type = 'success';
    }
}

$foods = $db->query("SELECT * FROM foods WHERE is_active=1 ORDER BY category, name")->fetchAll();

$edit_food = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM foods WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_food = $stmt->fetch();
}

$base_path  = '../';
$page_title = 'Kelola Makanan';
include '../includes/header.php';
?>
<div class="admin-layout">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <h2>🍿 KELOLA MAKANAN & MINUMAN</h2>

        <?php if ($msg): ?><div class="msg <?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

        <div class="admin-card">
            <h3><?= $edit_food ? '✏️ EDIT MENU' : '➕ TAMBAH MENU' ?></h3>
            <form method="POST">
                <input type="hidden" name="food_id" value="<?= $edit_food['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Menu *</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($edit_food['name'] ?? '') ?>" placeholder="Contoh: Popcorn Caramel">
                    </div>
                    <div class="form-group">
                        <label>Harga (Rp) *</label>
                        <input type="number" name="price" min="0" step="500" required value="<?= $edit_food['price'] ?? 10000 ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category">
                            <option value="snack" <?= ($edit_food['category'] ?? '') === 'snack' ? 'selected' : '' ?>>🍿 Snack</option>
                            <option value="drink" <?= ($edit_food['category'] ?? '') === 'drink' ? 'selected' : '' ?>>🥤 Minuman</option>
                            <option value="meal"  <?= ($edit_food['category'] ?? '') === 'meal'  ? 'selected' : '' ?>>🍔 Makanan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Icon FontAwesome (tanpa "fa-")</label>
                        <input type="text" name="icon" placeholder="popcorn / wine-bottle / utensils" value="<?= htmlspecialchars(str_replace('fa-', '', $edit_food['icon'] ?? '')) ?>">
                        <small style="font-size:0.65rem; color:var(--muted);">Lihat ikon di fontawesome.com — contoh: popcorn, mug-hot, cheese</small>
                    </div>
                </div>
                <div style="display:flex; gap:1rem;">
                    <button type="submit" name="save_food" class="btn-primary"><i class="fas fa-save"></i> Simpan Menu</button>
                    <?php if ($edit_food): ?>
                        <a href="foods.php" class="btn-sm btn-delete" style="padding:0.6rem 1.2rem; text-decoration:none; border-radius:8px;">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="admin-card">
            <h3>📋 DAFTAR MENU</h3>

            <?php
            $grouped = [];
            foreach ($foods as $fd) $grouped[$fd['category']][] = $fd;
            $labels = ['snack' => '🍿 Snack', 'drink' => '🥤 Minuman', 'meal' => '🍔 Makanan'];
            ?>

            <?php foreach ($grouped as $cat => $items): ?>
            <div style="margin-bottom:1.5rem;">
                <div style="font-size:0.8rem; font-weight:700; color:var(--gold); letter-spacing:1px; margin-bottom:0.5rem; text-transform:uppercase;">
                    <?= $labels[$cat] ?? $cat ?>
                </div>
                <table>
                    <thead><tr><th>ICON</th><th>NAMA</th><th>HARGA</th><th>KATEGORI</th><th>AKSI</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $fd): ?>
                        <tr>
                            <td><i class="fas <?= htmlspecialchars($fd['icon']) ?>" style="font-size:1.3rem; color:var(--gold);"></i></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($fd['name']) ?></td>
                            <td style="color:var(--gold);"><?= formatRupiah($fd['price']) ?></td>
                            <td><?= $labels[$fd['category']] ?? $fd['category'] ?></td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?= $fd['id'] ?>" class="btn-sm btn-edit">✏️ Edit</a>
                                &nbsp;
                                <a href="?delete=<?= $fd['id'] ?>" class="btn-sm btn-delete"
                                   onclick="return confirm('Hapus menu <?= addslashes($fd['name']) ?>?')">🗑 Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
