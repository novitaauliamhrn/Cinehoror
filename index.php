<?php
// ============================================================
// INDEX.PHP — Halaman Utama CineHoror
// ============================================================
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once 'config/database.php';
require_once 'config/whatsapp.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// ---- LOGOUT ----
if (isset($_GET['logout'])) {
    authLogout();
}

// ---- LOGIN ----
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (authLogin(trim($_POST['username']), trim($_POST['password']))) {
        header('Location: index.php');
        exit;
    } else {
        $login_error = 'Username atau password salah!';
    }
}

// ---- TEST WA ----
$wa_status = '';
if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_wa'])) {
    $result = sendWhatsApp(trim($_POST['test_phone']), trim($_POST['test_message']));
    $wa_status = $result['success']
        ? '✅ Pesan WhatsApp berhasil dikirim ke ' . $_POST['test_phone']
        : '❌ Gagal mengirim: ' . ($result['error'] ?? 'Unknown error');
    logWA($_POST['test_phone'], 'TEST', $_POST['test_message'], $result['success'] ? 'success' : 'failed');
}

// ---- PROSES BOOKING ----
$msg = ''; $msg_type = '';
if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $sid            = (int)$_POST['schedule_id'];
    $name           = trim($_POST['name']);
    $phone          = trim($_POST['phone']);
    $email          = trim($_POST['email']);
    $qty            = (int)$_POST['qty'];
    $selected_seats = array_filter(array_map('trim', explode(',', $_POST['selected_seats'] ?? '')));
    $food_items     = json_decode($_POST['food_items'] ?? '[]', true) ?: [];

    $sched = getScheduleById($sid);
    $sisa  = getRemainingQuota($sid);

    if (!$name || !$phone || !$email || $qty < 1) {
        $msg = '❌ Lengkapi semua data booking.';  $msg_type = 'error';
    } elseif ($qty > $sisa) {
        $msg = "❌ Kuota tidak cukup. Sisa: $sisa kursi."; $msg_type = 'error';
    } elseif (count($selected_seats) != $qty) {
        $msg = "❌ Pilih tepat $qty kursi!"; $msg_type = 'error';
    } else {
        // Hitung total
        $ticket_total = $qty * $sched['price'];
        $food_total   = 0;
        $food_details = [];
        foreach ($food_items as $item) {
            $food = getFoodById($item['id']);
            if (!$food) continue;
            $sub         = $food['price'] * $item['qty'];
            $food_total += $sub;
            $food_details[] = ['name' => $food['name'], 'price' => $food['price'], 'qty' => $item['qty'], 'subtotal' => $sub];
        }
        $grand_total = $ticket_total + $food_total;

        $booking_data = [
            'user_id'      => currentUser()['id'] ?? null,
            'schedule_id'  => $sid,
            'nama'         => $name,
            'phone'        => $phone,
            'email'        => $email,
            'qty'          => $qty,
            'seats'        => implode(',', $selected_seats),
            'ticket_total' => $ticket_total,
            'food_total'   => $food_total,
            'grand_total'  => $grand_total,
        ];

        $result = createBooking($booking_data, $food_items);

        if ($result['success']) {
            $film_data = getFilmById($sched['film_id']);
            $booking_data['booking_code'] = $result['booking_code'];
            $booking_data['food_details'] = $food_details;

            $wa_result = sendBookingNotification($booking_data, $film_data, $sched);
            logWA($phone, $result['booking_code'], 'Booking notification', $wa_result['success'] ? 'success' : 'failed');

            $msg = "✅ Booking berhasil! Kode: <b>{$result['booking_code']}</b>\n";
            $msg .= $wa_result['success']
                ? "📱 Notifikasi WhatsApp dikirim ke $phone"
                : "⚠️ Gagal kirim WhatsApp: " . ($wa_result['error'] ?? 'Unknown');
            $msg_type = 'success';
        } else {
            $msg = '❌ Gagal menyimpan booking: ' . ($result['error'] ?? ''); $msg_type = 'error';
        }
    }
}

