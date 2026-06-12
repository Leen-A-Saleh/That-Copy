<?php

declare(strict_types=1);

/**
 * ─────────────────────────────────────────────────────────────────────────────
 *  AI Therapist Recommendation System  —  نظام التوصية بالأخصائي النفسي
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  نظام ذكاء اصطناعي *داخلي* يعتمد على قواعد منطقية (Rule-Based AI) بالكامل،
 *  دون أي API خارجي. يحلّل إجابات المريض في الاستبيان المحفوظة في قاعدة البيانات
 *  (جدول client_surveys المرتبط عبر جدول clients) ثم يحسب نقاطاً لكل أخصائي
 *  ويرتّبهم ويُرجع أفضل 3 توصيات مع سبب الاقتراح.
 *
 *  المنطق موزّع على دوال صغيرة واضحة:
 *    1. recommend_get_user_survey()      → جلب إجابات الاستبيان من قاعدة البيانات
 *    2. recommend_load_therapists()      → جلب بيانات الأخصائيين من قاعدة البيانات
 *    3. recommend_resolve_roles()        → ربط كل أخصائي بدوره العلاجي عبر الاسم
 *    4. recommend_calculate_scores()     → نظام النقاط (قلب الذكاء الاصطناعي)
 *    5. recommend_rank_and_select()      → الترتيب واختيار أفضل النتائج
 *    6. recommend_for_user()             → الدالة الرئيسية التي تجمع كل ما سبق
 *
 *  ملاحظة مهمة: لا نعتمد على معرّفات (IDs) ثابتة للأخصائيين لأنها تختلف بين نسخ
 *  قاعدة البيانات؛ بدلاً من ذلك نربط كل أخصائي "بدوره العلاجي" عبر اسمه الأول
 *  (مها، زينب، عمر، تسنيم، هديل، نيروز) وهي أسماء مميّزة وثابتة حسب المواصفات.
 *  ولا يُجري هذا الملف أي تعديل على بنية قاعدة البيانات، ويستخدم نفس دالة الاتصال db().
 */

require_once __DIR__ . '/../../Database/db.php';
require_once __DIR__ . '/../../Database/client.php';

/**
 * الأدوار العلاجية (Roles) المعتمدة في منطق التوصية.
 * كل دور يربط أخصائياً بكلمة مفتاحية في اسمه + تخصّصه المنطقي.
 */
const RECO_ROLE_MAHA    = 'MAHA';    // مها الرفاعي  — نفسية اجتماعية، أسري، مشاكل العلاقات
const RECO_ROLE_ZAINAB  = 'ZAINAB';  // زينب كرمي    — أطفال، إرشاد والدي، صدمات، CBT
const RECO_ROLE_OMAR    = 'OMAR';    // عمر قدح      — حالات معقدة، اضطرابات متعددة، تقييمات
const RECO_ROLE_TASNEEM = 'TASNEEM'; // تسنيم زيدان  — مراهقون/بالغون، CBT، صدمات، حالات متقدمة
const RECO_ROLE_HADEEL  = 'HADEEL';  // هديل أبو رميلة — إرشاد فردي/زواجي، حالات خفيفة/متوسطة
const RECO_ROLE_NEROZ   = 'NEROZ';   // نيروز نجم الدين — سمع ونطق، لغة وتأتأة للأطفال

/**
 * الكلمة المفتاحية (الاسم الأول) التي نتعرّف بها على كل دور داخل اسم المستخدم.
 *
 * @return array<string,string>  role => keyword
 */
function recommend_role_keywords(): array
{
    return [
        RECO_ROLE_MAHA    => 'مها',
        RECO_ROLE_ZAINAB  => 'زينب',
        RECO_ROLE_OMAR    => 'عمر',
        RECO_ROLE_TASNEEM => 'تسنيم',
        RECO_ROLE_HADEEL  => 'هديل',
        RECO_ROLE_NEROZ   => 'نيروز',
    ];
}

/**
 * جلب إجابات الاستبيان الخاصة بالمستخدم من قاعدة البيانات.
 *
 * الربط: users.user_id = clients.client_id ، و clients.survey_id = client_surveys.id
 *
 * @return array<string,mixed>|null  صف الاستبيان أو null إن لم يوجد
 */
