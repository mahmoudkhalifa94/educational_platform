<?php
/**
 * app/controllers/teacher/BulkGradeController.php
 * متحكم التصحيح الجماعي للمعلمين
 * يدير عمليات تصحيح إجابات متعددة دفعة واحدة
 */
class BulkGradeController extends Controller
{
    private $assignmentModel;
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
        $this->submissionModel = new Submission();
        $this->gradeModel = new Grade();
        $this->notificationModel = new Notification();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * عرض صفحة التصحيح الجماعي لمهمة معينة
     * 
     * @param int $id معرّف المهمة
     */
    public function index($id)
    {
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($id);
        
        if (!$assignment) {
            $this->setFlash('error', 'المهمة غير موجودة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        $teacherId = $this->getTeacherId();
        
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على الإجابات المقدمة
        $submissions = $this->submissionModel->getAssignmentSubmissions($id);
        
        // الحصول على إحصائيات المهمة
        $stats = $this->assignmentModel->getAssignmentStats($id);
        
        echo $this->render('teacher/bulk_grade', [
            'assignment' => $assignment,
            'submissions' => $submissions,
            'stats' => $stats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة طلب التصحيح الجماعي
     * 
     * @param int $id معرّف المهمة
     */
    public function process($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.']);
            return;
        }
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->find($id);
        
        if (!$assignment) {
            $this->json(['success' => false, 'message' => 'المهمة غير موجودة.']);
            return;
        }
        
        // التحقق من صلاحية الوصول للمعلم
        $teacherId = $this->getTeacherId();
        
        if ($assignment['teacher_id'] != $teacherId) {
            $this->json(['success' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذه المهمة.']);
            return;
        }
        
        // استخراج البيانات من النموذج
        $selected = $this->request->post('selected', []);
        $points = $this->request->post('points', []);
        $feedback = $this->request->post('feedback', []);
        
        if (empty($selected)) {
            $this->json(['success' => false, 'message' => 'لم يتم تحديد أي إجابة للتصحيح.']);
            return;
        }
        
        // إعداد بيانات التصحيح
        $gradingData = [];
        $gradedSubmissions = [];
        
        foreach ($selected as $submissionId) {
            // التحقق من وجود درجة
            if (!isset($points[$submissionId]) || $points[$submissionId] === '') {
                continue;
            }
            
            // التحقق من صحة الدرجة
            $pointsValue = floatval($points[$submissionId]);
            if ($pointsValue < 0 || $pointsValue > $assignment['points']) {
                continue;
            }
            
            // إضافة بيانات التصحيح
            $gradingData[] = [
                'submission_id' => $submissionId,
                'points' => $pointsValue,
                'feedback' => $feedback[$submissionId] ?? '',
                'graded_by' => $this->auth->id()
            ];
            
            $gradedSubmissions[] = $submissionId;
        }
        
        if (empty($gradingData)) {
            $this->json(['success' => false, 'message' => 'لم يتم توفير درجات صالحة للتصحيح.']);
            return;
        }
        
        // تنفيذ عملية التصحيح الجماعي
        $result = $this->gradeModel->bulkImportGrades($gradingData, $this->auth->id());
        
        if ($result['success'] === 0) {
            $this->json(['success' => false, 'message' => 'فشلت عملية التصحيح، يرجى المحاولة مرة أخرى.']);
            return;
        }
        
        // إرسال إشعارات للطلاب
        foreach ($gradingData as $grade) {
            $this->notificationModel->createGradeNotification($grade['submission_id'], $grade['points']);
        }
        
        // تحديث إحصائيات المهمة
        $stats = $this->assignmentModel->getAssignmentStats($id);
        
        // إرجاع نتيجة العملية
        $this->json([
            'success' => true,
            'message' => "تم تصحيح {$result['success']} إجابة بنجاح.",
            'graded_submissions' => $gradedSubmissions,
            'graded_count' => $stats['graded_count']
        ]);
    }
    
    /**
     * تصحيح إجابة واحدة
     */
    public function gradeSingle()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.']);
            return;
        }
        
        // استخراج البيانات
        $submissionId = $this->request->post('submission_id');
        $points = $this->request->post('points');
        $feedback = $this->request->post('feedback');
        
        if (empty($submissionId) || $points === '') {
            $this->json(['success' => false, 'message' => 'البيانات المرسلة غير مكتملة.']);
            return;
        }
        
        // الحصول على معلومات الإجابة
        $submission = $this->submissionModel->getSubmissionWithDetails($submissionId);
        
        if (!$submission) {
            $this->json(['success' => false, 'message' => 'الإجابة غير موجودة.']);
            return;
        }
        
        // التحقق من صلاحية الوصول للمعلم
        $teacherId = $this->getTeacherId();
        
        if ($submission['teacher_id'] != $teacherId) {
            $this->json(['success' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذه الإجابة.']);
            return;
        }
        
        // تنفيذ التصحيح
        $gradeId = $this->submissionModel->gradeSubmission(
            $submissionId,
            floatval($points),
            $feedback,
            $this->auth->id()
        );
        
        if (!$gradeId) {
            $this->json(['success' => false, 'message' => 'فشلت عملية التصحيح، يرجى المحاولة مرة أخرى.']);
            return;
        }
        
        // إرسال إشعار للطالب
        $this->notificationModel->createGradeNotification($submissionId, $points);
        
        // إرجاع نتيجة العملية
        $this->json([
            'success' => true,
            'message' => 'تم تصحيح الإجابة بنجاح.'
        ]);
    }
    
    /**
     * الحصول على معرّف المعلم الحالي
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
}