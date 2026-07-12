<?php
// admin/users.php — Kelola User
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db  = getDB();
$msg = ''; $msg_type = '';

// ---- HAPUS ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Jangan hapus diri sendiri
    if ($id === (int)(currentUser()['id'] ?? 0)) {
        $msg = '❌ Tidak bisa menghapus akun sendiri.'; $msg_type = 'error';
    } else {
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $msg = '✅ User berhasil dihapus.'; $msg_type = 'success';
    }
}

// ---- RESET PASSWORD ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id       = (int)$_POST['user_id'];
    $new_pass = trim($_POST['new_password']);
    if (strlen($new_pass) < 6) {
        $msg = '❌ Password minimal 6 karakter.'; $msg_type = 'error';
    } else {
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new_pass, PASSWORD_DEFAULT), $id]);
        $msg = '✅ Password berhasil direset.'; $msg_type = 'success';
    }
}

// ---- SIMPAN (ADD/EDIT) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $id       = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username']);
    $name     = trim($_POST['name']);
    $phone    = trim($_POST['phone']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$name) {
        $msg = '❌ Username dan nama wajib diisi.'; $msg_type = 'error';
    } else {
        // Cek duplikat username
        $chk = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
        $chk->execute([$username, $id]);
        if ($chk->fetch()) {
            $msg = '❌ Username sudah digunakan.'; $msg_type = 'error';
        } else {
            if ($id) {
                // Edit — password hanya diupdate jika diisi
                if ($password) {
                    $db->prepare("UPDATE users SET username=?,name=?,phone=?,email=?,role=?,password=? WHERE id=?")
                       ->execute([$username,$name,$phone,$email,$role, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $db->prepare("UPDATE users SET username=?,name=?,phone=?,email=?,role=? WHERE id=?")
                       ->execute([$username,$name,$phone,$email,$role,$id]);
                }
                $msg = '✅ User berhasil diperbarui.';
            } else {
                if (!$password) { $msg = '❌ Password wajib diisi untuk user baru.'; $msg_type = 'error'; goto skip_save; }
                $db->prepare("INSERT INTO users (username,password,name,role,phone,email) VALUES (?,?,?,?,?,?)")
                   ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $role, $phone, $email]);
                $msg = '✅ User berhasil ditambahkan.';
            }
            $msg_type = 'success';
        }
    }
    skip_save:
}

$users = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM bookings b WHERE b.user_id=u.id AND b.status='confirmed') AS total_bookings,
           (SELECT COALESCE(SUM(grand_total),0) FROM bookings b WHERE b.user_id=u.id AND b.status='confirmed') AS total_spend
    FROM users u
    ORDER BY u.role ASC, u.created_at DESC
")->fetchAll();

$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

$base_path  = '../';
$page_title = 'Kelola User';
include '../includes/header.php';
?>
<div class="admin-layout">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <h2>👤 KELOLA USER</h2>

        <?php if ($msg): ?><div class="msg <?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

        <!-- FORM TAMBAH/EDIT -->
        <div class="admin-card">
            <h3><?= $edit_user ? '✏️ EDIT USER' : '➕ TAMBAH USER' ?></h3>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" required value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>" placeholder="username unik">
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($edit_user['name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>No. HP / WhatsApp</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>" placeholder="628xxx">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="user"  <?= ($edit_user['role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>👤 User</option>
                            <option value="admin" <?= ($edit_user['role'] ?? '')      === 'admin' ? 'selected' : '' ?>>⚙️ Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password <?= $edit_user ? '(kosongkan jika tidak diubah)' : '*' ?></label>
                        <input type="password" name="password" <?= !$edit_user ? 'required' : '' ?> placeholder="Min. 6 karakter">
                    </div>
                </div>
                <div style="display:flex; gap:1rem;">
                    <button type="submit" name="save_user" class="btn-primary"><i class="fas fa-save"></i> Simpan User</button>
                    <?php if ($edit_user): ?>
                        <a href="users.php" class="btn-sm btn-delete" style="padding:0.6rem 1.2rem; text-decoration:none; border-radius:8px;">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- TABEL USER -->
        <div class="admin-card">
            <h3>📋 DAFTAR USER (<?= count($users) ?>)</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>USERNAME</th><th>NAMA</th><th>ROLE</th><th>PHONE</th><th>EMAIL</th><th>BOOKING</th><th>TOTAL SPEND</th><th>BERGABUNG</th><th>AKSI</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <code style="color:var(--gold);"><?= htmlspecialchars($u['username']) ?></code>
                                <?php if ($u['id'] == currentUser()['id']): ?>
                                    <span style="font-size:0.65rem; color:var(--accent);"> (Kamu)</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></td>
                            <td>
                                <span style="background:<?= $u['role']==='admin' ? 'rgba(230,57,70,0.15)' : 'rgba(37,211,102,0.1)' ?>;
                                             color:<?= $u['role']==='admin' ? 'var(--accent)' : '#22c55e' ?>;
                                             padding:2px 10px; border-radius:20px; font-size:0.72rem; font-weight:700;">
                                    <?= strtoupper($u['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                            <td style="font-size:0.8rem;"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                            <td style="text-align:center;"><?= $u['total_bookings'] ?></td>
                            <td style="color:var(--gold);"><?= formatRupiah($u['total_spend']) ?></td>
                            <td style="font-size:0.75rem; color:var(--muted);"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?= $u['id'] ?>" class="btn-sm btn-edit">✏️ Edit</a>
                                <?php if ($u['id'] != currentUser()['id']): ?>
                                &nbsp;
                                <a href="?delete=<?= $u['id'] ?>" class="btn-sm btn-delete"
                                   onclick="return confirm('Hapus user <?= addslashes($u['username']) ?>?')">🗑</a>
                                <?php endif; ?>
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
