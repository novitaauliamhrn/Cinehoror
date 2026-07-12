<?php
// ============================================================
// KONFIGURASI FONNTE WHATSAPP API
// ============================================================
define('FONNTE_API_KEY', 'yBttuqLMyJ7ebbMBrchW');
define('FONNTE_API_URL', 'https://api.fonnte.com/send');

// ============================================================
// FUNGSI KIRIM WHATSAPP
// ============================================================
function sendWhatsApp($phone, $message) {
    $phone = preg_replace('/^0/', '62', $phone);
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (strlen($phone) < 10) {
        return ['success' => false, 'message' => 'Nomor WhatsApp tidak valid'];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => FONNTE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => http_build_query([
            'target'  => $phone,
            'message' => $message,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . FONNTE_API_KEY
        ],
    ]);

    $response  = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error     = curl_error($curl);
    curl_close($curl);

    return [
        'success'   => $http_code == 200 && !$error,
        'response'  => json_decode($response, true),
        'http_code' => $http_code,
        'error'     => $error
    ];
}

// ============================================================
// FUNGSI KIRIM WA NOTIFIKASI BOOKING
// ============================================================
function sendBookingNotification($booking, $film, $schedule) {
    $wa_message  = "🎬 *CINEHOROR BIOSKOP* 🎬\n\n";
    $wa_message .= "Halo *{$booking['nama']}*, booking kamu berhasil!\n\n";
    $wa_message .= "📋 *Detail Booking:*\n";
    $wa_message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $wa_message .= "🎫 Kode: *{$booking['booking_code']}*\n";
    $wa_message .= "🎬 Film: {$film['title']}\n";
    $wa_message .= "🕐 Jam: {$schedule['time']} WIB\n";
    $wa_message .= "🏢 Studio: {$schedule['studio']}\n";
    $wa_message .= "💺 Kursi: {$booking['seats']}\n";
    $wa_message .= "🎟️ Jumlah: {$booking['qty']} tiket\n";

    if (!empty($booking['food_details'])) {
        $wa_message .= "\n🍿 *Pesanan Makanan:*\n";
        foreach ($booking['food_details'] as $food) {
            $wa_message .= "• {$food['name']} x{$food['qty']} = Rp " . number_format($food['subtotal'], 0, ',', '.') . "\n";
        }
    }

    $wa_message .= "\n💰 *Total: Rp " . number_format($booking['grand_total'], 0, ',', '.') . "*\n";
    $wa_message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $wa_message .= "✅ Simpan pesan ini sebagai bukti booking.\n";
    $wa_message .= "Tunjukkan saat masuk bioskop.\n\n";
    $wa_message .= "Terima kasih! 🍿🎬";

    return sendWhatsApp($booking['phone'], $wa_message);
}
?>