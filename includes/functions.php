<?php
// ============================================================
// INCLUDES/FUNCTIONS.PHP — Helper Functions
// ============================================================

function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function generateBookingCode() {
    return 'TIX' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}

// ---- FILM ----
function getAllFilms() {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM films WHERE is_active = 1 ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getFilmById($id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM films WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getFilmCast($film_id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM film_cast WHERE film_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$film_id]);
    return $stmt->fetchAll();
}

// ---- SCHEDULE ----
function getSchedules($date = null) {
    $db = getDB();
    if (!$date) $date = date('Y-m-d');
    
    $stmt = $db->prepare("
        SELECT s.*, f.title AS film_title, f.genre, f.rating, f.image
        FROM schedules s
        JOIN films f ON f.id = s.film_id
        WHERE s.show_date >= ? AND s.is_active = 1 AND f.is_active = 1
        ORDER BY s.show_date ASC, s.time ASC
    ");
    $stmt->execute([$date]);
    return $stmt->fetchAll();
}

function getAllSchedules() {
    $db = getDB();
    $stmt = $db->query("
        SELECT s.*, f.title AS film_title
        FROM schedules s
        JOIN films f ON f.id = s.film_id
        WHERE s.is_active = 1
        ORDER BY s.show_date DESC, s.time ASC
    ");
    return $stmt->fetchAll();
}

function getScheduleById($id) {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT s.*, f.title AS film_title, f.image, f.genre
        FROM schedules s JOIN films f ON f.id = s.film_id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getRemainingQuota($schedule_id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COALESCE(SUM(qty),0) AS booked FROM bookings WHERE schedule_id = ? AND status != 'cancelled'");
    $stmt->execute([$schedule_id]);
    $row  = $stmt->fetch();
    $sched = getScheduleById($schedule_id);
    if (!$sched) return 0;
    return max(0, $sched['quota'] - (int)$row['booked']);
}

function getBookedSeats($schedule_id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT seats FROM bookings WHERE schedule_id = ? AND status != 'cancelled'");
    $stmt->execute([$schedule_id]);
    $rows = $stmt->fetchAll();
    $seats = [];
    foreach ($rows as $r) {
        if ($r['seats']) {
            foreach (explode(',', $r['seats']) as $s) {
                $seats[] = trim($s);
            }
        }
    }
    return $seats;
}

// ---- FOODS ----
function getAllFoods() {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM foods WHERE is_active = 1 ORDER BY category, id");
    return $stmt->fetchAll();
}

function getFoodById($id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM foods WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// ---- BOOKINGS (FIXED: Added seat validation) ----
function createBooking($data, $food_items) {
    $db = getDB();
    
    // VALIDATION: Check quota again to prevent race condition
    $remaining = getRemainingQuota($data['schedule_id']);
    if ($data['qty'] > $remaining) {
        return ['success' => false, 'error' => 'Kuota tiket tidak mencukupi. Silakan refresh halaman.'];
    }
    
    // VALIDATION: Check if selected seats are still available
    $booked_seats = getBookedSeats($data['schedule_id']);
    $selected_seats_array = explode(',', $data['seats']);
    foreach ($selected_seats_array as $seat) {
        if (in_array($seat, $booked_seats)) {
            return ['success' => false, 'error' => "Kursi $seat sudah dipesan orang lain. Silakan pilih kursi lain."];
        }
    }
    
    $db->beginTransaction();

    try {
        $code = generateBookingCode();

        $stmt = $db->prepare("
            INSERT INTO bookings
              (booking_code, user_id, schedule_id, nama, phone, email, qty, seats,
               ticket_total, food_total, grand_total, wa_sent, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $code,
            $data['user_id'] ?? null,
            $data['schedule_id'],
            $data['nama'],
            $data['phone'],
            $data['email'],
            $data['qty'],
            $data['seats'],
            $data['ticket_total'],
            $data['food_total'],
            $data['grand_total'],
            0,
            'confirmed'
        ]);
        $booking_id = $db->lastInsertId();

        foreach ($food_items as $item) {
            $food = getFoodById($item['id']);
            if (!$food) continue;
            $subtotal = $food['price'] * $item['qty'];
            $stmt2 = $db->prepare("INSERT INTO booking_foods (booking_id, food_id, food_name, price, qty, subtotal) VALUES (?,?,?,?,?,?)");
            $stmt2->execute([$booking_id, $food['id'], $food['name'], $food['price'], $item['qty'], $subtotal]);
        }

        $db->commit();
        return ['success' => true, 'booking_id' => $booking_id, 'booking_code' => $code];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getBookingByCode($code) {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT b.*, s.time, s.studio, s.price AS ticket_price, s.show_date,
               f.title AS film_title, f.director, f.image AS film_image
        FROM bookings b
        JOIN schedules s ON s.id = b.schedule_id
        JOIN films f ON f.id = s.film_id
        WHERE b.booking_code = ?
    ");
    $stmt->execute([$code]);
    $booking = $stmt->fetch();
    if ($booking) {
        $stmt2 = $db->prepare("SELECT * FROM booking_foods WHERE booking_id = ?");
        $stmt2->execute([$booking['id']]);
        $booking['foods'] = $stmt2->fetchAll();
    }
    return $booking;
}

function getUserBookings($user_id) {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT b.*, s.time, s.studio, s.show_date,
               f.title AS film_title, f.image AS film_image
        FROM bookings b
        JOIN schedules s ON s.id = b.schedule_id
        JOIN films f ON f.id = s.film_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
    foreach ($bookings as &$b) {
        $stmt2 = $db->prepare("SELECT * FROM booking_foods WHERE booking_id = ?");
        $stmt2->execute([$b['id']]);
        $b['foods'] = $stmt2->fetchAll();
    }
    return $bookings;
}

function logWA($phone, $booking_code, $message, $status) {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO wa_logs (phone, booking_code, message, status) VALUES (?,?,?,?)");
    $stmt->execute([$phone, $booking_code, $message, $status]);
}
?>