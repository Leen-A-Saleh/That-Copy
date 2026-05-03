<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/helpers.php';
require_once __DIR__ . '/../../Database/auth.php';

start_secure_session();

if (is_authenticated()) {
    redirect(role_home_path((string) ($_SESSION['auth']['role'] ?? 'CLIENT')));
}

$errors = [];
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = process_login_request($_POST);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - ذات للاستشارات النفسية</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="./img/Silver.png" href="../img/Silver.png">
    
   
    <link rel="stylesheet" href="style.css">
 <link rel="stylesheet" href="../../total.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">

    
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>

<header class="main-header">
  
    <div class="header-right">
        <div class="brand">
                 <a href="../../homepage/index.php">
  <img src="../img/Frame 392 (1).png" alt="شعار ذات" class="brand-icon">
</a>
         
           
        </div>
    </div>

    
    <div class="header-left">
        <a href="../loginpage/login.php" class="nav-link active-login">تسجيل الدخول</a>
        <a href="../signuppage/signup.php" class="nav-link active-login btn btn-primary">إنشاء حساب</a>
    </div>
</header>

<main class="page">
    <section class="login-section">
        <div class="card">
            <?php if ($flash !== null): ?>
                <div style="background:#eaffea;color:#155724;padding:10px;border-radius:10px;margin-bottom:12px;">
                    <?= e((string) $flash['message']) ?>
                </div>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <div style="background:#ffe8e8;color:#8b0000;padding:10px;border-radius:10px;margin-bottom:12px;">
                    <?= e(implode(' ', $errors)) ?>
                </div>
            <?php endif; ?>
            <h1 class="login-title">تسجيل الدخول</h1>
            <p class="login-subtitle">مرحباً بك في منصة ذات للاستشارات النفسية</p>

            <form class="login-form" method="post" action="">
                <?= csrf_input() ?>
             
                <div class="form-group">
                    <label for="username" class="form-label">اسم المستخدم</label>
                    <input id="username" name="username" type="text" class="form-input" placeholder="أدخل اسم المستخدم" required>
                </div>

              
                <div class="form-group">
                    <label for="password" class="form-label">كلمة المرور</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" class="form-input" placeholder="أدخل كلمة المرور" required>
                        <button type="button" class="toggle-password" aria-label="إظهار أو إخفاء كلمة المرور">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                
                <div class="form-footer">
                    <a href="../forgetpage/forgetpassord.php" class="forgot-link">نسيت كلمة المرور؟</a>
                       
                </div>

                
                <button type="submit" class="btn  btn-block login-btn">
                    تسجيل الدخول
                </button>

              <p class="login-hint">
    ليس لديك حساب؟
    <a href="../signuppage/signup.php" class="link">إنشاء حساب جديد</a>
</p>
            </form>
        </div>
    </section>
</main>

<footer class="main-footer">
    © 2026 ذات للاستشارات النفسية جميع الحقوق محفوظة.
</footer>

<script src="main.js"></script>
</body>
</html>