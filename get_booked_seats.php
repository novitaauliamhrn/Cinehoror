<?php
// get_booked_seats.php — AJAX endpoint
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Optional: allow non-logged in users to view seat map, but add rate limiting
// For better security, uncomment the line below to require login:
// if (!isLoggedIn()) {
//     echo json_encode(['error' => 'unauthorized', 'booked_seats' => []]);
//     exit;
// }

$schedule_id = (int)($_GET['schedule_id'] ?? 0);
if (!$schedule_id) {
    echo json_encode(['booked_seats' => []]);
    exit;
}

echo json_encode(['booked_seats' => getBookedSeats($schedule_id)]);
?>