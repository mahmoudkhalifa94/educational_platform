<?php
/**
 * app/controllers/teacher/DashboardController.php
 * متحكم لوحة التحكم للمعلم
 * يعرض الإحصائيات والبيانات الرئيسية للمعلم
 */
class DashboardController extends Controller
{
    private $classModel;
    private $studentModel;
    private $assignmentModel;
    private $submissionModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->classModel = new ClassModel();
        $this->studentModel = new Student();
        $this->assignmentModel = new Assignment();
        $this->submissionModel = new Submission();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * عرض لوحة التحكم
     */
    public function index()
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/login');
        }
        
        // الحصول على إحصائيات المعلم
        $stats = $this->getTeacherStats($teacherId);
        
        // الحصول على الصفوف والمواد للمعلم
        $classes = $this->classModel->getClassesByTeacher($teacherId);
        $subjects = (new Subject())->getSubjectsByTeacher($teacherId);
        
        // الحصول على المهام القادمة
        $upcomingAssignments = $this->assignmentModel->getUpcomingAssignments($teacherId);
        
        // الحصول على آخر المهام المقدمة التي تحتاج إلى تصحيح
        $recentSubmissions = $this->submissionModel->getRecentSubmissions($teacherId);
        
        // الحصول على نشاطات النظام
        $recentActivities = $this->getRecentActivities($teacherId);
        
        // عرض لوحة التحكم
        echo $this->render('teacher/dashboard', [
            'stats' => $stats,
            'classes' => $classes,
            'subjects' => $subjects,
            'upcomingAssignments' => $upcomingAssignments,
            'recentSubmissions' => $recentSubmissions,
            'recentActivities' => $recentActivities,
            'teacher' => (new Teacher())->getTeacherWithDetails($teacherId),
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض الملف الشخصي للمعلم
     */
    public function profile()
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/login');
        }
        
        // الحصول على بيانات المعلم
        $teacher = (new Teacher())->getTeacherWithDetails($teacherId);
        
        // الحصول على الصفوف والمواد للمعلم
        $classes = $this->classModel->getClassesByTeacher($teacherId);
        $subjects = (new Subject())->getSubjectsByTeacher($teacherId);
        
        // عرض صفحة الملف الشخصي
        echo $this->render('teacher/profile', [
            'teacher' => $teacher,
            'classes' => $classes,
            'subjects' => $subjects,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تحديث الملف الشخصي للمعلم
     */
    public function updateProfile()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/profile');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/login');
        }
        
        // الحصول على بيانات المستخدم
        $userId = $this->auth->id();
        $userModel = new User();
        $user = $userModel->find($userId);
        
        if (!$user) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المستخدم.');
            $this->redirect('/teacher/profile');
        }
        
        // استخراج البيانات من النموذج
        $userData = [
            'first_name' => $this->request->post('first_name'),
            'last_name' => $this->request->post('last_name'),
            'email' => $this->request->post('email'),
            'phone' => $this->request->post('phone', '')
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($userData, [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email,' . $userId
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/profile');
        }
        
        // معالجة رفع الصورة الشخصية إذا وجدت
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->uploadFile('profile_picture', 'assets/uploads/profile_pictures', ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024);
            
            if ($uploadResult['success']) {
                $userData['profile_picture'] = $uploadResult['path'];
                
                // حذف الصورة القديمة إذا وجدت
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
            }
        }
        
        // تحديث بيانات المستخدم
        $success = $userModel->update($userId, $userData);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث البيانات. يرجى المحاولة مرة أخرى.');
            $this->redirect('/teacher/profile');
        }
        
        // تحديث كلمة المرور إذا تم تقديمها
        $password = $this->request->post('password');
        $passwordConfirm = $this->request->post('password_confirm');
        
        if (!empty($password)) {
            // التحقق من كلمة المرور
            $passwordErrors = $this->validate([
                'password' => $password,
                'password_confirm' => $passwordConfirm
            ], [
                'password' => 'required|min:8',
                'password_confirm' => 'required|matches:password'
            ]);
            
            if (empty($passwordErrors)) {
                // تحديث كلمة المرور
                $userModel->updatePassword($userId, $password);
            }
        }
        
        // تسجيل النشاط
        $this->logActivity('تحديث الملف الشخصي', 'قام بتحديث بيانات الملف الشخصي');
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم تحديث الملف الشخصي بنجاح.');
        $this->redirect('/teacher/profile');
    }
    
    /**
     * تغيير كلمة المرور
     */
    public function changePassword()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/profile');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/login');
        }
        
        // استخراج البيانات من النموذج
        $currentPassword = $this->request->post('current_password');
        $newPassword = $this->request->post('new_password');
        $confirmPassword = $this->request->post('confirm_password');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
            'confirm_password' => $confirmPassword
        ], [
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required|matches:new_password'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/profile');
        }
        
        // التحقق من كلمة المرور الحالية
        $userId = $this->auth->id();
        $userModel = new User();
        
        if (!$userModel->verifyPassword($userId, $currentPassword)) {
            $this->setFlash('error', 'كلمة المرور الحالية غير صحيحة.');
            $this->redirect('/teacher/profile');
        }
        
        // تحديث كلمة المرور
        $success = $userModel->updatePassword($userId, $newPassword);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث كلمة المرور. يرجى المحاولة مرة أخرى.');
            $this->redirect('/teacher/profile');
        }
        
        // تسجيل النشاط
        $this->logActivity('تغيير كلمة المرور', 'قام بتغيير كلمة المرور');
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم تغيير كلمة المرور بنجاح.');
        $this->redirect('/teacher/profile');
    }
    
    /**
     * الحصول على إحصائيات المعلم
     * 
     * @param int $teacherId معرّف المعلم
     * @return array الإحصائيات
     */
    private function getTeacherStats($teacherId)
    {
        $stats = [];
        
        // عدد الصفوف
        $query = "SELECT COUNT(DISTINCT class_id) as count FROM teacher_assignments WHERE teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['classes_count'] = $result['count'] ?? 0;
        
        // عدد المواد
        $query = "SELECT COUNT(DISTINCT subject_id) as count FROM teacher_assignments WHERE teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['subjects_count'] = $result['count'] ?? 0;
        
        // عدد الطلاب
        $query = "SELECT COUNT(DISTINCT s.id) as count 
                 FROM students s 
                 JOIN teacher_assignments ta ON s.class_id = ta.class_id 
                 WHERE ta.teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['students_count'] = $result['count'] ?? 0;
        
        // عدد المهام
        $query = "SELECT COUNT(*) as count FROM assignments WHERE teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['assignments_count'] = $result['count'] ?? 0;
        
        // عدد المهام المنشورة
        $query = "SELECT COUNT(*) as count FROM assignments WHERE teacher_id = ? AND is_published = 1";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['published_assignments_count'] = $result['count'] ?? 0;
        
        // عدد الإجابات التي تحتاج إلى تصحيح
        $query = "SELECT COUNT(*) as count 
                 FROM submissions sub 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 WHERE a.teacher_id = ? AND sub.status IN ('submitted', 'late') AND sub.grade_id IS NULL";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['pending_submissions_count'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على آخر نشاطات المعلم
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $limit عدد النشاطات
     * @return array النشاطات
     */
    private function getRecentActivities($teacherId, $limit = 10)
    {
        $query = "SELECT a.*, u.first_name, u.last_name 
                 FROM activities a 
                 JOIN users u ON a.user_id = u.id 
                 WHERE a.entity_type = 'teacher' AND a.entity_id = ? 
                 ORDER BY a.created_at DESC 
                 LIMIT ?";
        
        return $this->db->fetchAll($query, [$teacherId, $limit]);
    }
    
    /**
     * الحصول على معرّف المعلم للمستخدم الحالي
     * 
     * @return int|null معرّف المعلم
     */
    private function getTeacherId()
    {
        $userId = $this->auth->id();
        
        if (!$userId) {
            return null;
        }
        
        $teacherModel = new Teacher();
        $teacher = $teacherModel->getTeacherByUserId($userId);
        
        return $teacher ? $teacher['id'] : null;
    }
    
    /**
     * تسجيل نشاط المعلم
     * 
     * @param string $action النشاط
     * @param string $details التفاصيل
     * @return void
     */
    private function logActivity($action, $details)
    {
        (new User())->logActivity(
            $this->auth->id(),
            $action,
            'teacher',
            $this->getTeacherId(),
            ['details' => $details]
        );
    }
}