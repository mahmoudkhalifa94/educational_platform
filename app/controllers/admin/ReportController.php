<?php
/**
 * app/controllers/admin/ReportController.php
 * متحكم التقارير للمدير الرئيسي
 * يدير عمليات عرض وتوليد التقارير المختلفة للنظام
 */
class ReportController extends Controller
{
    private $schoolModel;
    private $userModel;
    private $studentModel;
    private $teacherModel;
    private $classModel;
    private $subjectModel;
    private $assignmentModel;
    private $subscriptionModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->schoolModel = new School();
        $this->userModel = new User();
        $this->studentModel = new Student();
        $this->teacherModel = new Teacher();
        $this->classModel = new ClassModel();
        $this->subjectModel = new Subject();
        $this->assignmentModel = new Assignment();
        $this->subscriptionModel = new Subscription();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('super_admin');
    }
    
    /**
     * عرض صفحة التقارير الرئيسية
     */
    public function index()
    {
        echo $this->render('admin/reports/index', [
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير المدارس
     */
    public function schools()
    {
        // استخراج معلمات التصفية
        $timeRange = $this->request->get('time_range', 'all');
        $status = $this->request->get('status', 'all');
        $subscription = $this->request->get('subscription', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT * FROM schools WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب نوع الاشتراك
        if ($subscription !== 'all') {
            $query .= " AND subscription_type = ?";
            $params[] = $subscription;
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY created_at DESC";
        
        // تنفيذ الاستعلام
        $schools = $this->schoolModel->raw($query, $params);
        
        // الحصول على إحصائيات المدارس
        $stats = $this->getSchoolsStats($schools);
        
        // الحصول على بيانات رسم توزيع الاشتراكات
        $subscriptionData = $this->getSubscriptionDistribution();
        
        // الحصول على بيانات رسم المدارس الجديدة
        $newSchoolsData = $this->getNewSchoolsChart();
        
        echo $this->render('admin/reports/schools', [
            'schools' => $schools,
            'stats' => $stats,
            'subscriptionData' => $subscriptionData,
            'newSchoolsData' => $newSchoolsData,
            'timeRange' => $timeRange,
            'status' => $status,
            'subscription' => $subscription,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير المستخدمين
     */
    public function users()
    {
        // استخراج معلمات التصفية
        $role = $this->request->get('role', 'all');
        $status = $this->request->get('status', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT u.*, r.name as role_name 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب الدور
        if ($role !== 'all') {
            $query .= " AND r.name = ?";
            $params[] = $role;
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND u.active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY u.created_at DESC";
        
        // تنفيذ الاستعلام
        $users = $this->userModel->raw($query, $params);
        
        // الحصول على إحصائيات المستخدمين
        $stats = $this->getUsersStats();
        
        // الحصول على بيانات رسم توزيع الأدوار
        $rolesChart = $this->getRolesDistribution();
        
        // الحصول على بيانات رسم المستخدمين الجدد
        $newUsersChart = $this->getNewUsersChart();
        
        echo $this->render('admin/reports/users', [
            'users' => $users,
            'stats' => $stats,
            'rolesChart' => $rolesChart,
            'newUsersChart' => $newUsersChart,
            'role' => $role,
            'status' => $status,
            'timeRange' => $timeRange,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الاشتراكات
     */
    public function subscriptions()
    {
        // استخراج معلمات التصفية
        $status = $this->request->get('status', 'all');
        $type = $this->request->get('type', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT s.*, sc.name as school_name, sc.subdomain 
                 FROM subscriptions s 
                 JOIN schools sc ON s.school_id = sc.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND s.status = ?";
            $params[] = $status;
        }
        
        // إضافة التصفية حسب نوع الاشتراك
        if ($type !== 'all') {
            $query .= " AND s.plan_type = ?";
            $params[] = $type;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY s.start_date DESC";
        
        // تنفيذ الاستعلام
        $subscriptions = $this->db->fetchAll($query, $params);
        
        // الحصول على إحصائيات الاشتراكات
        $stats = $this->getSubscriptionsStats();
        
        echo $this->render('admin/reports/subscriptions', [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
            'status' => $status,
            'type' => $type,
            'timeRange' => $timeRange,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الأنشطة
     */
    public function activities()
    {
        // استخراج معلمات التصفية
        $entityType = $this->request->get('entity_type', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT sl.*, u.first_name, u.last_name, u.email 
                 FROM system_logs sl 
                 LEFT JOIN users u ON sl.user_id = u.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب نوع الكيان
        if ($entityType !== 'all') {
            $query .= " AND sl.entity_type = ?";
            $params[] = $entityType;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_day':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'last_week':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
                    break;
                case 'last_month':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY sl.created_at DESC";
        
        // التصفح والترقيم
        $page = $this->request->get('page', 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        // تنفيذ استعلام العد
        $countQuery = str_replace("SELECT sl.*, u.first_name, u.last_name, u.email", "SELECT COUNT(*) as count", $query);
        $countResult = $this->db->fetchOne($countQuery, $params);
        $total = $countResult['count'] ?? 0;
        
        // إضافة حدود الصفحة
        $query .= " LIMIT {$offset}, {$perPage}";
        
        // تنفيذ الاستعلام
        $logs = $this->db->fetchAll($query, $params);
        
        // حساب معلومات الترقيم
        $totalPages = ceil($total / $perPage);
        
        echo $this->render('admin/reports/activities', [
            'logs' => $logs,
            'entityType' => $entityType,
            'timeRange' => $timeRange,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الأداء العام
     */
    public function performance()
    {
        // استخراج معلمات التصفية
        $timeRange = $this->request->get('time_range', 'last_month');
        
        // جمع البيانات الإحصائية
        $schoolsStats = $this->getSchoolsStats();
        $usersStats = $this->getUsersStats();
        $studentsStats = $this->getStudentsStats();
        $teachersStats = $this->getTeachersStats();
        $assignmentsStats = $this->getAssignmentsStats();
        
        // الحصول على بيانات الرسوم البيانية
        $activeSchoolsChart = $this->getActiveSchoolsChart($timeRange);
        $userActivityChart = $this->getUserActivityChart($timeRange);
        $assignmentsCompletionChart = $this->getAssignmentsCompletionChart($timeRange);
        
        echo $this->render('admin/reports/performance', [
            'schoolsStats' => $schoolsStats,
            'usersStats' => $usersStats,
            'studentsStats' => $studentsStats,
            'teachersStats' => $teachersStats,
            'assignmentsStats' => $assignmentsStats,
            'activeSchoolsChart' => $activeSchoolsChart,
            'userActivityChart' => $userActivityChart,
            'assignmentsCompletionChart' => $assignmentsCompletionChart,
            'timeRange' => $timeRange,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تصدير تقرير المدارس بتنسيق CSV
     */
    public function exportSchoolsCSV()
    {
        // استخراج معلمات التصفية
        $timeRange = $this->request->get('time_range', 'all');
        $status = $this->request->get('status', 'all');
        $subscription = $this->request->get('subscription', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT * FROM schools WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب نوع الاشتراك
        if ($subscription !== 'all') {
            $query .= " AND subscription_type = ?";
            $params[] = $subscription;
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY created_at DESC";
        
        // تنفيذ الاستعلام
        $schools = $this->schoolModel->raw($query, $params);
        
        // تجهيز ملف CSV
        $filename = "schools_report_" . date('Y-m-d') . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // BOM للدعم الصحيح للعربية
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // عناوين الأعمدة
        fputcsv($output, [
            'رقم المدرسة', 'اسم المدرسة', 'النطاق الفرعي', 'نوع الاشتراك', 
            'تاريخ بدء الاشتراك', 'تاريخ انتهاء الاشتراك', 'الحد الأقصى للطلاب',
            'الحالة', 'تاريخ الإنشاء'
        ]);
        
        // بيانات المدارس
        foreach ($schools as $school) {
            fputcsv($output, [
                $school['id'],
                $school['name'],
                $school['subdomain'],
                $this->getSubscriptionTypeName($school['subscription_type']),
                $school['subscription_start_date'],
                $school['subscription_end_date'],
                $school['max_students'],
                $school['active'] ? 'نشطة' : 'غير نشطة',
                $school['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * تصدير تقرير المستخدمين بتنسيق CSV
     */
    public function exportUsersCSV()
    {
        // استخراج معلمات التصفية
        $role = $this->request->get('role', 'all');
        $status = $this->request->get('status', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT u.*, r.name as role_name, s.name as school_name 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 LEFT JOIN schools s ON u.school_id = s.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب الدور
        if ($role !== 'all') {
            $query .= " AND r.name = ?";
            $params[] = $role;
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND u.active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY u.created_at DESC";
        
        // تنفيذ الاستعلام
        $users = $this->db->fetchAll($query, $params);
        
        // تجهيز ملف CSV
        $filename = "users_report_" . date('Y-m-d') . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // BOM للدعم الصحيح للعربية
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // عناوين الأعمدة
        fputcsv($output, [
            'رقم المستخدم', 'الاسم الأول', 'الاسم الأخير', 'البريد الإلكتروني', 
            'اسم المستخدم', 'الدور', 'المدرسة', 'آخر تسجيل دخول', 
            'الحالة', 'تاريخ التسجيل'
        ]);
        
        // بيانات المستخدمين
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['username'],
                $this->getRoleNameArabic($user['role_name']),
                $user['school_name'] ?? '-',
                $user['last_login'] ?? 'لم يسجل الدخول بعد',
                $user['active'] ? 'نشط' : 'غير نشط',
                $user['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * الحصول على توزيع أنواع الاشتراكات
     * 
     * @return array بيانات توزيع الاشتراكات
     */
    private function getSubscriptionDistribution()
    {
        $query = "SELECT subscription_type, COUNT(*) as count 
                 FROM schools 
                 GROUP BY subscription_type";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => [],
            'colors' => [
                'rgba(59, 130, 246, 0.7)', // trial - blue
                'rgba(245, 158, 11, 0.7)', // limited - yellow
                'rgba(16, 185, 129, 0.7)'  // unlimited - green
            ],
            'borders' => [
                'rgba(59, 130, 246, 1)',
                'rgba(245, 158, 11, 1)',
                'rgba(16, 185, 129, 1)'
            ]
        ];
        
        foreach ($result as $row) {
            $label = '';
            switch ($row['subscription_type']) {
                case 'trial':
                    $label = 'تجريبي';
                    break;
                case 'limited':
                    $label = 'محدود';
                    break;
                case 'unlimited':
                    $label = 'غير محدود';
                    break;
                default:
                    $label = $row['subscription_type'];
            }
            
            $data['labels'][] = $label;
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على بيانات رسم المدارس الجديدة
     * 
     * @return array بيانات المدارس الجديدة
     */
    private function getNewSchoolsChart()
    {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                 FROM schools
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => []
        ];
        
        foreach ($result as $row) {
            // تحويل الشهر إلى اسم شهر عربي
            $date = new DateTime($row['month'] . '-01');
            $arabicMonth = $this->getArabicMonth($date->format('n'));
            $year = $date->format('Y');
            
            $data['labels'][] = $arabicMonth . ' ' . $year;
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على توزيع أدوار المستخدمين
     * 
     * @return array بيانات توزيع الأدوار
     */
    private function getRolesDistribution()
    {
        $query = "SELECT r.name as role, COUNT(*) as count 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 GROUP BY r.name";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => [],
            'colors' => [
                'rgba(59, 130, 246, 0.7)',  // super_admin - blue
                'rgba(16, 185, 129, 0.7)',  // school_admin - green
                'rgba(245, 158, 11, 0.7)',  // teacher - yellow
                'rgba(139, 92, 246, 0.7)',  // parent - purple
                'rgba(236, 72, 153, 0.7)'   // student - pink
            ],
            'borders' => [
                'rgba(59, 130, 246, 1)',
                'rgba(16, 185, 129, 1)',
                'rgba(245, 158, 11, 1)',
                'rgba(139, 92, 246, 1)',
                'rgba(236, 72, 153, 1)'
            ]
        ];
        
        foreach ($result as $row) {
            $data['labels'][] = $this->getRoleNameArabic($row['role']);
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على بيانات رسم المستخدمين الجدد
     * 
     * @return array بيانات المستخدمين الجدد
     */
    private function getNewUsersChart()
    {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                 FROM users
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => []
        ];
        
        foreach ($result as $row) {
            // تحويل الشهر إلى اسم شهر عربي
            $date = new DateTime($row['month'] . '-01');
            $arabicMonth = $this->getArabicMonth($date->format('n'));
            $year = $date->format('Y');
            
            $data['labels'][] = $arabicMonth . ' ' . $year;
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على بيانات رسم المدارس النشطة
     * 
     * @param string $timeRange النطاق الزمني
     * @return array بيانات المدارس النشطة
     */
    private function getActiveSchoolsChart($timeRange)
    {
        $timeRangeFilter = $this->getTimeRangeFilter($timeRange);
        
        // عدد المدارس النشطة عبر الزمن
        $query = "SELECT 
                    DATE_FORMAT(created_at, '{$timeRangeFilter['format']}') as period,
                    COUNT(*) as total,
                    SUM(active) as active
                 FROM schools
                 WHERE created_at >= DATE_SUB(CUR<?php
/**
 * app/controllers/admin/ReportController.php
 * متحكم التقارير للمدير الرئيسي
 * يدير عمليات عرض وتوليد التقارير المختلفة للنظام
 */
class ReportController extends Controller
{
    private $schoolModel;
    private $userModel;
    private $studentModel;
    private $teacherModel;
    private $classModel;
    private $subjectModel;
    private $assignmentModel;
    private $subscriptionModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->schoolModel = new School();
        $this->userModel = new User();
        $this->studentModel = new Student();
        $this->teacherModel = new Teacher();
        $this->classModel = new ClassModel();
        $this->subjectModel = new Subject();
        $this->assignmentModel = new Assignment();
        $this->subscriptionModel = new Subscription();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('super_admin');
    }
    
    /**
     * عرض صفحة التقارير الرئيسية
     */
    public function index()
    {
        echo $this->render('admin/reports/index', [
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير المدارس
     */
    public function schools()
    {
        // استخراج معلمات التصفية
        $timeRange = $this->request->get('time_range', 'all');
        $status = $this->request->get('status', 'all');
        $subscription = $this->request->get('subscription', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT * FROM schools WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب نوع الاشتراك
        if ($subscription !== 'all') {
            $query .= " AND subscription_type = ?";
            $params[] = $subscription;
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY created_at DESC";
        
        // تنفيذ الاستعلام
        $schools = $this->schoolModel->raw($query, $params);
        
        // الحصول على إحصائيات المدارس
        $stats = $this->getSchoolsStats($schools);
        
        // الحصول على بيانات رسم توزيع الاشتراكات
        $subscriptionData = $this->getSubscriptionDistribution();
        
        // الحصول على بيانات رسم المدارس الجديدة
        $newSchoolsData = $this->getNewSchoolsChart();
        
        echo $this->render('admin/reports/schools', [
            'schools' => $schools,
            'stats' => $stats,
            'subscriptionData' => $subscriptionData,
            'newSchoolsData' => $newSchoolsData,
            'timeRange' => $timeRange,
            'status' => $status,
            'subscription' => $subscription,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير المستخدمين
     */
    public function users()
    {
        // استخراج معلمات التصفية
        $role = $this->request->get('role', 'all');
        $status = $this->request->get('status', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT u.*, r.name as role_name 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب الدور
        if ($role !== 'all') {
            $query .= " AND r.name = ?";
            $params[] = $role;
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND u.active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY u.created_at DESC";
        
        // تنفيذ الاستعلام
        $users = $this->userModel->raw($query, $params);
        
        // الحصول على إحصائيات المستخدمين
        $stats = $this->getUsersStats();
        
        // الحصول على بيانات رسم توزيع الأدوار
        $rolesChart = $this->getRolesDistribution();
        
        // الحصول على بيانات رسم المستخدمين الجدد
        $newUsersChart = $this->getNewUsersChart();
        
        echo $this->render('admin/reports/users', [
            'users' => $users,
            'stats' => $stats,
            'rolesChart' => $rolesChart,
            'newUsersChart' => $newUsersChart,
            'role' => $role,
            'status' => $status,
            'timeRange' => $timeRange,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الاشتراكات
     */
    public function subscriptions()
    {
        // استخراج معلمات التصفية
        $status = $this->request->get('status', 'all');
        $type = $this->request->get('type', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT s.*, sc.name as school_name, sc.subdomain 
                 FROM subscriptions s 
                 JOIN schools sc ON s.school_id = sc.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND s.status = ?";
            $params[] = $status;
        }
        
        // إضافة التصفية حسب نوع الاشتراك
        if ($type !== 'all') {
            $query .= " AND s.plan_type = ?";
            $params[] = $type;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND s.start_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY s.start_date DESC";
        
        // تنفيذ الاستعلام
        $subscriptions = $this->db->fetchAll($query, $params);
        
        // الحصول على إحصائيات الاشتراكات
        $stats = $this->getSubscriptionsStats();
        
        echo $this->render('admin/reports/subscriptions', [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
            'status' => $status,
            'type' => $type,
            'timeRange' => $timeRange,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الأنشطة
     */
    public function activities()
    {
        // استخراج معلمات التصفية
        $entityType = $this->request->get('entity_type', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT sl.*, u.first_name, u.last_name, u.email 
                 FROM system_logs sl 
                 LEFT JOIN users u ON sl.user_id = u.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب نوع الكيان
        if ($entityType !== 'all') {
            $query .= " AND sl.entity_type = ?";
            $params[] = $entityType;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_day':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'last_week':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
                    break;
                case 'last_month':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND sl.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY sl.created_at DESC";
        
        // التصفح والترقيم
        $page = $this->request->get('page', 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        // تنفيذ استعلام العد
        $countQuery = str_replace("SELECT sl.*, u.first_name, u.last_name, u.email", "SELECT COUNT(*) as count", $query);
        $countResult = $this->db->fetchOne($countQuery, $params);
        $total = $countResult['count'] ?? 0;
        
        // إضافة حدود الصفحة
        $query .= " LIMIT {$offset}, {$perPage}";
        
        // تنفيذ الاستعلام
        $logs = $this->db->fetchAll($query, $params);
        
        // حساب معلومات الترقيم
        $totalPages = ceil($total / $perPage);
        
        echo $this->render('admin/reports/activities', [
            'logs' => $logs,
            'entityType' => $entityType,
            'timeRange' => $timeRange,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الأداء العام
     */
    public function performance()
    {
        // استخراج معلمات التصفية
        $timeRange = $this->request->get('time_range', 'last_month');
        
        // جمع البيانات الإحصائية
        $schoolsStats = $this->getSchoolsStats();
        $usersStats = $this->getUsersStats();
        $studentsStats = $this->getStudentsStats();
        $teachersStats = $this->getTeachersStats();
        $assignmentsStats = $this->getAssignmentsStats();
        
        // الحصول على بيانات الرسوم البيانية
        $activeSchoolsChart = $this->getActiveSchoolsChart($timeRange);
        $userActivityChart = $this->getUserActivityChart($timeRange);
        $assignmentsCompletionChart = $this->getAssignmentsCompletionChart($timeRange);
        
        echo $this->render('admin/reports/performance', [
            'schoolsStats' => $schoolsStats,
            'usersStats' => $usersStats,
            'studentsStats' => $studentsStats,
            'teachersStats' => $teachersStats,
            'assignmentsStats' => $assignmentsStats,
            'activeSchoolsChart' => $activeSchoolsChart,
            'userActivityChart' => $userActivityChart,
            'assignmentsCompletionChart' => $assignmentsCompletionChart,
            'timeRange' => $timeRange,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تصدير تقرير المدارس بتنسيق CSV
     */
    public function exportSchoolsCSV()
    {
        // استخراج معلمات التصفية
        $timeRange = $this->request->get('time_range', 'all');
        $status = $this->request->get('status', 'all');
        $subscription = $this->request->get('subscription', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT * FROM schools WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب نوع الاشتراك
        if ($subscription !== 'all') {
            $query .= " AND subscription_type = ?";
            $params[] = $subscription;
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY created_at DESC";
        
        // تنفيذ الاستعلام
        $schools = $this->schoolModel->raw($query, $params);
        
        // تجهيز ملف CSV
        $filename = "schools_report_" . date('Y-m-d') . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // BOM للدعم الصحيح للعربية
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // عناوين الأعمدة
        fputcsv($output, [
            'رقم المدرسة', 'اسم المدرسة', 'النطاق الفرعي', 'نوع الاشتراك', 
            'تاريخ بدء الاشتراك', 'تاريخ انتهاء الاشتراك', 'الحد الأقصى للطلاب',
            'الحالة', 'تاريخ الإنشاء'
        ]);
        
        // بيانات المدارس
        foreach ($schools as $school) {
            fputcsv($output, [
                $school['id'],
                $school['name'],
                $school['subdomain'],
                $this->getSubscriptionTypeName($school['subscription_type']),
                $school['subscription_start_date'],
                $school['subscription_end_date'],
                $school['max_students'],
                $school['active'] ? 'نشطة' : 'غير نشطة',
                $school['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * تصدير تقرير المستخدمين بتنسيق CSV
     */
    public function exportUsersCSV()
    {
        // استخراج معلمات التصفية
        $role = $this->request->get('role', 'all');
        $status = $this->request->get('status', 'all');
        $timeRange = $this->request->get('time_range', 'all');
        
        // بناء الاستعلام حسب معلمات التصفية
        $query = "SELECT u.*, r.name as role_name, s.name as school_name 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 LEFT JOIN schools s ON u.school_id = s.id 
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب الدور
        if ($role !== 'all') {
            $query .= " AND r.name = ?";
            $params[] = $role;
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND u.active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة التصفية حسب النطاق الزمني
        if ($timeRange !== 'all') {
            switch ($timeRange) {
                case 'last_month':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_3_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'last_6_months':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'last_year':
                    $query .= " AND u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY u.created_at DESC";
        
        // تنفيذ الاستعلام
        $users = $this->db->fetchAll($query, $params);
        
        // تجهيز ملف CSV
        $filename = "users_report_" . date('Y-m-d') . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // BOM للدعم الصحيح للعربية
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // عناوين الأعمدة
        fputcsv($output, [
            'رقم المستخدم', 'الاسم الأول', 'الاسم الأخير', 'البريد الإلكتروني', 
            'اسم المستخدم', 'الدور', 'المدرسة', 'آخر تسجيل دخول', 
            'الحالة', 'تاريخ التسجيل'
        ]);
        
        // بيانات المستخدمين
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['username'],
                $this->getRoleNameArabic($user['role_name']),
                $user['school_name'] ?? '-',
                $user['last_login'] ?? 'لم يسجل الدخول بعد',
                $user['active'] ? 'نشط' : 'غير نشط',
                $user['created_at']
            ]);
        }
        
                        fclose($output);
        exit;
    }
    
    /**
     * الحصول على إحصائيات المدارس
     * 
     * @param array $schools بيانات المدارس (اختياري)
     * @return array إحصائيات المدارس
     */
    private function getSchoolsStats($schools = null)
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'expired' => 0,
            'trial' => 0,
            'limited' => 0,
            'unlimited' => 0,
            'expiring_soon' => 0
        ];
        
        if ($schools === null) {
            // إحضار إحصائيات من قاعدة البيانات
            $query = "SELECT COUNT(*) as total FROM schools";
            $result = $this->db->fetchOne($query);
            $stats['total'] = $result['total'] ?? 0;
            
            $query = "SELECT COUNT(*) as active FROM schools WHERE active = 1";
            $result = $this->db->fetchOne($query);
            $stats['active'] = $result['active'] ?? 0;
            
            $query = "SELECT COUNT(*) as expired FROM schools WHERE subscription_end_date < CURDATE()";
            $result = $this->db->fetchOne($query);
            $stats['expired'] = $result['expired'] ?? 0;
            
            $query = "SELECT COUNT(*) as expiring_soon FROM schools WHERE 
                     subscription_end_date >= CURDATE() AND 
                     subscription_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            $result = $this->db->fetchOne($query);
            $stats['expiring_soon'] = $result['expiring_soon'] ?? 0;
            
            $query = "SELECT subscription_type, COUNT(*) as count FROM schools GROUP BY subscription_type";
            $result = $this->db->fetchAll($query);
            
            foreach ($result as $row) {
                $stats[$row['subscription_type']] = $row['count'];
            }
        } else {
            // حساب الإحصائيات من مصفوفة المدارس المقدمة
            $stats['total'] = count($schools);
            
            foreach ($schools as $school) {
                if ($school['active']) {
                    $stats['active']++;
                }
                
                if (strtotime($school['subscription_end_date']) < time()) {
                    $stats['expired']++;
                }
                
                if (strtotime($school['subscription_end_date']) >= time() && 
                    strtotime($school['subscription_end_date']) <= strtotime('+30 days')) {
                    $stats['expiring_soon']++;
                }
                
                // زيادة عداد نوع الاشتراك
                $stats[$school['subscription_type']]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات المستخدمين
     * 
     * @return array إحصائيات المستخدمين
     */
    private function getUsersStats()
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'super_admin' => 0,
            'school_admin' => 0,
            'teacher' => 0,
            'parent' => 0,
            'student' => 0,
            'recent_logins' => 0
        ];
        
        // إجمالي المستخدمين
        $query = "SELECT COUNT(*) as total FROM users";
        $result = $this->db->fetchOne($query);
        $stats['total'] = $result['total'] ?? 0;
        
        // المستخدمين النشطين وغير النشطين
        $query = "SELECT active, COUNT(*) as count FROM users GROUP BY active";
        $result = $this->db->fetchAll($query);
        
        foreach ($result as $row) {
            if ($row['active']) {
                $stats['active'] = $row['count'];
            } else {
                $stats['inactive'] = $row['count'];
            }
        }
        
        // المستخدمين حسب الدور
        $query = "SELECT r.name as role, COUNT(*) as count 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 GROUP BY r.name";
        $result = $this->db->fetchAll($query);
        
        foreach ($result as $row) {
            $stats[$row['role']] = $row['count'];
        }
        
        // عدد تسجيلات الدخول الحديثة (آخر 24 ساعة)
        $query = "SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $this->db->fetchOne($query);
        $stats['recent_logins'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات الطلاب
     * 
     * @return array إحصائيات الطلاب
     */
    private function getStudentsStats()
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'new_last_month' => 0,
            'completion_rate' => 0
        ];
        
        // إجمالي الطلاب
        $query = "SELECT COUNT(*) as total FROM students";
        $result = $this->db->fetchOne($query);
        $stats['total'] = $result['total'] ?? 0;
        
        // الطلاب النشطين من خلال حالة المستخدم
        $query = "SELECT COUNT(*) as active 
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE u.active = 1";
        $result = $this->db->fetchOne($query);
        $stats['active'] = $result['active'] ?? 0;
        $stats['inactive'] = $stats['total'] - $stats['active'];
        
        // الطلاب الجدد (آخر شهر)
        $query = "SELECT COUNT(*) as count 
                 FROM students 
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $result = $this->db->fetchOne($query);
        $stats['new_last_month'] = $result['count'] ?? 0;
        
        // متوسط معدل إكمال المهام
        $query = "SELECT AVG(
                    (SELECT COUNT(*) FROM submissions su WHERE su.student_id = s.id) /
                    (SELECT COUNT(*) FROM assignments a 
                     JOIN class_subjects cs ON a.subject_id = cs.subject_id 
                     WHERE cs.class_id = s.class_id)
                   ) * 100 as completion_rate
                 FROM students s";
        $result = $this->db->fetchOne($query);
        $stats['completion_rate'] = round($result['completion_rate'] ?? 0, 2);
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات المعلمين
     * 
     * @return array إحصائيات المعلمين
     */
    private function getTeachersStats()
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'new_last_month' => 0,
            'avg_assignments' => 0,
            'avg_subjects' => 0
        ];
        
        // إجمالي المعلمين
        $query = "SELECT COUNT(*) as total FROM teachers";
        $result = $this->db->fetchOne($query);
        $stats['total'] = $result['total'] ?? 0;
        
        // المعلمين النشطين من خلال حالة المستخدم
        $query = "SELECT COUNT(*) as active 
                 FROM teachers t 
                 JOIN users u ON t.user_id = u.id 
                 WHERE u.active = 1";
        $result = $this->db->fetchOne($query);
        $stats['active'] = $result['active'] ?? 0;
        $stats['inactive'] = $stats['total'] - $stats['active'];
        
        // المعلمين الجدد (آخر شهر)
        $query = "SELECT COUNT(*) as count 
                 FROM teachers 
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $result = $this->db->fetchOne($query);
        $stats['new_last_month'] = $result['count'] ?? 0;
        
        // متوسط عدد المهام لكل معلم
        $query = "SELECT AVG(assignment_count) as avg_assignments
                 FROM (
                     SELECT t.id, COUNT(a.id) as assignment_count
                     FROM teachers t
                     LEFT JOIN assignments a ON a.teacher_id = t.id
                     GROUP BY t.id
                 ) as teacher_assignments";
        $result = $this->db->fetchOne($query);
        $stats['avg_assignments'] = round($result['avg_assignments'] ?? 0, 1);
        
        // متوسط عدد المواد لكل معلم
        $query = "SELECT AVG(subject_count) as avg_subjects
                 FROM (
                     SELECT t.id, COUNT(DISTINCT ta.subject_id) as subject_count
                     FROM teachers t
                     LEFT JOIN teacher_assignments ta ON ta.teacher_id = t.id
                     GROUP BY t.id
                 ) as teacher_subjects";
        $result = $this->db->fetchOne($query);
        $stats['avg_subjects'] = round($result['avg_subjects'] ?? 0, 1);
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات المهام
     * 
     * @return array إحصائيات المهام
     */
    private function getAssignmentsStats()
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'completed' => 0,
            'avg_completion_rate' => 0,
            'avg_grade' => 0
        ];
        
        // إجمالي المهام
        $query = "SELECT COUNT(*) as total FROM assignments";
        $result = $this->db->fetchOne($query);
        $stats['total'] = $result['total'] ?? 0;
        
        // المهام النشطة (غير منتهية)
        $query = "SELECT COUNT(*) as active 
                 FROM assignments 
                 WHERE due_date >= CURDATE() AND is_published = 1";
        $result = $this->db->fetchOne($query);
        $stats['active'] = $result['active'] ?? 0;
        
        // المهام المكتملة (انتهى موعدها)
        $query = "SELECT COUNT(*) as completed 
                 FROM assignments 
                 WHERE due_date < CURDATE() AND is_published = 1";
        $result = $this->db->fetchOne($query);
        $stats['completed'] = $result['completed'] ?? 0;
        
        // متوسط معدل إكمال المهام (نسبة الإجابات المقدمة للمهمة)
        $query = "SELECT AVG(completion_rate) as avg_completion_rate
                 FROM (
                     SELECT a.id,
                         (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) /
                         (SELECT COUNT(*) FROM students s WHERE s.class_id = a.class_id) * 100 as completion_rate
                     FROM assignments a
                     WHERE is_published = 1
                 ) as assignment_completion";
        $result = $this->db->fetchOne($query);
        $stats['avg_completion_rate'] = round($result['avg_completion_rate'] ?? 0, 2);
        
        // متوسط درجات المهام
        $query = "SELECT AVG(g.points / a.points * 100) as avg_grade
                 FROM grades g
                 JOIN submissions s ON g.submission_id = s.id
                 JOIN assignments a ON s.assignment_id = a.id";
        $result = $this->db->fetchOne($query);
        $stats['avg_grade'] = round($result['avg_grade'] ?? 0, 2);
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات الاشتراكات
     * 
     * @return array إحصائيات الاشتراكات
     */
    private function getSubscriptionsStats()
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'expired' => 0,
            'trial' => 0,
            'limited' => 0,
            'unlimited' => 0,
            'expiring_in_30_days' => 0,
            'recent_subscriptions' => 0
        ];
        
        // إجمالي الاشتراكات
        $query = "SELECT COUNT(*) as total FROM subscriptions";
        $result = $this->db->fetchOne($query);
        $stats['total'] = $result['total'] ?? 0;
        
        // الاشتراكات حسب الحالة
        $query = "SELECT status, COUNT(*) as count FROM subscriptions GROUP BY status";
        $result = $this->db->fetchAll($query);
        
        foreach ($result as $row) {
            if ($row['status'] === 'active') {
                $stats['active'] = $row['count'];
            } elseif ($row['status'] === 'expired') {
                $stats['expired'] = $row['count'];
            }
        }
        
        // الاشتراكات حسب النوع
        $query = "SELECT plan_type, COUNT(*) as count FROM subscriptions GROUP BY plan_type";
        $result = $this->db->fetchAll($query);
        
        foreach ($result as $row) {
            $stats[$row['plan_type']] = $row['count'];
        }
        
        // الاشتراكات التي ستنتهي خلال 30 يوم
        $query = "SELECT COUNT(*) as count 
                 FROM subscriptions 
                 WHERE status = 'active' 
                 AND end_date >= CURDATE() 
                 AND end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        $result = $this->db->fetchOne($query);
        $stats['expiring_in_30_days'] = $result['count'] ?? 0;
        
        // الاشتراكات الحديثة (آخر شهر)
        $query = "SELECT COUNT(*) as count 
                 FROM subscriptions 
                 WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $result = $this->db->fetchOne($query);
        $stats['recent_subscriptions'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على بيانات نشاط المستخدمين
     * 
     * @param string $timeRange النطاق الزمني
     * @return array بيانات نشاط المستخدمين
     */
    private function getUserActivityChart($timeRange)
    {
        $timeRangeFilter = $this->getTimeRangeFilter($timeRange);
        
        $query = "SELECT 
                    DATE_FORMAT(created_at, '{$timeRangeFilter['format']}') as period,
                    COUNT(*) as count
                 FROM system_logs
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$timeRangeFilter['interval']})
                 GROUP BY period
                 ORDER BY period";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => []
        ];
        
        foreach ($result as $row) {
            $data['labels'][] = $row['period'];
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على بيانات إكمال المهام
     * 
     * @param string $timeRange النطاق الزمني
     * @return array بيانات إكمال المهام
     */
    private function getAssignmentsCompletionChart($timeRange)
    {
        $timeRangeFilter = $this->getTimeRangeFilter($timeRange);
        
        $query = "SELECT 
                    DATE_FORMAT(a.due_date, '{$timeRangeFilter['format']}') as period,
                    COUNT(a.id) as total_assignments,
                    SUM((SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) / 
                        (SELECT COUNT(*) FROM students st WHERE st.class_id = a.class_id) * 100) as completion_rate,
                    COUNT(DISTINCT a.id) as assignment_count
                 FROM assignments a
                 WHERE a.due_date >= DATE_SUB(CURDATE(), INTERVAL {$timeRangeFilter['interval']})
                   AND a.is_published = 1
                 GROUP BY period
                 ORDER BY period";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'rates' => []
        ];
        
        foreach ($result as $row) {
            $data['labels'][] = $row['period'];
            $data['rates'][] = $row['assignment_count'] > 0 ? round($row['completion_rate'] / $row['assignment_count'], 2) : 0;
        }
        
        return $data;
    }
    
    /**
     * الحصول على فلتر النطاق الزمني
     * 
     * @param string $timeRange النطاق الزمني
     * @return array معلومات الفلتر
     */
    private function getTimeRangeFilter($timeRange)
    {
        $filter = [
            'format' => '%Y-%m-%d',
            'interval' => '7 DAY'
        ];
        
        switch ($timeRange) {
            case 'last_week':
                $filter['format'] = '%Y-%m-%d';
                $filter['interval'] = '7 DAY';
                break;
            case 'last_month':
                $filter['format'] = '%Y-%m-%d';
                $filter['interval'] = '30 DAY';
                break;
            case 'last_3_months':
                $filter['format'] = '%Y-%m-%d';
                $filter['interval'] = '90 DAY';
                break;
            case 'last_year':
                $filter['format'] = '%Y-%m';
                $filter['interval'] = '12 MONTH';
                break;
            default:
                $filter['format'] = '%Y-%m-%d';
                $filter['interval'] = '7 DAY';
        }
        
        return $filter;
    }
    
    /**
     * الحصول على اسم الشهر بالعربية
     * 
     * @param int $month رقم الشهر (1-12)
     * @return string اسم الشهر بالعربية
     */
    private function getArabicMonth($month)
    {
        $arabicMonths = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر'
        ];
        
        return $arabicMonths[$month] ?? '';
    }
    
    /**
     * الحصول على اسم نوع الاشتراك بالعربية
     * 
     * @param string $type نوع الاشتراك بالإنجليزية
     * @return string اسم نوع الاشتراك بالعربية
     */
    private function getSubscriptionTypeName($type)
    {
        switch ($type) {
            case 'trial':
                return 'تجريبي';
            case 'limited':
                return 'محدود';
            case 'unlimited':
                return 'غير محدود';
            default:
                return $type;
        }
    }
    
    /**
     * الحصول على اسم دور المستخدم بالعربية
     * 
     * @param string $roleName اسم الدور بالإنجليزية
     * @return string اسم الدور بالعربية
     */
    private function getRoleNameArabic($roleName)
    {
        switch ($roleName) {
            case 'super_admin':
                return 'مدير النظام';
            case 'school_admin':
                return 'مدير مدرسة';
            case 'teacher':
                return 'معلم';
            case 'parent':
                return 'ولي أمر';
            case 'student':
                return 'طالب';
            default:
                return $roleName;
        }
    }
    
    /**
     * الحصول على توزيع أنواع الاشتراكات
     * 
     * @return array بيانات توزيع الاشتراكات
     */
    private function getSubscriptionDistribution()
    {
        $query = "SELECT subscription_type, COUNT(*) as count 
                 FROM schools 
                 GROUP BY subscription_type";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => [],
            'colors' => [
                'rgba(59, 130, 246, 0.7)', // trial - blue
                'rgba(245, 158, 11, 0.7)', // limited - yellow
                'rgba(16, 185, 129, 0.7)'  // unlimited - green
            ],
            'borders' => [
                'rgba(59, 130, 246, 1)',
                'rgba(245, 158, 11, 1)',
                'rgba(16, 185, 129, 1)'
            ]
        ];
        
        foreach ($result as $row) {
            $label = '';
            switch ($row['subscription_type']) {
                case 'trial':
                    $label = 'تجريبي';
                    break;
                case 'limited':
                    $label = 'محدود';
                    break;
                case 'unlimited':
                    $label = 'غير محدود';
                    break;
                default:
                    $label = $row['subscription_type'];
            }
            
            $data['labels'][] = $label;
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على بيانات رسم المدارس الجديدة
     * 
     * @return array بيانات المدارس الجديدة
     */
    private function getNewSchoolsChart()
    {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                 FROM schools
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => []
        ];
        
        foreach ($result as $row) {
            // تحويل الشهر إلى اسم شهر عربي
            $date = new DateTime($row['month'] . '-01');
            $arabicMonth = $this->getArabicMonth($date->format('n'));
            $year = $date->format('Y');
            
            $data['labels'][] = $arabicMonth . ' ' . $year;
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على توزيع أدوار المستخدمين
     * 
     * @return array بيانات توزيع الأدوار
     */
    private function getRolesDistribution()
    {
        $query = "SELECT r.name as role, COUNT(*) as count 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 GROUP BY r.name";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => [],
            'colors' => [
                'rgba(59, 130, 246, 0.7)',  // super_admin - blue
                'rgba(16, 185, 129, 0.7)',  // school_admin - green
                'rgba(245, 158, 11, 0.7)',  // teacher - yellow
                'rgba(139, 92, 246, 0.7)',  // parent - purple
                'rgba(236, 72, 153, 0.7)'   // student - pink
            ],
            'borders' => [
                'rgba(59, 130, 246, 1)',
                'rgba(16, 185, 129, 1)',
                'rgba(245, 158, 11, 1)',
                'rgba(139, 92, 246, 1)',
                'rgba(236, 72, 153, 1)'
            ]
        ];
        
        foreach ($result as $row) {
            $data['labels'][] = $this->getRoleNameArabic($row['role']);
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على بيانات رسم المستخدمين الجدد
     * 
     * @return array بيانات المستخدمين الجدد
     */
    private function getNewUsersChart()
    {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                 FROM users
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                 ORDER BY month";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'counts' => []
        ];
        
        foreach ($result as $row) {
            // تحويل الشهر إلى اسم شهر عربي
            $date = new DateTime($row['month'] . '-01');
            $arabicMonth = $this->getArabicMonth($date->format('n'));
            $year = $date->format('Y');
            
            $data['labels'][] = $arabicMonth . ' ' . $year;
            $data['counts'][] = $row['count'];
        }
        
        return $data;
    }
    
    /**
     * الحصول على بيانات رسم المدارس النشطة
     * 
     * @param string $timeRange النطاق الزمني
     * @return array بيانات المدارس النشطة
     */
    private function getActiveSchoolsChart($timeRange)
    {
        $timeRangeFilter = $this->getTimeRangeFilter($timeRange);
        
        // عدد المدارس النشطة عبر الزمن
        $query = "SELECT 
                    DATE_FORMAT(created_at, '{$timeRangeFilter['format']}') as period,
                    COUNT(*) as total,
                    SUM(active) as active
                 FROM schools
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$timeRangeFilter['interval']})
                 GROUP BY period
                 ORDER BY period";
        
        $result = $this->db->fetchAll($query);
        
        $data = [
            'labels' => [],
            'total' => [],
            'active' => []
        ];
        
        foreach ($result as $row) {
            $data['labels'][] = $row['period'];
            $data['total'][] = $row['total'];
            $data['active'][] = $row['active'];
        }
        
        return $data;
        