// ---- DATA ----
$films     = getAllFilms();
$schedules = getSchedules();
$foods     = getAllFoods();
$user      = currentUser();

// Bookings milik user ini
$my_bookings = isLoggedIn() ? getUserBookings($user['id']) : [];

// Hitung stats
$total_tiket = 0;
foreach ($schedules as $s) $total_tiket += getRemainingQuota($s['id']);

// Add cast to films
foreach ($films as &$f) {
    $f['cast'] = getFilmCast($f['id']);
}
unset($f);

// WA logs terbaru
$wa_logs = [];
if (isLoggedIn()) {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM wa_logs ORDER BY sent_at DESC LIMIT 10");
    $wa_logs = $stmt->fetchAll();
}

$page_title = 'Beranda';
include 'includes/header.php';
?>

<main>
<?php if (!isLoggedIn()): ?>
    <!-- ===== LOGIN PAGE ===== -->
    <div style="max-width:420px; margin:60px auto;">
        <div class="stat" style="margin-bottom:20px; border-radius:12px;">
            <div class="stat-icon">🎬</div>
            <div>
                <div class="stat-num">CineHoror</div>
                <div class="stat-label">Silakan login untuk memesan tiket</div>
            </div>
        </div>
        <div class="admin-card">
            <h3><i class="fas fa-sign-in-alt"></i> Login</h3>
            <?php if ($login_error): ?>
                <div class="msg error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" placeholder="admin / user1 / user2" required autofocus>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="admin123 / user123" required>
                </div>
                <button type="submit" name="login" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>
            <div style="margin-top:1rem; font-size:0.7rem; color:var(--muted); text-align:center;">
                Demo: <b>admin/admin123</b> | <b>user1/user123</b> | <b>user2/user123</b>
            </div>
        </div>
    </div>