function recommend_get_user_survey(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*
         FROM clients c
         INNER JOIN client_surveys s ON s.id = c.survey_id
         WHERE c.client_id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);

    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/**
 * جلب بيانات الأخصائيين من قاعدة البيانات للعرض والترتيب.
 * نُعيد لكل أخصائي بياناته الكاملة + سنوات الخبرة (مطلوبة لكسر التعادل).
 *
 * @return array<int,array<string,mixed>>  مفهرسة بحسب therapist_id (=user_id)
 */
function recommend_load_therapists(): array
{
    $stmt = db()->prepare(
        'SELECT
            u.user_id,
            u.name,
            u.email,
            u.avatar,
            t.specialization,
            t.bio,
            t.certification,
            t.experience_years
         FROM users u
         INNER JOIN therapists t ON t.therapist_id = u.user_id
         WHERE u.role = :role AND u.is_active = 1'
    );
    $stmt->execute(['role' => 'THERAPIST']);

    $therapists = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) ($row['user_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $therapists[$id] = $row;
    }

    return $therapists;
}

/**
 * ربط كل دور علاجي بمعرّف الأخصائي الفعلي في قاعدة البيانات (عبر مطابقة الاسم).
 * هذا يجعل النظام صامداً أمام اختلاف الـ IDs بين نسخ قاعدة البيانات.
 *
 * @param array<int,array<string,mixed>> $therapists
 * @return array<string,int>  role => therapist_id
 */
function recommend_resolve_roles(array $therapists): array
{
    $map = [];

    foreach (recommend_role_keywords() as $role => $keyword) {
        foreach ($therapists as $id => $row) {
            $name = (string) ($row['name'] ?? '');
            if (mb_strpos($name, $keyword) !== false) {
                $map[$role] = (int) $id;
                break;
            }
        }
    }

    return $map;
}

/**
 * تقسيم نص الأعراض المخزّن (مفصول بفاصلة عربية «،» أو عادية) إلى مصفوفة نظيفة.
 *
 * @return list<string>
 */
function recommend_split_symptoms(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }

    // الواجهة تخزّن الأعراض مفصولة بفاصلة عربية «،»؛ ندعم العادية احتياطاً.
    $parts = preg_split('/[،,]+/u', $value) ?: [];

    $clean = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $clean[] = $part;
        }
    }

    return $clean;
}

/**
 * هل يحتوي نص ما على إحدى الكلمات المفتاحية (مطابقة جزئية)؟
 *
 * @param list<string> $needles
 */
