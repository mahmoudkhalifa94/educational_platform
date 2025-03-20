<?php
/**
 * app/models/Teacher.php
 * نموذج المعلم
 * يتعامل مع بيانات المعلمين في النظام
 */
class Teacher extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'teachers';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'user_id', 'specialization', 'qualification'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء معلم جديد
     * 
     * @param array $data بيانات المعلم
     * @return int|false معرّف المعلم الجديد أو false في حالة الفشل
     */
    public function createTeacher($data)
    {
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات المعلم
     * 
     * @param int $id معرّف المعلم
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateTeacher($id, $data)
    {
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على معلومات المعلم بالكامل (مع المستخدم)
     * 
     * @param int $teacherId معرّف المعلم
     * @return array|false بيانات المعلم أو false إذا لم يتم العثور عليه
     */
    public function getTeacherWithUser($teacherId)
    {
        $query = "SELECT t.*, u.* 
                 FROM {$this->table} t 
                 JOIN users u ON t.user_id = u.id 
                 WHERE t.id = ? 
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$teacherId]);
    }
    
    /**
     * الحصول على معلم بواسطة معرّف المستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @return array|false بيانات المعلم أو false إذا لم يتم العثور عليه
     */
    public function getTeacherByUserId($userId)
    {
        return $this->whereFirst('user_id', $userId);
    }
    
    /**
     * الحصول على المعلمين حسب المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array قائمة المعلمين
     */
    public function getTeachersBySchool($schoolId)
    {
        $query = "SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.active,
                 (SELECT COUNT(DISTINCT ta.subject_id) FROM teacher_assignments ta WHERE ta.teacher_id = t.id) as subjects_count,
                 (SELECT COUNT(DISTINCT ta.class_id) FROM teacher_assignments ta WHERE ta.teacher_id = t.id) as classes_count,
                 (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = t.id) as assignments_count
                 FROM {$this->table} t 
                 JOIN users u ON t.user_id = u.id 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'teacher')
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * البحث عن معلمين
     * 
     * @param int $schoolId معرّف المدرسة
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchTeachers($schoolId, $searchTerm)
    {
        $query = "SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.active 
                 FROM {$this->table} t 
                 JOIN users u ON t.user_id = u.id 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'teacher')
                 AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.specialization LIKE ?) 
                 ORDER BY u.first_name, u.last_name 
                 LIMIT 100";
        
        $searchParam = "%{$searchTerm}%";
        
        return $this->db->fetchAll($query, [$schoolId, $searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    /**
     * الحصول على الصفوف والمواد التي يدرّسها معلم
     * 
     * @param int $teacherId معرّف المعلم
     * @return array قائمة التعيينات
     */
    public function getTeacherAssignments($teacherId)
    {
        $query = "SELECT ta.*, c.name as class_name, c.grade_level, s.name as subject_name, s.code as subject_code 
                 FROM teacher_assignments ta 
                 JOIN classes c ON ta.class_id = c.id 
                 JOIN subjects s ON ta.subject_id = s.id 
                 WHERE ta.teacher_id = ? 
                 ORDER BY c.grade_level, c.name, s.name";
        
        return $this->db->fetchAll($query, [$teacherId]);
    }
    
    /**
     * الحصول على المهام التي أنشأها معلم
     * 
     * @param int $teacherId معرّف المعلم
     * @param string|null $status حالة المهمة (اختياري)
     * @return array قائمة المهام
     */
    public function getTeacherAssignments($teacherId, $status = null)
    {
        $query = "SELECT a.*, c.name as class_name, s.name as subject_name,
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id) as submissions_count,
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id AND sub.status = 'graded') as graded_count
                 FROM assignments a 
                 JOIN classes c ON a.class_id = c.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 WHERE a.teacher_id = ?";
        
        $params = [$teacherId];
        
        if ($status === 'active') {
            $query .= " AND a.due_date >= CURDATE()";
        } elseif ($status === 'past') {
            $query .= " AND a.due_date < CURDATE()";
        }
        
        $query .= " ORDER BY a.due_date DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * إحصائيات المعلم
     * 
     * @param int $teacherId معرّف المعلم
     * @return array الإحصائيات
     */
    public function getTeacherStats($teacherId)
    {
        $stats = [];
        
        // عدد الصفوف التي يدرّسها
        $query = "SELECT COUNT(DISTINCT class_id) as count FROM teacher_assignments WHERE teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['classes_count'] = $result['count'] ?? 0;
        
        // عدد المواد التي يدرّسها
        $query = "SELECT COUNT(DISTINCT subject_id) as count FROM teacher_assignments WHERE teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['subjects_count'] = $result['count'] ?? 0;
        
        // عدد المهام التي أنشأها
        $query = "SELECT COUNT(*) as count FROM assignments WHERE teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['assignments_count'] = $result['count'] ?? 0;
        
        // عدد المهام النشطة
        $query = "SELECT COUNT(*) as count FROM assignments WHERE teacher_id = ? AND due_date >= CURDATE()";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['active_assignments_count'] = $result['count'] ?? 0;
        
        // عدد الطلاب الذين يدرّسهم
        $query = "SELECT COUNT(DISTINCT s.id) as count 
                 FROM students s 
                 JOIN teacher_assignments ta ON s.class_id = ta.class_id 
                 WHERE ta.teacher_id = ?";
        $result = $this->db->fetchOne($query, [$teacherId]);
        $stats['students_count'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على معدل نشاط المعلم
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $days عدد الأيام السابقة للتحليل
     * @return array معلومات النشاط
     */
    public function getTeacherActivity($teacherId, $days = 30)
    {
        $activity = [];
        
        // عدد المهام التي تم إنشاؤها خلال الفترة
        $query = "SELECT COUNT(*) as count 
                 FROM assignments 
                 WHERE teacher_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $result = $this->db->fetchOne($query, [$teacherId, $days]);
        $activity['new_assignments'] = $result['count'] ?? 0;
        
        // عدد الإجابات التي تم تصحيحها خلال الفترة
        $query = "SELECT COUNT(*) as count 
                 FROM grades g 
                 JOIN submissions s ON g.submission_id = s.id 
                 JOIN assignments a ON s.assignment_id = a.id 
                 WHERE a.teacher_id = ? AND g.graded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $result = $this->db->fetchOne($query, [$teacherId, $days]);
        $activity['graded_submissions'] = $result['count'] ?? 0;
        
        // متوسط وقت التصحيح (بالساعات)
        $query = "SELECT AVG(TIMESTAMPDIFF(HOUR, s.submitted_at, g.graded_at)) as avg_hours 
                 FROM grades g 
                 JOIN submissions s ON g.submission_id = s.id 
                 JOIN assignments a ON s.assignment_id = a.id 
                 WHERE a.teacher_id = ? AND g.graded_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $result = $this->db->fetchOne($query, [$teacherId, $days]);
        $activity['avg_grading_time'] = $result['avg_hours'] !== null ? round($result['avg_hours'], 1) : null;
        
        // عدد الإجابات التي تم إرسالها لمهامه
        $query = "SELECT COUNT(*) as count 
                 FROM submissions s 
                 JOIN assignments a ON s.assignment_id = a.id 
                 WHERE a.teacher_id = ? AND s.submitted_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $result = $this->db->fetchOne($query, [$teacherId, $days]);
        $activity['submissions_received'] = $result['count'] ?? 0;
        
        // نسبة المهام التي تم تصحيحها
        if ($activity['submissions_received'] > 0) {
            $activity['grading_rate'] = round(($activity['graded_submissions'] / $activity['submissions_received']) * 100, 1);
        } else {
            $activity['grading_rate'] = 100; // إذا لم يكن هناك إجابات، فنسبة التصحيح 100%
        }
        
        return $activity;
    }
}