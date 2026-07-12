<?php
// admin/films.php — Kelola Film
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$db = getDB();
$msg = ''; $msg_type = '';

// ---- HAPUS ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("UPDATE films SET is_active=0 WHERE id=?")->execute([$id]);
    $msg = '✅ Film berhasil dihapus.'; $msg_type = 'success';
}

// ---- SIMPAN (ADD/EDIT) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_film'])) {
    $id       = (int)($_POST['film_id'] ?? 0);
    $title    = trim($_POST['title']);
    $duration = trim($_POST['duration']);
    $rating   = (float)$_POST['rating'];
    $genre    = trim($_POST['genre']);
    $director = trim($_POST['director']);
    $year     = trim($_POST['year']);
    $synopsis = trim($_POST['synopsis']);
    $image    = trim($_POST['image']);

    if (!$title) {
        $msg = '❌ Judul film wajib diisi.'; $msg_type = 'error';
    } else {
        if ($id) {
            $db->prepare("UPDATE films SET title=?,duration=?,rating=?,genre=?,director=?,year=?,synopsis=?,image=? WHERE id=?")
               ->execute([$title,$duration,$rating,$genre,$director,$year,$synopsis,$image,$id]);
            $msg = '✅ Film berhasil diperbarui.';
        } else {
            $db->prepare("INSERT INTO films (title,duration,rating,genre,director,year,synopsis,image) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$title,$duration,$rating,$genre,$director,$year,$synopsis,$image]);
            $msg = '✅ Film berhasil ditambahkan.';
        }
        $msg_type = 'success';
    }
}

// ---- SIMPAN CAST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cast'])) {
    $film_id = (int)$_POST['film_id'];
    // Hapus cast lama
    $db->prepare("DELETE FROM film_cast WHERE film_id=?")->execute([$film_id]);
    // Simpan yang baru
    if (!empty($_POST['cast_name'])) {
        $stmt = $db->prepare("INSERT INTO film_cast (film_id,name,role_name,photo,sort_order) VALUES (?,?,?,?,?)");
        foreach ($_POST['cast_name'] as $i => $name) {
            if (trim($name)) {
                $stmt->execute([
                    $film_id, trim($name),
                    trim($_POST['cast_role'][$i] ?? ''),
                    trim($_POST['cast_photo'][$i] ?? ''),
                    $i
                ]);
            }
        }
    }
    $msg = '✅ Cast berhasil diperbarui.'; $msg_type = 'success';
}

$films     = $db->query("SELECT * FROM films WHERE is_active=1 ORDER BY id DESC")->fetchAll();
$edit_film = null;
$edit_cast = [];
if (isset($_GET['edit'])) {
    $edit_film = $db->prepare("SELECT * FROM films WHERE id=?");
    $edit_film->execute([(int)$_GET['edit']]);
    $edit_film = $edit_film->fetch();
    $edit_cast = getFilmCast($edit_film['id']);
}

