<?php
/**
 * app/controllers/teacher/ClassController.php
 * متحكم الصفوف للمعلمين
 * يدير عرض بيانات الصفوف والطلاب
 */
class ClassController extends Controller
{
    private $classModel;
    private $studentModel;
    private $subjectModel;
    private $assignmentModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->classModel = new ClassModel();
        $this->studentModel = new Student();
        $this->subjectModel = new Subject();
        $this->assignmentModel = new Assignment();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * عرض قائمة الصفوف
     */
    public function index()
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على الصفوف للمعلم
        $classes = $this->classModel->getClassesByTeacher($teacherId);
        
        // إضافة معلومات إضافية لكل صف
        foreach ($classes as &$class) {
            // عدد الطلاب في الصف
            $class['students_count'] = $this->studentModel->getStudentsCountByClass($class['id']);
            
            // عدد المواد التي يدرسها المعلم في الصف
            $class['subjects_count'] = count($this->subjectModel->getTeacherSubjectsByClass($teacherId, $class['id']));
            
            // عدد المهام المرتبطة بالصف
            $class['assignments_count'] = $this->assignmentModel->getAssignmentsCountByClass($teacherId, $class['id']);
        }
        
        // عرض الصفحة
        echo $this->render('teacher/classes/index', [
            'classes' => $classes,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تفاصيل صف
     * 
     * @param int $id معرّف الصف
     */
    public function show($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // التحقق مما إذا كان المعلم لديه صلاحية الوصول للصف
        if (!$this->hasAccessToClass($teacherId, $id)) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على بيانات الصف
        $class = $this->classModel->getClassWithDetails($id);
        
        if (!$class) {
            $this->setFlash('error', 'الصف غير موجود.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على المواد التي يدرسها المعلم في الصف
        $subjects = $this->subjectModel->getTeacherSubjectsByClass($teacherId, $id);
        
        // الحصول على الطلاب في الصف
        $students = $this->studentModel->getStudentsByClass($id);
        
        // الحصول على المهام المرتبطة بالصف
        $assignments = $this->assignmentModel->getTeacherAssignmentsByClass($teacherId, $id);
        
        // عرض الصفحة
        echo $this->render('teacher/classes/show', [
            'class' => $class,
            'subjects' => $subjects,
            'students' => $students,
            'assignments' => $assignments,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض قائمة طلاب الصف
     * 
     * @param int $id معرّف الصف
     */
    public function students($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // التحقق مما إذا كان المعلم لديه صلاحية الوصول للصف
        if (!$this->hasAccessToClass($teacherId, $id)) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على بيانات الصف
        $class = $this->classModel->getClassWithDetails($id);
        
        if (!$class) {
            $this->setFlash('error', 'الصف غير موجود.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على الطلاب في الصف
        $students = $this->studentModel->getStudentsByClass($id);
        
        // إضافة معلومات أكاديمية إضافية لكل طالب
        foreach ($students as &$student) {
            $student['attendance_rate'] = $this->calculateAttendanceRate($student['id']);
            $student['assignments_completion'] = $this->calculateAssignmentCompletion($student['id'], $teacherId);
            $student['average_grade'] = $this->calculateAverageGrade($student['id'], $teacherId);
        }
        
        // عرض الصفحة
        echo $this->render('teacher/classes/students', [
            'class' => $class,
            'students' => $students,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تفاصيل طالب
     * 
     * @param int $classId معرّف الصف
     * @param int $studentId معرّف الطالب
     */
    public function student($classId, $studentId)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // التحقق مما إذا كان المعلم لديه صلاحية الوصول للصف
        if (!$this->hasAccessToClass($teacherId, $classId)) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على بيانات الطالب
        $student = $this->studentModel->getStudentWithDetails($studentId);
        
        if (!$student || $student['class_id'] != $classId) {
            $this->setFlash('error', 'الطالب غير موجود في هذا الصف.');
            $this->redirect('/teacher/classes/students/' . $classId);
        }
        
        // الحصول على المواد التي يدرسها المعلم للطالب
        $subjects = $this->subjectModel->getTeacherSubjectsByClass($teacherId, $classId);
        
        // الحصول على المهام المكلف بها الطالب من قبل المعلم
        $assignments = $this->assignmentModel->getTeacherAssignmentsForStudent($teacherId, $studentId);
        
        // الحصول على إحصائيات الطالب
        $stats = [
            'attendance_rate' => $this->calculateAttendanceRate($studentId),
            'assignments_completion' => $this->calculateAssignmentCompletion($studentId, $teacherId),
            'average_grade' => $this->calculateAverageGrade($studentId, $teacherId),
            'active_assignments' => count(array_filter($assignments, function($a) {
                return $a['status'] === 'active' || $a['status'] === 'late';
            })),
            'completed_assignments' => count(array_filter($assignments, function($a) {
                return $a['status'] === 'graded';
            }))
        ];
        
        // الحصول على تاريخ الحضور والغياب
        $attendance = (new Attendance())->getStudentAttendance($studentId);
        
        // الحصول على تاريخ الدرجات
        $grades = (new Grade())->getStudentGradesByTeacher($studentId, $teacherId);
        
        // عرض الصفحة
        echo $this->render('teacher/classes/student', [
            'student' => $student,
            'subjects' => $subjects,
            'assignments' => $assignments,
            'stats' => $stats,
            'attendance' => $attendance,
            'grades' => $grades,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تحديث ملاحظات الطالب
     */
    public function updateNotes()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.']);
            return;
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->json(['success' => false, 'message' => 'لم يتم العثور على بيانات المعلم.']);
            return;
        }
        
        // استخراج البيانات
        $studentId = $this->request->post('student_id');
        $notes = $this->request->post('notes');
        
        // التحقق من وجود الطالب
        $student = $this->studentModel->find($studentId);
        
        if (!$student) {
            $this->json(['success' => false, 'message' => 'الطالب غير موجود.']);
            return;
        }
        
        // التحقق من صلاحية المعلم للوصول إلى الطالب
        if (!$this->hasAccessToClass($teacherId, $student['class_id'])) {
            $this->json(['success' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذا الطالب.']);
            return;
        }
        
        // تحديث ملاحظات الطالب
        $studentNoteModel = new StudentNote();
        $success = $studentNoteModel->updateOrCreate([
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($success) {
            $this->json(['success' => true, 'message' => 'تم تحديث الملاحظات بنجاح.']);
        } else {
            $this->json(['success' => false, 'message' => 'حدث خطأ أثناء تحديث الملاحظات.']);
        }
    }
    
    /**
     * عرض تقارير الصف
     * 
     * @param int $id معرّف الصف
     */
    public function reports($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // التحقق مما إذا كان المعلم لديه صلاحية الوصول للصف
        if (!$this->hasAccessToClass($teacherId, $id)) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على بيانات الصف
        $class = $this->classModel->getClassWithDetails($id);
        
        if (!$class) {
            $this->setFlash('error', 'الصف غير موجود.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على المواد التي يدرسها المعلم في الصف
        $subjects = $this->subjectModel->getTeacherSubjectsByClass($teacherId, $id);
        
        // الحصول على إحصائيات المهام
        $assignmentStats = $this->assignmentModel->getClassAssignmentStats($teacherId, $id);
        
        // الحصول على إحصائيات الدرجات
        $gradeStats = (new Grade())->getClassGradeStats($teacherId, $id);
        
        // الحصول على إحصائيات الحضور
        $attendanceStats = (new Attendance())->getClassAttendanceStats($id);
        
        // عرض الصفحة
        echo $this->render('teacher/classes/reports', [
            'class' => $class,
            'subjects' => $subjects,
            'assignmentStats' => $assignmentStats,
            'gradeStats' => $gradeStats,
            'attendanceStats' => $attendanceStats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تصدير تقرير الصف
     * 
     * @param int $id معرّف الصف
     */
    public function exportReport($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // التحقق مما إذا كان المعلم لديه صلاحية الوصول للصف
        if (!$this->hasAccessToClass($teacherId, $id)) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على نوع التقرير
        $reportType = $this->request->get('type', 'grades');
        $subjectId = $this->request->get('subject_id');
        
        // التحقق من صلاحية نوع التقرير
        if (!in_array($reportType, ['grades', 'attendance', 'assignments', 'full'])) {
            $this->setFlash('error', 'نوع التقرير غير صالح.');
            $this->redirect('/teacher/classes/reports/' . $id);
        }
        
        // الحصول على بيانات الصف
        $class = $this->classModel->getClassWithDetails($id);
        
        if (!$class) {
            $this->setFlash('error', 'الصف غير موجود.');
            $this->redirect('/teacher/classes');
        }
        
        require_once 'vendor/autoload.php';
        
        // إنشاء ملف إكسل جديد
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        
        // تعيين عنوان التقرير
        $reportTitle = 'تقرير ' . $this->getReportTitle($reportType) . ' - ' . $class['name'];
        $sheet->setCellValue('A1', $reportTitle);
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // إضافة معلومات التقرير
        $sheet->setCellValue('A2', 'تاريخ التقرير:');
        $sheet->setCellValue('B2', date('Y-m-d'));
        $sheet->setCellValue('C2', 'المعلم:');
        $sheet->setCellValue('D2', (new Teacher())->getTeacherName($teacherId));
        
        // إنشاء التقرير المطلوب
        switch ($reportType) {
            case 'grades':
                $this->createGradesReport($sheet, $id, $teacherId, $subjectId);
                break;
                
            case 'attendance':
                $this->createAttendanceReport($sheet, $id);
                break;
                
            case 'assignments':
                $this->createAssignmentsReport($sheet, $id, $teacherId, $subjectId);
                break;
                
            case 'full':
                // إنشاء أوراق متعددة للتقرير الشامل
                $sheet->setTitle('الدرجات');
                $this->createGradesReport($sheet, $id, $teacherId, $subjectId);
                
                $attendanceSheet = $spreadsheet->createSheet();
                $attendanceSheet->setTitle('الحضور والغياب');
                $attendanceSheet->setRightToLeft(true);
                $this->createAttendanceReport($attendanceSheet, $id);
                
                $assignmentsSheet = $spreadsheet->createSheet();
                $assignmentsSheet->setTitle('المهام');
                $assignmentsSheet->setRightToLeft(true);
                $this->createAssignmentsReport($assignmentsSheet, $id, $teacherId, $subjectId);
                break;
        }
        
        // تحديد اسم الملف
        $fileName = str_replace(' ', '_', $reportTitle) . '_' . date('Y-m-d') . '.xlsx';
        
        // إرسال الملف للتنزيل
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * إنشاء تقرير الدرجات
     * 
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param int $classId معرّف الصف
     * @param int $teacherId معرّف المعلم
     * @param int|null $subjectId معرّف المادة
     */
    private function createGradesReport($sheet, $classId, $teacherId, $subjectId = null)
    {
        // تعيين العناوين
        $sheet->setCellValue('A4', 'رقم الطالب');
        $sheet->setCellValue('B4', 'اسم الطالب');
        
        // الحصول على الطلاب في الصف
        $students = $this->studentModel->getStudentsByClass($classId);
        
        // الحصول على المواد التي يدرسها المعلم في الصف
        $subjects = $this->subjectModel->getTeacherSubjectsByClass($teacherId, $classId);
        
        if ($subjectId) {
            // تصفية المواد حسب المادة المحددة
            $subjects = array_filter($subjects, function($subject) use ($subjectId) {
                return $subject['id'] == $subjectId;
            });
        }
        
        // إضافة عناوين المواد
        $col = 2; // بدءًا من العمود C
        foreach ($subjects as $subject) {
            $sheet->setCellValueByColumnAndRow($col + 1, 4, $subject['name']);
            $col++;
        }
        
        // إضافة عمود المعدل
        $sheet->setCellValueByColumnAndRow($col + 1, 4, 'المعدل العام');
        
        // تنسيق العناوين
        $headerRange = 'A4:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '4';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');
        
        // إضافة بيانات الطلاب
        $row = 5;
        foreach ($students as $student) {
            $sheet->setCellValue('A' . $row, $student['student_id']);
            $sheet->setCellValue('B' . $row, $student['first_name'] . ' ' . $student['last_name']);
            
            // إضافة درجات المواد
            $col = 2; // بدءًا من العمود C
            $totalGrade = 0;
            $subjectCount = 0;
            
            foreach ($subjects as $subject) {
                $grade = (new Grade())->getStudentSubjectGrade($student['id'], $subject['id'], $teacherId);
                $gradeValue = $grade ? $grade['grade_percentage'] : 'لا توجد';
                
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $gradeValue);
                
                if ($grade) {
                    $totalGrade += $grade['grade_percentage'];
                    $subjectCount++;
                }
                
                $col++;
            }
            
            // حساب المعدل العام
            $average = $subjectCount > 0 ? round($totalGrade / $subjectCount, 2) : 'لا توجد';
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $average);
            
            $row++;
        }
        
        // تنسيق الجدول
        $dataRange = 'A4:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // ضبط عرض الأعمدة
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        
        for ($i = 3; $i <= $col + 1; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth(15);
        }
    }
    
    /**
     * إنشاء تقرير الحضور والغياب
     * 
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param int $classId معرّف الصف
     */
    private function createAttendanceReport($sheet, $classId)
    {
        // تعيين العناوين
        $sheet->setCellValue('A4', 'رقم الطالب');
        $sheet->setCellValue('B4', 'اسم الطالب');
        $sheet->setCellValue('C4', 'عدد أيام الحضور');
        $sheet->setCellValue('D4', 'عدد أيام الغياب');
        $sheet->setCellValue('E4', 'نسبة الحضور');
        $sheet->setCellValue('F4', 'عدد أيام الغياب بعذر');
        $sheet->setCellValue('G4', 'عدد أيام التأخير');
        
        // تنسيق العناوين
        $headerRange = 'A4:G4';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');
        
        // الحصول على الطلاب في الصف
        $students = $this->studentModel->getStudentsByClass($classId);
        
        // إضافة بيانات الطلاب
        $row = 5;
        $attendanceModel = new Attendance();
        
        foreach ($students as $student) {
            $stats = $attendanceModel->getStudentAttendanceStats($student['id']);
            
            $sheet->setCellValue('A' . $row, $student['student_id']);
            $sheet->setCellValue('B' . $row, $student['first_name'] . ' ' . $student['last_name']);
            $sheet->setCellValue('C' . $row, $stats['present_days'] ?? 0);
            $sheet->setCellValue('D' . $row, $stats['absent_days'] ?? 0);
            $sheet->setCellValue('E' . $row, $stats['attendance_rate'] ?? '0%');
            $sheet->setCellValue('F' . $row, $stats['excused_absences'] ?? 0);
            $sheet->setCellValue('G' . $row, $stats['late_days'] ?? 0);
            
            $row++;
        }
        
        // تنسيق الجدول
        $dataRange = 'A4:G' . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // ضبط عرض الأعمدة
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(15);
    }
    
    /**
     * إنشاء تقرير المهام
     * 
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param int $classId معرّف الصف
     * @param int $teacherId معرّف المعلم
     * @param int|null $subjectId معرّف المادة
     */
    private function createAssignmentsReport($sheet, $classId, $teacherId, $subjectId = null)
    {
        // تعيين العناوين
        $sheet->setCellValue('A4', 'رقم الطالب');
        $sheet->setCellValue('B4', 'اسم الطالب');
        
        // الحصول على الطلاب في الصف
        $students = $this->studentModel->getStudentsByClass($classId);
        
        // الحصول على المهام المرتبطة بالصف
        $assignments = $this->assignmentModel->getTeacherAssignmentsByClass($teacherId, $classId, $subjectId);
        
        // إضافة عناوين المهام
        $col = 2; // بدءًا من العمود C
        foreach ($assignments as $assignment) {
            $title = $assignment['title'];
            if (strlen($title) > 20) {
                $title = substr($title, 0, 17) . '...';
            }
            $sheet->setCellValueByColumnAndRow($col + 1, 4, $title);
            $col++;
        }
        
        // إضافة عمود نسبة الإكمال
        $sheet->setCellValueByColumnAndRow($col + 1, 4, 'نسبة إكمال المهام');
        
        // تنسيق العناوين
        $headerRange = 'A4:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '4';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');
        
        // إضافة بيانات الطلاب
        $row = 5;
        $submissionModel = new Submission();
        
        foreach ($students as $student) {
            $sheet->setCellValue('A' . $row, $student['student_id']);
            $sheet->setCellValue('B' . $row, $student['first_name'] . ' ' . $student['last_name']);
            
            // إضافة حالة المهام
            $col = 2; // بدءًا من العمود C
            $completedAssignments = 0;
            
            foreach ($assignments as $assignment) {
                $submission = $submissionModel->getStudentSubmission($assignment['id'], $student['id']);
                
                if ($submission) {
                    if ($submission['grade_id']) {
                        $status = $submission['points'] . '/' . $assignment['points'];
                    } else {
                        $status = 'مقدم';
                    }
                } else {
                    if (strtotime($assignment['due_date']) < time()) {
                        $status = 'غير مقدم';
                    } else {
                        $status = 'قيد الإنجاز';
                    }
                }
                
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $status);
                
                if ($submission && $submission['grade_id']) {
                    $completedAssignments++;
                }
                
                $col++;
            }
            
            // حساب نسبة إكمال المهام
            $completionRate = count($assignments) > 0 ? round(($completedAssignments / count($assignments)) * 100, 2) . '%' : '0%';
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $completionRate);
            
            $row++;
        }
        
        // تنسيق الجدول
        $dataRange = 'A4:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // ضبط عرض الأعمدة
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        
        for ($i = 3; $i <= $col + 1; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth(15);
        }
    }
    
    /**
     * الحصول على عنوان التقرير
     * 
     * @param string $type نوع التقرير
     * @return string عنوان التقرير
     */
    private function getReportTitle($type)
    {
        switch ($type) {
            case 'grades':
                return 'الدرجات';
            case 'attendance':
                return 'الحضور والغياب';
            case 'assignments':
                return 'المهام';
            case 'full':
                return 'شامل';
            default:
                return '';
        }
    }
    
    /**
     * تقييم نسبة الحضور للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return float نسبة الحضور
     */
    private function calculateAttendanceRate($studentId)
    {
        $attendanceModel = new Attendance();
        $stats = $attendanceModel->getStudentAttendanceStats($studentId);
        
        return $stats['attendance_rate'] ?? 0;
    }
    
    /**
     * تقييم نسبة إكمال المهام للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @param int $teacherId معرّف المعلم
     * @return float نسبة إكمال المهام
     */
    private function calculateAssignmentCompletion($studentId, $teacherId)
    {
        // الحصول على المهام المكلف بها الطالب من قبل المعلم
        $assignments = $this->assignmentModel->getTeacherAssignmentsForStudent($teacherId, $studentId);
        
        if (empty($assignments)) {
            return 0;
        }
        
        // عدد المهام المكتملة (المصححة)
        $completedCount = count(array_filter($assignments, function($a) {
            return $a['status'] === 'graded';
        }));
        
        return ($completedCount / count($assignments)) * 100;
    }
    
    /**
     * حساب متوسط درجات الطالب
     * 
     * @param int $studentId معرّف الطالب
     * @param int $teacherId معرّف المعلم
     * @return float متوسط الدرجات
     */
    private function calculateAverageGrade($studentId, $teacherId)
    {
        $gradeModel = new Grade();
        $grades = $gradeModel->getStudentGradesByTeacher($studentId, $teacherId);
        
        if (empty($grades)) {
            return 0;
        }
        
        $totalPercentage = array_sum(array_column($grades, 'grade_percentage'));
        return $totalPercentage / count($grades);
    }
    
    /**
     * التحقق مما إذا كان المعلم لديه صلاحية الوصول إلى الصف
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $classId معرّف الصف
     * @return bool هل لديه صلاحية الوصول
     */
    private function hasAccessToClass($teacherId, $classId)
    {
        $query = "SELECT COUNT(*) as count FROM teacher_assignments WHERE teacher_id = ? AND class_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId, $classId]);
        
        return $result['count'] > 0;
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
}