<!doctype html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>المواعيد</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/png" href="../images/Silver.png" />
  <link rel="stylesheet" href="../client-dashboard-page/style.css" />
  <link rel="stylesheet" href="./appointments.css" />
</head>

<body>
  <header class="navbar">
    <div class="menu-btn" id="menuBtn"><i class="fa fa-bars"></i></div>
    <div class="nav-title">المواعيد</div>
    <div class="nav-right">
      <a href="../client-notifications-page/notifications.php" class="bell">
        <i class="fa-regular fa-bell"></i>
        <span class="dot" id="notificationDot"></span>
      </a>
    </div>
  </header>

  <?php $clientSidebarActive = 'appointments';
  include __DIR__ . '/../partials/sidebar.php'; ?>

  <main class="main">
    <section class="stats-row">
      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">إجمالي الحالات</div>
          <div class="stat-value" id="totalCases">0</div>
        </div>
        <div class="stat-icon">
          <img src="../images/Container.png" alt="إجمالي الحالات" />
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">الحالات النشطة</div>
          <div class="stat-value" id="activeCases">0</div>
        </div>
        <div class="stat-icon">
          <img src="../images/Container (1).png" alt=" لحالات النشطة" />
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">مواعيد اليوم</div>
          <div class="stat-value" id="todayAppointments">0</div>
        </div>
        <div class="stat-icon">
          <img src="../images/Container (2).png" alt="مواعيد اليوم" />
        </div>
      </div>
    </section>

    <section class="appointments-section">
      <h2>تقويم مواعيدي</h2>
      <div class="calendar-header">
        <button id="prevMonth">&lt;</button>
        <h3 id="monthYear"></h3>
        <button id="nextMonth">&gt;</button>
      </div>
      <div class="weekdays">
        <div>أحد</div>
        <div>اثنين</div>
        <div>ثلاثاء</div>
        <div>أربعاء</div>
        <div>خميس</div>
        <div>جمعة</div>
        <div>سبت</div>
      </div>
      <div id="calendar"></div>
    </section>

    <div id="bookingDetails" class="booking-details hidden">
      <h3>تفاصيل الحجز</h3>
      <div id="detailsContent"></div>
      <button id="closeDetails">إغلاق</button>
    </div>

    <section class="appointments-list">
      <h2>جميع المواعيد</h2>
      <div id="appointmentsContainer"></div>
    </section>
  </main>

  <div class="sidebar-overlay"></div>

  <footer>© 2026 ذات للإستشارات النفسية جميع الحقوق محفوظة</footer>
  <script src="./appointments.js"></script>
</body>

</html>