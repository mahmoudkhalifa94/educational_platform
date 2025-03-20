<?php
/**
 * app/controllers/teacher/GradeController.php
 * متحكم الدرجات للمعلم
 * يدير عمليات تصحيح المهام وإدارة الدرجات للطلاب
 */
class GradeController extends Controller
{
    private $submissionModel;
    private $gradeModel;
    private $assignmentModel;
    private $studentModel;
    private $notificationModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        
        // التحقق من تسجيل الدخول ودور المستخدم
        $this->requireLogin();
        $this->requireRole('teacher');
        
        // تهيئة النماذج
        $this->submissionModel = new Submission();
        $this->gradeModel = new Grade();
        $this->assignmentModel = new Assignment();
        $this->studentModel = new Student();
        $this->notificationModel = new Notification();
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
    }
    
    /**
     * عرض قائمة الإجابات التي تحتاج إلى تصحيح
     */
    public function index()
    {
        $teacherId = $this->getTeacherId();
        
        // استخراج معلمات التصفية
        $classId = $this->request->get('class_id');
        $subjectId = $this->request->get('subject_id');
        
        // الحصول على الإجابات التي تحتاج إلى تصحيح
        $pendingSubmissions = $this->submissionModel->getPendingSubmissions($teacherId, $classId, $subjectId);
        
        // الحصول على قائمة الصفوف والمواد للتصفية
        $classes = (new ClassModel())->getClassesByTeacher($teacherId);
        $subjects = (new Subject())->getSubjectsByTeacher($teacherId);
        
        // عرض الصفحة
        echo $this->render('teacher/grades/index', [
            'pendingSubmissions' => $pendingSubmissions,
            'classes' => $classes,
            'subjects' => $subjects,
            'selectedClass' => $classId,
            'selectedSubject' => $subjectId,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض صفحة تصحيح إجابة طالب
     * 
     * @param int $submissionId معرّف الإجابة
     */
    public function grade($submissionId)
    {
        $teacherId = $this->getTeacherId();
        
        // الحصول على بيانات الإجابة
        $submission = $this->submissionModel->getSubmissionWithDetails($submissionId);
        
        if (!$submission) {
            $this->setFlash('error', 'لم يتم العثور على الإجابة المطلوبة.');
            $this->redirect('/teacher/grades');
        }
        
        // التحقق من صلاحية المعلم
        if ($submission['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية تصحيح هذه الإجابة.');
            $this->redirect('/teacher/grades');
        }
        
        // عرض صفحة التصحيح
        echo $this->render('teacher/grades/grade', [
            'submission' => $submission,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة تصحيح إجابة
     * 
     * @param int $submissionId معرّف الإجابة
     */
    public function saveGrade($submissionId)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/grades');
        }
        
        $teacherId = $this->getTeacherId();
        
        // الحصول على بيانات الإجابة
        $submission = $this->submissionModel->getSubmissionWithDetails($submissionId);
        
        if (!$submission) {
            $this->setFlash('error', 'لم يتم العثور على الإجابة المطلوبة.');
            $this->redirect('/teacher/grades');
        }
        
        // التحقق من صلاحية المعلم
        if ($submission['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية تصحيح هذه الإجابة.');
            $this->redirect('/teacher/grades');
        }
        
        // استخراج بيانات النموذج
        $points = $this->request->post('points');
        $feedback = $this->request->post('feedback');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'points' => $points,
            'feedback' => $feedback
        ], [
            'points' => 'required|numeric|max:' . $submission['assignment_points'],
            'feedback' => 'required'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من بيانات التصحيح.');
            $this->redirect('/teacher/grades/grade/' . $submissionId);
        }
        
        // تصحيح الإجابة
        $success = $this->submissionModel->gradeSubmission(
            $submissionId,
            $points,
            $feedback,
            $this->auth->id()
        );
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء حفظ التصحيح. يرجى المحاولة مرة أخرى.');
            $this->redirect('/teacher/grades/grade/' . $submissionId);
        }
        
        // إنشاء إشعار للطالب
        $this->notificationModel->createGradeNotification($submissionId, $points);
        
        // تسجيل النشاط
        $this->logActivity('تصحيح إجابة', 'تم تصحيح إجابة الطالب ' . $submission['student_first_name'] . ' ' . $submission['student_last_name']);
        
        $this->setFlash('success', 'تم حفظ التصحيح بنجاح.');
        $this->redirect('/teacher/grades');
    }
    
    /**
     * عرض صفحة التصحيح الجماعي للمهمة
     * 
     * @param int $assignmentId معرّف المهمة
     */
    public function bulkGrade($assignmentId)
    {
        $teacherId = $this->getTeacherId();
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($assignmentId);
        
        if (!$assignment) {
            $this->setFlash('error', 'لم يتم العثور على المهمة المطلوبة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية المعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية تصحيح هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // الحصول على إجابات الطلاب
        $submissions = $this->assignmentModel->getAssignmentSubmissions($assignmentId);
        
        // الحصول على الطلاب الذين لم يقدموا إجابات
        $studentsWithoutSubmission = $this->assignmentModel->getStudentsWithoutSubmission($assignmentId);
        
        // عرض صفحة التصحيح الجماعي
        echo $this->render('teacher/grades/bulk_grade', [
            'assignment' => $assignment,
            'submissions' => $submissions,
            'studentsWithoutSubmission' => $studentsWithoutSubmission,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة التصحيح الجماعي
     * 
     * @param int $assignmentId معرّف المهمة
     */
    public function saveBulkGrade($assignmentId)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/assignments');
        }
        
        $teacherId = $this->getTeacherId();
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($assignmentId);
        
        if (!$assignment) {
            $this->setFlash('error', 'لم يتم العثور على المهمة المطلوبة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية المعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية تصحيح هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // استخراج بيانات النموذج
        $submissions = $this->request->post('submissions');
        
        if (!is_array($submissions) || empty($submissions)) {
            $this->setFlash('error', 'لم يتم العثور على بيانات للتصحيح.');
            $this->redirect('/teacher/grades/bulk-grade/' . $assignmentId);
        }
        
        // تحضير بيانات التصحيح
        $gradesData = [];
        
        foreach ($submissions as $submissionId => $data) {
            if (isset($data['grade']) && is_numeric($data['grade']) && isset($data['feedback'])) {
                $gradesData[] = [
                    'submission_id' => $submissionId,
                    'points' => min($data['grade'], $assignment['points']),
                    'feedback' => $data['feedback']
                ];
                
                // إنشاء إشعار للطالب
                $this->notificationModel->createGradeNotification($submissionId, $data['grade']);
            }
        }
        
        // تصحيح الإجابات
        $result = $this->gradeModel->bulkImportGrades($gradesData, $this->auth->id());
        
        // تسجيل النشاط
        $this->logActivity('تصحيح جماعي', 'تم تصحيح ' . count($gradesData) . ' إجابة للمهمة: ' . $assignment['title']);
        
        $this->setFlash('success', 'تم حفظ التصحيحات بنجاح. تم تصحيح ' . $result['success'] . ' إجابة.');
        $this->redirect('/teacher/assignments/show/' . $assignmentId);
    }
    
    /**
     * عرض تقرير الدرجات لصف معين
     * 
     * @param int $classId معرّف الصف
     */
    public function classReport($classId)
    {
        $teacherId = $this->getTeacherId();
        
        // الحصول على معلومات الصف
        $class = (new ClassModel())->getClassWithStudentsCount($classId);
        
        if (!$class) {
            $this->setFlash('error', 'لم يتم العثور على الصف المطلوب.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على مواد المعلم في هذا الصف
        $subjects = (new Subject())->getSubjectsByTeacher($teacherId);
        $teacherSubjects = [];
        
        foreach ($subjects as $subject) {
            // التحقق من أن المعلم يدرّس هذه المادة في هذا الصف
            $teacherAssignments = (new Teacher())->getTeacherAssignments($teacherId);
            foreach ($teacherAssignments as $assignment) {
                if ($assignment['class_id'] == $classId && $assignment['subject_id'] == $subject['id']) {
                    $teacherSubjects[] = $subject;
                    break;
                }
            }
        }
        
        if (empty($teacherSubjects)) {
            $this->setFlash('error', 'ليس لديك مواد في هذا الصف.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج معلمات التصفية
        $subjectId = $this->request->get('subject_id', $teacherSubjects[0]['id']);
        
        // الحصول على المهام في المادة المحددة
        $assignments = $this->assignmentModel->getAssignmentsByClass($classId, $subjectId, true);
        
        // الحصول على الطلاب في الصف
        $students = (new User())->getStudentsByClass($classId);
        
        // جمع بيانات الدرجات
        $gradesData = [];
        
        foreach ($students as $student) {
            $studentGrades = [];
            $totalPoints = 0;
            $earnedPoints = 0;
            
            foreach ($assignments as $assignment) {
                // الحصول على درجة الطالب في هذه المهمة
                $submission = $this->submissionModel->getStudentSubmission($assignment['id'], $student['student_record_id']);
                
                if ($submission && isset($submission['grade_id'])) {
                    $studentGrades[$assignment['id']] = [
                        'points' => $submission['points'],
                        'percentage' => ($submission['points'] / $assignment['points']) * 100
                    ];
                    
                    $totalPoints += $assignment['points'];
                    $earnedPoints += $submission['points'];
                } else {
                    $studentGrades[$assignment['id']] = null;
                }
            }
            
            // حساب المتوسط
            $average = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
            
            $gradesData[$student['student_record_id']] = [
                'student' => $student,
                'grades' => $studentGrades,
                'total_points' => $totalPoints,
                'earned_points' => $earnedPoints,
                'average' => $average
            ];
        }
        
        // عرض تقرير الدرجات
        echo $this->render('teacher/grades/class_report', [
            'class' => $class,
            'subjects' => $teacherSubjects,
            'selectedSubject' => $subjectId,
            'assignments' => $assignments,
            'gradesData' => $gradesData,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير درجات طالب معين
     * 
     * @param int $studentId معرّف الطالب
     */
    public function studentReport($studentId)
    {
        $teacherId = $this->getTeacherId();
        
        // الحصول على معلومات الطالب
        $student = $this->studentModel->getStudentWithDetails($studentId);
        
        if (!$student) {
            $this->setFlash('error', 'لم يتم العثور على الطالب المطلوب.');
            $this->redirect('/teacher/dashboard');
        }
        
        // التحقق من أن المعلم يدرّس في صف الطالب
        $teacherClasses = (new ClassModel())->getClassesByTeacher($teacherId);
        $hasAccess = false;
        
        foreach ($teacherClasses as $class) {
            if ($class['id'] == $student['class_id']) {
                $hasAccess = true;
                break;
            }
        }
        
        if (!$hasAccess) {
            $this->setFlash('error', 'ليس لديك صلاحية الوصول إلى بيانات هذا الطالب.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على مواد المعلم في صف الطالب
        $subjects = (new Subject())->getSubjectsByTeacher($teacherId);
        $teacherSubjects = [];
        
        foreach ($subjects as $subject) {
            // التحقق من أن المعلم يدرّس هذه المادة في صف الطالب
            $teacherAssignments = (new Teacher())->getTeacherAssignments($teacherId);
            foreach ($teacherAssignments as $assignment) {
                if ($assignment['class_id'] == $student['class_id'] && $assignment['subject_id'] == $subject['id']) {
                    $teacherSubjects[] = $subject;
                    break;
                }
            }
        }
        
        // جمع بيانات الدرجات
        $gradesData = [];
        
        foreach ($teacherSubjects as $subject) {
            // الحصول على المهام في المادة
            $assignments = $this->assignmentModel->getAssignmentsByClass($student['class_id'], $subject['id'], true);
            
            $subjectGrades = [];
            $totalPoints = 0;
            $earnedPoints = 0;
            
            foreach ($assignments as $assignment) {
                // الحصول على درجة الطالب في هذه المهمة
                $submission = $this->submissionModel->getStudentSubmission($assignment['id'], $studentId);
                
                if ($submission && isset($submission['grade_id'])) {
                    $subjectGrades[] = [
                        'assignment' => $assignment,
                        'points' => $submission['points'],
                        'percentage' => ($submission['points'] / $assignment['points']) * 100,
                        'feedback' => $submission['feedback'],
                        'graded_at' => $submission['graded_at']
                    ];
                    
                    $totalPoints += $assignment['points'];
                    $earnedPoints += $submission['points'];
                } else {
                    $subjectGrades[] = [
                        'assignment' => $assignment,
                        'points' => null,
                        'percentage' => null,
                        'feedback' => null,
                        'graded_at' => null
                    ];
                }
            }
            
            // حساب المتوسط
            $average = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
            
            $gradesData[$subject['id']] = [
                'subject' => $subject,
                'grades' => $subjectGrades,
                'total_points' => $totalPoints,
                'earned_points' => $earnedPoints,
                'average' => $average
            ];
        }
        
        // الحصول على إحصائيات الطالب
        $stats = $this->studentModel->getStudentStats($studentId);
        
        // عرض تقرير الطالب
        echo $this->render('teacher/grades/student_report', [
            'student' => $student,
            'gradesData' => $gradesData,
            'stats' => $stats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تحميل ملف إكسل لدرجات الصف
     * 
     * @param int $classId معرّف الصف
     */
    public function exportClassGrades($classId)
    {
        $teacherId = $this->getTeacherId();
        
        // الحصول على معلومات الصف
        $class = (new ClassModel())->find($classId);
        
        if (!$class) {
            $this->setFlash('error', 'لم يتم العثور على الصف المطلوب.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج معلمات التصفية
        $subjectId = $this->request->get('subject_id');
        
        if (!$subjectId) {
            $this->setFlash('error', 'يرجى تحديد المادة الدراسية.');
            $this->redirect('/teacher/grades/class-report/' . $classId);
        }
        
        // الحصول على بيانات المادة
        $subject = (new Subject())->find($subjectId);
        
        if (!$subject) {
            $this->setFlash('error', 'لم يتم العثور على المادة المطلوبة.');
            $this->redirect('/teacher/grades/class-report/' . $classId);
        }
        
        // التحقق من صلاحية المعلم
        $hasAccess = false;
        $teacherAssignments = (new Teacher())->getTeacherAssignments($teacherId);
        
        foreach ($teacherAssignments as $assignment) {
            if ($assignment['class_id'] == $classId && $assignment['subject_id'] == $subjectId) {
                $hasAccess = true;
                break;
            }
        }
        
        if (!$hasAccess) {
            $this->setFlash('error', 'ليس لديك صلاحية الوصول إلى بيانات هذه المادة.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على المهام في المادة المحددة
        $assignments = $this->assignmentModel->getAssignmentsByClass($classId, $subjectId, true);
        
        // الحصول على الطلاب في الصف
        $students = (new User())->getStudentsByClass($classId);
        
        // إنشاء مصفوفة البيانات للتصدير
        $exportData = [];
        
        // إضافة رأس الجدول
        $header = ['رقم الطالب', 'اسم الطالب'];
        
        foreach ($assignments as $assignment) {
            $header[] = $assignment['title'] . ' (' . $assignment['points'] . ' نقطة)';
        }
        
        $header[] = 'المتوسط';
        $exportData[] = $header;
        
        // إضافة بيانات الطلاب
        foreach ($students as $student) {
            $row = [
                $student['student_id'],
                $student['first_name'] . ' ' . $student['last_name']
            ];
            
            $totalPoints = 0;
            $earnedPoints = 0;
            
            foreach ($assignments as $assignment) {
                // الحصول على درجة الطالب في هذه المهمة
                $submission = $this->submissionModel->getStudentSubmission($assignment['id'], $student['student_record_id']);
                
                if ($submission && isset($submission['grade_id'])) {
                    $row[] = $submission['points'];
                    $totalPoints += $assignment['points'];
                    $earnedPoints += $submission['points'];
                } else {
                    $row[] = 'لم يقدم';
                }
            }
            
            // حساب المتوسط
            $average = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 1) : 0;
            $row[] = $average . '%';
            
            $exportData[] = $row;
        }
        
        // توليد ملف إكسل
        $fileName = 'درجات_' . $class['name'] . '_' . $subject['name'] . '_' . date('Y-m-d') . '.xlsx';
        
        // إنشاء كائن PHPExcel
        require_once 'vendor/autoload.php';
        
        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $excel->getActiveSheet();
        $sheet->setRightToLeft(true);
        
        // إضافة البيانات إلى الملف
        foreach ($exportData as $rowIndex => $rowData) {
            foreach ($rowData as $colIndex => $cellValue) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $cellValue);
            }
        }
        
        // تنسيق الرأس
        $lastColumn = count($header);
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumn) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        
        // حفظ الملف
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($excel);
        
        // إرسال الملف للتنزيل
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
    
    /**
     * عرض صفحة استيراد الدرجات
     * 
     * @param int $assignmentId معرّف المهمة
     */
    public function importGrades($assignmentId)
    {
        $teacherId = $this->getTeacherId();
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($assignmentId);
        
        if (!$assignment) {
            $this->setFlash('error', 'لم يتم العثور على المهمة المطلوبة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية المعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية تعديل هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // عرض صفحة استيراد الدرجات
        echo $this->render('teacher/grades/import', [
            'assignment' => $assignment,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة استيراد الدرجات من ملف إكسل
     * 
     * @param int $assignmentId معرّف المهمة
     */
    public function processImport($assignmentId)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/assignments');
        }
        
        $teacherId = $this->getTeacherId();
        
        // الحصول على بيانات المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($assignmentId);
        
        if (!$assignment) {
            $this->setFlash('error', 'لم يتم العثور على المهمة المطلوبة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من صلاحية المعلم
        if ($assignment['teacher_id'] != $teacherId) {
            $this->setFlash('error', 'ليس لديك صلاحية تعديل هذه المهمة.');
            $this->redirect('/teacher/assignments');
        }
        
        // التحقق من وجود الملف
        if (!$this->request->hasFile('grades_file')) {
            $this->setFlash('error', 'يرجى تحديد ملف الدرجات.');
            $this->redirect('/teacher/grades/import/' . $assignmentId);
        }
        
        $file = $this->request->file('grades_file');
        
        // التحقق من نوع الملف
        $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->setFlash('error', 'يرجى تحديد ملف إكسل صالح.');
            $this->redirect('/teacher/grades/import/' . $assignmentId);
        }
        
        // قراءة الملف
        require_once 'vendor/autoload.php';
        
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
            $spreadsheet = $reader->load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // الحصول على البيانات من الملف
            $data = $worksheet->toArray();
            
            // التحقق من تنسيق الملف
            if (count($data) < 2) {
                $this->setFlash('error', 'الملف لا يحتوي على بيانات كافية.');
                $this->redirect('/teacher/grades/import/' . $assignmentId);
            }
            
            // استخراج البيانات
            $gradesData = [];
            
            // البحث عن أعمدة رقم الطالب والدرجة والملاحظات
            $headerRow = $data[0];
            $studentIdColumn = -1;
            $gradeColumn = -1;
            $feedbackColumn = -1;
            
            foreach ($headerRow as $index => $cell) {
                $cell = strtolower(trim($cell));
                if (strpos($cell, 'رقم الطالب') !== false || strpos($cell, 'student id') !== false) {
                    $studentIdColumn = $index;
                } elseif (strpos($cell, 'درجة') !== false || strpos($cell, 'grade') !== false || strpos($cell, 'points') !== false) {
                    $gradeColumn = $index;
                } elseif (strpos($cell, 'ملاحظات') !== false || strpos($cell, 'feedback') !== false || strpos($cell, 'comments') !== false) {
                    $feedbackColumn = $index;
                }
            }
            
            if ($studentIdColumn === -1 || $gradeColumn === -1) {
                $this->setFlash('error', 'الملف لا يحتوي على الأعمدة المطلوبة (رقم الطالب والدرجة).');
                $this->redirect('/teacher/grades/import/' . $assignmentId);
            }
            
            // استخراج الدرجات
            for ($i = 1; $i < count($data); $i++) {
                $row = $data[$i];
                
                if (!isset($row[$studentIdColumn]) || !isset($row[$gradeColumn])) {
                    continue;
                }
                
                $studentNumber = trim($row[$studentIdColumn]);
                $grade = trim($row[$gradeColumn]);
                $feedback = $feedbackColumn !== -1 && isset($row[$feedbackColumn]) ? trim($row[$feedbackColumn]) : 'تم التصحيح من خلال استيراد ملف';
                
                if (empty($studentNumber) || !is_numeric($grade)) {
                    continue;
                }
                
                // الحصول على معرّف الطالب من رقم الطالب
                $student = $this->studentModel->getStudentByStudentId($studentNumber);
                
                if (!$student) {
                    continue;
                }
                
                // الحصول على إجابة الطالب لهذه المهمة
                $submission = $this->submissionModel->getStudentSubmission($assignmentId, $student['id']);
                
                if (!$submission) {
                    // إنشاء إجابة جديدة
                    $submissionId = $this->submissionModel->createSubmission([
                        'assignment_id' => $assignmentId,
                        'student_id' => $student['id'],
                        'content' => 'تم إنشاء الإجابة تلقائيًا عند استيراد الدرجات',
                        'status' => 'submitted',
                        'submitted_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $submissionId = $submission['id'];
                }
                
                if ($submissionId) {
                    $gradesData[] = [
                        'submission_id' => $submissionId,
                        'points' => min((float)$grade, $assignment['points']),
                        'feedback' => $feedback
                    ];
                }
            }
            
            if (empty($gradesData)) {
                $this->setFlash('error', 'لم يتم العثور على درجات صالحة في الملف.');
                $this->redirect('/teacher/grades/import/' . $assignmentId);
            }
            
            // استيراد الدرجات
            $result = $this->gradeModel->bulkImportGrades($gradesData, $this->auth->id());
            
            // إرسال إشعارات للطلاب
            foreach ($gradesData as $gradeData) {
                $this->notificationModel->createGradeNotification($gradeData['submission_id'], $gradeData['points']);
            }
            
            // تسجيل النشاط
            $this->logActivity('استيراد درجات', 'تم استيراد ' . count($gradesData) . ' درجة للمهمة: ' . $assignment['title']);
            
            $this->setFlash('success', 'تم استيراد الدرجات بنجاح. تم استيراد ' . $result['success'] . ' درجة.');
            $this->redirect('/teacher/assignments/show/' . $assignmentId);
        } catch (Exception $e) {
            $this->setFlash('error', 'حدث خطأ أثناء قراءة الملف: ' . $e->getMessage());
            $this->redirect('/teacher/grades/import/' . $assignmentId);
        }
    }
    
    /**
     * الحصول على معرّف المعلم للمستخدم الحالي
     * 
     * @return int|false معرّف المعلم أو false إذا لم يتم العثور عليه
     */
    private function getTeacherId()
    {
        $teacher = (new Teacher())->getTeacherByUserId($this->auth->id());
        return $teacher ? $teacher['id'] : false;
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