<?php else: ?>

    <?php if ($msg): ?>
        <div class="msg <?= $msg_type ?>"><?= nl2br(htmlspecialchars($msg)) ?></div>
    <?php endif; ?>

    <!-- ===== WA SECTION ===== -->
    <div class="wa-section">
        <h3><i class="fab fa-whatsapp"></i> WhatsApp Notification</h3>
        <p style="font-size:0.82rem; color:var(--muted);">Notifikasi WhatsApp otomatis terkirim setelah booking berhasil.</p>
        <form method="POST" class="wa-test-form">
            <input type="tel" name="test_phone" placeholder="Nomor WA (contoh: 628123456789)" required>
            <input type="text" name="test_message" placeholder="Pesan test" value="🧪 Test dari CineHoror!" required>
            <button type="submit" name="test_wa" class="btn-wa"><i class="fab fa-whatsapp"></i> Test Kirim</button>
        </form>
        <?php if ($wa_status): ?>
            <!-- FIX: Replace str_contains with strpos for PHP 7 compatibility -->
            <div class="msg <?= strpos($wa_status, 'berhasil') !== false ? 'success' : 'error' ?>" style="margin-top:1rem; margin-bottom:0;">
                <?= htmlspecialchars($wa_status) ?>
            </div>
        <?php endif; ?>
        <?php if ($wa_logs): ?>
            <details style="margin-top:1rem; font-size:0.75rem; color:var(--muted);">
                <summary style="cursor:pointer;">📋 Riwayat WhatsApp (<?= count($wa_logs) ?>)</summary>
                <?php foreach ($wa_logs as $log): ?>
                <div style="padding:0.4rem 0; border-bottom:1px solid var(--border);">
                    <span style="color:var(--muted);">[<?= date('H:i:s', strtotime($log['sent_at'])) ?>]</span>
                    <?= htmlspecialchars($log['phone']) ?> — <?= htmlspecialchars(substr($log['message'],0,50)) ?>
                    <span style="color:<?= $log['status']==='success' ? '#22c55e' : '#e63946' ?>;">(<?= $log['status'] ?>)</span>
                </div>
                <?php endforeach; ?>
            </details>
        <?php endif; ?>
    </div>

    <!-- ===== DASHBOARD ===== -->
    <h2>⚡ DASHBOARD</h2>
    <div class="stats">
        <div class="stat"><div class="stat-icon">🎬</div><div><div class="stat-num"><?= count($films) ?></div><div class="stat-label">FILM TAYANG</div></div></div>
        <div class="stat"><div class="stat-icon">🕐</div><div><div class="stat-num"><?= count($schedules) ?></div><div class="stat-label">JADWAL HARI INI</div></div></div>
        <div class="stat"><div class="stat-icon">🎟️</div><div><div class="stat-num"><?= $total_tiket ?></div><div class="stat-label">TIKET TERSISA</div></div></div>
        <div class="stat"><div class="stat-icon">🍿</div><div><div class="stat-num"><?= count($foods) ?></div><div class="stat-label">MENU TERSEDIA</div></div></div>
    </div>

    <!-- ===== FILM LIST ===== -->
    <h2>🎥 FILM PILIHAN</h2>
    <div class="films-grid">
        <?php foreach ($films as $idx => $f): ?>
            <div class="film-card" onclick="showFilmDetail(<?= $idx ?>)">
                <div class="film-poster" style="background-image:url('<?= htmlspecialchars($f['image']) ?>');">
                    <div class="poster-badge"><i class="fas fa-clock"></i> <?= htmlspecialchars($f['duration']) ?></div>
                </div>
                <div class="film-info">
                    <div class="film-title"><?= htmlspecialchars($f['title']) ?></div>
                    <div class="film-meta">
                        <span><i class="fas fa-skull"></i> <?= htmlspecialchars($f['genre']) ?></span>
                        <span><i class="fas fa-star" style="color:var(--gold)"></i> <?= $f['rating'] ?></span>
                    </div>
                    <div class="film-director"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($f['director']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== JADWAL ===== -->
    <h2>📅 JADWAL & BOOKING</h2>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr><th>FILM</th><th>TANGGAL</th><th>JAM</th><th>STUDIO</th><th>HARGA</th><th>SISA KURSI</th><th>AKSI</th></tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s):
                    $sisa    = getRemainingQuota($s['id']);
                    $pct     = $s['quota'] > 0 ? ($sisa / $s['quota']) * 100 : 0;
                    $barCls  = $pct > 60 ? 'high' : ($pct > 30 ? 'mid' : 'low');
                    $full    = $sisa <= 0;
                ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($s['film_title']) ?></td>
                    <td><?= date('d/m/Y', strtotime($s['show_date'])) ?></td>
                    <td><b><?= htmlspecialchars($s['time']) ?></b></td>
                    <td><span class="badge-studio"><?= htmlspecialchars($s['studio']) ?></span></td>
                    <td style="color:var(--gold);font-weight:600"><?= formatRupiah($s['price']) ?></td>
                    <td>
                        <div class="sisa-bar">
                            <div class="bar-bg"><div class="bar-fill <?= $barCls ?>" style="width:<?= $pct ?>%"></div></div>
                            <span><?= $sisa ?>/<?= $s['quota'] ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($full): ?>
                            <button class="btn-book" disabled>Habis</button>
                        <?php else: ?>
                            <button class="btn-book" onclick="openBookingModal(
                                <?= $s['id'] ?>,
                                '<?= addslashes($s['film_title']) ?>',
                                '<?= addslashes($s['time']) ?>',
                                '<?= addslashes($s['studio']) ?>',
                                <?= $s['price'] ?>,
                                <?= $sisa ?>,
                                '<?= $s['show_date'] ?>'
                            )">Pesan</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ===== TIKET SAYA ===== -->
    <?php if (!empty($my_bookings)): ?>
    <h2>🎫 TIKET SAYA</h2>
    <div class="ticket-list">
        <?php foreach ($my_bookings as $idx => $b): ?>
            <div class="ticket-card" id="ticket-<?= $idx ?>">
                <div class="ticket-header">
                    <h4><i class="fas fa-ticket-alt"></i> CINEHOROR TICKET</h4>
                    <i class="fas fa-film" style="font-size:1.5rem;"></i>
                </div>
                <div class="ticket-body">
                    <div class="ticket-movie-title"><?= htmlspecialchars($b['film_title']) ?></div>
                    <div style="font-size:0.75rem; color:#888; margin-bottom:0.5rem;">
                        <b><?= htmlspecialchars($b['booking_code']) ?></b> &nbsp;·&nbsp;
                        <span class="badge-status <?= $b['status'] ?>"><?= strtoupper($b['status']) ?></span>
                    </div>
                    <div class="ticket-info-grid">
                        <div class="ticket-info-item"><i class="fas fa-user"></i> <?= htmlspecialchars($b['nama']) ?></div>
                        <div class="ticket-info-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($b['phone']) ?></div>
                        <div class="ticket-info-item"><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($b['show_date'])) ?></div>
                        <div class="ticket-info-item"><i class="fas fa-clock"></i> <?= htmlspecialchars($b['time']) ?> WIB</div>
                        <div class="ticket-info-item"><i class="fas fa-video"></i> <?= htmlspecialchars($b['studio']) ?></div>
                        <div class="ticket-info-item"><i class="fas fa-envelope"></i> <?= htmlspecialchars($b['email']) ?></div>
                    </div>
                    <div class="ticket-seats">
                        <i class="fas fa-chair"></i> Kursi: <span><?= htmlspecialchars($b['seats']) ?></span>
                        (<?= $b['qty'] ?> tiket)
                    </div>
                    <?php if (!empty($b['foods'])): ?>
                    <div style="margin:0.5rem 0; font-size:0.78rem;">
                        <b><i class="fas fa-utensils"></i> Pesanan:</b><br>
                        <?php foreach ($b['foods'] as $food): ?>
                            • <?= htmlspecialchars($food['food_name']) ?> x<?= $food['qty'] ?> = <?= formatRupiah($food['subtotal']) ?><br>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="ticket-barcode">
                        <svg id="barcode-<?= $idx ?>"></svg>
                    </div>
                    <div style="text-align:center; font-size:1.1rem; font-weight:bold; color:var(--accent);">
                        Total: <?= formatRupiah($b['grand_total']) ?>
                    </div>
                </div>
                <div class="ticket-footer">
                    <span><i class="fab fa-whatsapp"></i> <?= htmlspecialchars(substr($b['phone'],0,4)) ?>****<?= htmlspecialchars(substr($b['phone'],-3)) ?></span>
                    <button class="print-ticket-btn" onclick="printTicket(<?= $idx ?>)">
                        <i class="fas fa-print"></i> Cetak
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
</main>