$base_path  = '../';
$page_title = 'Kelola Film';
include '../includes/header.php';
?>
<div class="admin-layout">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <h2>🎬 KELOLA FILM</h2>

        <?php if ($msg): ?><div class="msg <?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

        <!-- FORM TAMBAH/EDIT FILM -->
        <div class="admin-card">
            <h3><?= $edit_film ? '✏️ EDIT FILM' : '➕ TAMBAH FILM' ?></h3>
            <form method="POST">
                <input type="hidden" name="film_id" value="<?= $edit_film['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Judul Film *</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($edit_film['title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Sutradara</label>
                        <input type="text" name="director" value="<?= htmlspecialchars($edit_film['director'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Durasi</label>
                        <input type="text" name="duration" placeholder="120 menit" value="<?= htmlspecialchars($edit_film['duration'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tahun</label>
                        <input type="text" name="year" placeholder="2024" value="<?= htmlspecialchars($edit_film['year'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Genre</label>
                        <select name="genre">
                            <?php foreach (['Horor','Drama','Action','Komedi','Thriller','Sci-Fi'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($edit_film['genre'] ?? '') == $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rating (0-5)</label>
                        <input type="number" name="rating" min="0" max="5" step="0.1" value="<?= $edit_film['rating'] ?? '4.5' ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Path/URL Poster (contoh: gambar/film.jpg)</label>
                    <input type="text" name="image" value="<?= htmlspecialchars($edit_film['image'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Sinopsis</label>
                    <textarea name="synopsis" rows="4"><?= htmlspecialchars($edit_film['synopsis'] ?? '') ?></textarea>
                </div>
                <div style="display:flex; gap:1rem;">
                    <button type="submit" name="save_film" class="btn-primary">
                        <i class="fas fa-save"></i> Simpan Film
                    </button>
                    <?php if ($edit_film): ?>
                        <a href="films.php" class="btn-sm btn-delete" style="padding:0.6rem 1.2rem; text-decoration:none; border-radius:8px;">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- FORM EDIT CAST (hanya jika mode edit) -->
        <?php if ($edit_film): ?>
        <div class="admin-card">
            <h3>🎭 KELOLA CAST — <?= htmlspecialchars($edit_film['title']) ?></h3>
            <form method="POST" id="castForm">
                <input type="hidden" name="film_id" value="<?= $edit_film['id'] ?>">
                <div id="castList">
                    <?php foreach ($edit_cast as $i => $c): ?>
                    <div class="cast-row" style="display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:0.5rem;margin-bottom:0.5rem;align-items:center;">
                        <input type="text" name="cast_name[]" placeholder="Nama Aktor" value="<?= htmlspecialchars($c['name']) ?>" required>
                        <input type="text" name="cast_role[]" placeholder="Peran" value="<?= htmlspecialchars($c['role_name']) ?>">
                        <input type="text" name="cast_photo[]" placeholder="URL Foto" value="<?= htmlspecialchars($c['photo']) ?>">
                        <button type="button" onclick="this.parentElement.remove()" class="btn-sm btn-delete">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($edit_cast)): ?>
                    <div class="cast-row" style="display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:0.5rem;margin-bottom:0.5rem;align-items:center;">
                        <input type="text" name="cast_name[]" placeholder="Nama Aktor">
                        <input type="text" name="cast_role[]" placeholder="Peran">
                        <input type="text" name="cast_photo[]" placeholder="URL Foto">
                        <button type="button" onclick="this.parentElement.remove()" class="btn-sm btn-delete">✕</button>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:1rem;margin-top:0.8rem;">
                    <button type="button" onclick="addCastRow()" class="btn-add">+ Tambah Aktor</button>
                    <button type="submit" name="save_cast" class="btn-primary"><i class="fas fa-save"></i> Simpan Cast</button>
                </div>
            </form>
        </div>
        <script>
        function addCastRow() {
            const row = document.createElement('div');
            row.className = 'cast-row';
            row.style = 'display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:0.5rem;margin-bottom:0.5rem;align-items:center;';
            row.innerHTML = `
                <input type="text" name="cast_name[]" placeholder="Nama Aktor">
                <input type="text" name="cast_role[]" placeholder="Peran">
                <input type="text" name="cast_photo[]" placeholder="URL Foto">
                <button type="button" onclick="this.parentElement.remove()" class="btn-sm btn-delete">✕</button>
            `;
            document.getElementById('castList').appendChild(row);
        }
        </script>
        <?php endif; ?>

        <!-- TABEL FILM -->
        <div class="admin-card">
            <h3>📋 DAFTAR FILM</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>POSTER</th><th>JUDUL</th><th>SUTRADARA</th><th>GENRE</th><th>RATING</th><th>TAHUN</th><th>AKSI</th></tr></thead>
                    <tbody>
                        <?php foreach ($films as $f): ?>
                        <tr>
                            <td><img src="../<?= htmlspecialchars($f['image']) ?>" alt="" style="width:50px;height:70px;object-fit:cover;border-radius:6px;" onerror="this.style.display='none'"></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($f['title']) ?></td>
                            <td><?= htmlspecialchars($f['director']) ?></td>
                            <td><?= htmlspecialchars($f['genre']) ?></td>
                            <td><span style="color:var(--gold);"><i class="fas fa-star"></i> <?= $f['rating'] ?></span></td>
                            <td><?= $f['year'] ?></td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?= $f['id'] ?>" class="btn-sm btn-edit">✏️ Edit</a>
                                &nbsp;
                                <a href="?delete=<?= $f['id'] ?>" class="btn-sm btn-delete"
                                   onclick="return confirm('Hapus film <?= addslashes($f['title']) ?>?')">🗑 Hapus</a>
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