function recommend_text_has(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * مطابقة أحد العناصر داخل قائمة الأعراض المختارة (مطابقة دقيقة أو جزئية).
 *
 * @param list<string> $haystack  الأعراض المختارة فعلاً
 * @param list<string> $needles   القيم المقبولة (مرادفات)
 */
function recommend_array_has(array $haystack, array $needles): bool
{
    foreach ($haystack as $item) {
        foreach ($needles as $needle) {
            if ($needle !== '' && ($item === $needle || mb_strpos($item, $needle) !== false)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * ─── قلب الذكاء الاصطناعي: نظام النقاط (Scoring System) ───
 *
 * كل أخصائي (دور) يبدأ من 0 نقطة، ثم نطبّق قواعد منطقية متدرّجة بحسب إجابات
 * الاستبيان. نُرجع لكل دور: مجموع نقاطه + قائمة الأسباب التي رفعت نقاطه (لعرضها).
 *
 * @param array<string,mixed> $survey
 * @return array<string,array{score:int,reasons:list<string>}>  مفهرسة بحسب الدور
 */
function recommend_calculate_scores(array $survey): array
{
    // تهيئة سجلّ النقاط لكل دور علاجي
    $scores = [];
    foreach (array_keys(recommend_role_keywords()) as $role) {
        $scores[$role] = ['score' => 0, 'reasons' => []];
    }

    // دالة داخلية مختصرة لإضافة نقاط + سبب
    $add = static function (string $role, int $points, string $reason) use (&$scores): void {
        if (!isset($scores[$role])) {
            return;
        }
        $scores[$role]['score'] += $points;
        $scores[$role]['reasons'][] = $reason;
    };

    // استخراج الحقول المطلوبة من الاستبيان
    $treatmentType   = trim((string) ($survey['treatment_type'] ?? ''));
    $symptomsRaw     = (string) ($survey['symptoms'] ?? '');
    $repeatedRaw     = (string) ($survey['repeated_symptoms'] ?? '');
    $physicalDetails = (string) ($survey['physical_details'] ?? '');

    $symptoms      = recommend_split_symptoms($symptomsRaw);
    $repeated      = recommend_split_symptoms($repeatedRaw);
    $symptomsCount = count($symptoms);

    // نصّ مجمّع نبحث فيه عن كلمات النطق/اللغة
    $allText = $treatmentType . ' ' . $symptomsRaw . ' ' . $repeatedRaw . ' ' . $physicalDetails;

    // ───────────────────────────── 1) نوع العلاج ─────────────────────────────

    // إرشاد والدي → زينب +5
    if (recommend_text_has($treatmentType, ['إرشاد والدي', 'ارشاد والدي'])) {
        $add(RECO_ROLE_ZAINAB, 5, 'مختصة بالإرشاد الوالدي الذي طلبته في الاستبيان.');
    }

    // علاج أطفال أو مشاكل نطق → نيروز أو زينب حسب الحالة
    $wantsChild  = recommend_text_has($treatmentType, ['أطفال', 'اطفال', 'طفل']);
    $wantsSpeech = recommend_text_has($allText, ['نطق', 'تأتأة', 'تاتاة', 'تلعثم', 'لغة', 'لفظ', 'كلام']);

    if ($wantsSpeech) {
        // مشاكل نطق/لغة → نيروز هي الأنسب (مختصة سمع ونطق)
        $add(RECO_ROLE_NEROZ, 5, 'مختصة في السمع والنطق وعلاج صعوبات اللغة والتأتأة.');
    }
    if ($wantsChild) {
        // علاج أطفال عام → زينب (تتعامل مع الأطفال)، ونيروز إن كان هناك بُعد نطقي
        $add(RECO_ROLE_ZAINAB, 5, 'لديها خبرة في التعامل مع الأطفال والإرشاد الوالدي.');
        if (!$wantsSpeech) {
            $add(RECO_ROLE_NEROZ, 3, 'متخصصة في تطوير اللغة والنطق لدى الأطفال.');
        }
    }

    // ──────────────────── 2) السؤال الثاني: عدد الأعراض ──────────────────────

    if ($symptomsCount >= 6) {
        // عدد كبير من الأعراض → حالة معقّدة/متعددة الاضطرابات
        $add(RECO_ROLE_OMAR, 5, 'متخصص بالحالات المعقدة والاضطرابات المتعددة (اخترت ' . $symptomsCount . ' أعراض).');
        $add(RECO_ROLE_TASNEEM, 5, 'مناسبة للحالات المتوسطة والمتقدمة (اخترت ' . $symptomsCount . ' أعراض).');
    } elseif ($symptomsCount > 0) {
        // عدد أقل من 6 → حالة خفيفة/متوسطة
        $add(RECO_ROLE_HADEEL, 4, 'مناسبة للحالات الخفيفة والمتوسطة المطابقة لأعراضك.');
        $add(RECO_ROLE_ZAINAB, 4, 'مناسبة للحالات الخفيفة والمتوسطة مع خبرة في العلاج السلوكي المعرفي.');
    }

    // أعراض اجتماعية/عائلية → مها (مختصة نفسية اجتماعية وعلاقات أسرية)
    if (recommend_array_has($symptoms, ['صعوبة تكوين علاقات اجتماعية'])
        || recommend_array_has($symptoms, ['مشاكل عائلية مستمرة', 'مشاكل مع شخص من أفراد العائلة'])
    ) {
        $add(RECO_ROLE_MAHA, 5, 'مختصة نفسية اجتماعية ومناسبة للمشاكل الاجتماعية والعلاقات العائلية.');
    }

    // وساوس/نوبات هلع → عمر + تسنيم
    if (recommend_array_has($symptoms, ['وساوس مزعجة']) || recommend_array_has($symptoms, ['نوبات هلع'])) {
        $add(RECO_ROLE_OMAR, 4, 'متخصص في تقييم وعلاج الوساوس ونوبات الهلع.');
        $add(RECO_ROLE_TASNEEM, 4, 'تقدّم العلاج السلوكي المعرفي المناسب للوساوس ونوبات الهلع.');
    }

    // ──────────────────── 3) السؤال الثالث: سلوك إدماني ──────────────────────

    if (recommend_array_has($repeated, ['سلوك إدماني'])
        || recommend_text_has($repeatedRaw, ['إدمان', 'ادمان', 'مخدرات', 'كحول'])
    ) {
        $add(RECO_ROLE_OMAR, 5, 'متخصص بالحالات المعقدة ومنها السلوك الإدماني.');
        $add(RECO_ROLE_TASNEEM, 5, 'لديها خبرة في الحالات المتقدمة ومنها السلوك الإدماني.');
    }

    return $scores;
}

/**
 * ─── منطق اختيار الأخصائي ───
 *
 *   1. رتّب الأخصائيين من الأعلى نقاطاً إلى الأقل.
 *   2. عند التعادل، فضّل الأعلى خبرة (experience_years).
 *   3. استبعد أي أخصائي نقاطه 0.
 *   4. أعِد أفضل 3 فقط، مدموجة مع بيانات العرض من قاعدة البيانات.
 *
 * @param array<string,array{score:int,reasons:list<string>}> $scores       role => data
 * @param array<int,array<string,mixed>>                       $therapists   id => row
 * @param array<string,int>                                    $roleToId     role => id
 * @return list<array<string,mixed>>
 */
function recommend_rank_and_select(array $scores, array $therapists, array $roleToId, int $limit = 3): array
{
    $ranked = [];

    foreach ($scores as $role => $data) {
        // 3) استبعاد من حصل على 0 نقطة
        if ($data['score'] <= 0) {
            continue;
        }
        // تجاهل أي دور لم نعثر له على أخصائي فعلي في قاعدة البيانات
        if (!isset($roleToId[$role])) {
            continue;
        }

        $therapistId = $roleToId[$role];
        if (!isset($therapists[$therapistId])) {
            continue;
        }

        $therapist       = $therapists[$therapistId];
        $experienceYears = (int) ($therapist['experience_years'] ?? 0);

        $ranked[] = [
            'id'               => $therapistId,
            'role'             => $role,
            'score'            => (int) $data['score'],
            'experience_years' => $experienceYears,
            'reasons'          => $data['reasons'],
            'name'             => trim((string) ($therapist['name'] ?? '')),
            'specialization'   => trim((string) ($therapist['specialization'] ?? '')),
            'certification'    => trim((string) ($therapist['certification'] ?? '')),
            'image'            => client_normalize_therapist_avatar((string) ($therapist['avatar'] ?? '')),
            'email'            => trim((string) ($therapist['email'] ?? '')),
            'experience_text'  => client_build_experience_text($experienceYears, (string) ($therapist['bio'] ?? '')),
            // لا يوجد عمود سعر في قاعدة البيانات؛ نستخدم نفس الأسعار المعتمدة في لوحة العميل.
            'consult_price'    => '150 شيكل',
            'therapy_price'    => '120 شيكل',
        ];
    }

    // 1) + 2) الترتيب: النقاط تنازلياً، ثم الخبرة تنازلياً عند التعادل
    usort($ranked, static function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        return $b['experience_years'] <=> $a['experience_years'];
    });

    // 4) أفضل N فقط
    return array_slice($ranked, 0, $limit);
}

/**
 * الدالة الرئيسية: تُرجع أفضل الأخصائيين المقترحين لمستخدم معيّن.
 *
 * @return array{minor:bool,has_survey:bool,age:int,recommendations:list<array<string,mixed>>}
 */
function recommend_for_user(int $userId, int $limit = 3): array
{
    $survey = recommend_get_user_survey($userId);

    if ($survey === null) {
        return ['minor' => false, 'has_survey' => false, 'age' => 0, 'recommendations' => []];
    }

    $age = (int) ($survey['age'] ?? 0);

    // قاعدة العمر: من هم أقل من 18 لا يُفترض وصولهم لهذه المرحلة (يُمنع التسجيل)،
    // لكن نُبقي حارساً احتياطياً هنا أيضاً.
    $isMinor = ($age > 0 && $age < 18);

    $scores      = recommend_calculate_scores($survey);
    $therapists  = recommend_load_therapists();
    $roleToId    = recommend_resolve_roles($therapists);
    $recommendations = recommend_rank_and_select($scores, $therapists, $roleToId, $limit);

    return [
        'minor'           => $isMinor,
        'has_survey'      => true,
        'age'             => $age,
        'recommendations' => $recommendations,
    ];
}
