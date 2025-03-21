<?php
/**
 * app/controllers/teacher/AssignmentController.php
 * متحكم المهام للمعلمين
 * يدير عمليات إنشاء وتعديل وحذف المهام
 */
class AssignmentController extends Controller
{
    private $assignmentModel;
    private $classModel;
    private $subjectModel;
    private $submissionModel;
    private $gradeModel;
    private $notificationModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->assignmentModel = new Assignment();
        $this->classModel = new ClassModel();
        $this->subjectModel = new Subject();
        $this->submissionModel = new Submission();
        $this->gradeModel = new Grade();
        $this->notificationModel = new Notification();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * عرض قائمة المهام
     */
    public function index()
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج معلمات التصفية
        $classId = $this->request->get('class_id');
        $subjectId = $this->request->get('subject_id');
        $status = $this->request->get('status', 'all');
        
        // الحصول على قائمة المهام
        $assignments = $this->assignmentModel->getTeacherAssignments($teacherId, $classId, $subjectId, $status);
        
        // الحصول على الصفوف والمواد للمعلم
        $classes = $this->classModel->getClassesByTeacher($teacherId);
        $subjects = $this->subjectModel->getSubjectsByTeacher($teacherId);
        
        // عرض الصفحة
        echo $this->render('teacher/assignments/index', [
            'assignments' => $assignments,
            'classes' => $classes,
            'subjects' => $subjects,
            'selectedClass' => $classId,
            'selectedSubject' => $subjectId,
            'selectedStatus' => $status,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج إنشاء مهمة جديدة
     */
    public function create()
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على الصفوف والمواد للمعلم
        $classes = $this->classModel->getClassesByTeacher($teacherId);
        $subjects = $this->subjectModel->getSubjectsByTeacher($teacherId);
        
        // عرض نموذج الإنشاء
        echo $this->render('teacher/assignments/create', [
            'classes' => $classes,
            'subjects' => $subjects,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إنشاء مهمة جديدة
     */
    public function store()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج بيانات المهمة
        $assignmentData = [
            'teacher_id' => $teacherId,
            'class_id' => $this->request->post('class_id'),
            'subject_id' => $this->request->post('subject_id'),
            'title' => $this->request->post('title'),
            'description' => $this->request->post('description'),
            'type' => $this->request->post('type'),
            'points' => $this->request->post('points'),
            'due_date' => $this->request->post('due_date'),
            'is_published' => $this->request->post('is_published', 0),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($assignmentData, [
            'class_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'title' => 'required|min:3',
            'points' => 'required|numeric|min:1',
            'due_date' => 'required|date',
            'type' => 'required|in:homework,quiz,project,exam'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/assignments/create');
        }
        
        // التحقق من صلاحية الوصول للمعلم للصف والمادة
        $hasAccess = $this->checkTeacherAccess($teacherId, $assignmentData['class_id'], $assignmentData['subject_id']);
        
        if (!$hasAccess) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف أو المادة.');
            $this->redirect('/teacher/assignments/create');
        }
        
        // رفع الملفات المرفقة إن وجدت
        $attachmentFiles = [];
        
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $fileCount = count($_FILES['attachments']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['attachments']['name'][$i],
                        'type' => $_FILES['attachments']['type'][$i],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                        'error' => $_FILES['attachments']['error'][$i],
                        'size' => $_FILES['attachments']['size'][$i]
                    ];
                    
                    $uploadResult = $this->uploadFile($file, 'assets/uploads/assignments', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip']);
                    
                    if ($uploadResult['success']) {
                        $attachmentFiles[] = [
                            'file_name' => $file['name'],
                            'file_path' => $uploadResult['path'],
                            'file_type' => $file['type']
                        ];
                    }
                }
            }
        }
        
        // إنشاء المهمة
        $assignmentId = $this->assignmentModel->createAssignment($assignmentData, $attachmentFiles);
        
        if (!$assignmentId) {
            $this->setFlash('error', 'حدث خطأ أثناء إنشاء المهمة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/teacher/assignments/create');
        }
        
        // إرسال إشعارات للطلاب إذا تم نشر المهمة
        if ($assignmentData['is_published']) {
            $this->notifyStudents($assignmentId, 'new_assignment');
        }
        
        // تسجيل النشاط
        $this->logActivity('إنشاء مهمة', 'تم إنشاء مهمة جديدة: ' . $assignmentData['title']);
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم إنشاء المهمة بنجاح.');
        $this->redirect('/teacher/assignments/show/' . $assignmentId);
    }
    
    /**
     * عرض تفاصيل مهمة
     * 
     * @param int $id معرّف المهمة
     */
    public function show($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($id);
        
        if (!$assignment) {
            $this->setFlash('error', 'المهمة غير موجودة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على ملفات المهمة
        $attachments = $this->assignmentModel->getAssignmentAttachments($id);
        
        // الحصول على إحصائيات الإجابات
        $submissionStats = $this->submissionModel->getSubmissionStats($id);
        
        // الحصول على قائمة الإجابات
        $submissions = $this->submissionModel->getSubmissionsByAssignment($id);
        
        // عرض صفحة التفاصيل
        echo $this->render('teacher/assignments/show', [
            'assignment' => $assignment,
            'attachments' => $attachments,
            'stats' => $submissionStats,
            'submissions' => $submissions,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض نموذج تعديل مهمة
     * 
     * @param int $id معرّف المهمة
     */
    public function edit($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($id);
        
        if (!$assignment) {
            $this->setFlash('error', 'المهمة غير موجودة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على ملفات المهمة
        $attachments = $this->assignmentModel->getAssignmentAttachments($id);
        
        // الحصول على الصفوف والمواد للمعلم
        $classes = $this->classModel->getClassesByTeacher($teacherId);
        $subjects = $this->subjectModel->getSubjectsByTeacher($teacherId);
        
        // عرض نموذج التعديل
        echo $this->render('teacher/assignments/edit', [
            'assignment' => $assignment,
            'attachments' => $attachments,
            'classes' => $classes,
            'subjects' => $subjects,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة تعديل مهمة
     * 
     * @param int $id معرّف المهمة
     */
    public function update($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على بيانات المهمة الحالية
        $assignment = $this->assignmentModel->find($id);
        
        if (!$assignment) {
            $this->setFlash('error', 'المهمة غير موجودة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // استخراج بيانات المهمة الجديدة
        $assignmentData = [
            'class_id' => $this->request->post('class_id'),
            'subject_id' => $this->request->post('subject_id'),
            'title' => $this->request->post('title'),
            'description' => $this->request->post('description'),
            'type' => $this->request->post('type'),
            'points' => $this->request->post('points'),
            'due_date' => $this->request->post('due_date'),
            'is_published' => $this->request->post('is_published', 0),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // التحقق من البيانات
        $errors = $this->validate($assignmentData, [
            'class_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'title' => 'required|min:3',
            'points' => 'required|numeric|min:1',
            'due_date' => 'required|date',
            'type' => 'required|in:homework,quiz,project,exam'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/assignments/edit/' . $id);
        }
        
        // التحقق من صلاحية الوصول للمعلم للصف والمادة
        $hasAccess = $this->checkTeacherAccess($teacherId, $assignmentData['class_id'], $assignmentData['subject_id']);
        
        if (!$hasAccess) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف أو المادة.');
            $this->redirect('/teacher/assignments/edit/' . $id);
        }
        
        // رفع الملفات المرفقة الجديدة إن وجدت
        $attachmentFiles = [];
        
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $fileCount = count($_FILES['attachments']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['attachments']['name'][$i],
                        'type' => $_FILES['attachments']['type'][$i],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                        'error' => $_FILES['attachments']['error'][$i],
                        'size' => $_FILES['attachments']['size'][$i]
                    ];
                    
                    $uploadResult = $this->uploadFile($file, 'assets/uploads/assignments', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip']);
                    
                    if ($uploadResult['success']) {
                        $attachmentFiles[] = [
                            'file_name' => $file['name'],
                            'file_path' => $uploadResult['path'],
                            'file_type' => $file['type']
                        ];
                    }
                }
            }
        }
        
        // حذف الملفات المرفقة المحددة
        $deleteAttachments = $this->request->post('delete_attachments', []);
        
        if (!empty($deleteAttachments)) {
            foreach ($deleteAttachments as $attachmentId) {
                $this->assignmentModel->deleteAttachment($attachmentId);
            }
        }
        
        // تحديث المهمة
        $success = $this->assignmentModel->updateAssignment($id, $assignmentData, $attachmentFiles);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث المهمة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/teacher/assignments/edit/' . $id);
        }
        
        // إرسال إشعارات للطلاب إذا تم نشر المهمة
        if ($assignmentData['is_published'] && !$assignment['is_published']) {
            $this->notifyStudents($id, 'new_assignment');
        } elseif ($assignmentData['is_published'] && $assignment['is_published']) {
            $this->notifyStudents($id, 'updated_assignment');
        }
        
        // تسجيل النشاط
        $this->logActivity('تعديل مهمة', 'تم تعديل المهمة: ' . $assignmentData['title']);
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم تحديث المهمة بنجاح.');
        $this->redirect('/teacher/assignments/show/' . $id);
    }
    
    /**
     * تغيير حالة نشر المهمة
     * 
     * @param int $id معرّف المهمة
     */
    public function togglePublish($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->json(['success' => false, 'message' => 'لم يتم العثور على بيانات المعلم.']);
            return;
        }
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->find($id);
        
        if (!$assignment) {
            $this->json(['success' => false, 'message' => 'المهمة غير موجودة.']);
            return;
        }
        
        // التحقق من صلاحية الوصول للمعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->json(['success' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذه المهمة.']);
            return;
        }
        
        // تغيير حالة النشر
        $newStatus = $assignment['is_published'] ? 0 : 1;
        $result = $this->assignmentModel->togglePublish($id, $newStatus);
        
        if (!$result) {
            $this->json(['success' => false, 'message' => 'حدث خطأ أثناء تغيير حالة النشر.']);
            return;
        }
        
        // إرسال إشعارات للطلاب إذا تم نشر المهمة
        if ($newStatus) {
            $this->notifyStudents($id, 'new_assignment');
        }
        
        // تسجيل النشاط
        $action = $newStatus ? 'نشر مهمة' : 'إلغاء نشر مهمة';
        $this->logActivity($action, 'تم ' . $action . ': ' . $assignment['title']);
        
        // إرجاع النتيجة
        $this->json([
            'success' => true, 
            'message' => $newStatus ? 'تم نشر المهمة بنجاح.' : 'تم إلغاء نشر المهمة بنجاح.',
            'status' => $newStatus
        ]);
    }
    
    /**
     * حذف مهمة
     * 
     * @param int $id معرّف المهمة
     */
    public function delete($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->find($id);
        
        if (!$assignment) {
            $this->setFlash('error', 'المهمة غير موجودة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // حذف المهمة
        $success = $this->assignmentModel->delete($id);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء حذف المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // تسجيل النشاط
        $this->logActivity('حذف مهمة', 'تم حذف المهمة: ' . $assignment['title']);
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم حذف المهمة بنجاح.');
        $this->redirect('/teacher/assignments');
    }
    
    /**
     * عرض تحليلات المهمة
     * 
     * @param int $id معرّف المهمة
     */
    public function analytics($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($id);
        
        if (!$assignment) {
            $this->setFlash('error', 'المهمة غير موجودة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على إحصائيات الإجابات
        $stats = $this->submissionModel->getDetailedSubmissionStats($id);
        
        // الحصول على توزيع الدرجات
        $gradesDistribution = $this->gradeModel->getGradesDistribution($id);
        
        // عرض صفحة التحليلات
        echo $this->render('teacher/assignments/analytics', [
            'assignment' => $assignment,
            'stats' => $stats,
            'gradesDistribution' => $gradesDistribution,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تنزيل نموذج لرفع الدرجات
     * 
     * @param int $id معرّف المهمة
     */
    public function downloadGradesTemplate($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($id);
        
        if (!$assignment) {
            $this->setFlash('error', 'المهمة غير موجودة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على قائمة الطلاب في الصف
        $students = $this->classModel->getStudentsByClass($assignment['class_id']);
        
        // إنشاء ملف إكسل
        require_once 'vendor/autoload.php';
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        
        // إعداد العناوين
        $sheet->setCellValue('A1', 'رقم الطالب');
        $sheet->setCellValue('B1', 'اسم الطالب');
        $sheet->setCellValue('C1', 'الدرجة (من ' . $assignment['points'] . ')');
        $sheet->setCellValue('D1', 'ملاحظات');
        
        // إضافة بيانات الطلاب
        $row = 2;
        foreach ($students as $student) {
            $sheet->setCellValue('A' . $row, $student['student_id']);
            $sheet->setCellValue('B' . $row, $student['first_name'] . ' ' . $student['last_name']);
            $row++;
        }
        
        // تنسيق الملف
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(40);
        
        // تحضير الملف للتنزيل
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // تحديد اسم الملف
        $fileName = 'درجات_' . preg_replace('/[^A-Za-z0-9_-]/', '', $assignment['title']) . '_' . date('Y-m-d') . '.xlsx';
        
        // إرسال الملف
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
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
     * التحقق من صلاحية المعلم للوصول إلى صف ومادة
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $classId معرّف الصف
     * @param int $subjectId معرّف المادة
     * @return bool هل لديه صلاحية الوصول
     */
    private function checkTeacherAccess($teacherId, $classId, $subjectId)
    {
        if (!$teacherId || !$classId || !$subjectId) {
            return false;
        }
        
        $query = "SELECT COUNT(*) as count 
                 FROM teacher_assignments 
                 WHERE teacher_id = ? AND class_id = ? AND subject_id = ?";
        
        $result = $this->db->fetchOne($query, [$teacherId, $classId, $subjectId]);
        
        return $result['count'] > 0;
    }
    
    /**
     * إرسال إشعارات للطلاب
     * 
     * @param int $assignmentId معرّف المهمة
     * @param string $type نوع الإشعار
     */
    private function notifyStudents($assignmentId, $type)
    {
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($assignmentId);
        
        if (!$assignment) {
            return;
        }
        
        // الحصول على قائمة الطلاب في الصف
        $students = $this->classModel->getStudentsByClass($assignment['class_id']);
        
        if (empty($students)) {
            return;
        }
        
        // تحديد عنوان ومحتوى الإشعار
        $title = '';
        $message = '';
        
        switch ($type) {
            case 'new_assignment':
                $title = 'مهمة جديدة: ' . $assignment['title'];
                $message = 'تم إضافة مهمة جديدة في مادة ' . $assignment['subject_name'] . '. تاريخ التسليم: ' . date('Y-m-d', strtotime($assignment['due_date']));
                break;
                
            case 'updated_assignment':
                $title = 'تحديث المهمة: ' . $assignment['title'];
                $message = 'تم تحديث المهمة في مادة ' . $assignment['subject_name'] . '. تاريخ التسليم: ' . date('Y-m-d', strtotime($assignment['due_date']));
                break;
        }
        
        if (empty($title) || empty($message)) {
            return;
        }
        
        // إنشاء إشعارات للطلاب
        foreach ($students as $student) {
            $this->notificationModel->create([
                'user_id' => $student['user_id'],
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'entity_type' => 'assignment',
                'entity_id' => $assignmentId,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
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