<!-- ===== MODAL DETAIL FILM ===== -->
<div class="modal-overlay" id="filmDetailModal">
    <div class="modal modal-small">
        <button class="btn-close" onclick="closeFilmDetail()">✕</button>
        <div id="filmDetailContent"></div>
    </div>
</div>

<!-- ===== MODAL BOOKING ===== -->
<div class="modal-overlay" id="bookingModal">
    <div class="modal">
        <button class="btn-close" onclick="closeBookingModal()">✕</button>
        <h3><i class="fas fa-ticket-alt"></i> PESAN TIKET</h3>
        <div class="modal-sub" id="modalSub">—</div>

        <form method="POST" id="bookingForm">
            <input type="hidden" name="confirm_booking" value="1">
            <input type="hidden" name="schedule_id" id="scheduleId">
            <input type="hidden" name="selected_seats" id="selectedSeats">
            <input type="hidden" name="food_items" id="foodItems">

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> NAMA LENGKAP</label>
                    <input type="text" name="name" placeholder="Nama lengkap" required
                        value="<?= isLoggedIn() ? htmlspecialchars(currentUser()['name']) : '' ?>">
                </div>
                <div class="form-group">
                    <label><i class="fab fa-whatsapp"></i> NO. WHATSAPP</label>
                    <input type="tel" name="phone" placeholder="08xxxxxxxxxx" required>
                    <small style="font-size:0.6rem; color:var(--muted);">Tiket dikirim via WhatsApp</small>
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> EMAIL</label>
                <input type="email" name="email" placeholder="email@example.com" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-ticket"></i> JUMLAH TIKET</label>
                <input type="number" name="qty" id="ticketQty" min="1" max="10" value="1" required onchange="updateSeatSelection()">
            </div>

            <div class="seat-map">
                <div class="screen">▶ LAYAR BIOSKOP ◀</div>
                <div class="seats-grid" id="seatsGrid"></div>
                <div class="seat-legend">
                    <div class="legend-item"><div class="legend-box" style="background:var(--card);border:1px solid var(--border);"></div> Tersedia</div>
                    <div class="legend-item"><div class="legend-box" style="background:var(--success);"></div> Dipilih</div>
                    <div class="legend-item"><div class="legend-box" style="background:var(--accent);"></div> Terisi</div>
                </div>
            </div>

            <label style="margin-bottom:0.5rem; display:block;"><i class="fas fa-utensils"></i> MAKANAN & MINUMAN</label>
            <div class="food-menu" id="foodMenu"></div>

            <div class="cart-summary" id="cartSummary">
                <div class="cart-item"><span>Harga/tiket</span><span id="perTicketPrice">—</span></div>
                <div class="cart-item"><span>Total Tiket</span><span id="ticketTotal">Rp 0</span></div>
                <div id="foodCartItems"></div>
                <div class="cart-total" id="grandTotal">Grand Total: Rp 0</div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-check-circle"></i> KONFIRMASI BOOKING
            </button>
        </form>
        <div style="margin-top:0.8rem; font-size:0.7rem; text-align:center; color:var(--muted);">
            <i class="fab fa-whatsapp"></i> Tiket digital dikirim ke WhatsApp kamu
        </div>
    </div>
