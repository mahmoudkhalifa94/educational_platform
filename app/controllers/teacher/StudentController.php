<?php
/**
 * app/controllers/teacher/StudentController.php
 * متحكم الطلاب للمعلمين
 * يدير عرض بيانات الطلاب وتقييمهم
 */
class StudentController extends Controller
{
    private $studentModel;
    private $classModel;
    private $submissionModel;
    private $gradeModel;
    private $attendanceModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->studentModel = new Student();
        $this->classModel = new ClassModel();
        $this->submissionModel = new Submission();
        $this->gradeModel = new Grade();
        $this->attendanceModel = new Attendance();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * عرض قائمة الطلاب لجميع الصفوف التي يدرسها المعلم
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
        $search = $this->request->get('search');
        
        // الحصول على الصفوف التي يدرسها المعلم
        $classes = $this->classModel->getClassesByTeacher($teacherId);
        
        // الحصول على قائمة الطلاب
        $students = $this->studentModel->getStudentsByTeacher($teacherId, $classId, $search);
        
        // إضافة معلومات أكاديمية لكل طالب
        foreach ($students as &$student) {
            $student['stats'] = $this->getStudentStats($student['id'], $teacherId);
        }
        
        // عرض الصفحة
        echo $this->render('teacher/students/index', [
            'students' => $students,
            'classes' => $classes,
            'selectedClass' => $classId,
            'search' => $search,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تفاصيل طالب
     * 
     * @param int $id معرّف الطالب
     */
    public function show($id)
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على بيانات الطالب
        $student = $this->studentModel->getStudentWithDetails($id);
        
        if (!$student) {
            $this->setFlash('error', 'الطالب غير موجود.');
            $this->redirect('/teacher/students');
        }
        
        // التحقق من صلاحية المعلم للوصول إلى الطالب
        if (!$this->hasAccessToClass($teacherId, $student['class_id'])) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الطالب.');
            $this->redirect('/teacher/students');
        }
        
        // الحصول على المواد التي يدرسها المعلم للطالب
        $subjects = (new Subject())->getTeacherSubjectsByClass($teacherId, $student['class_id']);
        
        // الحصول على المهام المكلف بها الطالب من قبل المعلم
        $assignments = (new Assignment())->getTeacherAssignmentsForStudent($teacherId, $id);
        
        // الحصول على إحصائيات الطالب
        $stats = $this->getStudentStats($id, $teacherId);
        
        // الحصول على ملاحظات المعلم للطالب
        $notes = (new StudentNote())->getTeacherNotes($teacherId, $id);
        
        // الحصول على تاريخ الحضور والغياب
        $attendance = $this->attendanceModel->getStudentAttendance($id);
        
        // الحصول على تاريخ الدرجات
        $grades = $this->gradeModel->getStudentGradesByTeacher($id, $teacherId);
        
        // الحصول على معلومات ولي الأمر
        $parent = (new ParentModel())->getParentByStudent($id);
        
        // عرض الصفحة
        echo $this->render('teacher/students/show', [
            'student' => $student,
            'subjects' => $subjects,
            'assignments' => $assignments,
            'stats' => $stats,
            'notes' => $notes,
            'attendance' => $attendance,
            'grades' => $grades,
            'parent' => $parent,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * التعامل مع الحضور والغياب
     */
    public function attendance()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->setFlash('error', 'طريقة الطلب غير صحيحة.');
            $this->redirect('/teacher/students');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج البيانات
        $studentId = $this->request->post('student_id');
        $date = $this->request->post('date');
        $status = $this->request->post('status');
        $notes = $this->request->post('notes', '');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'student_id' => $studentId,
            'date' => $date,
            'status' => $status
        ], [
            'student_id' => 'required|numeric',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,excused'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/students/show/' . $studentId);
        }
        
        // الحصول على بيانات الطالب
        $student = $this->studentModel->find($studentId);
        
        if (!$student) {
            $this->setFlash('error', 'الطالب غير موجود.');
            $this->redirect('/teacher/students');
        }
        
        // التحقق من صلاحية المعلم للوصول إلى الطالب
        if (!$this->hasAccessToClass($teacherId, $student['class_id'])) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الطالب.');
            $this->redirect('/teacher/students');
        }
        
        // الحصول على المادة (الافتراضية الأولى إذا لم يتم تحديدها)
        $subjectId = $this->request->post('subject_id');
        
