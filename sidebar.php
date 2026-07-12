<?php
// admin/sidebar.php
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div style="padding: 1rem 1.5rem 0.5rem; font-size:0.7rem; color:var(--muted); letter-spacing:2px; font-weight:700;">MENU ADMIN</div>
    <ul class="sidebar-menu">
        <li><a href="index.php"     class="<?= $current==='index.php'     ?'active':'' ?>"><i class="fas fa-chart-bar"></i> Dashboard</a></li>
        <li><a href="films.php"     class="<?= $current==='films.php'     ?'active':'' ?>"><i class="fas fa-film"></i> Kelola Film</a></li>
        <li><a href="schedules.php" class="<?= $current==='schedules.php' ?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Kelola Jadwal</a></li>
        <li><a href="bookings.php"  class="<?= $current==='bookings.php'  ?'active':'' ?>"><i class="fas fa-ticket-alt"></i> Kelola Booking</a></li>
        <li><a href="foods.php"     class="<?= $current==='foods.php'     ?'active':'' ?>"><i class="fas fa-utensils"></i> Kelola Makanan</a></li>
        <li><a href="users.php"     class="<?= $current==='users.php'     ?'active':'' ?>"><i class="fas fa-users"></i> Kelola User</a></li>
        <li><a href="wa_logs.php"   class="<?= $current==='wa_logs.php'   ?'active':'' ?>"><i class="fab fa-whatsapp"></i> Log WhatsApp</a></li>
        <li style="border-top:1px solid var(--border); margin-top:1rem; padding-top:0.5rem;">
            <a href="../index.php"><i class="fas fa-home"></i> Kembali ke Beranda</a>
        </li>
    </ul>
</div>