</div>

<script>
const filmsData = <?= json_encode(array_values($films)) ?>;
const foodsData = <?= json_encode(array_values($foods)) ?>;

// ===== FILM DETAIL MODAL =====
function showFilmDetail(index) {
    const film = filmsData[index];
    const content = document.getElementById('filmDetailContent');

    let castHtml = film.cast && film.cast.length
        ? '<div class="cast-grid">' + film.cast.map(c =>
            `<div class="cast-item">
                <div class="cast-photo" style="background-image:url('${escapeHtml(c.photo)}');"></div>
                <div class="cast-name">${escapeHtml(c.name)}</div>
                <div class="cast-role">${escapeHtml(c.role_name)}</div>
            </div>`).join('') + '</div>'
        : '';

    content.innerHTML = `
        <div class="film-detail-poster" style="background-image:url('${escapeHtml(film.image)}');"></div>
        <h3>${escapeHtml(film.title)}</h3>
        <div class="detail-row"><div class="detail-label"><i class="fas fa-user-tie"></i> Sutradara</div><div class="detail-value">${escapeHtml(film.director)}</div></div>
        <div class="detail-row"><div class="detail-label"><i class="fas fa-clock"></i> Durasi</div><div class="detail-value">${escapeHtml(film.duration)}</div></div>
        <div class="detail-row"><div class="detail-label"><i class="fas fa-calendar"></i> Tahun</div><div class="detail-value">${escapeHtml(film.year)}</div></div>
        <div class="detail-row"><div class="detail-label"><i class="fas fa-star"></i> Rating</div><div class="detail-value">${film.rating} / 5.0</div></div>
        <div class="synopsis-text"><b><i class="fas fa-align-left"></i> Sinopsis:</b><br>${escapeHtml(film.synopsis)}</div>
        ${castHtml ? `<div class="synopsis-text" style="margin-top:1rem"><b><i class="fas fa-users"></i> Pemain:</b></div>${castHtml}` : ''}
    `;
    document.getElementById('filmDetailModal').classList.add('open');
}
function closeFilmDetail() { document.getElementById('filmDetailModal').classList.remove('open'); }

