<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function login_user(string $identity, string $password): bool
{
    $identity = trim($identity);
    if ($identity === '' || $password === '') {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT user_id, name, username, email, password, role, is_active
         FROM users
         WHERE username = :username OR email = :email
         LIMIT 1'
    );
    $stmt->execute(['username' => $identity, 'email' => $identity]);
    $user = $stmt->fetch();

    if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
        return false;
    }

    if (!password_verify($password, (string) $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['auth'] = [
        'user_id' => (int) $user['user_id'],
        'name' => (string) $user['name'],
        'username' => (string) $user['username'],
        'email' => (string) $user['email'],
        'role' => strtoupper((string) $user['role']),
        'logged_at' => time(),
    ];

    return true;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

/**
 * Call this early in a request to process a logout form submit.
 * Expects POST: action=logout + csrf_token.
 */
function handle_logout_post(?string $redirectTo = null): void
{
    start_secure_session();

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    if (($_POST['action'] ?? null) !== 'logout') {
        return;
    }

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'انتهت صلاحية الطلب. أعد المحاولة.');
        redirect($_SERVER['REQUEST_URI'] ?? '/');
    }

    logout_user();

    redirect($redirectTo ?: '/That-Copy/Auth/loginpage/login.php');
}

function current_user(): ?array
{
    return $_SESSION['auth'] ?? null;
}

function is_authenticated(): bool
{
    return isset($_SESSION['auth']['user_id']);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        redirect('/That-Copy/Auth/loginpage/login.php');
    }
}

