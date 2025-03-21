<?php
/**
 * app/controllers/admin/SubscriptionController.php
 * متحكم إدارة الاشتراكات للمدير الرئيسي
 * يدير عمليات الاشتراكات وخطط الأسعار للمدارس
 */
class SubscriptionController extends Controller
{
    private $subscriptionModel;
    private $schoolModel;
    private $planModel;
    private $userModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->subscriptionModel = new Subscription();
        $this->schoolModel = new School();
        $this->planModel = new Plan();
        $this->userModel = new User();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('super_admin');
    }
    
    /**
     * عرض قائمة الاشتراكات
     */
    public function index()
    {
        // استخراج معلمات البحث والتصفية
        $search = $this->request->get('search', '');
        $status = $this->request->get('status', 'all');
        $planType = $this->request->get('plan_type', 'all');
        
        // بناء الاستعلام
        $query = "SELECT s.*, sch.name as school_name, sch.subdomain 
                 FROM subscriptions s 
                 JOIN schools sch ON s.school_id = sch.id
                 WHERE 1=1";
        $params = [];
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND s.status = ?";
            $params[] = $status;
        }
        
        // إضافة التصفية حسب نوع الخطة
        if ($planType !== 'all') {
            $query .= " AND s.plan_type = ?";
            $params[] = $planType;
        }
        
        // إضافة البحث
        if (!empty($search)) {
            $query .= " AND (sch.name LIKE ? OR sch.subdomain LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY s.created_at DESC";
        
        // تنفيذ الاستعلام
        $subscriptions = $this->db->fetchAll($query, $params);
        
        // الحصول على إحصائيات الاشتراكات
        $stats = $this->getSubscriptionStats();
        
        // عرض القالب
        echo $this->render('admin/subscriptions/index', [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'planType' => $planType,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض صفحة خطط الأسعار
     */
    public function plans()
    {
        // الحصول على جميع خطط الأسعار
        $plans = $this->planModel->getAllPlans();
        
        // الحصول على إحصائيات الخطط
        $stats = $this->getPlanStats();
        
        // عرض القالب
        echo $this->render('admin/subscriptions/plans', [
            'plans' => $plans,
            'stats' => $stats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج إضافة خطة جديدة
     */
    public function createPlan()
    {
        echo $this->render('admin/subscriptions/create_plan', [
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إضافة خطة جديدة
     */
    public function storePlan()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/subscriptions/plans');
        }
        
        // استخراج البيانات من النموذج
        $planData = [
            'name' => $this->request->post('name'),
            'type' => $this->request->post('type'),
            'description' => $this->request->post('description'),
            'price' => $this->request->post('price'),
            'price_yearly' => $this->request->post('price_yearly'),
            'max_students' => $this->request->post('max_students'),
            'duration_months' => $this->request->post('duration_months'),
            'features' => $this->request->post('features'),
            'is_public' => $this->request->post('is_public', 0),
            'active' => $this->request->post('active', 1)
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($planData, [
            'name' => 'required',
            'type' => 'required|in:trial,limited,unlimited',
            'price' => 'required|numeric',
            'price_yearly' => 'required|numeric',
            'max_students' => 'required|numeric',
            'duration_months' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect('/admin/subscriptions/plans/create');
        }
        
        // إضافة الخطة
        $planId = $this->planModel->createPlan($planData);
        
        if (!$planId) {
            $this->setFlash('error', 'حدث خطأ أثناء إضافة الخطة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/subscriptions/plans/create');
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إضافة خطة اشتراك',
            'plan',
            $planId,
            ['name' => $planData['name']]
        );
        
        $this->setFlash('success', 'تم إضافة الخطة بنجاح.');
        $this->redirect('/admin/subscriptions/plans');
    }
    
    /**
     * عرض نموذج تعديل خطة
     * 
     * @param int $id معرّف الخطة
     */
    public function editPlan($id)
    {
        // الحصول على بيانات الخطة
        $plan = $this->planModel->find($id);
        
        if (!$plan) {
            $this->setFlash('error', 'الخطة غير موجودة.');
            $this->redirect('/admin/subscriptions/plans');
        }
        
        echo $this->render('admin/subscriptions/edit_plan', [
            'plan' => $plan,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة تحديث بيانات خطة
     * 
     * @param int $id معرّف الخطة
     */
    public function updatePlan($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/subscriptions/plans');
        }
        
        // الحصول على بيانات الخطة الحالية
        $plan = $this->planModel->find($id);
        
        if (!$plan) {
            $this->setFlash('error', 'الخطة غير موجودة.');
            $this->redirect('/admin/subscriptions/plans');
        }
        
        // استخراج البيانات من النموذج
        $planData = [
            'name' => $this->request->post('name'),
            'description' => $this->request->post('description'),
            'price' => $this->request->post('price'),
            'price_yearly' => $this->request->post('price_yearly'),
            'max_students' => $this->request->post('max_students'),
            'duration_months' => $this->request->post('duration_months'),
            'features' => $this->request->post('features'),
            'is_public' => $this->request->post('is_public', 0),
            'active' => $this->request->post('active', 0)
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($planData, [
            'name' => 'required',
            'price' => 'required|numeric',
            'price_yearly' => 'required|numeric',
            'max_students' => 'required|numeric',
            'duration_months' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect("/admin/subscriptions/plans/{$id}/edit");
        }
        
        // تحديث بيانات الخطة
        $success = $this->planModel->updatePlan($id, $planData);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث بيانات الخطة. يرجى المحاولة مرة أخرى.');
            $this->redirect("/admin/subscriptions/plans/{$id}/edit");
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تعديل خطة اشتراك',
            'plan',
            $id,
            ['name' => $planData['name']]
        );
        
        $this->setFlash('success', 'تم تحديث بيانات الخطة بنجاح.');
        $this->redirect('/admin/subscriptions/plans');
    }
    
    /**
     * تفعيل/تعطيل خطة
     * 
     * @param int $id معرّف الخطة
     */
    public function togglePlanStatus($id)
    {
        // الحصول على بيانات الخطة الحالية
        $plan = $this->planModel->find($id);
        
        if (!$plan) {
            $this->json([
                'success' => false,
                'message' => 'الخطة غير موجودة'
            ]);
        }
        
        // تغيير الحالة
        $newStatus = $plan['active'] ? 0 : 1;
        $success = $this->planModel->update($id, ['active' => $newStatus]);
        
        if (!$success) {
            $this->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة الخطة'
            ]);
        }
        
        // تسجيل النشاط
        $actionText = $newStatus ? 'تفعيل خطة اشتراك' : 'تعطيل خطة اشتراك';
        $this->userModel->logActivity(
            $this->auth->id(),
            $actionText,
            'plan',
            $id,
            ['name' => $plan['name']]
        );
        
        $this->json([
            'success' => true,
            'message' => $newStatus ? 'تم تفعيل الخطة بنجاح' : 'تم تعطيل الخطة بنجاح',
            'new_status' => $newStatus
        ]);
    }
    
    /**
     * حذف خطة
     * 
     * @param int $id معرّف الخطة
     */
    public function deletePlan($id)
    {
        // الحصول على بيانات الخطة
        $plan = $this->planModel->find($id);
        
        if (!$plan) {
            $this->setFlash('error', 'الخطة غير موجودة.');
            $this->redirect('/admin/subscriptions/plans');
        }
        
        // التحقق من وجود اشتراكات مرتبطة بالخطة
        $count = $this->db->fetchOne("SELECT COUNT(*) as count FROM subscriptions WHERE plan_id = ?", [$id]);
        
        if ($count['count'] > 0) {
            $this->setFlash('error', 'لا يمكن حذف الخطة لأنها مرتبطة باشتراكات.');
            $this->redirect('/admin/subscriptions/plans');
        }
        
        // حذف الخطة
        $success = $this->planModel->delete($id);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء حذف الخطة.');
            $this->redirect('/admin/subscriptions/plans');
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'حذف خطة اشتراك',
            'plan',
            $id,
            ['name' => $plan['name']]
        );
        
        $this->setFlash('success', 'تم حذف الخطة بنجاح.');
        $this->redirect('/admin/subscriptions/plans');
    }
    
    /**
     * عرض تفاصيل اشتراك
     * 
     * @param int $id معرّف الاشتراك
     */
    public function show($id)
    {
        // الحصول على بيانات الاشتراك
        $subscription = $this->subscriptionModel->getSubscriptionWithDetails($id);
        
        if (!$subscription) {
            $this->setFlash('error', 'الاشتراك غير موجود.');
            $this->redirect('/admin/subscriptions');
        }
        
        // الحصول على سجل المدفوعات
        $payments = $this->subscriptionModel->getSubscriptionPayments($id);
        
        // الحصول على سجل التغييرات
        $history = $this->subscriptionModel->getSubscriptionHistory($id);
        
        echo $this->render('admin/subscriptions/show', [
            'subscription' => $subscription,
            'payments' => $payments,
            'history' => $history,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج إضافة اشتراك جديد
     */
    public function create()
    {
        // الحصول على قائمة المدارس
        $schools = $this->schoolModel->getActiveSchools();
        
        // الحصول على قائمة الخطط
        $plans = $this->planModel->getActivePlans();
        
        echo $this->render('admin/subscriptions/create', [
            'schools' => $schools,
            'plans' => $plans,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إضافة اشتراك جديد
     */
    public function store()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/subscriptions');
        }
        
        // استخراج البيانات من النموذج
        $subscriptionData = [
            'school_id' => $this->request->post('school_id'),
            'plan_id' => $this->request->post('plan_id'),
            'plan_type' => $this->request->post('plan_type'),
            'start_date' => $this->request->post('start_date', date('Y-m-d')),
            'end_date' => $this->request->post('end_date'),
            'max_students' => $this->request->post('max_students'),
            'price' => $this->request->post('price'),
            'payment_status' => $this->request->post('payment_status', 'pending'),
            'status' => $this->request->post('status', 'active'),
            'notes' => $this->request->post('notes')
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($subscriptionData, [
            'school_id' => 'required',
            'plan_type' => 'required|in:trial,limited,unlimited',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'max_students' => 'required|numeric',
            'price' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect('/admin/subscriptions/create');
        }
        
        // إضافة الاشتراك
        $subscriptionId = $this->subscriptionModel->createSubscription($subscriptionData);
        
        if (!$subscriptionId) {
            $this->setFlash('error', 'حدث خطأ أثناء إضافة الاشتراك. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/subscriptions/create');
        }
        
        // تحديث بيانات اشتراك المدرسة
        $this->schoolModel->updateSubscription(
            $subscriptionData['school_id'],
            $subscriptionData['plan_type'],
            $subscriptionData['start_date'],
            $subscriptionData['end_date'],
            $subscriptionData['max_students']
        );
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إضافة اشتراك جديد',
            'subscription',
            $subscriptionId,
            [
                'school_id' => $subscriptionData['school_id'],
                'plan_type' => $subscriptionData['plan_type']
            ]
        );
        
        $this->setFlash('success', 'تم إضافة الاشتراك بنجاح.');
        $this->redirect('/admin/subscriptions/' . $subscriptionId);
    }
    
    /**
     * عرض نموذج تعديل اشتراك
     * 
     * @param int $id معرّف الاشتراك
     */
    public function edit($id)
    {
        // الحصول على بيانات الاشتراك
        $subscription = $this->subscriptionModel->find($id);
        
        if (!$subscription) {
            $this->setFlash('error', 'الاشتراك غير موجود.');
            $this->redirect('/admin/subscriptions');
        }
        
        // الحصول على بيانات المدرسة
        $school = $this->schoolModel->find($subscription['school_id']);
        
        // الحصول على قائمة الخطط
        $plans = $this->planModel->getActivePlans();
        
        echo $this->render('admin/subscriptions/edit', [
            'subscription' => $subscription,
            'school' => $school,
            'plans' => $plans,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة تحديث بيانات اشتراك
     * 
     * @param int $id معرّف الاشتراك
     */
    public function update($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/subscriptions');
        }
        
        // الحصول على بيانات الاشتراك الحالية
        $subscription = $this->subscriptionModel->find($id);
        
        if (!$subscription) {
            $this->setFlash('error', 'الاشتراك غير موجود.');
            $this->redirect('/admin/subscriptions');
        }
        
        // استخراج البيانات من النموذج
        $subscriptionData = [
            'plan_id' => $this->request->post('plan_id'),
            'plan_type' => $this->request->post('plan_type'),
            'end_date' => $this->request->post('end_date'),
            'max_students' => $this->request->post('max_students'),
            'price' => $this->request->post('price'),
            'payment_status' => $this->request->post('payment_status'),
            'status' => $this->request->post('status'),
            'notes' => $this->request->post('notes')
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($subscriptionData, [
            'plan_type' => 'required|in:trial,limited,unlimited',
            'end_date' => 'required|date',
            'max_students' => 'required|numeric',
            'price' => 'required|numeric',
            'status' => 'required|in:active,expired,cancelled'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect("/admin/subscriptions/{$id}/edit");
        }
        
        // تحديث بيانات الاشتراك
        $success = $this->subscriptionModel->updateSubscription($id, $subscriptionData);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث بيانات الاشتراك. يرجى المحاولة مرة أخرى.');
            $this->redirect("/admin/subscriptions/{$id}/edit");
        }
        
        // تحديث بيانات اشتراك المدرسة
        $this->schoolModel->updateSubscription(
            $subscription['school_id'],
            $subscriptionData['plan_type'],
            $subscription['start_date'],
            $subscriptionData['end_date'],
            $subscriptionData['max_students']
        );
        
        // تسجيل تاريخ التغيير
        $this->subscriptionModel->logSubscriptionChange(
            $id,
            'تعديل الاشتراك',
            json_encode([
                'from' => [
                    'plan_type' => $subscription['plan_type'],
                    'end_date' => $subscription['end_date'],
                    'max_students' => $subscription['max_students'],
                    'status' => $subscription['status']
                ],
                'to' => [
                    'plan_type' => $subscriptionData['plan_type'],
                    'end_date' => $subscriptionData['end_date'],
                    'max_students' => $subscriptionData['max_students'],
                    'status' => $subscriptionData['status']
                ]
            ]),
            $this->auth->id()
        );
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تعديل بيانات اشتراك',
            'subscription',
            $id,
            [
                'school_id' => $subscription['school_id'],
                'plan_type' => $subscriptionData['plan_type']
            ]
        );
        
        $this->setFlash('success', 'تم تحديث بيانات الاشتراك بنجاح.');
        $this->redirect('/admin/subscriptions/' . $id);
    }
    
    /**
     * تجديد اشتراك
     * 
     * @param int $id معرّف الاشتراك
     */
    public function renew($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/subscriptions');
        }
        
        // الحصول على بيانات الاشتراك الحالية
        $subscription = $this->subscriptionModel->find($id);
        
        if (!$subscription) {
            $this->setFlash('error', 'الاشتراك غير موجود.');
            $this->redirect('/admin/subscriptions');
        }
        
        // استخراج بيانات التجديد
        $duration = $this->request->post('duration', 12); // عدد الأشهر
        $price = $this->request->post('price');
        $maxStudents = $this->request->post('max_students');
        $planType = $this->request->post('plan_type');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'duration' => $duration,
            'price' => $price,
            'max_students' => $maxStudents,
            'plan_type' => $planType
        ], [
            'duration' => 'required|numeric',
            'price' => 'required|numeric',
            'max_students' => 'required|numeric',
            'plan_type' => 'required|in:trial,limited,unlimited'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في بيانات التجديد.');
            $this->redirect('/admin/subscriptions/' . $id);
        }
        
        // حساب تاريخ الانتهاء الجديد
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("{$startDate} +{$duration} months"));
        
        // إنشاء اشتراك جديد
        $newSubscriptionData = [
            'school_id' => $subscription['school_id'],
            'plan_id' => $subscription['plan_id'],
            'plan_type' => $planType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'max_students' => $maxStudents,
            'price' => $price,
            'payment_status' => $this->request->post('payment_status', 'pending'),
            'status' => 'active',
            'notes' => $this->request->post('notes', 'تجديد من الاشتراك #' . $id)
        ];
        
        $newSubscriptionId = $this->subscriptionModel->createSubscription($newSubscriptionData);
        
        if (!$newSubscriptionId) {
            $this->setFlash('error', 'حدث خطأ أثناء تجديد الاشتراك. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/subscriptions/' . $id);
        }
        
        // تحديث بيانات اشتراك المدرسة
        $this->schoolModel->updateSubscription(
            $subscription['school_id'],
            $planType,
            $startDate,
            $endDate,
            $maxStudents
        );
        
        // تحديث حالة الاشتراك القديم
        if ($subscription['status'] !== 'expired') {
            $this->subscriptionModel->update($id, ['status' => 'expired']);
            
            // تسجيل تاريخ التغيير
            $this->subscriptionModel->logSubscriptionChange(
                $id,
                'إنهاء الاشتراك بسبب التجديد',
                json_encode([
                    'from' => ['status' => $subscription['status']],
                    'to' => ['status' => 'expired']
                ]),
                $this->auth->id()
            );
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تجديد اشتراك',
            'subscription',
            $newSubscriptionId,
            [
                'school_id' => $subscription['school_id'],
                'plan_type' => $planType,
                'old_subscription_id' => $id
            ]
        );
        
        $this->setFlash('success', 'تم تجديد الاشتراك بنجاح.');
        $this->redirect('/admin/subscriptions/' . $newSubscriptionId);
    }
    
    /**
     * إلغاء اشتراك
     * 
     * @param int $id معرّف الاشتراك
     */
    public function cancel($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/subscriptions');
        }
        
        // الحصول على بيانات الاشتراك
        $subscription = $this->subscriptionModel->find($id);
        
        if (!$subscription) {
            $this->setFlash('error', 'الاشتراك غير موجود.');
            $this->redirect('/admin/subscriptions');
        }
        
        // التحقق من حالة الاشتراك
        if ($subscription['status'] !== 'active') {
            $this->setFlash('error', 'لا يمكن إلغاء اشتراك غير نشط.');
            $this->redirect('/admin/subscriptions/' . $id);
        }
        
        // استخراج سبب الإلغاء
        $cancelReason = $this->request->post('cancel_reason', '');
        
        // تحديث حالة الاشتراك
        $success = $this->subscriptionModel->update($id, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancelled_by' => $this->auth->id(),
            'cancel_reason' => $cancelReason
        ]);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء إلغاء الاشتراك. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/subscriptions/' . $id);
        }
        
        // تسجيل تاريخ التغيير
        $this->subscriptionModel->logSubscriptionChange(
            $id,
            'إلغاء الاشتراك',
            json_encode([
                'from' => ['status' => 'active'],
                'to' => ['status' => 'cancelled'],
                'reason' => $cancelReason
            ]),
            $this->auth->id()
        );
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إلغاء اشتراك',
            'subscription',
            $id,
            [
                'school_id' => $subscription['school_id'],
                'reason' => $cancelReason
            ]
        );
        
        $this->setFlash('success', 'تم إلغاء الاشتراك بنجاح.');
        $this->redirect('/admin/subscriptions/' . $id);
    }
    
    /**
     * إضافة مدفوعات للاشتراك
     * 
     * @param int $id معرّف الاشتراك
     */
    public function addPayment($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/subscriptions');
        }
        
        // الحصول على بيانات الاشتراك
        $subscription = $this->subscriptionModel->find($id);
        
        if (!$subscription) {
            $this->setFlash('error', 'الاشتراك غير موجود.');
            $this->redirect('/admin/subscriptions');
        }
        
        // استخراج بيانات الدفع
        $paymentData = [
            'subscription_id' => $id,
            'amount' => $this->request->post('amount'),
            'payment_method' => $this->request->post('payment_method'),
            'payment_date' => $this->request->post('payment_date', date('Y-m-d')),
            'transaction_id' => $this->request->post('transaction_id', ''),
            'notes' => $this->request->post('notes', '')
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($paymentData, [
            'amount' => 'required|numeric',
            'payment_method' => 'required',
            'payment_date' => 'required|date'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في بيانات الدفع.');
            $this->redirect('/admin/subscriptions/' . $id);
        }
        
        // إضافة سجل الدفع
        $paymentId = $this->subscriptionModel->addPayment($paymentData);
        
        if (!$paymentId) {
            $this->setFlash('error', 'حدث خطأ أثناء إضافة سجل الدفع. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/subscriptions/' . $id);
        }
        
        // حساب إجمالي المدفوعات
        $totalPayments = $this->subscriptionModel->getTotalPayments($id);
        
        // تحديث حالة الدفع للاشتراك
        $paymentStatus = 'partial';
        if ($totalPayments >= $subscription['price']) {
            $paymentStatus = 'paid';
        }
        
        $this->subscriptionModel->update($id, ['payment_status' => $paymentStatus]);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إضافة مدفوعات للاشتراك',
            'payment',
            $paymentId,
            [
                'subscription_id' => $id,
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['payment_method']
            ]
        );
        
        $this->setFlash('success', 'تم إضافة سجل الدفع بنجاح.');
        $this->redirect('/admin/subscriptions/' . $id);
    }
    
    /**
     * حذف سجل دفع
     * 
     * @param int $id معرّف سجل الدفع
     */
    public function deletePayment($id)
    {
        // الحصول على بيانات سجل الدفع
        $payment = $this->db->fetchOne("SELECT * FROM subscription_payments WHERE id = ?", [$id]);
        
        if (!$payment) {
            $this->setFlash('error', 'سجل الدفع غير موجود.');
            $this->redirect('/admin/subscriptions');
        }
        
        // الحصول على معرّف الاشتراك
        $subscriptionId = $payment['subscription_id'];
        
        // حذف سجل الدفع
        $success = $this->db->query("DELETE FROM subscription_payments WHERE id = ?", [$id]);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء حذف سجل الدفع.');
            $this->redirect('/admin/subscriptions/' . $subscriptionId);
        }
        
        // الحصول على بيانات الاشتراك
        $subscription = $this->subscriptionModel->find($subscriptionId);
        
        // حساب إجمالي المدفوعات المتبقية
        $totalPayments = $this->subscriptionModel->getTotalPayments($subscriptionId);
        
        // تحديث حالة الدفع للاشتراك
        $paymentStatus = 'pending';
        if ($totalPayments > 0) {
            $paymentStatus = $totalPayments >= $subscription['price'] ? 'paid' : 'partial';
        }
        
        $this->subscriptionModel->update($subscriptionId, ['payment_status' => $paymentStatus]);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'حذف سجل دفع',
            'payment',
            $id,
            [
                'subscription_id' => $subscriptionId,
                'amount' => $payment['amount']
            ]
        );
        
        $this->setFlash('success', 'تم حذف سجل الدفع بنجاح.');
        $this->redirect('/admin/subscriptions/' . $subscriptionId);
    }
    
    /**
     * عرض صفحة الإيرادات والتقارير المالية
     */
    public function revenues()
    {
        // استخراج معلمات التصفية
        $period = $this->request->get('period', 'month');
        $year = $this->request->get('year', date('Y'));
        $month = $this->request->get('month', date('m'));
        
        // الحصول على بيانات الإيرادات
        $revenueData = $this->getRevenueData($period, $year, $month);
        
        // الحصول على إحصائيات المدفوعات
        $paymentStats = $this->getPaymentStats();
        
        echo $this->render('admin/subscriptions/revenues', [
            'revenueData' => $revenueData,
            'paymentStats' => $paymentStats,
            'filters' => [
                'period' => $period,
                'year' => $year,
                'month' => $month
            ],
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تصدير تقرير الإيرادات
     */
    public function exportRevenues()
    {
        // استخراج معلمات التصفية
        $period = $this->request->get('period', 'month');
        $year = $this->request->get('year', date('Y'));
        $month = $this->request->get('month', date('m'));
        $format = $this->request->get('format', 'excel');
        
        // الحصول على بيانات الإيرادات
        $revenueData = $this->getRevenueData($period, $year, $month);
        
        // تحديد اسم الملف
        $fileName = 'revenues_';
        if ($period === 'year') {
            $fileName .= $year;
        } elseif ($period === 'month') {
            $fileName .= $year . '_' . $month;
        } else {
            $fileName .= date('Y_m_d');
        }
        
        // تصدير البيانات حسب التنسيق المطلوب
        if ($format === 'excel') {
            $this->exportRevenuesExcel($revenueData, $fileName);
        } else {
            $this->exportRevenuesCsv($revenueData, $fileName);
        }
    }
    
    /**
     * تصدير بيانات الإيرادات بتنسيق Excel
     * 
     * @param array $data بيانات الإيرادات
     * @param string $fileName اسم الملف
     */
    private function exportRevenuesExcel($data, $fileName)
    {
        // إنشاء كائن PHPExcel
        require_once 'vendor/autoload.php';
        
        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $excel->getActiveSheet();
        $sheet->setRightToLeft(true);
        
        // إضافة الترويسة
        $sheet->setCellValue('A1', 'تقرير الإيرادات');
        $sheet->setCellValue('A2', 'تاريخ التقرير: ' . date('Y-m-d'));
        
        // إضافة رأس الجدول
        $sheet->setCellValue('A4', 'التاريخ');
        $sheet->setCellValue('B4', 'المدرسة');
        $sheet->setCellValue('C4', 'نوع الخطة');
        $sheet->setCellValue('D4', 'المبلغ');
        $sheet->setCellValue('E4', 'طريقة الدفع');
        $sheet->setCellValue('F4', 'الحالة');
        
        // إضافة البيانات
        $row = 5;
        foreach ($data['payments'] as $payment) {
            $sheet->setCellValue('A' . $row, $payment['payment_date']);
            $sheet->setCellValue('B' . $row, $payment['school_name']);
            $sheet->setCellValue('C' . $row, $payment['plan_type']);
            $sheet->setCellValue('D' . $row, $payment['amount']);
            $sheet->setCellValue('E' . $row, $payment['payment_method']);
            $sheet->setCellValue('F' . $row, $payment['payment_status']);
            $row++;
        }
        
        // إضافة الإحصائيات
        $row += 2;
        $sheet->setCellValue('A' . $row, 'الإحصائيات');
        $row++;
        $sheet->setCellValue('A' . $row, 'إجمالي الإيرادات:');
        $sheet->setCellValue('B' . $row, $data['summary']['total_revenue']);
        $row++;
        $sheet->setCellValue('A' . $row, 'عدد المدفوعات:');
        $sheet->setCellValue('B' . $row, $data['summary']['payments_count']);
        
        // تنسيق الجدول
        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A4:F4')->getFont()->setBold(true);
        
        // ضبط عرض الأعمدة
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(15);
        
        // إنشاء كائن Writer وإرسال الملف
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($excel);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
    
    /**
     * تصدير بيانات الإيرادات بتنسيق CSV
     * 
     * @param array $data بيانات الإيرادات
     * @param string $fileName اسم الملف
     */
    private function exportRevenuesCsv($data, $fileName)
    {
        // فتح مخرج للكتابة
        $output = fopen('php://output', 'w');
        
        // إعداد ترويسة الملف
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $fileName . '.csv"');
        
        // إضافة BOM لدعم Unicode
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // إضافة رأس الجدول
        fputcsv($output, ['التاريخ', 'المدرسة', 'نوع الخطة', 'المبلغ', 'طريقة الدفع', 'الحالة']);
        
        // إضافة البيانات
        foreach ($data['payments'] as $payment) {
            fputcsv($output, [
                $payment['payment_date'],
                $payment['school_name'],
                $payment['plan_type'],
                $payment['amount'],
                $payment['payment_method'],
                $payment['payment_status']
            ]);
        }
        
        // إضافة الإحصائيات
        fputcsv($output, []);
        fputcsv($output, ['الإحصائيات']);
        fputcsv($output, ['إجمالي الإيرادات:', $data['summary']['total_revenue']]);
        fputcsv($output, ['عدد المدفوعات:', $data['summary']['payments_count']]);
        
        // إغلاق المخرج
        fclose($output);
        exit;
    }
    
    /**
     * الحصول على بيانات الإيرادات
     * 
     * @param string $period الفترة الزمنية (year, month, custom)
     * @param int $year السنة
     * @param int $month الشهر
     * @return array بيانات الإيرادات
     */
    private function getRevenueData($period, $year, $month)
    {
        // بناء شرط التاريخ
        $dateCondition = '';
        $params = [];
        
        if ($period === 'year') {
            $dateCondition = " AND YEAR(sp.payment_date) = ?";
            $params[] = $year;
        } elseif ($period === 'month') {
            $dateCondition = " AND YEAR(sp.payment_date) = ? AND MONTH(sp.payment_date) = ?";
            $params[] = $year;
            $params[] = $month;
        }
        
        // استعلام للحصول على المدفوعات
        $query = "SELECT sp.*, s.plan_type, s.payment_status, sch.name as school_name
                 FROM subscription_payments sp
                 JOIN subscriptions s ON sp.subscription_id = s.id
                 JOIN schools sch ON s.school_id = sch.id
                 WHERE 1=1" . $dateCondition . "
                 ORDER BY sp.payment_date DESC";
        
        $payments = $this->db->fetchAll($query, $params);
        
        // حساب إجمالي الإيرادات
        $totalRevenue = 0;
        foreach ($payments as $payment) {
            $totalRevenue += $payment['amount'];
        }
        
        // إعداد بيانات الإيرادات الشهرية
        $monthlyData = [];
        if ($period === 'year') {
            for ($i = 1; $i <= 12; $i++) {
                $monthName = date('F', mktime(0, 0, 0, $i, 1));
                $monthlyData[$i] = [
                    'month' => $monthName,
                    'amount' => 0
                ];
            }
            
            foreach ($payments as $payment) {
                $paymentMonth = date('n', strtotime($payment['payment_date']));
                $monthlyData[$paymentMonth]['amount'] += $payment['amount'];
            }
        }
        
        return [
            'payments' => $payments,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'payments_count' => count($payments)
            ],
            'monthly_data' => $monthlyData
        ];
    }
    
    /**
     * الحصول على إحصائيات الاشتراكات
     * 
     * @return array إحصائيات الاشتراكات
     */
    private function getSubscriptionStats()
    {
        $stats = [];
        
        // إجمالي الاشتراكات
        $query = "SELECT COUNT(*) as count FROM subscriptions";
        $result = $this->db->fetchOne($query);
        $stats['total_subscriptions'] = $result['count'] ?? 0;
        
        // الاشتراكات النشطة
        $query = "SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'";
        $result = $this->db->fetchOne($query);
        $stats['active_subscriptions'] = $result['count'] ?? 0;
        
        // الاشتراكات المنتهية
        $query = "SELECT COUNT(*) as count FROM subscriptions WHERE status = 'expired'";
        $result = $this->db->fetchOne($query);
        $stats['expired_subscriptions'] = $result['count'] ?? 0;
        
        // الاشتراكات الملغاة
        $query = "SELECT COUNT(*) as count FROM subscriptions WHERE status = 'cancelled'";
        $result = $this->db->fetchOne($query);
        $stats['cancelled_subscriptions'] = $result['count'] ?? 0;
        
        // الاشتراكات حسب نوع الخطة
        $query = "SELECT plan_type, COUNT(*) as count FROM subscriptions GROUP BY plan_type";
        $result = $this->db->fetchAll($query);
        $stats['subscriptions_by_type'] = [];
        
        foreach ($result as $row) {
            $stats['subscriptions_by_type'][$row['plan_type']] = $row['count'];
        }
        
        // الاشتراكات التي ستنتهي خلال الشهر القادم
        $query = "SELECT COUNT(*) as count FROM subscriptions 
                 WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
        $result = $this->db->fetchOne($query);
        $stats['expiring_soon'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات خطط الأسعار
     * 
     * @return array إحصائيات الخطط
     */
    private function getPlanStats()
    {
        $stats = [];
        
        // إجمالي الخطط
        $query = "SELECT COUNT(*) as count FROM plans";
        $result = $this->db->fetchOne($query);
        $stats['total_plans'] = $result['count'] ?? 0;
        
        // الخطط النشطة
        $query = "SELECT COUNT(*) as count FROM plans WHERE active = 1";
        $result = $this->db->fetchOne($query);
        $stats['active_plans'] = $result['count'] ?? 0;
        
        // الخطط العامة
        $query = "SELECT COUNT(*) as count FROM plans WHERE is_public = 1";
        $result = $this->db->fetchOne($query);
        $stats['public_plans'] = $result['count'] ?? 0;
        
        // الخطط حسب النوع
        $query = "SELECT type, COUNT(*) as count FROM plans GROUP BY type";
        $result = $this->db->fetchAll($query);
        $stats['plans_by_type'] = [];
        
        foreach ($result as $row) {
            $stats['plans_by_type'][$row['type']] = $row['count'];
        }
        
        // الخطط الأكثر استخدامًا
        $query = "SELECT p.id, p.name, p.type, COUNT(s.id) as usage_count 
                 FROM plans p 
                 LEFT JOIN subscriptions s ON p.id = s.plan_id 
                 GROUP BY p.id, p.name, p.type 
                 ORDER BY usage_count DESC 
                 LIMIT 5";
        $stats['most_used_plans'] = $this->db->fetchAll($query);
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات المدفوعات
     * 
     * @return array إحصائيات المدفوعات
     */
    private function getPaymentStats()
    {
        $stats = [];
        
        // إجمالي الإيرادات
        $query = "SELECT SUM(amount) as total FROM subscription_payments";
        $result = $this->db->fetchOne($query);
        $stats['total_revenue'] = $result['total'] ?? 0;
        
        // إجمالي المدفوعات
        $query = "SELECT COUNT(*) as count FROM subscription_payments";
        $result = $this->db->fetchOne($query);
        $stats['total_payments'] = $result['count'] ?? 0;
        
        // إيرادات العام الحالي
        $query = "SELECT SUM(amount) as total FROM subscription_payments WHERE YEAR(payment_date) = YEAR(CURDATE())";
        $result = $this->db->fetchOne($query);
        $stats['yearly_revenue'] = $result['total'] ?? 0;
        
        // إيرادات الشهر الحالي
        $query = "SELECT SUM(amount) as total FROM subscription_payments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())";
        $result = $this->db->fetchOne($query);
        $stats['monthly_revenue'] = $result['total'] ?? 0;
        
        // المدفوعات حسب الطريقة
        $query = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total FROM subscription_payments GROUP BY payment_method";
        $result = $this->db->fetchAll($query);
        $stats['payments_by_method'] = [];
        
        foreach ($result as $row) {
            $stats['payments_by_method'][$row['payment_method']] = [
                'count' => $row['count'],
                'total' => $row['total']
            ];
        }
        
        // إيرادات السنة حسب الشهر
        $query = "SELECT MONTH(payment_date) as month, SUM(amount) as total 
                 FROM subscription_payments 
                 WHERE YEAR(payment_date) = YEAR(CURDATE()) 
                 GROUP BY MONTH(payment_date)";
        $result = $this->db->fetchAll($query);
        $stats['revenue_by_month'] = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $stats['revenue_by_month'][$i] = 0;
        }
        
        foreach ($result as $row) {
            $stats['revenue_by_month'][$row['month']] = $row['total'];
        }
        
        return $stats;
    }
}