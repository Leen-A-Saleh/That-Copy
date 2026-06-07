<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/helpers.php';
require_once __DIR__ . '/../../Database/client.php';
require_once __DIR__ . '/../../Database/profile-database.php';

start_secure_session();
require_role(['CLIENT']);

$therapists = [];
$bookingError = trim((string) ($_GET['booking_error'] ?? ''));
$showNotificationDot = false;

try {
  $therapists = client_get_therapist_listings();
} catch (Throwable $exception) {
  $therapists = [];
}

try {
  $showNotificationDot = hasUnreadNotifications();
} catch (Throwable) {
  $showNotificationDot = false;
}
?>
<!doctype html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ذات - للإستشارات النفسية</title>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="icon" type="image/png" sizes="32x32" href="../images/Silver.png" />
  <link rel="shortcut icon" sizes="10x10" href="../images/Silver.png" />
  <link rel="apple-touch-icon" href="../images/Silver.png" />
  <link rel="stylesheet" href="./style.css" />
</head>

<body>
  <header class="navbar">
    <div class="menu-btn" id="menuBtn">
      <i class="fa fa-bars"></i>
    </div>
    <div class="nav-title">الرئيسية</div>
    <div class="nav-right">
      <a href="../client-notifications-page/notifications.php" class="bell">
        <i class="fa-regular fa-bell"></i>
        <span class="dot" id="notificationDot"<?= $showNotificationDot ? '' : ' style="display:none;"' ?>></span>
      </a>
    </div>
  </header>

  <?php $clientSidebarActive = 'dashboard';
  include __DIR__ . '/../partials/sidebar.php'; ?>

  <main class="main">
    <section class="hero">
      <h1>مرحباً بك في ذات</h1>
      <p>إبحث عن الأخصائي المناسب لك واحجز موعدك الآن</p>
    </section>

    <div class="search">
      <input type="text" placeholder="إبحث عن أخصائي أو تخصص..." id="searchInput" />
      <i class="fa fa-search"></i>
    </div>

    <div class="count">
      <span id="doctorCount">0</span>
      أخصائي متاح
    </div>
    
    <div class="doctors" id="doctorsContainer"></div>
  </main>

  <footer>© 2026 ذات للإستشارات النفسية جميع الحقوق محفوظة</footer>

  <script>
    const doctorsFromDatabase = <?= json_encode($therapists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const bookingError = <?= json_encode($bookingError, JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script src="./dashboard.js"></script>
</body>

</html>