        if (!$subjectId) {
            $subjects = (new Subject())->getTeacherSubjectsByClass($teacherId, $student['class_id']);
            if (!empty($subjects)) {
                $subjectId = $subjects[0]['id'];
            }
        }
        
        // تسجيل الحضور أو تحديثه
        $attendanceData = [
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'date' => $date,
            'status' => $status,
            'notes' => $notes
        ];
        
        $result = $this->attendanceModel->recordAttendance($attendanceData);
        
        if ($result) {
            $this->setFlash('success', 'تم تسجيل الحضور بنجاح.');
        } else {
            $this->setFlash('error', 'حدث خطأ أثناء تسجيل الحضور.');
        }
        
        $this->redirect('/teacher/students/show/' . $studentId);
    }
    
    /**
     * إدخال درجة مباشرة للطالب
     */
    public function addGrade()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->setFlash('error', 'طريقة الطلب غير صحيحة.');
            $this->redirect('/teacher/students');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج البيانات
        $studentId = $this->request->post('student_id');
        $subjectId = $this->request->post('subject_id');
        $title = $this->request->post('grade_title');
        $grade = $this->request->post('grade');
        $maxGrade = $this->request->post('max_grade');
        $notes = $this->request->post('notes', '');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'grade_title' => $title,
            'grade' => $grade,
            'max_grade' => $maxGrade
        ], [
            'student_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'grade_title' => 'required',
            'grade' => 'required|numeric',
            'max_grade' => 'required|numeric|min:1'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/students/show/' . $studentId);
        }
        
        // الحصول على بيانات الطالب
        $student = $this->studentModel->find($studentId);
        
        if (!$student) {
            $this->setFlash('error', 'الطالب غير موجود.');
            $this->redirect('/teacher/students');
        }
        
        // التحقق من صلاحية المعلم للوصول إلى الطالب والمادة
        if (!$this->hasAccessToClass($teacherId, $student['class_id']) || !$this->hasAccessToSubject($teacherId, $subjectId)) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الطالب أو المادة.');
            $this->redirect('/teacher/students');
        }
        
        // إنشاء درجة جديدة
        $gradeData = [
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'title' => $title,
            'grade' => $grade,
            'max_grade' => $maxGrade,
            'notes' => $notes,
            'date' => date('Y-m-d')
        ];
        
        $result = $this->gradeModel->addDirectGrade($gradeData);
        
        if ($result) {
            // إرسال إشعار للطالب
            $studentName = $student['first_name'] . ' ' . $student['last_name'];
            $subjectName = (new Subject())->find($subjectId)['name'];
            
            (new Notification())->create([
                'user_id' => $student['user_id'],
                'title' => 'درجة جديدة في ' . $subjectName,
                'message' => 'تم إضافة درجة جديدة في ' . $title . ': ' . $grade . ' من ' . $maxGrade,
                'type' => 'grade',
                'entity_type' => 'grade',
                'entity_id' => $result,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->setFlash('success', 'تم إضافة الدرجة بنجاح.');
        } else {
            $this->setFlash('error', 'حدث خطأ أثناء إضافة الدرجة.');
        }
        
        $this->redirect('/teacher/students/show/' . $studentId);
    }
    
    /**
     * إضافة ملاحظة للطالب
     */
    public function addNote()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->setFlash('error', 'طريقة الطلب غير صحيحة.');
            $this->redirect('/teacher/students');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج البيانات
        $studentId = $this->request->post('student_id');
        $notes = $this->request->post('notes');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'student_id' => $studentId,
            'notes' => $notes
        ], [
            'student_id' => 'required|numeric',
            'notes' => 'required'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/students/show/' . $studentId);
        }
        
        // الحصول على بيانات الطالب
        $student = $this->studentModel->find($studentId);
        
        if (!$student) {
            $this->setFlash('error', 'الطالب غير موجود.');
            $this->redirect('/teacher/students');
        }
        
        // التحقق من صلاحية المعلم للوصول إلى الطالب
        if (!$this->hasAccessToClass($teacherId, $student['class_id'])) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الطالب.');
            $this->redirect('/teacher/students');
        }
        
        // إضافة الملاحظة
        $noteModel = new StudentNote();
        $result = $noteModel->addNote([
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            $this->setFlash('success', 'تم إضافة الملاحظة بنجاح.');
        } else {
            $this->setFlash('error', 'حدث خطأ أثناء إضافة الملاحظة.');
        }
        
        $this->redirect('/teacher/students/show/' . $studentId);
    }
    
    /**
     * تواصل مع ولي الأمر
     */
    public function contactParent()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->setFlash('error', 'طريقة الطلب غير صحيحة.');
            $this->redirect('/teacher/students');
        }
        
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // استخراج البيانات
        $studentId = $this->request->post('student_id');
        $subject = $this->request->post('subject');
        $message = $this->request->post('message');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'student_id' => $studentId,
            'subject' => $subject,
            'message' => $message
        ], [
            'student_id' => 'required|numeric',
            'subject' => 'required',
            'message' => 'required'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/students/show/' . $studentId);
        }
        
        // الحصول على بيانات الطالب
        $student = $this->studentModel->find($studentId);
        
        if (!$student) {
            $this->setFlash('error', 'الطالب غير موجود.');
            $this->redirect('/teacher/students');
        }
        
        // التحقق من صلاحية المعلم للوصول إلى الطالب
        if (!$this->hasAccessToClass($teacherId, $student['class_id'])) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الطالب.');
            $this->redirect('/teacher/students');
        }
        
        // الحصول على ولي الأمر
        $parent = (new ParentModel())->getParentByStudent($studentId);
        
        if (!$parent) {
            $this->setFlash('error', 'لم يتم العثور على ولي أمر مرتبط بالطالب.');
            $this->redirect('/teacher/students/show/' . $studentId);
        }
        
        // إرسال رسالة لولي الأمر
        $messageModel = new Message();
        $result = $messageModel->send([
            'sender_id' => $this->auth->id(),
            'receiver_id' => $parent['user_id'],
            'subject' => $subject,
            'message' => $message,
            'student_id' => $studentId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            // إرسال إشعار لولي الأمر
            (new Notification())->create([
                'user_id' => $parent['user_id'],
                'title' => 'رسالة جديدة من المعلم',
                'message' => 'لديك رسالة جديدة من المعلم بخصوص الطالب ' . $student['first_name'] . ' ' . $student['last_name'],
                'type' => 'message',
                'entity_type' => 'message',
                'entity_id' => $result,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->setFlash('success', 'تم إرسال الرسالة إلى ولي الأمر بنجاح.');
        } else {
            $this->setFlash('error', 'حدث خطأ أثناء إرسال الرسالة.');
        }
        
        $this->redirect('/teacher/students/show/' . $studentId);
    }
    
    /**
     * الحصول على إحصائيات الطالب
     * 
     * @param int $studentId معرّف الطالب
     * @param int $teacherId معرّف المعلم
     * @return array الإحصائيات
     */
    private function getStudentStats($studentId, $teacherId)
    {
        // الحصول على إحصائيات الحضور
        $attendanceStats = $this->attendanceModel->getStudentAttendanceStatsByTeacher($studentId, $teacherId);
        
        // الحصول على إحصائيات المهام
        $assignmentStats = (new Assignment())->getStudentAssignmentStats($studentId, $teacherId);
        
        // الحصول على إحصائيات الدرجات
        $gradeStats = $this->gradeModel->getStudentGradeStatsByTeacher($studentId, $teacherId);
        
        return [
            'attendance_rate' => $attendanceStats['attendance_rate'] ?? 0,
            'absent_days' => $attendanceStats['absent_days'] ?? 0,
            'late_days' => $attendanceStats['late_days'] ?? 0,
            'excused_absences' => $attendanceStats['excused_absences'] ?? 0,
            'assignment_completion' => $assignmentStats['completion_rate'] ?? 0,
            'submitted_assignments' => $assignmentStats['submitted_count'] ?? 0,
            'total_assignments' => $assignmentStats['total_count'] ?? 0,
            'late_submissions' => $assignmentStats['late_count'] ?? 0,
            'average_grade' => $gradeStats['average'] ?? 0,
            'highest_grade' => $gradeStats['highest'] ?? 0,
            'lowest_grade' => $gradeStats['lowest'] ?? 0
        ];
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
     * التحقق مما إذا كان المعلم لديه صلاحية الوصول إلى المادة
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $subjectId معرّف المادة
     * @return bool هل لديه صلاحية الوصول
     */
    private function hasAccessToSubject($teacherId, $subjectId)
    {
        $query = "SELECT COUNT(*) as count FROM teacher_assignments WHERE teacher_id = ? AND subject_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId, $subjectId]);
        
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