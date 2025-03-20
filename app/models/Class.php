<?php
/**
 * app/models/Class.php
 * نموذج الصف الدراسي
 * يتعامل مع بيانات الصفوف الدراسية في النظام
 */
class ClassModel extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'classes';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'school_id', 'name', 'grade_level', 'academic_year', 
        'section', 'description', 'active'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء صف دراسي جديد
     * 
     * @param array $data بيانات الصف
     * @return int|false معرّف الصف الجديد أو false في حالة الفشل
     */
    public function createClass($data)
    {
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات الصف
     * 
     * @param int $id معرّف الصف
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateClass($id, $data)
    {
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على الصفوف حسب المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @param bool $activeOnly جلب الصفوف النشطة فقط
     * @param string|null $academicYear العام الدراسي (اختياري)
     * @return array قائمة الصفوف
     */
    public function getClassesBySchool($schoolId, $activeOnly = true, $academicYear = null)
    {
        $query = "SELECT * FROM {$this->table} WHERE school_id = ?";
        $params = [$schoolId];
        
        if ($activeOnly) {
            $query .= " AND active = 1";
        }
        
        if ($academicYear) {
            $query .= " AND academic_year = ?";
            $params[] = $academicYear;
        }
        
        $query .= " ORDER BY grade_level, name";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على معلومات صف مع عدد الطلاب
     * 
     * @param int $classId معرّف الصف
     * @return array|false بيانات الصف أو false إذا لم يتم العثور عليه
     */
    public function getClassWithStudentsCount($classId)
    {
        $query = "SELECT c.*, 
                (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as students_count 
                FROM {$this->table} c 
                WHERE c.id = ? 
                LIMIT 1";
        
        return $this->db->fetchOne($query, [$classId]);
    }
    
    /**
     * الحصول على قائمة الصفوف مع عدد الطلاب في كل صف
     * 
     * @param int $schoolId معرّف المدرسة
     * @param bool $activeOnly جلب الصفوف النشطة فقط
     * @return array قائمة الصفوف
     */
    public function getClassesWithStudentsCount($schoolId, $activeOnly = true)
    {
        $query = "SELECT c.*, 
                (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as students_count 
                FROM {$this->table} c 
                WHERE c.school_id = ?";
        
        $params = [$schoolId];
        
        if ($activeOnly) {
            $query .= " AND c.active = 1";
        }
        
        $query .= " ORDER BY c.grade_level, c.name";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * البحث عن صفوف
     * 
     * @param int $schoolId معرّف المدرسة
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchClasses($schoolId, $searchTerm)
    {
        $query = "SELECT c.*, 
                (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as students_count 
                FROM {$this->table} c 
                WHERE c.school_id = ? AND (c.name LIKE ? OR c.grade_level LIKE ? OR c.description LIKE ?) 
                ORDER BY c.grade_level, c.name 
                LIMIT 100";
        
        $searchParam = "%{$searchTerm}%";
        
        return $this->db->fetchAll($query, [$schoolId, $searchParam, $searchParam, $searchParam]);
    }
    
    /**
     * الحصول على الصفوف التي يدرّسها معلم معين
     * 
     * @param int $teacherId معرّف المعلم
     * @return array قائمة الصفوف
     */
    public function getClassesByTeacher($teacherId)
    {
        $query = "SELECT DISTINCT c.* 
                 FROM {$this->table} c 
                 JOIN teacher_assignments ta ON c.id = ta.class_id 
                 WHERE ta.teacher_id = ? AND c.active = 1 
                 ORDER BY c.grade_level, c.name";
        
        return $this->db->fetchAll($query, [$teacherId]);
    }
    
    /**
     * الحصول على المواد التي تُدرَّس في صف معين
     * 
     * @param int $classId معرّف الصف
     * @return array قائمة المواد
     */
    public function getSubjectsByClass($classId)
    {
        $query = "SELECT s.*, 
                 (SELECT COUNT(*) FROM assignments a WHERE a.subject_id = s.id AND a.class_id = ?) as assignments_count,
                 (SELECT u.first_name FROM teachers t 
                  JOIN users u ON t.user_id = u.id 
                  JOIN teacher_assignments ta ON t.id = ta.teacher_id 
                  WHERE ta.subject_id = s.id AND ta.class_id = ? AND ta.is_main_teacher = 1 
                  LIMIT 1) as teacher_first_name,
                 (SELECT u.last_name FROM teachers t 
                  JOIN users u ON t.user_id = u.id 
                  JOIN teacher_assignments ta ON t.id = ta.teacher_id 
                  WHERE ta.subject_id = s.id AND ta.class_id = ? AND ta.is_main_teacher = 1 
                  LIMIT 1) as teacher_last_name
                 FROM subjects s 
                 JOIN teacher_assignments ta ON s.id = ta.subject_id 
                 WHERE ta.class_id = ? 
                 GROUP BY s.id 
                 ORDER BY s.name";
        
        return $this->db->fetchAll($query, [$classId, $classId, $classId, $classId]);
    }
    
    /**
     * الحصول على معلمي صف معين
     * 
     * @param int $classId معرّف الصف
     * @return array قائمة المعلمين
     */
    public function getTeachersByClass($classId)
    {
        $query = "SELECT t.id as teacher_id, u.id as user_id, u.first_name, u.last_name, u.email, 
                 GROUP_CONCAT(s.name SEPARATOR ', ') as subjects 
                 FROM teachers t 
                 JOIN users u ON t.user_id = u.id 
                 JOIN teacher_assignments ta ON t.id = ta.teacher_id 
                 JOIN subjects s ON ta.subject_id = s.id 
                 WHERE ta.class_id = ? 
                 GROUP BY t.id 
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$classId]);
    }
    
    /**
     * تعيين معلم لمادة في صف
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $subjectId معرّف المادة
     * @param int $classId معرّف الصف
     * @param bool $isMainTeacher هل هو المعلم الرئيسي للمادة
     * @return int|false معرّف التعيين الجديد أو false في حالة الفشل
     */
    public function assignTeacher($teacherId, $subjectId, $classId, $isMainTeacher = false)
    {
        // التحقق من عدم وجود تعيين مسبق
        $query = "SELECT id FROM teacher_assignments 
                 WHERE teacher_id = ? AND subject_id = ? AND class_id = ?";
        
        $existing = $this->db->fetchOne($query, [$teacherId, $subjectId, $classId]);
        
        if ($existing) {
            // تحديث التعيين الموجود
            return $this->db->update(
                'teacher_assignments',
                ['is_main_teacher' => $isMainTeacher ? 1 : 0],
                'id = ?',
                [$existing['id']]
            );
        }
        
        // إذا كان معلم رئيسي، نقوم بإلغاء تعيين المعلمين الرئيسيين الآخرين للمادة في الصف
        if ($isMainTeacher) {
            $this->db->update(
                'teacher_assignments',
                ['is_main_teacher' => 0],
                'subject_id = ? AND class_id = ?',
                [$subjectId, $classId]
            );
        }
        
        // إنشاء تعيين جديد
        return $this->db->insert('teacher_assignments', [
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'is_main_teacher' => $isMainTeacher ? 1 : 0
        ]);
    }
    
    /**
     * إلغاء تعيين معلم من مادة في صف
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $subjectId معرّف المادة
     * @param int $classId معرّف الصف
     * @return bool نجاح العملية أم لا
     */
    public function unassignTeacher($teacherId, $subjectId, $classId)
    {
        return $this->db->delete(
            'teacher_assignments',
            'teacher_id = ? AND subject_id = ? AND class_id = ?',
            [$teacherId, $subjectId, $classId]
        );
    }
    
    /**
     * ترقية جميع الطلاب من صف إلى آخر
     * 
     * @param int $fromClassId معرّف الصف الحالي
     * @param int $toClassId معرّف الصف الجديد
     * @return int عدد الطلاب الذين تم ترقيتهم
     */
    public function promoteStudents($fromClassId, $toClassId)
    {
        $query = "UPDATE students SET class_id = ? WHERE class_id = ?";
        $this->db->query($query, [$toClassId, $fromClassId]);
        
        return $this->db->rowCount();
    }
}