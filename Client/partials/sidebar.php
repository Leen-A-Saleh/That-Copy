<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/helpers.php';
require_once __DIR__ . '/../../Database/client.php';
require_once __DIR__ . '/../../Database/profile-database.php';

handle_logout_post('/That-Copy/Public/login/index.php');

$clientSidebarActive = $clientSidebarActive ?? '';

$clientMessageNotificationsEnabled = true;
try {
    $clientMessageNotificationsEnabled = client_get_notification_preferences_for_current_user()['message_notifications'];
} catch (Throwable) {
    $clientMessageNotificationsEnabled = true;
}

$clientChatUnreadConversations = 0;
try {
    $clientChatUnreadConversations = $clientMessageNotificationsEnabled
        ? client_unread_chat_conversations_count()
        : 0;
} catch (Throwable) {
    $clientChatUnreadConversations = 0;
}

function client_sidebar_li_class(string $key, string $active): string
{
    return $key === $active ? ' class="active"' : '';
}
?>

<style>
  .sidebar {
    position: fixed;
    top: 0;
    height: 100vh;
    height: 100dvh;
    max-height: 100vh;
    max-height: 100dvh;
    z-index: 1500;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-sizing: border-box;
  }

  .sidebar .sidebar-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
  }

  .sidebar .sidebar-scroll ul {
    overflow: visible;
  }

  .sidebar .logout-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    border-radius: 8px;
    background: transparent;
    border: none;
    border-right: 4px solid transparent;
    cursor: pointer;
    transition: 0.2s;
    color: #e74c3c;
    font: inherit;
    text-align: start;
  }

  .sidebar .logout-btn:hover {
    background: rgba(231, 76, 60, 0.12);
    border-right-color: #e74c3c;
    color: #e74c3c;
  }

  .sidebar .logout-btn i {
    width: 20px;
    min-width: 20px;
    text-align: center;
    font-size: 18px;
  }

  .sidebar .sidebar-chat-link {
    position: relative;
  }

  .sidebar .sidebar-chat-badge.unread-badge {
    margin-inline-start: auto;
    flex-shrink: 0;
    background: #30b7c4;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
</style>

<aside class="sidebar">
  <div class="sidebar-scroll">
    <div class="logo">
      <img src="../images/Frame 393.svg" alt="logo" />
    </div>

    <div class="user-card">
      <div class="user-info">
        <div class="user-greeting">مرحباً،</div>
        <div class="user-name"><?= e(client_display_name()) ?></div>
        <div class="user-role">مريض</div>
      </div>
    </div>

    <ul>
      <li<?= client_sidebar_li_class('dashboard', (string) $clientSidebarActive) ?>>
        <a href="../client-dashboard-page/index.php">
          <img src="../images/Icon.svg" alt="" />
          الرئيسية
        </a>
      </li>

      <li<?= client_sidebar_li_class('appointments', (string) $clientSidebarActive) ?>>
        <a href="../client-appointments-page/appointments.php">
          <img src="../images/Icon1.svg" alt="" />
          المواعيد
        </a>
      </li>

      <li<?= client_sidebar_li_class('chat', (string) $clientSidebarActive) ?>>
        <a href="../client-chat-page/chat.php" class="sidebar-chat-link">
          <img src="../images/Icon2.svg" alt="" />
          الرسائل
          <span
            id="clientChatSidebarBadge"
            class="unread-badge sidebar-chat-badge"
            <?= $clientChatUnreadConversations > 0 ? '' : ' style="display:none;"' ?>
          ><?= $clientChatUnreadConversations > 0 ? (int) $clientChatUnreadConversations : '' ?></span>
        </a>
      </li>

      <li<?= client_sidebar_li_class('tests', (string) $clientSidebarActive) ?>>
        <a href="../client-tests-page/tests.php">
          <img src="../images/Icon3.svg" alt="" />
          الإختبارات
        </a>
      </li>

      <li<?= client_sidebar_li_class('activities', (string) $clientSidebarActive) ?>>
        <a href="../client-games-page/index.php">
          <img src="../images/Icon4.svg" alt="" />
          الأنشطة
        </a>
      </li>

      <li<?= client_sidebar_li_class('notifications', (string) $clientSidebarActive) ?>>
        <a href="../client-notifications-page/notifications.php">
          <img src="../images/Icon6.svg" alt="" />
          الإشعارات
        </a>
      </li>

      <li<?= client_sidebar_li_class('profile', (string) $clientSidebarActive) ?>>
        <a href="../client-profile-page/profile.php">
          <img src="../images/Icon7.svg" alt="" />
          الملف الشخصي
        </a>
      </li>

      <li class="logout-item">
        <form method="post" action="/That-Copy/Public/handlers/logout.php">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="logout" />
          <button class="logout-btn" id="logoutBtn" type="submit" aria-label="تسجيل الخروج">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>تسجيل الخروج</span>
          </button>
        </form>
      </li>
    </ul>
  </div>
</aside>
<script>
  window.clientMessageNotificationsEnabled = <?= $clientMessageNotificationsEnabled ? 'true' : 'false' ?>;

  window.refreshClientChatSidebarBadge = function () {
    const badge = document.getElementById("clientChatSidebarBadge");
    if (!badge) return Promise.resolve();

    if (!window.clientMessageNotificationsEnabled) {
      badge.textContent = "";
      badge.style.display = "none";
      return Promise.resolve();
    }

    return fetch("/That-Copy/Client/client-chat-page/chat-database.php?action=badge_state", {
      method: "GET",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      cache: "no-store",
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("chat_badge_state_failed");
        }
        return response.json();
      })
      .then(function (payload) {
        const count = Number(
          payload && payload.success ? payload.unread_conversations : 0
        );
        if (count > 0) {
          badge.textContent = String(count);
          badge.style.display = "";
        } else {
          badge.textContent = "";
          badge.style.display = "none";
        }
      })
      .catch(function () {
      });
  };

  refreshClientChatSidebarBadge();

  (() => {
    const dot = document.getElementById("notificationDot");
    if (!dot) return;

    fetch("/That-Copy/Client/client-notifications-page/notifications.php?action=badge_state", {
      method: "GET",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
      cache: "no-store",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("badge_state_failed");
        }
        return response.json();
      })
      .then((payload) => {
        const hasUnread = Boolean(payload && payload.success && payload.has_unread);
        dot.style.display = hasUnread ? "block" : "none";
      })
      .catch(() => {
      });
  })();
</script>

<!-- نظام التنبيهات الموحّد SweetAlert2 (متاح لكل صفحات العميل) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/That-Copy/Public/js/sweet-alerts.js"></script>
