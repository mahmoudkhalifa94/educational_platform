<?php
/**
 * app/controllers/parent/ChildrenController.php
 * متحكم إدارة الأبناء لولي الأمر
 * يدير عرض معلومات الأبناء وتقاريرهم وإدارة متابعتهم
 */
class ChildrenController extends Controller
{
    private $parentModel;
    private $childModel;
    private $assignmentModel;
    private $attendanceModel;
    private $gradeModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->parentModel = new ParentModel();
        $this->childModel = new Student();
        $this->assignmentModel = new Assignment();
        $this->attendanceModel = new Attendance();
        $this->gradeModel = new Grade();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('parent');
    }
    
    /**
     * عرض قائمة الأبناء
     */
    public function index()
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // الحصول على قائمة الأبناء
        $children = $this->parentModel->getChildrenByParentId($parentId);
        
        echo $this->render('parent/children/index', [
            'children' => $children,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض معلومات طالب محدد
     * 
     * @param int $id معرّف الطالب
     */
    public function show($id)
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // التحقق من أن الطالب ينتمي لولي الأمر
        $isParentChild = $this->parentModel->isParentChild($parentId, $id);
        
        if (!$isParentChild) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى بيانات هذا الطالب.');
            $this->redirect('/parent/children');
        }
        
        // الحصول على بيانات الطالب
        $child = $this->childModel->getStudentWithDetails($id);
        
        if (!$child) {
            $this->setFlash('error', 'لم يتم العثور على بيانات الطالب.');
            $this->redirect('/parent/children');
        }
        
        // الحصول على مواد الطالب
        $subjects = $this->childModel->getStudentSubjects($id);
        
        // الحصول على الإحصائيات الأساسية للطالب
        $stats = $this->getStudentBasicStats($id);
        
        echo $this->render('parent/children/show', [
            'child' => $child,
            'subjects' => $subjects,
            'stats' => $stats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير المهام للطالب
     * 
     * @param int $id معرّف الطالب
     */
    public function assignments($id)
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // التحقق من أن الطالب ينتمي لولي الأمر
        $isParentChild = $this->parentModel->isParentChild($parentId, $id);
        
        if (!$isParentChild) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى بيانات هذا الطالب.');
            $this->redirect('/parent/children');
        }
        
        // الحصول على بيانات الطالب
        $child = $this->childModel->find($id);
        
        if (!$child) {
            $this->setFlash('error', 'لم يتم العثور على بيانات الطالب.');
            $this->redirect('/parent/children');
        }
        
        // استخراج معلمات التصفية
        $subjectId = $this->request->get('subject_id');
        $status = $this->request->get('status', 'all');
        
        // الحصول على المهام
        $assignments = $this->assignmentModel->getStudentAssignments($id, $subjectId, $status);
        
        // الحصول على مواد الطالب للتصفية
        $subjects = $this->childModel->getStudentSubjects($id);
        
        // حساب إحصائيات المهام
        $assignmentStats = $this->getAssignmentStats($id);
        
        echo $this->render('parent/children/assignments', [
            'child' => $child,
            'assignments' => $assignments,
            'subjects' => $subjects,
            'selectedSubject' => $subjectId,
            'selectedStatus' => $status,
            'stats' => $assignmentStats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تفاصيل مهمة محددة
     * 
     * @param int $childId معرّف الطالب
     * @param int $assignmentId معرّف المهمة
     */
    public function assignmentDetails($childId, $assignmentId)
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // التحقق من أن الطالب ينتمي لولي الأمر
        $isParentChild = $this->parentModel->isParentChild($parentId, $childId);
        
        if (!$isParentChild) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى بيانات هذا الطالب.');
            $this->redirect('/parent/children');
        }
        
        // الحصول على بيانات الطالب
        $child = $this->childModel->find($childId);
        
        if (!$child) {
            $this->setFlash('error', 'لم يتم العثور على بيانات الطالب.');
            $this->redirect('/parent/children');
        }
        
        // الحصول على تفاصيل المهمة
        $assignment = $this->assignmentModel->getAssignmentWithDetails($assignmentId);
        
        if (!$assignment) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المهمة.');
            $this->redirect('/parent/children/' . $childId . '/assignments');
        }
        
        // الحصول على إجابة الطالب إن وجدت
        $submission = $this->assignmentModel->getStudentSubmission($assignmentId, $childId);
        
        echo $this->render('parent/children/assignment_details', [
            'child' => $child,
            'assignment' => $assignment,
            'submission' => $submission,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الدرجات للطالب
     * 
     * @param int $id معرّف الطالب
     */
    public function grades($id)
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // التحقق من أن الطالب ينتمي لولي الأمر
        $isParentChild = $this->parentModel->isParentChild($parentId, $id);
        
        if (!$isParentChild) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى بيانات هذا الطالب.');
            $this->redirect('/parent/children');
        }
        
        // الحصول على بيانات الطالب
        $child = $this->childModel->find($id);
        
        if (!$child) {
            $this->setFlash('error', 'لم يتم العثور على بيانات الطالب.');
            $this->redirect('/parent/children');
        }
        
        // استخراج معلمات التصفية
        $subjectId = $this->request->get('subject_id');
        $timeRange = $this->request->get('time_range', 'all');
        
        // الحصول على تفاصيل الدرجات
        $grades = $this->gradeModel->getStudentGrades($id, $subjectId, $timeRange);
        
        // الحصول على مواد الطالب للتصفية
        $subjects = $this->childModel->getStudentSubjects($id);
        
        // حساب معدلات المواد
        $subjectAverages = $this->getSubjectAverages($id);
        
        echo $this->render('parent/children/grades', [
            'child' => $child,
            'grades' => $grades,
            'subjects' => $subjects,
            'subjectAverages' => $subjectAverages,
            'selectedSubject' => $subjectId,
            'selectedTimeRange' => $timeRange,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض تقرير الحضور والغياب للطالب
     * 
     * @param int $id معرّف الطالب
     */
    public function attendance($id)
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // التحقق من أن الطالب ينتمي لولي الأمر
        $isParentChild = $this->parentModel->isParentChild($parentId, $id);
        
        if (!$isParentChild) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى بيانات هذا الطالب.');
            $this->redirect('/parent/children');
        }
        
        // الحصول على بيانات الطالب
        $child = $this->childModel->find($id);
        
        if (!$child) {
            $this->setFlash('error', 'لم يتم العثور على بيانات الطالب.');
            $this->redirect('/parent/children');
        }
        
        // استخراج معلمات التصفية
        $month = $this->request->get('month', date('Y-m'));
        
        // الحصول على سجلات الحضور والغياب
        $attendanceRecords = $this->attendanceModel->getStudentAttendanceByMonth($id, $month);
        
        // حساب إحصائيات الحضور
        $attendanceStats = $this->getAttendanceStats($id);
        
        echo $this->render('parent/children/attendance', [
            'child' => $child,
            'attendanceRecords' => $attendanceRecords,
            'selectedMonth' => $month,
            'stats' => $attendanceStats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * الحصول على الإحصائيات الأساسية للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return array إحصائيات الطالب
     */
    private function getStudentBasicStats($studentId)
    {
        $stats = [
            'attendance_rate' => 0,
            'average_grade' => 0,
            'completed_assignments' => 0,
            'overdue_assignments' => 0
        ];
        
        // نسبة الحضور
        $query = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
                 FROM attendance
                 WHERE student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        
        if ($result && $result['total_days'] > 0) {
            $stats['attendance_rate'] = round(($result['present_days'] / $result['total_days']) * 100);
        }
        
        // متوسط الدرجات
        $query = "SELECT AVG(g.points / a.points * 100) as average 
                 FROM grades g 
                 JOIN submissions s ON g.submission_id = s.id 
                 JOIN assignments a ON s.assignment_id = a.id 
                 WHERE s.student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['average_grade'] = round($result['average'] ?? 0);
        
        // عدد المهام المكتملة
        $query = "SELECT COUNT(*) as count 
                 FROM submissions 
                 WHERE student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['completed_assignments'] = $result['count'] ?? 0;
        
        // عدد المهام المتأخرة
        $query = "SELECT COUNT(*) as count 
                 FROM assignments a 
                 JOIN class_subjects cs ON a.subject_id = cs.subject_id 
                 WHERE cs.class_id = (SELECT class_id FROM students WHERE id = ?) 
                 AND a.due_date < CURDATE() 
                 AND a.is_published = 1 
                 AND NOT EXISTS (
                     SELECT 1 FROM submissions s 
                     WHERE s.assignment_id = a.id AND s.student_id = ?
                 )";
        $result = $this->db->fetchOne($query, [$studentId, $studentId]);
        $stats['overdue_assignments'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على إحصائيات المهام للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return array إحصائيات المهام
     */
    private function getAssignmentStats($studentId)
    {
        $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'overdue' => 0,
            'graded' => 0,
            'completion_rate' => 0
        ];
        
        // إجمالي المهام
        $query = "SELECT COUNT(*) as count 
                 FROM assignments a 
                 JOIN class_subjects cs ON a.subject_id = cs.subject_id 
                 WHERE cs.class_id = (SELECT class_id FROM students WHERE id = ?) 
                 AND a.is_published = 1";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['total'] = $result['count'] ?? 0;
        
        // المهام المكتملة
        $query = "SELECT COUNT(*) as count 
                 FROM submissions 
                 WHERE student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['completed'] = $result['count'] ?? 0;
        
        // المهام المعلقة (لم يحن موعدها بعد)
        $query = "SELECT COUNT(*) as count 
                 FROM assignments a 
                 JOIN class_subjects cs ON a.subject_id = cs.subject_id 
                 WHERE cs.class_id = (SELECT class_id FROM students WHERE id = ?) 
                 AND a.due_date > CURDATE() 
                 AND a.is_published = 1 
                 AND NOT EXISTS (
                     SELECT 1 FROM submissions s 
                     WHERE s.assignment_id = a.id AND s.student_id = ?
                 )";
        $result = $this->db->fetchOne($query, [$studentId, $studentId]);
        $stats['pending'] = $result['count'] ?? 0;
        
        // المهام المتأخرة
        $query = "SELECT COUNT(*) as count 
                 FROM assignments a 
                 JOIN class_subjects cs ON a.subject_id = cs.subject_id 
                 WHERE cs.class_id = (SELECT class_id FROM students WHERE id = ?) 
                 AND a.due_date < CURDATE() 
                 AND a.is_published = 1 
                 AND NOT EXISTS (
                     SELECT 1 FROM submissions s 
                     WHERE s.assignment_id = a.id AND s.student_id = ?
                 )";
        $result = $this->db->fetchOne($query, [$studentId, $studentId]);
        $stats['overdue'] = $result['count'] ?? 0;
        
        // المهام المصححة
        $query = "SELECT COUNT(*) as count 
                 FROM submissions s 
                 JOIN grades g ON s.id = g.submission_id 
                 WHERE s.student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['graded'] = $result['count'] ?? 0;
        
        // معدل إكمال المهام
        $stats['completion_rate'] = $stats['total'] > 0 
            ? round(($stats['completed'] / $stats['total']) * 100) 
            : 0;
        
        return $stats;
    }
    
    /**
     * الحصول على معدلات المواد
     * 
     * @param int $studentId معرّف الطالب
     * @return array معدلات المواد
     */
    private function getSubjectAverages($studentId)
    {
        $query = "SELECT 
                    sub.id as subject_id,
                    sub.name as subject_name,
                    sub.code as subject_code,
                    AVG(g.points / a.points * 100) as average
                 FROM grades g 
                 JOIN submissions s ON g.submission_id = s.id 
                 JOIN assignments a ON s.assignment_id = a.id 
                 JOIN subjects sub ON a.subject_id = sub.id
                 WHERE s.student_id = ?
                 GROUP BY sub.id, sub.name, sub.code
                 ORDER BY average DESC";
        
        return $this->db->fetchAll($query, [$studentId]);
    }
    
    /**
     * الحصول على إحصائيات الحضور للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return array إحصائيات الحضور
     */
    private function getAttendanceStats($studentId)
    {
        $stats = [
            'total_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'late_days' => 0,
            'excused_days' => 0,
            'attendance_rate' => 0,
            'monthly_data' => []
        ];
        
        // إحصائيات عامة
        $query = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
                 FROM attendance
                 WHERE student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        
        if ($result) {
            $stats['total_days'] = $result['total_days'] ?? 0;
            $stats['present_days'] = $result['present_days'] ?? 0;
            $stats['absent_days'] = $result['absent_days'] ?? 0;
            $stats['late_days'] = $result['late_days'] ?? 0;
            $stats['excused_days'] = $result['excused_days'] ?? 0;
            $stats['attendance_rate'] = $stats['total_days'] > 0 
                ? round(($stats['present_days'] / $stats['total_days']) * 100) 
                : 0;
        }
        
        // بيانات الحضور الشهرية
        $query = "SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month,
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
                 FROM attendance
                 WHERE student_id = ?
                 GROUP BY DATE_FORMAT(date, '%Y-%m')
                 ORDER BY month";
        $results = $this->db->fetchAll($query, [$studentId]);
        
        foreach ($results as $row) {
            $attendanceRate = $row['total_days'] > 0 
                ? round(($row['present_days'] / $row['total_days']) * 100) 
                : 0;
            
            $stats['monthly_data'][] = [
                'month' => $row['month'],
                'total_days' => $row['total_days'],
                'present_days' => $row['present_days'],
                'absent_days' => $row['absent_days'],
                'late_days' => $row['late_days'],
                'excused_days' => $row['excused_days'],
                'attendance_rate' => $attendanceRate
            ];
        }
        
        return $stats;
    }
    
    /**
     * الحصول على معرّف ولي الأمر للمستخدم الحالي
     * 
     * @return int|false معرّف ولي الأمر أو false إذا لم يتم العثور عليه
     */
    private function getParentId()
    {
        $parent = $this->parentModel->getParentByUserId($this->auth->id());
        return $parent ? $parent['id'] : false;
    }
}