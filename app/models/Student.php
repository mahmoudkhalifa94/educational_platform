<?php
/**
 * app/models/Student.php
 * نموذج الطالب
 * يتعامل مع بيانات الطلاب في النظام
 */
class Student extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'students';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'user_id', 'class_id', 'student_id', 'parent_id', 
        'date_of_birth', 'address', 'emergency_contact', 'medical_notes'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء طالب جديد
     * 
     * @param array $data بيانات الطالب
     * @return int|false معرّف الطالب الجديد أو false في حالة الفشل
     */
    public function createStudent($data)
    {
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات الطالب
     * 
     * @param int $id معرّف الطالب
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateStudent($id, $data)
    {
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على معلومات الطالب بالكامل (مع المستخدم والصف)
     * 
     * @param int $studentId معرّف الطالب
     * @return array|false بيانات الطالب أو false إذا لم يتم العثور عليه
     */
    public function getStudentWithDetails($studentId)
    {
        $query = "SELECT s.*, u.first_name, u.last_name, u.email, u.active,
                 c.name as class_name, c.grade_level,
                 p.first_name as parent_first_name, p.last_name as parent_last_name, p.email as parent_email, p.phone as parent_phone
                 FROM {$this->table} s 
                 JOIN users u ON s.user_id = u.id 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 LEFT JOIN users p ON s.parent_id = p.id 
                 WHERE s.id = ? 
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$studentId]);
    }
    
    /**
     * الحصول على طالب بواسطة معرّف المستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @return array|false بيانات الطالب أو false إذا لم يتم العثور عليه
     */
    public function getStudentByUserId($userId)
    {
        return $this->whereFirst('user_id', $userId);
    }
    
    /**
     * الحصول على طالب بواسطة الرقم الطلابي
     * 
     * @param string $studentId الرقم الطلابي
     * @return array|false بيانات الطالب أو false إذا لم يتم العثور عليه
     */
    public function getStudentByStudentId($studentId)
    {
        return $this->whereFirst('student_id', $studentId);
    }
    
    /**
     * الحصول على الطلاب حسب المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @param int|null $classId معرّف الصف (اختياري)
     * @return array قائمة الطلاب
     */
    public function getStudentsBySchool($schoolId, $classId = null)
    {
        $query = "SELECT s.*, u.first_name, u.last_name, u.email, u.active,
                 c.name as class_name, c.grade_level
                 FROM {$this->table} s 
                 JOIN users u ON s.user_id = u.id 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'student')";
        
        $params = [$schoolId];
        
        if ($classId !== null) {
            $query .= " AND s.class_id = ?";
            $params[] = $classId;
        }
        
        $query .= " ORDER BY c.grade_level, c.name, u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * البحث عن طلاب
     * 
     * @param int $schoolId معرّف المدرسة
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchStudents($schoolId, $searchTerm)
    {
        $query = "SELECT s.*, u.first_name, u.last_name, u.email, u.active,
                 c.name as class_name, c.grade_level
                 FROM {$this->table} s 
                 JOIN users u ON s.user_id = u.id 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'student')
                 AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.student_id LIKE ?) 
                 ORDER BY c.grade_level, c.name, u.first_name, u.last_name 
                 LIMIT 100";
        
        $searchParam = "%{$searchTerm}%";
        
        return $this->db->fetchAll($query, [$schoolId, $searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    /**
     * الحصول على طلاب ولي أمر معين
     * 
     * @param int $parentId معرّف ولي الأمر
     * @return array قائمة الطلاب
     */
    public function getStudentsByParent($parentId)
    {
        $query = "SELECT s.*, u.first_name, u.last_name, u.email, u.active,
                 c.name as class_name, c.grade_level
                 FROM {$this->table} s 
                 JOIN users u ON s.user_id = u.id 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 WHERE s.parent_id = ? 
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$parentId]);
    }
    
    /**
     * ربط طالب بولي أمر
     * 
     * @param int $studentId معرّف الطالب
     * @param int $parentId معرّف ولي الأمر
     * @return bool نجاح العملية أم لا
     */
    public function assignParent($studentId, $parentId)
    {
        return $this->update($studentId, ['parent_id' => $parentId]);
    }
    
    /**
     * نقل طالب إلى صف آخر
     * 
     * @param int $studentId معرّف الطالب
     * @param int $classId معرّف الصف الجديد
     * @return bool نجاح العملية أم لا
     */
    public function moveToClass($studentId, $classId)
    {
        return $this->update($studentId, ['class_id' => $classId]);
    }
    
    /**
     * الحصول على المهام المسندة للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @param string|null $status حالة المهمة (اختياري)
     * @return array قائمة المهام
     */
    public function getStudentAssignments($studentId, $status = null)
    {
        // الحصول على معرّف الصف الخاص بالطالب
        $student = $this->find($studentId);
        
        if (!$student || !$student['class_id']) {
            return [];
        }
        
        $query = "SELECT a.*, s.name as subject_name, 
                 CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                 sub.id as submission_id, sub.status as submission_status, 
                 g.points, g.feedback, g.graded_at
                 FROM assignments a 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN teachers t ON a.teacher_id = t.id 
                 JOIN users u ON t.user_id = u.id 
                 LEFT JOIN submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
                 LEFT JOIN grades g ON sub.id = g.submission_id
                 WHERE a.class_id = ? AND a.is_published = 1";
        
        $params = [$studentId, $student['class_id']];
        
        if ($status === 'pending') {
            $query .= " AND (sub.id IS NULL OR sub.status IN ('draft', 'submitted'))";
            $query .= " AND a.due_date >= CURDATE()";
        } elseif ($status === 'submitted') {
            $query .= " AND sub.status = 'submitted'";
        } elseif ($status === 'graded') {
            $query .= " AND sub.status = 'graded'";
        } elseif ($status === 'late') {
            $query .= " AND ((sub.id IS NULL AND a.due_date < CURDATE()) OR sub.status = 'late')";
        }
        
        $query .= " ORDER BY a.due_date DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على درجات الطالب
     * 
     * @param int $studentId معرّف الطالب
     * @param int|null $subjectId معرّف المادة (اختياري)
     * @return array قائمة الدرجات
     */
    public function getStudentGrades($studentId, $subjectId = null)
    {
        $query = "SELECT g.*, sub.id as submission_id, sub.submitted_at,
                 a.id as assignment_id, a.title as assignment_title, a.points as assignment_points,
                 s.id as subject_id, s.name as subject_name, s.code as subject_code
                 FROM grades g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 WHERE sub.student_id = ?";
        
        $params = [$studentId];
        
        if ($subjectId !== null) {
            $query .= " AND s.id = ?";
            $params[] = $subjectId;
        }
        
        $query .= " ORDER BY g.graded_at DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * حساب متوسط درجات الطالب
     * 
     * @param int $studentId معرّف الطالب
     * @param int|null $subjectId معرّف المادة (اختياري)
     * @return array متوسطات الدرجات
     */
    public function calculateStudentAverages($studentId, $subjectId = null)
    {
        $averages = [];
        
        if ($subjectId === null) {
            // حساب متوسط درجات الطالب لكل المواد
            $query = "SELECT s.id as subject_id, s.name as subject_name, s.code as subject_code,
                     COUNT(g.id) as grades_count,
                     SUM(g.points) as total_points,
                     SUM(a.points) as total_possible_points,
                     (SUM(g.points) / SUM(a.points) * 100) as percentage
                     FROM grades g 
                     JOIN submissions sub ON g.submission_id = sub.id 
                     JOIN assignments a ON sub.assignment_id = a.id 
                     JOIN subjects s ON a.subject_id = s.id 
                     WHERE sub.student_id = ? 
                     GROUP BY s.id 
                     ORDER BY s.name";
            
            $subjects = $this->db->fetchAll($query, [$studentId]);
            
            if (!empty($subjects)) {
                $averages['subjects'] = $subjects;
                
                // حساب المتوسط العام
                $totalPoints = 0;
                $totalPossiblePoints = 0;
                
                foreach ($subjects as $subject) {
                    $totalPoints += $subject['total_points'];
                    $totalPossiblePoints += $subject['total_possible_points'];
                }
                
                $averages['overall'] = [
                    'total_points' => $totalPoints,
                    'total_possible_points' => $totalPossiblePoints,
                    'percentage' => $totalPossiblePoints > 0 ? ($totalPoints / $totalPossiblePoints * 100) : 0
                ];
            }
        } else {
            // حساب متوسط درجات الطالب لمادة محددة
            $query = "SELECT s.id as subject_id, s.name as subject_name, s.code as subject_code,
                     COUNT(g.id) as grades_count,
                     SUM(g.points) as total_points,
                     SUM(a.points) as total_possible_points,
                     (SUM(g.points) / SUM(a.points) * 100) as percentage
                     FROM grades g 
                     JOIN submissions sub ON g.submission_id = sub.id 
                     JOIN assignments a ON sub.assignment_id = a.id 
                     JOIN subjects s ON a.subject_id = s.id 
                     WHERE sub.student_id = ? AND s.id = ? 
                     GROUP BY s.id";
            
            $subject = $this->db->fetchOne($query, [$studentId, $subjectId]);
            
            if ($subject) {
                $averages['subject'] = $subject;
            }
        }
        
        return $averages;
    }
    
    /**
     * الحصول على إحصائيات الطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return array الإحصائيات
     */
    public function getStudentStats($studentId)
    {
        $stats = [];
        
        // عدد المهام المسندة
        $query = "SELECT COUNT(*) as count 
                 FROM assignments a 
                 JOIN students s ON a.class_id = s.class_id 
                 WHERE s.id = ? AND a.is_published = 1";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['total_assignments'] = $result['count'] ?? 0;
        
        // عدد المهام المقدمة
        $query = "SELECT COUNT(*) as count 
                 FROM submissions 
                 WHERE student_id = ? AND status IN ('submitted', 'graded')";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['submitted_assignments'] = $result['count'] ?? 0;
        
        // عدد المهام المتأخرة
        $query = "SELECT COUNT(*) as count 
                 FROM submissions 
                 WHERE student_id = ? AND status = 'late'";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['late_assignments'] = $result['count'] ?? 0;
        
        // عدد المهام المصححة
        $query = "SELECT COUNT(*) as count 
                 FROM submissions 
                 WHERE student_id = ? AND status = 'graded'";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['graded_assignments'] = $result['count'] ?? 0;
        
        // المعدل العام
        $averages = $this->calculateStudentAverages($studentId);
        if (isset($averages['overall'])) {
            $stats['overall_average'] = round($averages['overall']['percentage'], 1);
        } else {
            $stats['overall_average'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * الحصول على تقدم الطالب في المواد
     * 
     * @param int $studentId معرّف الطالب
     * @return array معلومات التقدم
     */
    public function getStudentProgress($studentId)
    {
        // الحصول على المواد التي يدرسها الطالب
        $student = $this->getStudentWithDetails($studentId);
        
        if (!$student || !$student['class_id']) {
            return [];
        }
        
        $query = "SELECT s.id as subject_id, s.name as subject_name, s.code as subject_code,
                 COUNT(a.id) as total_assignments,
                 COUNT(sub.id) as submitted_assignments,
                 COUNT(CASE WHEN sub.status = 'graded' THEN sub.id END) as graded_assignments,
                 COUNT(CASE WHEN sub.status = 'late' THEN sub.id END) as late_assignments,
                 AVG(CASE WHEN g.id IS NOT NULL THEN (g.points / a.points * 100) END) as average_grade
                 FROM subjects s 
                 JOIN teacher_assignments ta ON s.id = ta.subject_id 
                 JOIN assignments a ON s.id = a.subject_id AND a.class_id = ? AND a.is_published = 1
                 LEFT JOIN submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
                 LEFT JOIN grades g ON sub.id = g.submission_id 
                 WHERE ta.class_id = ? 
                 GROUP BY s.id 
                 ORDER BY s.name";
        
        return $this->db->fetchAll($query, [$student['class_id'], $studentId, $student['class_id']]);
    }
    
    /**
     * إنشاء رقم طالب جديد
     * 
     * @param int $schoolId معرّف المدرسة
     * @param int $classId معرّف الصف
     * @return string الرقم الطلابي الجديد
     */
    public function generateStudentId($schoolId, $classId)
    {
        // الحصول على معلومات الصف والعام الدراسي
        $classInfo = $this->db->fetchOne("SELECT * FROM classes WHERE id = ?", [$classId]);
        $academicYear = $classInfo['academic_year'] ?? date('Y');
        
        // الحصول على آخر رقم تسلسلي للطلاب في المدرسة
        $query = "SELECT MAX(CAST(SUBSTRING(student_id, -4) AS UNSIGNED)) as last_number 
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE u.school_id = ? AND student_id LIKE ?";
        
        $yearPrefix = substr($academicYear, 2, 2); // آخر رقمين من العام الدراسي
        $schoolPrefix = str_pad($schoolId, 3, '0', STR_PAD_LEFT); // رمز المدرسة (3 أرقام)
        
        $idPattern = $yearPrefix . $schoolPrefix . '%';
        
        $result = $this->db->fetchOne($query, [$schoolId, $idPattern]);
        $lastNumber = $result['last_number'] ?? 0;
        
        // إنشاء الرقم الجديد
        $nextNumber = $lastNumber + 1;
        $newStudentId = $yearPrefix . $schoolPrefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        return $newStudentId;
    }
}