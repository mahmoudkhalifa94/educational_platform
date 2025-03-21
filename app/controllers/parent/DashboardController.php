<?php
/**
 * app/controllers/parent/DashboardController.php
 * متحكم لوحة التحكم لولي الأمر
 * يدير عرض ملخص البيانات والإحصائيات لولي الأمر
 */
class DashboardController extends Controller
{
    private $parentModel;
    private $childModel;
    private $assignmentModel;
    private $notificationModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->parentModel = new ParentModel();
        $this->childModel = new Student();
        $this->assignmentModel = new Assignment();
        $this->notificationModel = new Notification();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('parent');
    }
    
    /**
     * عرض لوحة التحكم
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
        
        if (empty($children)) {
            $this->setFlash('info', 'لم يتم ربط أي طالب بحسابك حتى الآن.');
        }
        
        // تحضير بيانات لوحة التحكم
        $dashboardData = [];
        
        foreach ($children as $child) {
            // الحصول على المهام القادمة للطالب
            $upcomingAssignments = $this->assignmentModel->getUpcomingAssignmentsByStudent($child['id'], 5);
            
            // الحصول على آخر الدرجات للطالب
            $latestGrades = $this->assignmentModel->getLatestGradesByStudent($child['id'], 5);
            
            // الحصول على الإحصائيات الأساسية للطالب
            $studentStats = $this->getStudentStats($child['id']);
            
            $dashboardData[$child['id']] = [
                'student' => $child,
                'upcoming_assignments' => $upcomingAssignments,
                'latest_grades' => $latestGrades,
                'stats' => $studentStats
            ];
        }
        
        // الحصول على آخر الإشعارات لولي الأمر
        $notifications = $this->notificationModel->getLatestNotificationsByUser($this->auth->id(), 5);
        
        echo $this->render('parent/dashboard', [
            'children' => $children,
            'dashboardData' => $dashboardData,
            'notifications' => $notifications,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض ملخص معلومات طالب محدد
     * 
     * @param int $childId معرّف الطالب
     */
    public function childSummary($childId)
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
            $this->redirect('/parent/dashboard');
        }
        
        // الحصول على بيانات الطالب
        $child = $this->childModel->getStudentWithDetails($childId);
        
        if (!$child) {
            $this->setFlash('error', 'لم يتم العثور على بيانات الطالب.');
            $this->redirect('/parent/dashboard');
        }
        
        // الحصول على المهام القادمة للطالب
        $upcomingAssignments = $this->assignmentModel->getUpcomingAssignmentsByStudent($childId, 10);
        
        // الحصول على آخر الدرجات للطالب
        $latestGrades = $this->assignmentModel->getLatestGradesByStudent($childId, 10);
        
        // الحصول على الإحصائيات المفصلة للطالب
        $studentStats = $this->getStudentDetailedStats($childId);
        
        // الحصول على معدلات المواد
        $subjectAverages = $this->getStudentSubjectAverages($childId);
        
        echo $this->render('parent/child_summary', [
            'child' => $child,
            'upcomingAssignments' => $upcomingAssignments,
            'latestGrades' => $latestGrades,
            'stats' => $studentStats,
            'subjectAverages' => $subjectAverages,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * الحصول على الإحصائيات الأساسية للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return array إحصائيات الطالب
     */
    private function getStudentStats($studentId)
    {
        $stats = [
            'upcoming_assignments_count' => 0,
            'average_grade' => 0,
            'completed_assignments' => 0,
            'overdue_assignments' => 0
        ];
        
        // عدد المهام القادمة
        $query = "SELECT COUNT(*) as count 
                 FROM assignments a 
                 JOIN class_subjects cs ON a.subject_id = cs.subject_id 
                 WHERE cs.class_id = (SELECT class_id FROM students WHERE id = ?) 
                 AND a.due_date > CURDATE() 
                 AND a.is_published = 1";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['upcoming_assignments_count'] = $result['count'] ?? 0;
        
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
     * الحصول على الإحصائيات المفصلة للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return array إحصائيات مفصلة للطالب
     */
    private function getStudentDetailedStats($studentId)
    {
        $stats = $this->getStudentStats($studentId);
        
        // إجمالي المهام
        $query = "SELECT COUNT(*) as count 
                 FROM assignments a 
                 JOIN class_subjects cs ON a.subject_id = cs.subject_id 
                 WHERE cs.class_id = (SELECT class_id FROM students WHERE id = ?) 
                 AND a.is_published = 1";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['total_assignments'] = $result['count'] ?? 0;
        
        // نسبة إكمال المهام
        $stats['completion_rate'] = $stats['total_assignments'] > 0 
            ? round(($stats['completed_assignments'] / $stats['total_assignments']) * 100) 
            : 0;
        
        // توزيع الدرجات
        $query = "SELECT 
                    SUM(CASE WHEN (g.points / a.points * 100) >= 90 THEN 1 ELSE 0 END) as excellent,
                    SUM(CASE WHEN (g.points / a.points * 100) >= 80 AND (g.points / a.points * 100) < 90 THEN 1 ELSE 0 END) as very_good,
                    SUM(CASE WHEN (g.points / a.points * 100) >= 70 AND (g.points / a.points * 100) < 80 THEN 1 ELSE 0 END) as good,
                    SUM(CASE WHEN (g.points / a.points * 100) >= 60 AND (g.points / a.points * 100) < 70 THEN 1 ELSE 0 END) as fair,
                    SUM(CASE WHEN (g.points / a.points * 100) < 60 THEN 1 ELSE 0 END) as poor
                 FROM grades g 
                 JOIN submissions s ON g.submission_id = s.id 
                 JOIN assignments a ON s.assignment_id = a.id 
                 WHERE s.student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        
        $stats['grade_distribution'] = [
            'excellent' => (int)($result['excellent'] ?? 0),
            'very_good' => (int)($result['very_good'] ?? 0),
            'good' => (int)($result['good'] ?? 0),
            'fair' => (int)($result['fair'] ?? 0),
            'poor' => (int)($result['poor'] ?? 0)
        ];
        
        // تتبع التقدم الزمني
        $query = "SELECT 
                    DATE_FORMAT(a.due_date, '%Y-%m') as month,
                    AVG(g.points / a.points * 100) as average
                 FROM grades g 
                 JOIN submissions s ON g.submission_id = s.id 
                 JOIN assignments a ON s.assignment_id = a.id 
                 WHERE s.student_id = ?
                 GROUP BY DATE_FORMAT(a.due_date, '%Y-%m')
                 ORDER BY month";
        $result = $this->db->fetchAll($query, [$studentId]);
        
        $stats['progress_data'] = $result;
        
        return $stats;
    }
    
    /**
     * الحصول على معدلات الطالب حسب المواد
     * 
     * @param int $studentId معرّف الطالب
     * @return array معدلات المواد
     */
    private function getStudentSubjectAverages($studentId)
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