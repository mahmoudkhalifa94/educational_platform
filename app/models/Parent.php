<?php
/**
 * app/models/Parent.php
 * نموذج ولي الأمر
 * يتعامل مع بيانات أولياء الأمور في النظام
 */
class ParentModel extends Model
{
    // اسم الجدول - نستخدم users لأن أولياء الأمور هم مستخدمون بدور معين
    protected $table = 'users';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'school_id', 'role_id', 'username', 'email', 'password',
        'first_name', 'last_name', 'phone', 'profile_picture', 'active'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء ولي أمر جديد
     * 
     * @param array $data بيانات ولي الأمر
     * @return int|false معرّف ولي الأمر الجديد أو false في حالة الفشل
     */
    public function createParent($data)
    {
        // التأكد من تعيين دور ولي أمر
        if (!isset($data['role_id'])) {
            // الحصول على معرّف دور ولي أمر
            $query = "SELECT id FROM roles WHERE name = 'parent' LIMIT 1";
            $parentRole = $this->db->fetchOne($query);
            
            if ($parentRole) {
                $data['role_id'] = $parentRole['id'];
            } else {
                // إذا لم يتم العثور على دور ولي أمر، فلا يمكن إكمال العملية
                return false;
            }
        }
        
        // تشفير كلمة المرور إذا تم توفيرها
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // إنشاء المستخدم (ولي الأمر)
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات ولي الأمر
     * 
     * @param int $id معرّف ولي الأمر
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateParent($id, $data)
    {
        // تشفير كلمة المرور إذا تم توفيرها
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } elseif (isset($data['password']) && empty($data['password'])) {
            // إذا كانت كلمة المرور فارغة، فلا تقم بتحديثها
            unset($data['password']);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على أولياء الأمور حسب المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array قائمة أولياء الأمور
     */
    public function getParentsBySchool($schoolId)
    {
        $query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM students s WHERE s.parent_id = u.id) as children_count
                 FROM {$this->table} u 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'parent')
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * البحث عن أولياء أمور
     * 
     * @param int $schoolId معرّف المدرسة
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchParents($schoolId, $searchTerm)
    {
        $query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM students s WHERE s.parent_id = u.id) as children_count
                 FROM {$this->table} u 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'parent')
                 AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?) 
                 ORDER BY u.first_name, u.last_name 
                 LIMIT 100";
        
        $searchParam = "%{$searchTerm}%";
        
        return $this->db->fetchAll($query, [$schoolId, $searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    /**
     * الحصول على أولياء الأمور الذين ليس لديهم أبناء في المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array قائمة أولياء الأمور
     */
    public function getParentsWithoutChildren($schoolId)
    {
        $query = "SELECT u.* 
                 FROM {$this->table} u 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'parent')
                 AND NOT EXISTS (SELECT 1 FROM students s WHERE s.parent_id = u.id)
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * الحصول على ولي أمر مع تفاصيل أبنائه
     * 
     * @param int $parentId معرّف ولي الأمر
     * @return array|false بيانات ولي الأمر وأبنائه أو false إذا لم يتم العثور عليه
     */
    public function getParentWithChildren($parentId)
    {
        // الحصول على بيانات ولي الأمر
        $query = "SELECT u.* 
                 FROM {$this->table} u 
                 WHERE u.id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'parent') 
                 LIMIT 1";
        
        $parent = $this->db->fetchOne($query, [$parentId]);
        
        if (!$parent) {
            return false;
        }
        
        // الحصول على أبناء ولي الأمر
        $query = "SELECT s.id as student_record_id, s.student_id, s.date_of_birth, 
                 u.id as user_id, u.first_name, u.last_name, u.email, u.active,
                 c.id as class_id, c.name as class_name, c.grade_level
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 WHERE s.parent_id = ? 
                 ORDER BY u.first_name, u.last_name";
        
        $children = $this->db->fetchAll($query, [$parentId]);
        
        // إضافة الأبناء إلى بيانات ولي الأمر
        $parent['children'] = $children;
        
        return $parent;
    }
    
    /**
     * ربط طالب بولي أمر
     * 
     * @param int $parentId معرّف ولي الأمر
     * @param int $studentId معرّف الطالب
     * @return bool نجاح العملية أم لا
     */
    public function assignChildToParent($parentId, $studentId)
    {
        $query = "UPDATE students SET parent_id = ? WHERE id = ?";
        $this->db->query($query, [$parentId, $studentId]);
        
        return $this->db->rowCount() > 0;
    }
    
    /**
     * إلغاء ربط طالب بولي أمر
     * 
     * @param int $parentId معرّف ولي الأمر
     * @param int $studentId معرّف الطالب
     * @return bool نجاح العملية أم لا
     */
    public function unassignChildFromParent($parentId, $studentId)
    {
        $query = "UPDATE students SET parent_id = NULL WHERE id = ? AND parent_id = ?";
        $this->db->query($query, [$studentId, $parentId]);
        
        return $this->db->rowCount() > 0;
    }
    
    /**
     * الحصول على إشعارات ولي الأمر
     * 
     * @param int $parentId معرّف ولي الأمر
     * @param bool $unreadOnly جلب الإشعارات غير المقروءة فقط
     * @param int $limit عدد الإشعارات
     * @return array قائمة الإشعارات
     */
    public function getParentNotifications($parentId, $unreadOnly = false, $limit = 20)
    {
        $query = "SELECT n.* 
                 FROM notifications n 
                 WHERE n.user_id = ?";
        
        $params = [$parentId];
        
        if ($unreadOnly) {
            $query .= " AND n.is_read = 0";
        }
        
        $query .= " ORDER BY n.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على معلومات التقدم الدراسي لأبناء ولي الأمر
     * 
     * @param int $parentId معرّف ولي الأمر
     * @return array معلومات التقدم لكل ابن
     */
    public function getChildrenProgress($parentId)
    {
        // الحصول على قائمة الأبناء
        $query = "SELECT s.id as student_id, s.user_id, u.first_name, u.last_name, s.class_id
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE s.parent_id = ?";
        
        $children = $this->db->fetchAll($query, [$parentId]);
        
        $progress = [];
        
        foreach ($children as $child) {
            $childProgress = [];
            $childProgress['student'] = [
                'id' => $child['student_id'],
                'user_id' => $child['user_id'],
                'name' => $child['first_name'] . ' ' . $child['last_name']
            ];
            
            // الحصول على المواد والدرجات
            $query = "SELECT s.id as subject_id, s.name as subject_name, s.code as subject_code,
                     (SELECT COUNT(*) FROM assignments a 
                      WHERE a.subject_id = s.id AND a.class_id = ? AND a.is_published = 1) as total_assignments,
                     (SELECT COUNT(*) FROM assignments a 
                      JOIN submissions sub ON a.id = sub.assignment_id 
                      WHERE a.subject_id = s.id AND a.class_id = ? AND sub.student_id = ? AND sub.status IN ('submitted', 'graded')) as submitted_assignments,
                     (SELECT AVG(g.points / a.points * 100) 
                      FROM grades g 
                      JOIN submissions sub ON g.submission_id = sub.id 
                      JOIN assignments a ON sub.assignment_id = a.id 
                      WHERE a.subject_id = s.id AND sub.student_id = ?) as average_grade
                     FROM subjects s 
                     JOIN teacher_assignments ta ON s.id = ta.subject_id 
                     WHERE ta.class_id = ? 
                     GROUP BY s.id 
                     ORDER BY s.name";
            
            $subjects = $this->db->fetchAll($query, [
                $child['class_id'], $child['class_id'], $child['student_id'], 
                $child['student_id'], $child['class_id']
            ]);
            
            $childProgress['subjects'] = $subjects;
            
            // الحصول على المهام القادمة
            $query = "SELECT a.id, a.title, a.due_date, s.name as subject_name 
                     FROM assignments a 
                     JOIN subjects s ON a.subject_id = s.id 
                     WHERE a.class_id = ? AND a.is_published = 1 AND a.due_date >= CURDATE() 
                     AND NOT EXISTS (
                         SELECT 1 FROM submissions sub 
                         WHERE sub.assignment_id = a.id AND sub.student_id = ? AND sub.status IN ('submitted', 'graded')
                     )
                     ORDER BY a.due_date ASC 
                     LIMIT 5";
            
            $upcomingAssignments = $this->db->fetchAll($query, [$child['class_id'], $child['student_id']]);
            
            $childProgress['upcoming_assignments'] = $upcomingAssignments;
            
            // الحصول على آخر الدرجات
            $query = "SELECT g.points, g.graded_at, a.title as assignment_title, 
                     a.points as assignment_points, s.name as subject_name 
                     FROM grades g 
                     JOIN submissions sub ON g.submission_id = sub.id 
                     JOIN assignments a ON sub.assignment_id = a.id 
                     JOIN subjects s ON a.subject_id = s.id 
                     WHERE sub.student_id = ? 
                     ORDER BY g.graded_at DESC 
                     LIMIT 5";
            
            $recentGrades = $this->db->fetchAll($query, [$child['student_id']]);
            
            $childProgress['recent_grades'] = $recentGrades;
            
            $progress[] = $childProgress;
        }
        
        return $progress;
    }
}