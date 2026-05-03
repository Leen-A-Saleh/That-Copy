<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/helpers.php';
require_once __DIR__ . '/../../Database/client.php';

start_secure_session();
require_role(['CLIENT']);

$therapistId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$doctorFromDatabase = null;
$bookingErrorMessage = '';
$showNotificationDot = false;

if (!is_int($therapistId) || $therapistId <= 0) {
  $redirectUrl = '../client-dashboard-page/index.php?booking_error=missing_therapist_id';
  header('Location: ' . $redirectUrl);
  exit;
}

try {
  $doctorFromDatabase = client_get_therapist_by_id($therapistId);
  $showNotificationDot = client_has_unread_notifications_for_current_user();
} catch (Throwable $exception) {
  $doctorFromDatabase = null;
  $showNotificationDot = false;
}

if (!is_array($doctorFromDatabase)) {
  $bookingErrorMessage = 'تعذر العثور على بيانات الأخصائي المطلوب.';
  $doctorFromDatabase = null;
}
?>
<!doctype html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>حجز جلسة</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="../images/Silver.png" />
  <link rel="shortcut icon" href="../images/Silver.png" />
  <link rel="apple-touch-icon" href="../images/Silver.png" />
  <link rel="stylesheet" href="./booking.css" />
  <link rel="stylesheet" href="../client-dashboard-page/style.css" />
</head>

<body>
  <header class="navbar">
    <div class="menu-btn" id="menuBtn">
      <i class="fa fa-bars"></i>
    </div>
    <div class="nav-title">حجز جلسة</div>
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
    <section class="booking-section">
      <div class="doctor-card" id="doctorCard">
        <img src="" alt="Doctor" id="doctorImg" />
        <div class="doctor-overlay">
          <h2 id="doctorName"></h2>
          <div class="info-row">
            <i class="fa fa-graduation-cap"></i><span id="doctorSpecial"></span>
          </div>
          <div class="info-row">
            <i class="fa fa-certificate"></i><span id="doctorDegree"></span>
          </div>
          <div class="info-row">
            <i class="fa fa-briefcase"></i><span id="doctorExperience"></span>
          </div>
          <div class="info-row">
            <i class="fa fa-clock"></i><span id="doctorWork"></span>
          </div>
          <div class="info-row">
            <i class="fa fa-envelope"></i><a href="" id="doctorEmail"></a>
          </div>
          <div class="price-row">
            <div class="price-tag">
              استشارية: <span id="doctorConsult"></span>
            </div>
            <div class="price-tag">
              علاجية: <span id="doctorTherapy"></span>
            </div>
          </div>
        </div>
      </div>

      <div class="booking-form">
        <label for="sessionType">نوع الجلسة:</label>
        <select id="sessionType">
          <option value="consult">جلسة استشارية</option>
          <option value="therapy">جلسة علاجية</option>
        </select>

        <label for="meetingType">طريقة الجلسة:</label>
        <select id="meetingType">
          <option value="online">جلسة إلكترونية</option>
          <option value="offline">جلسة وجاهي</option>
        </select>

        <label for="sessionDate">إختر التاريخ:</label>
        <input type="date" id="sessionDate" />

        <label for="sessionTime">إختر الوقت:</label>
        <input type="time" id="sessionTime" />

        <button id="confirmBooking">تأكيد الحجز</button>

        <p class="success-msg" id="successMsg"></p>
      </div>
    </section>
  </main>

  <footer>© 2026 ذات للإستشارات النفسية جميع الحقوق محفوظة</footer>
  <script>
    const doctorFromDatabase = <?= json_encode($doctorFromDatabase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const bookingErrorMessage = <?= json_encode($bookingErrorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
  <script src="./booking.js"></script>
</body>

</html>