// ===== BOOKING MODAL =====
let currentPrice = 0, selectedSeats = [], bookedSeats = [], foodCart = {};

function openBookingModal(id, film, time, studio, price, sisa, date) {
    currentPrice = price;
    selectedSeats = []; foodCart = {};
    document.getElementById('scheduleId').value = id;
    document.getElementById('selectedSeats').value = '';
    document.getElementById('ticketQty').value = 1;
    document.getElementById('modalSub').innerHTML =
        `<i class="fas fa-film"></i> ${escapeHtml(film)} &nbsp;·&nbsp; <i class="fas fa-calendar"></i> ${escapeHtml(date)} &nbsp;·&nbsp; <i class="fas fa-clock"></i> ${escapeHtml(time)} &nbsp;·&nbsp; <i class="fas fa-video"></i> ${escapeHtml(studio)} &nbsp;·&nbsp; <b style="color:var(--gold)">Rp ${price.toLocaleString('id-ID')}/tiket</b>`;

    // Load booked seats via AJAX
    fetch(`get_booked_seats.php?schedule_id=${id}`)
        .then(r => r.json())
        .then(data => {
            bookedSeats = data.booked_seats || [];
            updateSeatsGrid();
        })
        .catch(() => {
            bookedSeats = [];
            updateSeatsGrid();
        });

    renderFoodMenu();
    updateCart();
    document.getElementById('bookingModal').classList.add('open');
}
function closeBookingModal() { document.getElementById('bookingModal').classList.remove('open'); }

function updateSeatsGrid() {
    const grid = document.getElementById('seatsGrid');
    grid.innerHTML = '';
    const rows = ['A','B','C','D','E','F'];
    rows.forEach(row => {
        for (let i = 1; i <= 8; i++) {
            const sid = row + i;
            const div = document.createElement('div');
            div.className = 'seat';
            div.textContent = sid;
            if (bookedSeats.includes(sid)) {
                div.classList.add('booked');
            } else if (selectedSeats.includes(sid)) {
                div.classList.add('selected');
                div.onclick = () => toggleSeat(sid);
            } else {
                div.classList.add('available');
                div.onclick = () => toggleSeat(sid);
            }
            grid.appendChild(div);
        }
    });
}

function toggleSeat(sid) {
    const qty = parseInt(document.getElementById('ticketQty').value);
    if (selectedSeats.includes(sid)) {
        selectedSeats = selectedSeats.filter(s => s !== sid);
    } else if (selectedSeats.length < qty) {
        selectedSeats.push(sid);
    } else {
        alert(`Maksimal memilih ${qty} kursi!`); return;
    }
    document.getElementById('selectedSeats').value = selectedSeats.join(',');
    updateSeatsGrid(); updateCart();
}

function updateSeatSelection() {
    const qty = parseInt(document.getElementById('ticketQty').value);
    if (selectedSeats.length > qty) selectedSeats = selectedSeats.slice(0, qty);
    document.getElementById('selectedSeats').value = selectedSeats.join(',');
    updateSeatsGrid(); updateCart();
}

function renderFoodMenu() {
    const menu = document.getElementById('foodMenu');
    menu.innerHTML = foodsData.map(food => `
        <div class="food-item">
            <div class="food-info">
                <i class="fas ${food.icon || 'fa-utensils'}"></i>
                <div>
                    <div class="food-name">${escapeHtml(food.name)}</div>
                    <div class="food-price">Rp ${food.price.toLocaleString('id-ID')}</div>
                </div>
            </div>
            <input type="number" class="food-qty" id="food-${food.id}"
                min="0" max="10" value="${foodCart[food.id] || 0}"
                onchange="updateFoodCart(${food.id}, this.value)">
        </div>
    `).join('');
}

function updateFoodCart(id, qty) {
    if (parseInt(qty) > 0) foodCart[id] = parseInt(qty); else delete foodCart[id];
    updateCart();
}

