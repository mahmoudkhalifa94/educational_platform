<?php
/**
 * app/models/Subject.php
 * نموذج المادة الدراسية
 * يتعامل مع بيانات المواد الدراسية في النظام
 */
class Subject extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'subjects';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'school_id', 'name', 'code', 'description'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء مادة دراسية جديدة
     * 
     * @param array $data بيانات المادة
     * @return int|false معرّف المادة الجديدة أو false في حالة الفشل
     */
    public function createSubject($data)
    {
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات المادة
     * 
     * @param int $id معرّف المادة
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateSubject($id, $data)
    {
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على المواد الدراسية حسب المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array قائمة المواد
     */
    public function getSubjectsBySchool($schoolId)
    {
        $query = "SELECT * FROM {$this->table} WHERE school_id = ? ORDER BY name";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * البحث عن مواد دراسية
     * 
     * @param int $schoolId معرّف المدرسة
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchSubjects($schoolId, $searchTerm)
    {
        $query = "SELECT * FROM {$this->table} 
                 WHERE school_id = ? AND (name LIKE ? OR code LIKE ? OR description LIKE ?) 
                 ORDER BY name 
                 LIMIT 100";
        
        $searchParam = "%{$searchTerm}%";
        
        return $this->db->fetchAll($query, [$schoolId, $searchParam, $searchParam, $searchParam]);
    }
    
    /**
     * الحصول على المواد التي يدرّسها معلم معين
     * 
     * @param int $teacherId معرّف المعلم
     * @return array قائمة المواد
     */
    public function getSubjectsByTeacher($teacherId)
    {
        $query = "SELECT DISTINCT s.* 
                 FROM {$this->table} s 
                 JOIN teacher_assignments ta ON s.id = ta.subject_id 
                 WHERE ta.teacher_id = ? 
                 ORDER BY s.name";
        
        return $this->db->fetchAll($query, [$teacherId]);
    }
    
    /**
     * الحصول على المواد مع الصفوف التي تُدرّس فيها
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array قائمة المواد مع الصفوف
     */
    public function getSubjectsWithClasses($schoolId)
    {
        $query = "SELECT s.*, 
                 (SELECT COUNT(DISTINCT ta.class_id) FROM teacher_assignments ta WHERE ta.subject_id = s.id) as classes_count,
                 (SELECT COUNT(DISTINCT ta.teacher_id) FROM teacher_assignments ta WHERE ta.subject_id = s.id) as teachers_count,
                 (SELECT COUNT(*) FROM assignments a WHERE a.subject_id = s.id) as assignments_count
                 FROM {$this->table} s 
                 WHERE s.school_id = ? 
                 ORDER BY s.name";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * الحصول على الصفوف التي تُدرّس فيها مادة معينة
     * 
     * @param int $subjectId معرّف المادة
     * @return array قائمة الصفوف
     */
    public function getClassesBySubject($subjectId)
    {
        $query = "SELECT c.* 
                 FROM classes c 
                 JOIN teacher_assignments ta ON c.id = ta.class_id 
                 WHERE ta.subject_id = ? AND c.active = 1
                 GROUP BY c.id
                 ORDER BY c.grade_level, c.name";
        
        return $this->db->fetchAll($query, [$subjectId]);
    }
    
    /**
     * الحصول على المعلمين الذين يدرّسون مادة معينة
     * 
     * @param int $subjectId معرّف المادة
     * @return array قائمة المعلمين
     */
    public function getTeachersBySubject($subjectId)
    {
        $query = "SELECT t.id as teacher_id, u.id as user_id, u.first_name, u.last_name, u.email,
                 GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as classes,
                 COUNT(DISTINCT ta.class_id) as classes_count
                 FROM teachers t 
                 JOIN users u ON t.user_id = u.id 
                 JOIN teacher_assignments ta ON t.id = ta.teacher_id 
                 JOIN classes c ON ta.class_id = c.id
                 WHERE ta.subject_id = ? 
                 GROUP BY t.id 
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$subjectId]);
    }
    
    /**
     * الحصول على المهام المرتبطة بمادة معينة
     * 
     * @param int $subjectId معرّف المادة
     * @param int|null $classId معرّف الصف (اختياري)
     * @return array قائمة المهام
     */
    public function getAssignmentsBySubject($subjectId, $classId = null)
    {
        $query = "SELECT a.*, c.name as class_name, u.first_name, u.last_name,
                 (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submissions_count
                 FROM assignments a 
                 JOIN classes c ON a.class_id = c.id
                 JOIN teachers t ON a.teacher_id = t.id
                 JOIN users u ON t.user_id = u.id
                 WHERE a.subject_id = ?";
        
        $params = [$subjectId];
        
        if ($classId !== null) {
            $query .= " AND a.class_id = ?";
            $params[] = $classId;
        }
        
        $query .= " ORDER BY a.due_date DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * إنشاء نسخة من المادة لمدرسة أخرى
     * 
     * @param int $subjectId معرّف المادة المصدر
     * @param int $targetSchoolId معرّف المدرسة الهدف
     * @return int|false معرّف المادة الجديدة أو false في حالة الفشل
     */
    public function copySubjectToSchool($subjectId, $targetSchoolId)
    {
        // الحصول على بيانات المادة المصدر
        $sourceSubject = $this->find($subjectId);
        
        if (!$sourceSubject) {
            return false;
        }
        
        // إنشاء بيانات المادة الجديدة
        $newSubjectData = [
            'school_id' => $targetSchoolId,
            'name' => $sourceSubject['name'],
            'code' => $sourceSubject['code'],
            'description' => $sourceSubject['description']
        ];
        
        // التحقق من عدم وجود تكرار في الرمز
        $existingWithCode = $this->whereFirst('school_id', $targetSchoolId, 'code', $sourceSubject['code']);
        
        if ($existingWithCode) {
            // إضافة رقم للرمز للتمييز
            $newSubjectData['code'] = $sourceSubject['code'] . '_' . date('Ymd');
        }
        
        // إنشاء المادة الجديدة
        return $this->createSubject($newSubjectData);
    }
}