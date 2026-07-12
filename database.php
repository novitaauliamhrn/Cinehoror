<?php
// ============================================================
// KONFIGURASI DATABASE - VERSI DIPERBAIKI (TANPA REDIRECT LOOP)
// ============================================================

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Coba konek ke database cinehoror
            $pdo = new PDO('mysql:host=127.0.0.1;dbname=cinehoror;charset=utf8mb4', 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            // Jika database belum ada, buat dulu
            try {
                $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Buat database
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `cinehoror` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `cinehoror`");
                
                // Buat semua tabel
                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    role ENUM('admin','user') DEFAULT 'user',
                    phone VARCHAR(20),
                    email VARCHAR(100),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS films (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(200) NOT NULL,
                    duration VARCHAR(50),
                    rating DECIMAL(3,1) DEFAULT 0,
                    genre VARCHAR(50),
                    director VARCHAR(100),
                    year VARCHAR(10),
                    synopsis TEXT,
                    image VARCHAR(300),
                    is_active TINYINT(1) DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS film_cast (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    film_id INT NOT NULL,
                    name VARCHAR(100),
                    role_name VARCHAR(100),
                    photo VARCHAR(300),
                    sort_order INT DEFAULT 0,
                    FOREIGN KEY (film_id) REFERENCES films(id) ON DELETE CASCADE
                )");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS schedules (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    film_id INT NOT NULL,
                    show_date DATE NOT NULL,
                    time VARCHAR(10) NOT NULL,
                    studio VARCHAR(50),
                    price INT DEFAULT 50000,
                    quota INT DEFAULT 50,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (film_id) REFERENCES films(id) ON DELETE CASCADE
                )");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS foods (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    price INT DEFAULT 0,
                    icon VARCHAR(50) DEFAULT 'fa-utensils',
                    category ENUM('snack','drink','meal') DEFAULT 'snack',
                    is_active TINYINT(1) DEFAULT 1
                )");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    booking_code VARCHAR(20) NOT NULL UNIQUE,
                    user_id INT,
                    schedule_id INT NOT NULL,
                    nama VARCHAR(100),
                    phone VARCHAR(20),
                    email VARCHAR(100),
                    qty INT DEFAULT 1,
                    seats VARCHAR(200),
                    ticket_total INT DEFAULT 0,
                    food_total INT DEFAULT 0,
                    grand_total INT DEFAULT 0,
                    wa_sent TINYINT(1) DEFAULT 0,
                    status ENUM('pending','confirmed','cancelled') DEFAULT 'confirmed',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (schedule_id) REFERENCES schedules(id)
                )");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS booking_foods (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    booking_id INT NOT NULL,
                    food_id INT NOT NULL,
                    food_name VARCHAR(100),
                    price INT DEFAULT 0,
                    qty INT DEFAULT 1,
                    subtotal INT DEFAULT 0,
                    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
                )");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS wa_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    phone VARCHAR(20),
                    booking_code VARCHAR(20),
                    message TEXT,
                    status ENUM('success','failed') DEFAULT 'success',
                    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                
                // Cek apakah sudah ada data user
                $check = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                
                if ($check == 0) {
                    // Insert data awal
                    $pdo->exec("INSERT INTO users (username, password, name, role, phone, email) VALUES
                        ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Administrator', 'admin', '628123456789', 'admin@cinehoror.com'),
                        ('user1', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'Budi Santoso', 'user', '628123456789', 'budi@email.com'),
                        ('user2', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'Siti Aminah', 'user', '628123456789', 'siti@email.com')
                    ");
                    
                    // Insert films
                    $films = [
                    [1,'KKN di Desa Penari','120 menit',4.8,'Horor','Awi Suryadi','2022',
                    'Cerita tentang sekelompok mahasiswa yang menjalani KKN di sebuah desa terpencil. Mereka tanpa sengaja melanggar larangan misterius yang mengakibatkan teror mengerikan dari makhluk gaib penari desa. Film ini diangkat dari kisah nyata yang viral di Twitter dengan berbagai kejadian mistis yang mencekam. Seorang mahasiswi bernama Nur harus berjuang melawan kutukan yang mengancam jiwanya setelah desa tersebut menyimpan rahasia kelam tentang seorang penari cantik yang mati misterius.',
                    'gambar/kkn.jpg'],
                    [2,'Sewu Dino','125 menit',4.7,'Horor','Kimo Stamboel','2023',
                    'Sri, seorang wanita yang terjebak dalam kutukan misterius setelah menikah dengan seorang pria dari keluarga kaya. Ia harus bertahan hidup selama seribu hari melawan makhluk-makhluk gaib yang terus mengganggunya. Kutukan yang diturunkan dari generasi ke generasi ini hanya bisa dipatahkan jika Sri mampu melewati hari ke-1000. Namun setiap hari semakin mendekati angka tersebut, teror yang dialaminya semakin mengerikan dan mengancam nyawanya serta orang-orang terdekatnya.',
                    'gambar/sewudino.jpg'],
                    [3,'Waktu Maghrib','100 menit',4.6,'Horor','Sidharta Tata','2023',
                    'Sepasang suami istri yang baru pindah ke rumah warisan mendapati bahwa setiap kali maghrib tiba, hal-hal aneh mulai terjadi. Mereka tidak sendirian di rumah tersebut. Sosok misterius wanita berpakaian putih selalu muncul saat adzan maghrib berkumandang. Berbagai usaha dilakukan untuk mengusir gangguan tersebut, namun semakin mereka melawan, semakin kuat pula kekuatan gaib yang menghantui mereka. Waktu maghrib menjadi saat yang paling ditakuti karena batas antara dunia nyata dan gaib mulai kabur.',
                    'gambar/waktumaghrib.jpg'],
                    [4,'Ivanna','110 menit',4.5,'Horor','Kimo Stamboel','2022',
                    'Kisah tentang seorang pemuda bernama Duta yang harus menyelamatkan kekasihnya Ambar dari kutukan kuntilanak bernama Ivanna. Ivanna adalah wanita Belanda yang mati tragis di masa penjajahan dan kini kembali untuk membalas dendam kepada keturunan yang dulu membunuhnya. Duta harus memecahkan misteri masa lalu dan menemukan cara untuk menenangkan arwah Ivanna sebelum kekasihnya menjadi korban berikutnya. Film ini merupakan bagian dari Danur Universe.',
                    'gambar/ivanna.jpg'],
                    [5,'Pengabdi Setan 2','119 menit',4.9,'Horor','Joko Anwar','2022',
                    'Lanjutan kisah dari film Pengabdi Setan. Keluarga yang selamat dari teror di rumah duka kini harus menghadapi ancaman baru yang lebih mengerikan. Rini bersama kedua adiknya berusaha menjalani hidup normal setelah kematian ibu mereka. Namun kegelapan dari masa lalu terus memburu mereka. Sekte sesat yang dulu mengincar keluarga mereka ternyata belum menyerah. Mereka harus berjuang melawan kekuatan jahat yang ingin menguasai jiwa-jiwa mereka. Sebuah perjuangan antara iman dan kegelapan.',
                    'gambar/pengabdisetan.jpg'],
                ];
                    $stmt = $pdo->prepare("INSERT INTO films (id,title,duration,rating,genre,director,year,synopsis,image) VALUES (?,?,?,?,?,?,?,?,?)");
                    foreach ($films as $f) $stmt->execute($f);
                    
                    // Insert foods
                    $foods = [
                        [1,'Popcorn Caramel',25000,'fa-popcorn','snack'],
                        [2,'Popcorn Asin',22000,'fa-popcorn','snack'],
                        [3,'Nachos Cheese',30000,'fa-cheese','snack'],
                        [4,'French Fries',20000,'fa-french-fries','snack'],
                        [5,'Coca Cola',15000,'fa-wine-bottle','drink'],
                        [6,'Sprite',15000,'fa-wine-bottle','drink'],
                        [7,'Mineral Water',10000,'fa-tint','drink'],
                        [8,'Tea Jus',12000,'fa-mug-hot','drink'],
                        [9,'Hotdog',25000,'fa-hotdog','meal'],
                        [10,'Chocolate',18000,'fa-candy-cane','snack'],
                    ];
                    $stmt = $pdo->prepare("INSERT INTO foods (id,name,price,icon,category) VALUES (?,?,?,?,?)");
                    foreach ($foods as $fd) $stmt->execute($fd);
                    
                    // Harga tiket per film
                    $film_prices = [
                        1 => 55000,  // KKN di Desa Penari
                        2 => 60000,  // Sewu Dino
                        3 => 65000,  // Waktu Maghrib
                        4 => 70000,  // Ivanna
                        5 => 75000   // Pengabdi Setan 2
                    ];
                    
                    $start_date = '2026-07-01';
                    $end_date = '2026-07-30';
                    $current_date = $start_date;

                    while (strtotime($current_date) <= strtotime($end_date)) {
                        for ($film_id = 1; $film_id <= 5; $film_id++) {
                            $times = ['10:00', '13:00', '16:00', '19:00'];
                            $studios = ['Studio A', 'Studio B', 'Studio C'];
                            foreach ($times as $time) {
                                $studio = $studios[array_rand($studios)];
                                $price = $film_prices[$film_id]; // harga sesuai film
                                $stmt = $pdo->prepare("INSERT INTO schedules (film_id,show_date,time,studio,price,quota) VALUES (?,?,?,?,?,50)");
                                $stmt->execute([$film_id, $current_date, $time, $studio, $price]);
                            }
                        }
                        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                    }

                    // Insert cast pemain
                    // Kolom: film_id, name, role_name, photo, sort_order
                    $cast_data = [
                        // Film 1: KKN di Desa Penari (2022)
                        [1, 'Adinda Thomas', 'Widya', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT8UVbJzrU8PnoNE0iTDPaqBPq34HA2pS6JH1j3avY1lZiCG5u-cS1azM3K&s=10', 1],
                        [1, 'Aghniny Haque', 'Ayu', 'https://statik.tempo.co/data/2024/05/17/id_1302970/1302970_720.jpg', 2],
                        [1, 'Achmad Megantara', 'Bima', 'https://file.indonesianfilmcenter.com/uploads/2019-08/df9776d405e69f64e4799814676a6145.jpg', 3],
                        [1, 'Tissa Biani', 'Nur', 'https://assets.pikiran-rakyat.com/crop/0x0:0x0/720x0/webp/photo/2024/08/02/156456185.jpg', 4],
                        [1, 'Calvin Jeremy', 'Anton', 'https://cdn.antaranews.com/cache/1200x800/2021/01/03/Screenshot_2021-01-03-11-28-33-21_copy_800x534.jpg', 5],
                        [1, 'Fajar Nugraha', 'Wahyu', 'https://assets.promediateknologi.id/crop/0x0:0x0/1200x0/webp/photo/2022/12/14/1727154618.jpg', 6],

                        // Film 2: Sewu Dino (2023)
                        [2, 'Mikha Tambayong', 'Sri', 'https://static.republika.co.id/uploads/images/inpicture_slide/mikha-tambayong-_130322101145-302.jpg', 1],
                        [2, 'Gisellma Firmansyah', 'Dela', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRe7w8jmXBvNqID9gNx35A08qiG0cVkOCjaWaiV0YQvTSALbM6-fNHhn68&s=10', 2],
                        [2, 'Marthino Lio', 'Sabdo Kuncoro', 'https://file.indonesianfilmcenter.com/uploads/2019-08/4d2941544fa5c2eea562feda3f37a127.png', 3],
                        [2, 'Karina Suwandi', 'Karsa Atmojo', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSrvUnfo1EmklM7TSq97M4P-ftHeSR9HhakjrTPQRvVruuEHrng3f94wWQ&s=10', 4],
                        [2, 'Dayinta Melira', 'Sengarturih', 'https://image.idntimes.com/post/20230430/snapinstaapp-277076666-1406549089765433-8911658906202973247-n-1080-4e296c1867ec260e77f03fa731ebb596.jpg', 5],

                        // Film 3: Waktu Maghrib (2023)
                        [3, 'Ali Fikri','Adi', 'https://www.dailysia.com/wp-content/uploads/2024/12/Ali-Fikry_2.jpg', 1],
                        [3, 'Taskya Namya', 'Ningsih', 'https://asset.kompas.com/crops/iB5M0NVAc4oghf9_vak_YyuwnN8=/0x0:0x0/750x500/data/photo/2024/09/18/66eaa3f7200c7.jpg', 2],
                        [3, 'Aulia Sarah', 'Bu Woro', 'https://www.wowkeren.com/display/images/photo/2022/12/07/00462863.jpg', 3],
                        [3, 'Bima Sena', 'Saman', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTCjlPJsAv55BYxR4Ogcso4q3yQ8Z1AgPn3y6qMmDCMJsudGKTtM5FbqMU&s=10', 4],
                        [3, 'Nafiza Fatia Rani', 'Ayu', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSj6nxAOuari_R8E-0n4W3zjnO4wiMTmB03FRYtSif5qIS2YhgdcoNS1fqp&s=10', 5],

                        // Film 4: Ivanna (2022)
                        [4, 'Caitlin Halderman', 'Ambar', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRp9PWri55qeWu7tDhMTuehV1naKszDJ-sFhwhVe6J5rp06N4kjmoZSuGg&s=10', 1],
                        [4, 'Sonia Alyssa', 'Ivanna', 'https://assets.pikiran-rakyat.com/crop/99x22:963x614/720x0/webp/photo/2021/06/07/1606666203.jpg', 2],
                        [4, 'Taskya Namya', 'Rina', 'https://asset.kompas.com/crops/iB5M0NVAc4oghf9_vak_YyuwnN8=/0x0:0x0/750x500/data/photo/2024/09/18/66eaa3f7200c7.jpg', 3],
                        [4, 'Junior Roberts', 'Arthur', 'https://image.tmdb.org/t/p/w500/iGLfo5jA5mjmz5a3nHqph7Ekyrq.jpg', 4],
                        [4, 'Jovarel Callum', 'Dika', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT91evooD6u_mkBUwLB4lf2yqngVDXQMi7JhSrI4UAKgDrMGAg86hrME-E6ZHoaBiLoMvhz9JrcL6tg9ofCJ8_FShWIC2yx60Q_9Ld7kw&s=10', 5],

                        // Film 5: Pengabdi Setan (2017)
                        [5, 'Tara Basro', 'Rini', 'https://cdn.grid.id/crop/0x0:0x0/700x465/photo/bolasport/medium_27787dd2d0f08c4922bab35cc588787c.jpg', 1],
                        [5, 'Endy Arfian', 'Tony', 'https://awsimages.detik.net.id/community/media/visual/2018/07/17/8fc1de0a-5e1f-42cd-8e59-8b427ff1b288_43.jpeg?w=600&q=90', 2],
                        [5, 'Nasar Annuz', 'Bondi', 'https://cdn0-production-images-kly.akamaized.net/23539zrNh1uqdOl-DKujhrhR8GI=/500x500/smart/filters:quality(75):strip_icc():format(webp)/kly-media-production/medias/3471571/original/004285300_1622688298-sar_1.jpg', 3],
                        [5, 'Bront Palarae', 'Ayah', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSab7jvG2RX-b1KQ1XbbrbeepLpAwgfKLv4u4_PDfY31ty2tdn3XE7KYxNrOg6-zsqRWRFlYOVHsyLvFSXvLiHhL8MwPGLw0_bDC5uubOg&s=10', 4],
                        [5, 'Ratu Felisha', 'Ibu (Arwah)', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTzobQAiE0OhU_iBnMJrEviqcs7j_kXomsHk6RZGWXvYGidQuCvhqu3duU&s=10', 5],
                        [5, 'Asmara Abigail', 'Darminah', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT3MKFyDBvKXZCHHn1qlo22bPTcG-Q1WXKaLbWugtDNR5OvHhSJPiokjwb9JDpMW-HggxV6L08s7S9uNFYQ7VteKSQT2RYrxaKBC3VfuJ8&s=10', 6],
                    ];
                    $stmt = $pdo->prepare("INSERT INTO film_cast (film_id, name, role_name, photo, sort_order) VALUES (?,?,?,?,?)");
                    foreach ($cast_data as $c) $stmt->execute($c);
                }
                
                // Reconnect ke database yang sudah dibuat
                $pdo = new PDO('mysql:host=127.0.0.1;dbname=cinehoror;charset=utf8mb4', 'root', '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
            } catch (PDOException $e2) {
                die('<div style="background:#1a0a0a;color:#e63946;font-family:monospace;padding:2rem;border-radius:8px;margin:2rem auto;max-width:600px;text-align:center">
                    <h2>❌ Koneksi Database Gagal</h2>
                    <p>' . htmlspecialchars($e2->getMessage()) . '</p>
                    <p>Pastikan MySQL di XAMPP sedang berjalan (tombol Start berwarna HIJAU)</p>
                </div>');
            }
        }
    }
    return $pdo;
}
?>