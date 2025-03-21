<?php
/**
 * app/controllers/admin/UserController.php
 * متحكم إدارة المستخدمين للمدير الرئيسي
 */
class UserController extends Controller
{
    private $userModel;
    private $schoolModel;
    private $roleModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
        $this->schoolModel = new School();
        $this->roleModel = new Role();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('super_admin');
    }
    
    /**
     * عرض قائمة المستخدمين
     */
    public function index()
    {
        // استخراج معلمات البحث والتصفية
        $search = $this->request->get('search', '');
        $role = $this->request->get('role', 'all');
        $school = $this->request->get('school', 'all');
        $status = $this->request->get('status', 'all');
        
        // بناء الاستعلام
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
        
        // إضافة التصفية حسب المدرسة
        if ($school !== 'all') {
            $query .= " AND u.school_id = ?";
            $params[] = $school;
        }
        
        // إضافة التصفية حسب الحالة
        if ($status !== 'all') {
            $query .= " AND u.active = ?";
            $params[] = ($status === 'active') ? 1 : 0;
        }
        
        // إضافة البحث
        if (!empty($search)) {
            $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY u.id DESC";
        
        // تنفيذ الاستعلام
        $users = $this->db->fetchAll($query, $params);
        
        // الحصول على قائمة الأدوار
        $roles = $this->roleModel->getAll();
        
        // الحصول على قائمة المدارس
        $schools = $this->schoolModel->getAll(['id', 'name']);
        
        // عرض القالب
        echo $this->render('admin/users/index', [
            'users' => $users,
            'roles' => $roles,
            'schools' => $schools,
            'search' => $search,
            'selectedRole' => $role,
            'selectedSchool' => $school,
            'selectedStatus' => $status,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج إنشاء مستخدم جديد
     */
    public function create()
    {
        // الحصول على معلمات الإنشاء
        $schoolId = $this->request->get('school_id');
        $roleId = $this->request->get('role');
        
        // الحصول على قائمة الأدوار
        $roles = $this->roleModel->getAll();
        
        // الحصول على قائمة المدارس
        $schools = $this->schoolModel->getAll(['id', 'name']);
        
        echo $this->render('admin/users/create', [
            'roles' => $roles,
            'schools' => $schools,
            'selectedSchool' => $schoolId,
            'selectedRole' => $roleId,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إنشاء مستخدم جديد
     */
    public function store()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/users');
        }
        
        // استخراج البيانات من النموذج
        $userData = [
            'first_name' => $this->request->post('first_name'),
            'last_name' => $this->request->post('last_name'),
            'email' => $this->request->post('email'),
            'username' => $this->request->post('username'),
            'password' => $this->request->post('password'),
            'role_id' => $this->request->post('role_id'),
            'school_id' => $this->request->post('school_id'),
            'phone' => $this->request->post('phone', ''),
            'active' => $this->request->post('active', 0),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // التحقق من البيانات
        $validationRules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|unique:users,username',
            'password' => 'required|min:8',
            'role_id' => 'required|numeric'
        ];
        
        // التحقق من حقل المدرسة إذا كان الدور ليس مديرًا رئيسيًا
        if ($userData['role_id'] != 1) { // افتراضًا أن مدير النظام له الدور 1
            $validationRules['school_id'] = 'required|numeric';
        } else {
            $userData['school_id'] = null; // مدير النظام ليس مرتبطًا بمدرسة
        }
        
        $errors = $this->validate($userData, $validationRules);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect('/admin/users/create');
        }
        
        // معالجة رفع الصورة الشخصية إذا تم تقديمها
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->uploadFile('profile_picture', 'assets/uploads/profile_pictures', ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024);
            
            if ($uploadResult['success']) {
                $userData['profile_picture'] = $uploadResult['path'];
            }
        }
        
        // إنشاء المستخدم
        $userId = $this->userModel->createUser($userData);
        
        if (!$userId) {
            $this->setFlash('error', 'حدث خطأ أثناء إنشاء المستخدم. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/users/create');
        }
        
        // إذا كان الدور معلمًا، طالبًا، أو ولي أمر، قم بإنشاء السجل المناسب
        $roleName = $this->roleModel->find($userData['role_id'])['name'];
        
        switch ($roleName) {
            case 'teacher':
                $this->createTeacherRecord($userId, $userData);
                break;
                
            case 'student':
                $this->createStudentRecord($userId, $userData);
                break;
                
            case 'parent':
                $this->createParentRecord($userId, $userData);
                break;
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إنشاء مستخدم',
            'user',
            $userId,
            ['name' => $userData['first_name'] . ' ' . $userData['last_name'], 'role' => $roleName]
        );
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم إنشاء المستخدم بنجاح.');
        $this->redirect('/admin/users');
    }
    
    /**
     * عرض تفاصيل مستخدم
     * 
     * @param int $id معرّف المستخدم
     */
    public function show($id)
    {
        // الحصول على بيانات المستخدم
        $user = $this->userModel->getUserWithDetails($id);
        
        if (!$user) {
            $this->setFlash('error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
        }
        
        // الحصول على سجلات النشاط
        $activities = $this->userModel->getUserActivities($id);
        
        // الحصول على معلومات إضافية حسب نوع المستخدم
        $additionalInfo = $this->getAdditionalUserInfo($user);
        
        echo $this->render('admin/users/show', [
            'user' => $user,
            'activities' => $activities,
            'additionalInfo' => $additionalInfo,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج تعديل مستخدم
     * 
     * @param int $id معرّف المستخدم
     */
    public function edit($id)
    {
        // الحصول على بيانات المستخدم
        $user = $this->userModel->getUserWithDetails($id);
        
        if (!$user) {
            $this->setFlash('error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
        }
        
        // الحصول على قائمة الأدوار
        $roles = $this->roleModel->getAll();
        
        // الحصول على قائمة المدارس
        $schools = $this->schoolModel->getAll(['id', 'name']);
        
        // الحصول على معلومات إضافية حسب نوع المستخدم
        $additionalInfo = $this->getAdditionalUserInfo($user);
        
        echo $this->render('admin/users/edit', [
            'user' => $user,
            'roles' => $roles,
            'schools' => $schools,
            'additionalInfo' => $additionalInfo,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة تحديث بيانات مستخدم
     * 
     * @param int $id معرّف المستخدم
     */
    public function update($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/users');
        }
        
        // الحصول على بيانات المستخدم الحالية
        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->setFlash('error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
        }
        
        // استخراج البيانات من النموذج
        $userData = [
            'first_name' => $this->request->post('first_name'),
            'last_name' => $this->request->post('last_name'),
            'email' => $this->request->post('email'),
            'username' => $this->request->post('username'),
            'role_id' => $this->request->post('role_id'),
            'school_id' => $this->request->post('school_id'),
            'phone' => $this->request->post('phone', ''),
            'active' => $this->request->post('active', 0),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // التحقق من البيانات
        $validationRules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'username' => 'required|unique:users,username,' . $id,
            'role_id' => 'required|numeric'
        ];
        
        // التحقق من حقل المدرسة إذا كان الدور ليس مديرًا رئيسيًا
        if ($userData['role_id'] != 1) { // افتراضًا أن مدير النظام له الدور 1
            $validationRules['school_id'] = 'required|numeric';
        } else {
            $userData['school_id'] = null; // مدير النظام ليس مرتبطًا بمدرسة
        }
        
        $errors = $this->validate($userData, $validationRules);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يوجد أخطاء في البيانات المدخلة.');
            $this->redirect('/admin/users/edit/' . $id);
        }
        
        // معالجة رفع الصورة الشخصية إذا تم تقديمها
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->uploadFile('profile_picture', 'assets/uploads/profile_pictures', ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024);
            
            if ($uploadResult['success']) {
                $userData['profile_picture'] = $uploadResult['path'];
                
                // حذف الصورة القديمة إذا كانت موجودة
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
            }
        }
        
        // تحديث كلمة المرور إذا تم تقديمها
        $password = $this->request->post('password');
        if (!empty($password)) {
            // التحقق من كلمة المرور
            $passwordErrors = $this->validate(['password' => $password], ['password' => 'required|min:8']);
            
            if (empty($passwordErrors)) {
                $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
        } else {
            // إزالة حقل كلمة المرور من البيانات المحدثة
            unset($userData['password']);
        }
        
        // تحديث بيانات المستخدم
        $success = $this->userModel->update($id, $userData);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث بيانات المستخدم. يرجى المحاولة مرة أخرى.');
            $this->redirect('/admin/users/edit/' . $id);
        }
        
        // إذا تغير دور المستخدم، قم بإنشاء أو تحديث السجل المناسب
        $roleChanged = $user['role_id'] != $userData['role_id'];
        $newRoleName = $this->roleModel->find($userData['role_id'])['name'];
        $oldRoleName = $this->roleModel->find($user['role_id'])['name'];
        
        if ($roleChanged) {
            // إنشاء سجل جديد للدور الجديد
            switch ($newRoleName) {
                case 'teacher':
                    $this->createTeacherRecord($id, $userData);
                    break;
                    
                case 'student':
                    $this->createStudentRecord($id, $userData);
                    break;
                    
                case 'parent':
                    $this->createParentRecord($id, $userData);
                    break;
            }
            
            // حذف السجل القديم
            switch ($oldRoleName) {
                case 'teacher':
                    $this->db->delete('teachers', ['user_id' => $id]);
                    break;
                    
                case 'student':
                    $this->db->delete('students', ['user_id' => $id]);
                    break;
                    
                case 'parent':
                    $this->db->delete('parents', ['user_id' => $id]);
                    break;
            }
        } else {
            // تحديث السجل الحالي إذا لزم الأمر
            $this->updateUserRecord($id, $newRoleName, $userData);
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تعديل مستخدم',
            'user',
            $id,
            ['name' => $userData['first_name'] . ' ' . $userData['last_name'], 'role' => $newRoleName]
        );
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم تحديث بيانات المستخدم بنجاح.');
        $this->redirect('/admin/users/show/' . $id);
    }
    
    /**
     * حذف مستخدم
     * 
     * @param int $id معرّف المستخدم
     */
    public function delete($id)
    {
        // الحصول على بيانات المستخدم
        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->setFlash('error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
        }
        
        // منع حذف حساب مدير النظام الرئيسي
        if ($user['role_id'] == 1 && $user['id'] == 1) {
            $this->setFlash('error', 'لا يمكن حذف حساب مدير النظام الرئيسي.');
            $this->redirect('/admin/users');
        }
        
        // حذف الصورة الشخصية إذا كانت موجودة
        if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
            unlink($user['profile_picture']);
        }
        
        // حذف المستخدم (soft delete)
        $success = $this->userModel->update($id, [
            'active' => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
            'email' => $user['email'] . '_deleted_' . time(),
            'username' => $user['username'] . '_deleted_' . time()
        ]);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء حذف المستخدم.');
            $this->redirect('/admin/users');
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'حذف مستخدم',
            'user',
            $id,
            ['name' => $user['first_name'] . ' ' . $user['last_name']]
        );
        
        $this->setFlash('success', 'تم حذف المستخدم بنجاح.');
        $this->redirect('/admin/users');
    }
    
    /**
     * تفعيل/تعطيل مستخدم
     * 
     * @param int $id معرّف المستخدم
     */
    public function toggleActive($id)
    {
        // الحصول على بيانات المستخدم
        $user = $this->userModel->find($id);
        
        if (!$user) {
            $this->json(['success' => false, 'message' => 'المستخدم غير موجود']);
        }
        
        // تغيير الحالة
        $newStatus = $user['active'] ? 0 : 1;
        $success = $this->userModel->update($id, ['active' => $newStatus]);
        
        if (!$success) {
            $this->json(['success' => false, 'message' => 'حدث خطأ أثناء تحديث حالة المستخدم']);
        }
        
        // تسجيل النشاط
        $actionText = $newStatus ? 'تفعيل مستخدم' : 'تعطيل مستخدم';
        $this->userModel->logActivity(
            $this->auth->id(),
            $actionText,
            'user',
            $id,
            ['name' => $user['first_name'] . ' ' . $user['last_name']]
        );
        
        $this->json([
            'success' => true,
            'message' => $newStatus ? 'تم تفعيل المستخدم بنجاح' : 'تم تعطيل المستخدم بنجاح',
            'new_status' => $newStatus
        ]);
    }
    
    /**
     * إعادة تعيين كلمة مرور مستخدم
     */
    public function resetPassword()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/users');
        }
        
        // استخراج البيانات
        $userId = $this->request->post('user_id');
        
        // الحصول على بيانات المستخدم
        $user = $this->userModel->find($userId);
        
        if (!$user) {
            $this->setFlash('error', 'المستخدم غير موجود.');
            $this->redirect('/admin/users');
        }
        
        // توليد كلمة مرور عشوائية
        $newPassword = $this->generateRandomPassword();
        
        // تحديث كلمة المرور
        $success = $this->userModel->updatePassword($userId, $newPassword);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء إعادة تعيين كلمة المرور.');
            $this->redirect('/admin/users');
        }
        
        // إرسال كلمة المرور عبر البريد الإلكتروني (يمكن تنفيذ هذا لاحقًا)
        // $this->sendPasswordResetEmail($user['email'], $newPassword);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إعادة تعيين كلمة المرور',
            'user',
            $userId,
            ['name' => $user['first_name'] . ' ' . $user['last_name']]
        );
        
        $this->setFlash('success', 'تم إعادة تعيين كلمة المرور بنجاح. كلمة المرور الجديدة: ' . $newPassword);
        $this->redirect('/admin/users/show/' . $userId);
    }
    
    /**
     * إنشاء سجل معلم
     * 
     * @param int $userId معرّف المستخدم
     * @param array $userData بيانات المستخدم
     */
    private function createTeacherRecord($userId, $userData)
    {
        // التحقق من وجود سجل سابق
        $teacher = $this->db->fetchOne("SELECT id FROM teachers WHERE user_id = ?", [$userId]);
        
        if ($teacher) {
            return; // السجل موجود بالفعل
        }
        
        // بيانات المعلم
        $teacherData = [
            'user_id' => $userId,
            'school_id' => $userData['school_id'],
            'employee_id' => $this->request->post('employee_id', ''),
            'specialization' => $this->request->post('specialization', ''),
            'qualification' => $this->request->post('qualification', ''),
            'join_date' => $this->request->post('join_date', date('Y-m-d')),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // إنشاء سجل المعلم
        $this->db->insert('teachers', $teacherData);
    }
    
    /**
     * إنشاء سجل طالب
     * 
     * @param int $userId معرّف المستخدم
     * @param array $userData بيانات المستخدم
     */
    private function createStudentRecord($userId, $userData)
    {
        // التحقق من وجود سجل سابق
        $student = $this->db->fetchOne("SELECT id FROM students WHERE user_id = ?", [$userId]);
        
        if ($student) {
            return; // السجل موجود بالفعل
        }
        
        // بيانات الطالب
        $studentData = [
            'user_id' => $userId,
            'school_id' => $userData['school_id'],
            'class_id' => $this->request->post('class_id', null),
            'student_id' => $this->request->post('student_id', ''),
            'grade_level' => $this->request->post('grade_level', ''),
            'enrollment_date' => $this->request->post('enrollment_date', date('Y-m-d')),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // إنشاء سجل الطالب
        $this->db->insert('students', $studentData);
    }
    
    /**
     * إنشاء سجل ولي أمر
     * 
     * @param int $userId معرّف المستخدم
     * @param array $userData بيانات المستخدم
     */
    private function createParentRecord($userId, $userData)
    {
        // التحقق من وجود سجل سابق
        $parent = $this->db->fetchOne("SELECT id FROM parents WHERE user_id = ?", [$userId]);
        
        if ($parent) {
            return; // السجل موجود بالفعل
        }
        
        // بيانات ولي الأمر
        $parentData = [
            'user_id' => $userId,
            'school_id' => $userData['school_id'],
            'occupation' => $this->request->post('occupation', ''),
            'relationship' => $this->request->post('relationship', ''),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // إنشاء سجل ولي الأمر
        $this->db->insert('parents', $parentData);
        
        // ربط ولي الأمر بالطلاب إذا تم تحديدهم
        $studentIds = $this->request->post('student_ids', []);
        
        if (!empty($studentIds) && is_array($studentIds)) {
            $parentId = $this->db->lastInsertId();
            
            foreach ($studentIds as $studentId) {
                $this->db->insert('parent_student', [
                    'parent_id' => $parentId,
                    'student_id' => $studentId
                ]);
            }
        }
    }
    
    /**
     * تحديث سجل المستخدم الإضافي
     * 
     * @param int $userId معرّف المستخدم
     * @param string $roleName اسم الدور
     * @param array $userData بيانات المستخدم
     */
    private function updateUserRecord($userId, $roleName, $userData)
    {
        switch ($roleName) {
            case 'teacher':
                $teacherData = [
                    'school_id' => $userData['school_id'],
                    'employee_id' => $this->request->post('employee_id', ''),
                    'specialization' => $this->request->post('specialization', ''),
                    'qualification' => $this->request->post('qualification', ''),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->update('teachers', $teacherData, ['user_id' => $userId]);
                break;
                
            case 'student':
                $studentData = [
                    'school_id' => $userData['school_id'],
                    'class_id' => $this->request->post('class_id', null),
                    'student_id' => $this->request->post('student_id', ''),
                    'grade_level' => $this->request->post('grade_level', ''),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->update('students', $studentData, ['user_id' => $userId]);
                break;
                
            case 'parent':
                $parentData = [
                    'school_id' => $userData['school_id'],
                    'occupation' => $this->request->post('occupation', ''),
                    'relationship' => $this->request->post('relationship', ''),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->update('parents', $parentData, ['user_id' => $userId]);
                
                // تحديث ربط ولي الأمر بالطلاب
                $parentId = $this->db->fetchOne("SELECT id FROM parents WHERE user_id = ?", [$userId])['id'];
                $studentIds = $this->request->post('student_ids', []);
                
                if (!empty($studentIds) && is_array($studentIds)) {
                    // حذف العلاقات القديمة
                    $this->db->delete('parent_student', ['parent_id' => $parentId]);
                    
                    // إضافة العلاقات الجديدة
                    foreach ($studentIds as $studentId) {
                        $this->db->insert('parent_student', [
                            'parent_id' => $parentId,
                            'student_id' => $studentId
                        ]);
                    }
                }
                break;
        }
    }
    
    /**
     * الحصول على معلومات إضافية للمستخدم حسب دوره
     * 
     * @param array $user بيانات المستخدم
     * @return array المعلومات الإضافية
     */
    private function getAdditionalUserInfo($user)
    {
        $info = [];
        
        switch ($user['role_name']) {
            case 'teacher':
                // الحصول على بيانات المعلم
                $teacher = $this->db->fetchOne("SELECT * FROM teachers WHERE user_id = ?", [$user['id']]);
                
                if ($teacher) {
                    $info['teacher'] = $teacher;
                    
                    // الحصول على الصفوف والمواد التي يدرسها المعلم
                    $info['classes'] = $this->db->fetchAll(
                        "SELECT c.id, c.name, c.grade_level FROM classes c 
                         JOIN teacher_assignments ta ON c.id = ta.class_id 
                         WHERE ta.teacher_id = ? 
                         GROUP BY c.id, c.name, c.grade_level",
                        [$teacher['id']]
                    );
                    
                    $info['subjects'] = $this->db->fetchAll(
                        "SELECT s.id, s.name, s.code FROM subjects s 
                         JOIN teacher_assignments ta ON s.id = ta.subject_id 
                         WHERE ta.teacher_id = ? 
                         GROUP BY s.id, s.name, s.code",
                        [$teacher['id']]
                    );
                }
                break;
                
            case 'student':
                // الحصول على بيانات الطالب
                $student = $this->db->fetchOne(
                    "SELECT s.*, c.name as class_name, c.grade_level 
                     FROM students s 
                     LEFT JOIN classes c ON s.class_id = c.id 
                     WHERE s.user_id = ?",
                    [$user['id']]
                );
                
                if ($student) {
                    $info['student'] = $student;
                    
                    // الحصول على أولياء الأمور المرتبطين بالطالب
                    $info['parents'] = $this->db->fetchAll(
                        "SELECT p.id, u.first_name, u.last_name, u.email, p.relationship 
                         FROM parents p 
                         JOIN users u ON p.user_id = u.id 
                         JOIN parent_student ps ON p.id = ps.parent_id 
                         WHERE ps.student_id = ?",
                        [$student['id']]
                    );
                }
                break;
                
            case 'parent':
                // الحصول على بيانات ولي الأمر
                $parent = $this->db->fetchOne("SELECT * FROM parents WHERE user_id = ?", [$user['id']]);
                
                if ($parent) {
                    $info['parent'] = $parent;
                    
                    // الحصول على الطلاب المرتبطين بولي الأمر
                    $info['students'] = $this->db->fetchAll(
                        "SELECT s.id, s.student_id, u.first_name, u.last_name, c.name as class_name, c.grade_level 
                         FROM students s 
                         JOIN users u ON s.user_id = u.id 
                         LEFT JOIN classes c ON s.class_id = c.id 
                         JOIN parent_student ps ON s.id = ps.student_id 
                         WHERE ps.parent_id = ?",
                        [$parent['id']]
                    );
                }
                break;
                
            case 'school_admin':
                // الحصول على مدرسة المدير
                $info['school'] = $this->db->fetchOne("SELECT * FROM schools WHERE id = ?", [$user['school_id']]);
                
                // الحصول على إحصائيات المدرسة
                if (!empty($info['school'])) {
                    $school_id = $info['school']['id'];
                    
                    $info['stats'] = [
                        'teachers_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM teachers WHERE school_id = ?", [$school_id])['count'],
                        'students_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM students WHERE school_id = ?", [$school_id])['count'],
                        'classes_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM classes WHERE school_id = ?", [$school_id])['count'],
                        'subjects_count' => $this->db->fetchOne("SELECT COUNT(*) as count FROM subjects WHERE school_id = ?", [$school_id])['count']
                    ];
                }
                break;
        }
        
        return $info;
    }
    
    /**
     * توليد كلمة مرور عشوائية
     * 
     * @param int $length طول كلمة المرور
     * @return string كلمة المرور
     */
    private function generateRandomPassword($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_=+';
        $password = '';
        $charactersLength = strlen($characters);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $password;
    }
}