<?php
/**
 * app/models/Assignment.php
 * نموذج المهمة
 * يتعامل مع بيانات المهام في النظام
 */
class Assignment extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'assignments';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'subject_id', 'teacher_id', 'class_id', 'title', 'description',
        'type', 'content', 'start_date', 'due_date', 'points', 'is_published'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء مهمة جديدة
     * 
     * @param array $data بيانات المهمة
     * @return int|false معرّف المهمة الجديدة أو false في حالة الفشل
     */
    public function createAssignment($data)
    {
        // تحويل محتوى JSON إلى نص إذا لزم الأمر
        if (isset($data['content']) && is_array($data['content'])) {
            $data['content'] = json_encode($data['content']);
        }
        
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات المهمة
     * 
     * @param int $id معرّف المهمة
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateAssignment($id, $data)
    {
        // تحويل محتوى JSON إلى نص إذا لزم الأمر
        if (isset($data['content']) && is_array($data['content'])) {
            $data['content'] = json_encode($data['content']);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على مهمة مع التفاصيل الكاملة
     * 
     * @param int $assignmentId معرّف المهمة
     * @return array|false بيانات المهمة أو false إذا لم يتم العثور عليها
     */
    public function getAssignmentWithDetails($assignmentId)
    {
        $query = "SELECT a.*, s.name as subject_name, s.code as subject_code, 
                 c.name as class_name, c.grade_level,
                 u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id) as submissions_count,
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id AND sub.status = 'graded') as graded_count
                 FROM {$this->table} a 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN classes c ON a.class_id = c.id 
                 JOIN teachers t ON a.teacher_id = t.id 
                 JOIN users u ON t.user_id = u.id 
                 WHERE a.id = ? 
                 LIMIT 1";
        
        $assignment = $this->db->fetchOne($query, [$assignmentId]);
        
        if ($assignment && isset($assignment['content'])) {
            $assignment['content'] = json_decode($assignment['content'], true);
        }
        
        return $assignment;
    }
    
    /**
     * الحصول على المهام حسب المعلم
     * 
     * @param int $teacherId معرّف المعلم
     * @param string|null $status حالة المهمة (اختياري)
     * @return array قائمة المهام
     */
    public function getAssignmentsByTeacher($teacherId, $status = null)
    {
        $query = "SELECT a.*, s.name as subject_name, c.name as class_name,
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id) as submissions_count,
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id AND sub.status = 'graded') as graded_count
                 FROM {$this->table} a 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN classes c ON a.class_id = c.id 
                 WHERE a.teacher_id = ?";
        
        $params = [$teacherId];
        
        if ($status === 'active') {
            $query .= " AND a.due_date >= CURDATE()";
        } elseif ($status === 'past') {
            $query .= " AND a.due_date < CURDATE()";
        } elseif ($status === 'published') {
            $query .= " AND a.is_published = 1";
        } elseif ($status === 'draft') {
            $query .= " AND a.is_published = 0";
        }
        
        $query .= " ORDER BY a.due_date DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على المهام حسب الصف
     * 
     * @param int $classId معرّف الصف
     * @param int|null $subjectId معرّف المادة (اختياري)
     * @param bool $publishedOnly جلب المهام المنشورة فقط
     * @return array قائمة المهام
     */
    public function getAssignmentsByClass($classId, $subjectId = null, $publishedOnly = true)
    {
        $query = "SELECT a.*, s.name as subject_name, s.code as subject_code,
                 u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id) as submissions_count,
                 (SELECT COUNT(*) FROM students st WHERE st.class_id = a.class_id) as students_count
                 FROM {$this->table} a 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN teachers t ON a.teacher_id = t.id 
                 JOIN users u ON t.user_id = u.id 
                 WHERE a.class_id = ?";
        
        $params = [$classId];
        
        if ($subjectId !== null) {
            $query .= " AND a.subject_id = ?";
            $params[] = $subjectId;
        }
        
        if ($publishedOnly) {
            $query .= " AND a.is_published = 1";
        }
        
        $query .= " ORDER BY a.due_date DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * نشر مهمة
     * 
     * @param int $assignmentId معرّف المهمة
     * @return bool نجاح العملية أم لا
     */
    public function publishAssignment($assignmentId)
    {
        return $this->update($assignmentId, ['is_published' => 1]);
    }
    
    /**
     * الحصول على إجابات مهمة معينة
     * 
     * @param int $assignmentId معرّف المهمة
     * @param string|null $status حالة الإجابة (اختياري)
     * @return array قائمة الإجابات
     */
    public function getAssignmentSubmissions($assignmentId, $status = null)
    {
        $query = "SELECT sub.*, 
                 u.first_name, u.last_name, u.email,
                 g.id as grade_id, g.points, g.feedback, g.graded_at, g.graded_by,
                 t.first_name as grader_first_name, t.last_name as grader_last_name
                 FROM submissions sub 
                 JOIN students s ON sub.student_id = s.id 
                 JOIN users u ON s.user_id = u.id 
                 LEFT JOIN grades g ON sub.id = g.submission_id 
                 LEFT JOIN users t ON g.graded_by = t.id
                 WHERE sub.assignment_id = ?";
        
        $params = [$assignmentId];
        
        if ($status !== null) {
            $query .= " AND sub.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY sub.submitted_at DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على قائمة الطلاب الذين لم يقدموا إجابة على مهمة معينة
     * 
     * @param int $assignmentId معرّف المهمة
     * @return array قائمة الطلاب
     */
    public function getStudentsWithoutSubmission($assignmentId)
    {
        $assignment = $this->find($assignmentId);
        
        if (!$assignment) {
            return [];
        }
        
        $query = "SELECT s.id as student_id, s.student_id as student_number, u.first_name, u.last_name, u.email
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE s.class_id = ? 
                 AND s.id NOT IN (
                     SELECT student_id FROM submissions WHERE assignment_id = ?
                 )
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$assignment['class_id'], $assignmentId]);
    }
    
    /**
     * حساب إحصائيات المهمة
     * 
     * @param int $assignmentId معرّف المهمة
     * @return array الإحصائيات
     */
    public function getAssignmentStats($assignmentId)
    {
        $stats = [];
        
        // الحصول على معلومات المهمة
        $assignment = $this->getAssignmentWithDetails($assignmentId);
        
        if (!$assignment) {
            return $stats;
        }
        
        // عدد الطلاب في الصف
        $query = "SELECT COUNT(*) as count FROM students WHERE class_id = ?";
        $result = $this->db->fetchOne($query, [$assignment['class_id']]);
        $stats['total_students'] = $result['count'] ?? 0;
        
        // عدد الإجابات المقدمة
        $query = "SELECT COUNT(*) as count FROM submissions WHERE assignment_id = ?";
        $result = $this->db->fetchOne($query, [$assignmentId]);
        $stats['submissions_count'] = $result['count'] ?? 0;
        
        // عدد الإجابات المصححة
        $query = "SELECT COUNT(*) as count FROM submissions WHERE assignment_id = ? AND status = 'graded'";
        $result = $this->db->fetchOne($query, [$assignmentId]);
        $stats['graded_count'] = $result['count'] ?? 0;
        
        // عدد الإجابات المتأخرة
        $query = "SELECT COUNT(*) as count FROM submissions WHERE assignment_id = ? AND status = 'late'";
        $result = $this->db->fetchOne($query, [$assignmentId]);
        $stats['late_count'] = $result['count'] ?? 0;
        
        // نسبة المشاركة
        $stats['participation_rate'] = $stats['total_students'] > 0 ? 
            round(($stats['submissions_count'] / $stats['total_students']) * 100, 1) : 0;
        
        // نسبة التصحيح
        $stats['grading_rate'] = $stats['submissions_count'] > 0 ? 
            round(($stats['graded_count'] / $stats['submissions_count']) * 100, 1) : 0;
        
        // متوسط الدرجات
        $query = "SELECT AVG(g.points) as avg_points, MAX(g.points) as max_points, MIN(g.points) as min_points
                 FROM grades g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 WHERE sub.assignment_id = ?";
        $result = $this->db->fetchOne($query, [$assignmentId]);
        
        $stats['avg_points'] = $result['avg_points'] !== null ? round($result['avg_points'], 1) : 0;
        $stats['max_points'] = $result['max_points'] ?? 0;
        $stats['min_points'] = $result['min_points'] ?? 0;
        $stats['avg_percentage'] = $assignment['points'] > 0 ? 
            round(($stats['avg_points'] / $assignment['points']) * 100, 1) : 0;
        
        // توزيع الدرجات
        $query = "SELECT 
                 COUNT(CASE WHEN (g.points / ?) >= 0.9 THEN 1 END) as a_count,
                 COUNT(CASE WHEN (g.points / ?) >= 0.8 AND (g.points / ?) < 0.9 THEN 1 END) as b_count,
                 COUNT(CASE WHEN (g.points / ?) >= 0.7 AND (g.points / ?) < 0.8 THEN 1 END) as c_count,
                 COUNT(CASE WHEN (g.points / ?) >= 0.6 AND (g.points / ?) < 0.7 THEN 1 END) as d_count,
                 COUNT(CASE WHEN (g.points / ?) < 0.6 THEN 1 END) as f_count
                 FROM grades g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 WHERE sub.assignment_id = ?";
        
        $result = $this->db->fetchOne($query, [
            $assignment['points'], $assignment['points'], $assignment['points'], 
            $assignment['points'], $assignment['points'], $assignment['points'], 
            $assignment['points'], $assignment['points'], $assignmentId
        ]);
        
        $stats['grade_distribution'] = [
            'A' => $result['a_count'] ?? 0,
            'B' => $result['b_count'] ?? 0,
            'C' => $result['c_count'] ?? 0,
            'D' => $result['d_count'] ?? 0,
            'F' => $result['f_count'] ?? 0
        ];
        
        return $stats;
    }
    
    /**
     * نسخ مهمة إلى صف آخر
     * 
     * @param int $assignmentId معرّف المهمة المصدر
     * @param int $targetClassId معرّف الصف الهدف
     * @param bool $publish نشر المهمة مباشرة أم لا
     * @return int|false معرّف المهمة الجديدة أو false في حالة الفشل
     */
    public function copyAssignmentToClass($assignmentId, $targetClassId, $publish = false)
    {
        // الحصول على بيانات المهمة المصدر
        $sourceAssignment = $this->find($assignmentId);
        
        if (!$sourceAssignment) {
            return false;
        }
        
        // إنشاء بيانات المهمة الجديدة
        $newAssignmentData = [
            'subject_id' => $sourceAssignment['subject_id'],
            'teacher_id' => $sourceAssignment['teacher_id'],
            'class_id' => $targetClassId,
            'title' => $sourceAssignment['title'],
            'description' => $sourceAssignment['description'],
            'type' => $sourceAssignment['type'],
            'content' => $sourceAssignment['content'],
            'start_date' => $sourceAssignment['start_date'],
            'due_date' => $sourceAssignment['due_date'],
            'points' => $sourceAssignment['points'],
            'is_published' => $publish ? 1 : 0
        ];
        
        // إنشاء المهمة الجديدة
        return $this->createAssignment($newAssignmentData);
    }
}