function require_role(array $roles): void
{
    require_auth();
    $userRole = strtoupper((string) ($_SESSION['auth']['role'] ?? ''));
    $allowed = array_map(static fn(string $r): string => strtoupper($r), $roles);

    if (!in_array($userRole, $allowed, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function username_exists(string $username): bool
{
    $stmt = db()->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    return (bool) $stmt->fetchColumn();
}

function email_exists(string $email): bool
{
    $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    return (bool) $stmt->fetchColumn();
}

function role_home_path(string $role): string
{
    $role = strtoupper($role);
    if ($role === 'ADMIN') {
        return '/That-Copy/Admin/admin-dashboard-page/admin-dashboard.php';
    }
    if ($role === 'THERAPIST') {
        return '/That-Copy/Therapist/therapist-control-page/therapist-control.php';
    }
    return '/That-Copy/Client/client-dashboard-page/index.php';
}

function process_login_request(array $post): array
{
    $errors = [];

    if (!verify_csrf($post['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الطلب. أعد المحاولة.';
        return $errors;
    }

    $identity = trim((string) ($post['username'] ?? ''));
    $password = (string) ($post['password'] ?? '');
    if (!login_user($identity, $password)) {
        $errors[] = 'بيانات الدخول غير صحيحة.';
        return $errors;
    }

    $role = strtoupper((string) ($_SESSION['auth']['role'] ?? 'CLIENT'));
    redirect(role_home_path($role));
    return [];
}

function process_forgot_password_request(array $post): array
{
    $errors = [];

    if (!verify_csrf($post['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الطلب. حاول مرة أخرى.';
        return $errors;
    }

    $email = trim((string) ($post['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'يرجى إدخال بريد إلكتروني صحيح.';
        return $errors;
    }

    set_flash('success', 'إذا كان البريد مسجلًا، فسيتم إرسال رابط إعادة التعيين.');
    redirect('../loginpage/login.php');
    return [];
}

function process_signup_survey_request(array $post): array
{
    $errors = [];

    if (($post['action'] ?? '') !== 'submit_survey') {
        return $errors;
    }

    if (!verify_csrf($post['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الطلب، يرجى المحاولة مرة أخرى.';
        return $errors;
    }

    $survey = [
        'q1' => trim((string) ($post['treatment_type'] ?? '')),
        'q2' => $post['symptoms'] ?? [],
        'q3' => $post['repeated_symptoms'] ?? [],
        'q4' => trim((string) ($post['prev_therapy'] ?? '')),
        'q5' => trim((string) ($post['age'] ?? '')),
        'q6' => trim((string) ($post['gender'] ?? '')),
        'q7' => trim((string) ($post['nationality'] ?? '')),
        'q8' => trim((string) ($post['therapist_gender'] ?? '')),
        'q9' => trim((string) ($post['family_history'] ?? '')),
        'q10' => trim((string) ($post['physical_issues'] ?? '')),
        'q11' => trim((string) ($post['physical_details'] ?? '')),
        'q12' => trim((string) ($post['marital_status'] ?? '')),
        'q13' => trim((string) ($post['education_level'] ?? '')),
        'q14' => trim((string) ($post['smoking'] ?? '')),
        'q15' => trim((string) ($post['alcohol'] ?? '')),
        'q16' => trim((string) ($post['drugs'] ?? '')),
        'contact_preference' => trim((string) ($post['contact_preference'] ?? '')),
        'treatment_type' => trim((string) ($post['treatment_type'] ?? '')),
        'gender' => trim((string) ($post['gender'] ?? '')),
    ];

    $requiredKeys = ['q1', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10', 'q12', 'q13', 'q14', 'q15', 'q16', 'contact_preference'];
    foreach ($requiredKeys as $key) {
        if ($survey[$key] === '') {
            $errors[] = 'يرجى إكمال جميع الأسئلة المطلوبة قبل المتابعة.';
            break;
        }
    }
    if (!is_array($survey['q2']) || count($survey['q2']) === 0) {
        $errors[] = 'يرجى اختيار الأعراض في السؤال الثاني.';
    }
    if (!is_array($survey['q3']) || count($survey['q3']) === 0) {
        $errors[] = 'يرجى اختيار عرض واحد على الأقل في السؤال الثالث.';
    }
    if ($survey['q5'] !== '' && (!ctype_digit($survey['q5']) || (int) $survey['q5'] < 1)) {
        $errors[] = 'يرجى إدخال عمر صحيح.';
    }
    if ($errors !== []) {
        return $errors;
    }

    $_SESSION['signup']['survey'] = $survey;

    try {
        $stmt = db()->prepare(
            'INSERT INTO client_surveys (
                treatment_type, symptoms, repeated_symptoms, prev_therapy, age, nationality,
                therapist_gender, family_history, physical_issues, physical_details, marital_status,
                education_level, smoking, alcohol, drugs, contact_preference
            ) VALUES (
                :treatment_type, :symptoms, :repeated_symptoms, :prev_therapy, :age, :nationality,
                :therapist_gender, :family_history, :physical_issues, :physical_details, :marital_status,
                :education_level, :smoking, :alcohol, :drugs, :contact_preference
            )'
        );
        $stmt->execute([
            'treatment_type' => $survey['q1'],
            'symptoms' => json_encode($survey['q2'], JSON_UNESCAPED_UNICODE),
            'repeated_symptoms' => json_encode($survey['q3'], JSON_UNESCAPED_UNICODE),
            'prev_therapy' => auth_yes_no($survey['q4']),
            'age' => (int) $survey['q5'],
            'nationality' => $survey['q7'],
            'therapist_gender' => auth_therapist_gender($survey['q8']),
            'family_history' => auth_yes_no($survey['q9']),
            'physical_issues' => auth_yes_no($survey['q10']),
            'physical_details' => $survey['q11'],
            'marital_status' => auth_marital_status($survey['q12']),
            'education_level' => auth_education($survey['q13']),
            'smoking' => auth_yes_no($survey['q14']),
            'alcohol' => auth_yes_no($survey['q15']),
            'drugs' => auth_yes_no($survey['q16']),
            'contact_preference' => auth_contact_preference($survey['contact_preference']),
        ]);

        $_SESSION['signup']['survey_id'] = (int) db()->lastInsertId();
        redirect('../sendpage/send.php');
        return [];
    } catch (Throwable $e) {
        return ['تعذر حفظ الاستبيان. تأكد من وجود جدول client_surveys بشكل صحيح ثم حاول مرة أخرى.'];
    }
}

function can_access_signup_send(): bool
{
    return !empty($_SESSION['signup']['survey']) && !empty($_SESSION['signup']['survey_id']);
}

function process_signup_send_request(array $post): array
{
    $errors = [];
    if (!verify_csrf($post['csrf_token'] ?? null)) {
        return ['انتهت صلاحية الطلب، يرجى المحاولة مرة أخرى.'];
    }

    $fullName = trim((string) ($post['fullName'] ?? ''));
    $email = trim((string) ($post['email'] ?? ''));
    $phone = trim((string) ($post['phone'] ?? ''));
    $sessionType = trim((string) ($post['session_type'] ?? ''));
    $sessionTime = trim((string) ($post['session_time'] ?? ''));
    $username = trim((string) ($post['username'] ?? ''));
    $password = (string) ($post['password'] ?? '');
    $confirmPassword = (string) ($post['confirmPassword'] ?? '');

    if ($fullName === '' || $email === '' || $phone === '') {
        $errors[] = 'يرجى إدخال بياناتك الأساسية كاملة.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'صيغة البريد الإلكتروني غير صحيحة.';
    }
    if ($sessionType === '' || $sessionTime === '') {
        $errors[] = 'يرجى اختيار نوع ووقت الجلسة.';
    }
    if ($username === '') {
        $errors[] = 'اسم المستخدم مطلوب.';
    } elseif (username_exists($username)) {
        $errors[] = 'اسم المستخدم مستخدم مسبقًا.';
    }
    if (email_exists($email)) {
        $errors[] = 'البريد الإلكتروني مستخدم مسبقًا.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'كلمتا المرور غير متطابقتين.';
    }

    $survey = $_SESSION['signup']['survey'] ?? [];
    $gender = auth_gender_from_arabic((string) ($survey['gender'] ?? ''));
    $treatmentType = auth_treatment_type((string) ($survey['treatment_type'] ?? ''));
    if ($gender === null || $treatmentType === null) {
        $errors[] = 'تعذر قراءة بيانات التفضيلات من الاستبيان. يرجى إعادة تعبئة الاستبيان.';
    }
    if ($errors !== []) {
        return $errors;
    }

    $_SESSION['signup']['user'] = [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
    ];
    $_SESSION['signup']['client'] = [
        'session_type' => $sessionType,
        'session_time' => $sessionTime,
        'gender' => $gender,
        'treatment_type' => $treatmentType,
    ];
    $_SESSION['signup']['auth'] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ];

    try {
        db()->beginTransaction();
        $userStmt = db()->prepare(
            "INSERT INTO users (name, email, phone, username, password, role)
             VALUES (?, ?, ?, ?, ?, 'CLIENT')"
        );
        $userStmt->execute([
            $_SESSION['signup']['user']['full_name'],
            $_SESSION['signup']['user']['email'],
            $_SESSION['signup']['user']['phone'],
            $_SESSION['signup']['auth']['username'],
            $_SESSION['signup']['auth']['password'],
        ]);

        $userId = (int) db()->lastInsertId();
        if (auth_clients_has_column('user_id')) {
            $clientSql = 'INSERT INTO clients (user_id, preferred_session_type, preferred_session_time, gender, treatment_type';
            $clientVals = [$userId, $sessionType, $sessionTime, $gender, $treatmentType];
        } else {
            $clientSql = 'INSERT INTO clients (client_id, preferred_session_type, preferred_session_time, gender, treatment_type';
            $clientVals = [$userId, $sessionType, $sessionTime, $gender, $treatmentType];
        }
        if (auth_clients_has_column('survey_id')) {
            $clientSql .= ', survey_id';
            $clientVals[] = (int) $_SESSION['signup']['survey_id'];
        }
        $clientSql .= ') VALUES (' . implode(', ', array_fill(0, count($clientVals), '?')) . ')';
        db()->prepare($clientSql)->execute($clientVals);

        db()->commit();
        unset($_SESSION['signup']);
        set_flash('success', 'تم إنشاء الحساب بنجاح. يمكنك تسجيل الدخول الآن.');
        redirect('../loginpage/login.php');
        return [];
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return ['حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.'];
    }
}

function auth_clients_has_column(string $column): bool
{
    static $columns = null;
    if ($columns === null) {
        $stmt = db()->query('SHOW COLUMNS FROM clients');
        $columns = array_map(static fn(array $row): string => (string) $row['Field'], $stmt->fetchAll());
    }
    return in_array($column, $columns, true);
}

function auth_yes_no(string $value): string
{
    return $value === 'نعم' ? 'YES' : 'NO';
}

function auth_therapist_gender(string $value): string
{
    return match ($value) {
        'ذكر' => 'MALE',
        'أنثى' => 'FEMALE',
        default => 'NO_PREFERENCE',
    };
}

function auth_marital_status(string $value): string
{
    return match ($value) {
        'أعزب/عزباء' => 'SINGLE',
        'متزوج/ـة' => 'MARRIED',
        'أرمل/ـة' => 'WIDOWED',
        'مطلق/ـة' => 'DIVORCED',
        'مرتبط/ـة' => 'IN_RELATIONSHIP',
        'منفصل/ـة' => 'SEPARATED',
        default => 'PREFER_NOT_TO_SAY',
    };
}

function auth_education(string $value): string
{
    return match ($value) {
        'أقل من الثانوية' => 'LESS_THAN_HIGH_SCHOOL',
        'ثانوية عامة' => 'HIGH_SCHOOL',
        'بكالوريوس' => 'BACHELOR',
        'ماستر' => 'MASTER',
        'دكتوراه' => 'PHD',
        default => 'OTHER',
    };
}

function auth_contact_preference(string $value): string
{
    return $value === 'الواتساب' ? 'WHATSAPP' : 'EMAIL';
}

function auth_gender_from_arabic(string $arabic): ?string
{
    return match ($arabic) {
        'ذكر' => 'MALE',
        'أنثى' => 'FEMALE',
        default => null,
    };
}

function auth_treatment_type(string $value): ?string
{
    return match ($value) {
        'علاج فردي للبالغين', 'علاج فردي' => 'INDIVIDUAL_THERAPY',
        'علاج زواجي', 'علاج زوجي' => 'COUPLES_THERAPY',
        'علاج فردي للأطفال', 'العلاج الجماعي', 'إرشاد والدي', 'التقييم النفسي للبالغين', 'التقييم النفسي للأطفال', 'علاج سلوكي للأطفال والمراهقين' => 'CHILD_ADOLESCENT_BEHAVIORAL_THERAPY',
        default => null,
    };
}