function updateCart() {
    const qty = parseInt(document.getElementById('ticketQty').value) || 1;
    const ticketTotal = qty * currentPrice;
    document.getElementById('perTicketPrice').textContent = 'Rp ' + currentPrice.toLocaleString('id-ID');
    document.getElementById('ticketTotal').textContent = 'Rp ' + ticketTotal.toLocaleString('id-ID');

    let foodTotal = 0, foodHtml = '';
    Object.entries(foodCart).forEach(([id, qtyF]) => {
        const food = foodsData.find(f => f.id == id);
        if (!food) return;
        const sub = food.price * qtyF;
        foodTotal += sub;
        foodHtml += `<div class="cart-item"><span>${escapeHtml(food.name)} ×${qtyF}</span><span>Rp ${sub.toLocaleString('id-ID')}</span></div>`;
    });
    document.getElementById('foodCartItems').innerHTML = foodHtml;
    document.getElementById('grandTotal').textContent = 'Grand Total: Rp ' + (ticketTotal + foodTotal).toLocaleString('id-ID');
    document.getElementById('foodItems').value = JSON.stringify(
        Object.entries(foodCart).map(([id, qty]) => ({ id: parseInt(id), qty }))
    );
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

// ===== BARCODE =====
<?php foreach ($my_bookings as $idx => $b): ?>
try {
    JsBarcode("#barcode-<?= $idx ?>", "<?= addslashes($b['booking_code']) ?>", {
        format:"CODE128", lineColor:"#333", width:2, height:40, displayValue:true, fontSize:12
    });
} catch(e) { console.error('Barcode error:', e); }
<?php endforeach; ?>

// ===== PRINT =====
function printTicket(idx) {
    const el = document.getElementById(`ticket-${idx}`).cloneNode(true);
    const pw = window.open('', '_blank');
    pw.document.write(`<!DOCTYPE html><html><head>
        <title>Tiket CineHoror</title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:'DM Sans',Arial,sans-serif;background:#e5e5e5;display:flex;flex-direction:column;align-items:center;padding:20px;gap:16px;}
            .ticket-card{max-width:480px;width:100%;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.2);}
            .ticket-header{background:linear-gradient(135deg,#e63946,#c1121f);padding:16px 20px;color:#fff;display:flex;justify-content:space-between;align-items:center;}
            .ticket-header h4{font-size:1.3rem;letter-spacing:2px;}
            .ticket-body{padding:20px;color:#333;}
            .ticket-movie-title{font-size:1.3rem;font-weight:700;color:#e63946;margin-bottom:8px;}
            .ticket-info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:12px 0;padding:12px 0;border-top:2px dashed #ddd;border-bottom:2px dashed #ddd;}
            .ticket-info-item{display:flex;align-items:center;gap:6px;font-size:0.82rem;}
            .ticket-seats{background:#f5f5f5;padding:12px;border-radius:8px;text-align:center;margin:10px 0;}
            .ticket-seats span{font-weight:700;color:#e63946;font-size:1.1rem;}
            .ticket-barcode{text-align:center;padding:12px;background:#fff;border-radius:8px;margin:10px 0;}
            .ticket-footer{background:#f5f5f5;padding:10px 20px;display:flex;justify-content:space-between;font-size:0.75rem;color:#666;}
            .no-print{margin-top:16px;}
            @media print{.no-print{display:none}body{background:#fff;padding:0}}
        </style>
    </head><body>
        ${el.outerHTML}
        <div class="no-print">
            <button onclick="window.print()" style="padding:10px 30px;background:#e63946;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:1rem;">🖨️ Cetak Tiket</button>
        </div>
        <script>setTimeout(()=>window.print(),600);<\/script>
    </body></html>`);
    pw.document.close();
}

// Close on backdrop
document.getElementById('bookingModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeBookingModal(); });
document.getElementById('filmDetailModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeFilmDetail(); });
</script>
</body>
</html>