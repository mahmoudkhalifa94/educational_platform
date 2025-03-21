<?php
/**
 * app/controllers/admin/SchoolController.php
 * متحكم إدارة المدارس للمدير الرئيسي
 */
class SchoolController extends Controller
{
    private $schoolModel;
    private $userModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->schoolModel = new School();
        $this->userModel = new User();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('super_admin');
    }
    
    /**
     * عرض قائمة المدارس
     */
    public function index()
    {
        // استخراج معلمات البحث والتصفية
        $search = $this->request->get('search', '');
        $status = $this->request->get('status', 'all');
        $subscription = $this->request->get('subscription', 'all');
        
        // بناء الاستعلام
        $query = "SELECT * FROM schools WHERE 1=1";
        $params = [];
        
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
        
        // إضافة البحث
        if (!empty($search)) {
            $query .= " AND (name LIKE ? OR subdomain LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY name ASC";
        
        // تنفيذ الاستعلام
        $schools = $this->schoolModel->raw($query, $params);
        
        // الحصول على إحصائيات عامة
        $stats = $this->getSystemStats();
        
        // عرض القالب
        echo $this->render('admin/schools/index', [
            'schools' => $schools,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'subscription' => $subscription,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج إنشاء مدرسة جديدة
     */
    public function create()
    {
        echo $this->render('admin/schools/create', [
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إنشاء مدرسة جديدة
     */
    public function store()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/schools');
        }
        
        // استخراج البيانات من النموذج
        $schoolData = [
            'name' => $this->request->post('name'),
            'subdomain' => $this->request->post('subdomain'),
            'subscription_type' => $this->request->post('subscription_type', 'trial'),
            'subscription_start_date' => date('Y-m-d'),
            'subscription_end_date' => date('Y-m-d', strtotime('+3 months')), // افتراضيًا 3 أشهر للنسخة التجريبية
            'active' => 1,
            'theme' => 'default'
        ];
        
        // تعيين الحد الأقصى لعدد الطلاب حسب نوع الاشتراك
        switch ($schoolData['subscription_type']) {
            case 'trial':
                $schoolData['max_students'] = 50;
                break;
            case 'limited':
                $schoolData['max_students'] = 500;
                $schoolData['subscription_end_date'] = date('Y-m-d', strtotime('+1 year'));
                break;
            case 'unlimited':
                $schoolData['max_students'] = 9999999;
                $schoolData['subscription_end_date'] = date('Y-m-d', strtotime('+1 year'));
                break;
        }
        
        // التحقق من البيانات
        $errors = $this->validate($schoolData, [
            'name' => 'required',
            'subdomain' => 'required|unique:schools,subdomain'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect('/admin/schools/create');
        }
        
        // معالجة رفع الشعار إذا تم تقديمه
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoUpload = $this->uploadFile('logo', 'assets/uploads/schools', ['jpg', 'jpeg', 'png', 'svg'], 2 * 1024 * 1024);
            
            if ($logoUpload['success']) {
                $schoolData['logo'] = $logoUpload['path'];
            }
        }
        
        // إنشاء المدرسة
        $schoolId = $this->schoolModel->createSchool($schoolData);
        
        if (!$schoolId) {
            $this->setFlash('error', 'حدث خطأ أثناء إنشاء المدرسة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/schools/create');
        }
        
        // استخراج بيانات مدير المدرسة
        $adminData = [
            'school_id' => $schoolId,
            'role_id' => 2, // مدير مدرسة
            'first_name' => $this->request->post('admin_first_name'),
            'last_name' => $this->request->post('admin_last_name'),
            'email' => $this->request->post('admin_email'),
            'username' => $this->request->post('admin_username'),
            'password' => $this->request->post('admin_password'),
            'phone' => $this->request->post('admin_phone', ''),
            'active' => 1
        ];
        
        // التحقق من بيانات المدير
        $adminErrors = $this->validate($adminData, [
            'email' => 'required|email|unique:users,email',
            'username' => 'required|unique:users,username',
            'password' => 'required|min:8',
            'first_name' => 'required',
            'last_name' => 'required'
        ]);
        
        if (!empty($adminErrors)) {
            // في حالة وجود أخطاء، نحذف المدرسة التي تم إنشاؤها
            $this->schoolModel->delete($schoolId);
            
            $this->setFlash('error', 'يوجد أخطاء في بيانات مدير المدرسة.');
            $this->redirect('/admin/schools/create');
        }
        
        // إنشاء حساب مدير المدرسة
        $adminId = $this->userModel->createUser($adminData);
        
        if (!$adminId) {
            // في حالة وجود خطأ، نحذف المدرسة التي تم إنشاؤها
            $this->schoolModel->delete($schoolId);
            
            $this->setFlash('error', 'حدث خطأ أثناء إنشاء حساب مدير المدرسة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/schools/create');
        }
        
        // إضافة سجل في جدول الاشتراكات
        $this->db->insert('subscriptions', [
            'school_id' => $schoolId,
            'plan_type' => $schoolData['subscription_type'],
            'start_date' => $schoolData['subscription_start_date'],
            'end_date' => $schoolData['subscription_end_date'],
            'max_students' => $schoolData['max_students'],
            'status' => 'active'
        ]);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إنشاء مدرسة',
            'school',
            $schoolId,
            ['name' => $schoolData['name']]
        );
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم إنشاء المدرسة وحساب المدير بنجاح.');
        $this->redirect('/admin/schools');
    }
    
    /**
     * عرض تفاصيل مدرسة
     * 
     * @param int $id معرّف المدرسة
     */
    public function show($id)
    {
        // الحصول على بيانات المدرسة
        $school = $this->schoolModel->find($id);
        
        if (!$school) {
            $this->setFlash('error', 'المدرسة غير موجودة.');
            $this->redirect('/admin/schools');
        }
        
        // الحصول على إحصائيات المدرسة
        $stats = $this->schoolModel->getStats($id);
        
        // الحصول على تاريخ الاشتراكات
        $subscriptions = $this->schoolModel->getSubscriptionHistory($id);
        
        // الحصول على مدير المدرسة
        $schoolAdmin = $this->userModel->whereFirst('school_id', $id, [
            'id', 'first_name', 'last_name', 'email', 'phone', 'last_login'
        ]);
        
        echo $this->render('admin/schools/show', [
            'school' => $school,
            'stats' => $stats,
            'subscriptions' => $subscriptions,
            'schoolAdmin' => $schoolAdmin,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج تعديل مدرسة
     * 
     * @param int $id معرّف المدرسة
     */
    public function edit($id)
    {
        // الحصول على بيانات المدرسة
        $school = $this->schoolModel->find($id);
        
        if (!$school) {
            $this->setFlash('error', 'المدرسة غير موجودة.');
            $this->redirect('/admin/schools');
        }
        
        echo $this->render('admin/schools/edit', [
            'school' => $school,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة تحديث بيانات مدرسة
     * 
     * @param int $id معرّف المدرسة
     */
    public function update($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/schools');
        }
        
        // الحصول على بيانات المدرسة الحالية
        $school = $this->schoolModel->find($id);
        
        if (!$school) {
            $this->setFlash('error', 'المدرسة غير موجودة.');
            $this->redirect('/admin/schools');
        }
        
        // استخراج البيانات من النموذج
        $schoolData = [
            'name' => $this->request->post('name'),
            'active' => $this->request->post('active', 0),
            'theme' => $this->request->post('theme', 'default')
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($schoolData, [
            'name' => 'required'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect("/admin/schools/{$id}/edit");
        }
        
        // معالجة رفع الشعار إذا تم تقديمه
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoUpload = $this->uploadFile('logo', 'assets/uploads/schools', ['jpg', 'jpeg', 'png', 'svg'], 2 * 1024 * 1024);
            
            if ($logoUpload['success']) {
                $schoolData['logo'] = $logoUpload['path'];
                
                // حذف الشعار القديم إذا كان موجودًا
                if (!empty($school['logo']) && file_exists($school['logo'])) {
                    unlink($school['logo']);
                }
            }
        }
        
        // تحديث بيانات المدرسة
        $success = $this->schoolModel->updateSchool($id, $schoolData);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث بيانات المدرسة. يرجى المحاولة مرة أخرى.');
            $this->redirect("/admin/schools/{$id}/edit");
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تعديل بيانات مدرسة',
            'school',
            $id,
            ['name' => $schoolData['name']]
        );
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم تحديث بيانات المدرسة بنجاح.');
        $this->redirect("/admin/schools/{$id}");
    }
    
    /**
     * تحديث اشتراك مدرسة
     * 
     * @param int $id معرّف المدرسة
     */
    public function updateSubscription($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/schools');
        }
        
        // الحصول على بيانات المدرسة الحالية
        $school = $this->schoolModel->find($id);
        
        if (!$school) {
            $this->setFlash('error', 'المدرسة غير موجودة.');
            $this->redirect('/admin/schools');
        }
        
        // استخراج البيانات من النموذج
        $subscriptionType = $this->request->post('subscription_type');
        $startDate = $this->request->post('start_date', date('Y-m-d'));
        $endDate = $this->request->post('end_date');
        $maxStudents = $this->request->post('max_students');
        
        // تعيين الحد الأقصى لعدد الطلاب حسب نوع الاشتراك إذا لم يتم تحديده
        if (empty($maxStudents)) {
            switch ($subscriptionType) {
                case 'trial':
                    $maxStudents = 50;
                    break;
                case 'limited':
                    $maxStudents = 500;
                    break;
                case 'unlimited':
                    $maxStudents = 9999999;
                    break;
            }
        }
        
        // تعيين تاريخ الانتهاء إذا لم يتم تحديده
        if (empty($endDate)) {
            switch ($subscriptionType) {
                case 'trial':
                    $endDate = date('Y-m-d', strtotime($startDate . ' +3 months'));
                    break;
                default:
                    $endDate = date('Y-m-d', strtotime($startDate . ' +1 year'));
                    break;
            }
        }
        
        // التحقق من البيانات
        $errors = $this->validate([
            'subscription_type' => $subscriptionType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'max_students' => $maxStudents
        ], [
            'subscription_type' => 'required|in:trial,limited,unlimited',
            'start_date' => 'required',
            'end_date' => 'required',
            'max_students' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في بيانات الاشتراك.');
            $this->redirect("/admin/schools/{$id}");
        }
        
        // تحديث بيانات الاشتراك
        $success = $this->schoolModel->updateSubscription(
            $id,
            $subscriptionType,
            $startDate,
            $endDate,
            $maxStudents
        );
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث بيانات الاشتراك. يرجى المحاولة مرة أخرى.');
            $this->redirect("/admin/schools/{$id}");
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تحديث اشتراك مدرسة',
            'school',
            $id,
            [
                'subscription_type' => $subscriptionType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'max_students' => $maxStudents
            ]
        );
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم تحديث بيانات الاشتراك بنجاح.');
        $this->redirect("/admin/schools/{$id}");
    }
    
    /**
     * تفعيل/تعطيل مدرسة
     * 
     * @param int $id معرّف المدرسة
     */
    public function toggleStatus($id)
    {
        // الحصول على بيانات المدرسة الحالية
        $school = $this->schoolModel->find($id);
        
        if (!$school) {
            $this->json([
                'success' => false,
                'message' => 'المدرسة غير موجودة'
            ]);
        }
        
        // تغيير الحالة
        $newStatus = $school['active'] ? 0 : 1;
        $success = $this->schoolModel->toggleActive($id, $newStatus);
        
        if (!$success) {
            $this->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة المدرسة'
            ]);
        }
        
        // تسجيل النشاط
        $actionText = $newStatus ? 'تفعيل مدرسة' : 'تعطيل مدرسة';
        $this->userModel->logActivity(
            $this->auth->id(),
            $actionText,
            'school',
            $id,
            ['name' => $school['name']]
        );
        
        $this->json([
            'success' => true,
            'message' => $newStatus ? 'تم تفعيل المدرسة بنجاح' : 'تم تعطيل المدرسة بنجاح',
            'new_status' => $newStatus
        ]);
    }
    
    /**
     * حذف مدرسة
     * 
     * @param int $id معرّف المدرسة
     */
    public function delete($id)
    {
        // الحصول على بيانات المدرسة
        $school = $this->schoolModel->find($id);
        
        if (!$school) {
            $this->setFlash('error', 'المدرسة غير موجودة.');
            $this->redirect('/admin/schools');
        }
        
        // تعطيل المدرسة بدلًا من حذفها (soft delete)
        $success = $this->schoolModel->update($id, [
            'active' => 0,
            'name' => $school['name'] . ' (محذوفة)',
            'subdomain' => $school['subdomain'] . '_deleted_' . time()
        ]);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء حذف المدرسة.');
            $this->redirect('/admin/schools');
        }
        
        // تعطيل جميع مستخدمي المدرسة
        $query = "UPDATE users SET active = 0 WHERE school_id = ?";
        $this->db->query($query, [$id]);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'حذف مدرسة',
            'school',
            $id,
            ['name' => $school['name']]
        );
        
        $this->setFlash('success', 'تم حذف المدرسة بنجاح.');
        $this->redirect('/admin/schools');
    }
    
    /**
     * الحصول على إحصائيات النظام
     * 
     * @return array الإحصائيات
     */
    private function getSystemStats()
    {
        $stats = [];
        
        // إجمالي المدارس
        $query = "SELECT COUNT(*) as count FROM schools";
        $result = $this->db->fetchOne($query);
        $stats['total_schools'] = $result['count'] ?? 0;
        
        // المدارس النشطة
        $query = "SELECT COUNT(*) as count FROM schools WHERE active = 1";
        $result = $this->db->fetchOne($query);
        $stats['active_schools'] = $result['count'] ?? 0;
        
        // المدارس حسب نوع الاشتراك
        $query = "SELECT subscription_type, COUNT(*) as count FROM schools GROUP BY subscription_type";
        $result = $this->db->fetchAll($query);
        $stats['schools_by_subscription'] = [];
        
        foreach ($result as $row) {
            $stats['schools_by_subscription'][$row['subscription_type']] = $row['count'];
        }
        
        // إجمالي المستخدمين
        $query = "SELECT COUNT(*) as count FROM users";
        $result = $this->db->fetchOne($query);
        $stats['total_users'] = $result['count'] ?? 0;
        
        // إجمالي الطلاب
        $query = "SELECT COUNT(*) as count FROM students";
        $result = $this->db->fetchOne($query);
        $stats['total_students'] = $result['count'] ?? 0;
        
        // إجمالي المعلمين
        $query = "SELECT COUNT(*) as count FROM teachers";
        $result = $this->db->fetchOne($query);
        $stats['total_teachers'] = $result['count'] ?? 0;
        
        return $stats;
    }
}