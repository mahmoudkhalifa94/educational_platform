<?php
/**
 * نموذج المستخدم
 * يتعامل مع بيانات المستخدمين في النظام
 */
class User extends Model
{
    // اسم الجدول في قاعدة البيانات
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
     * إنشاء مستخدم جديد
     * 
     * @param array $data بيانات المستخدم
     * @return int|false معرّف المستخدم الجديد أو false في حالة الفشل
     */
    public function createUser($data)
    {
        // التأكد من تشفير كلمة المرور
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات المستخدم
     * 
     * @param int $id معرّف المستخدم
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateUser($id, $data)
    {
        // إذا تم تضمين كلمة مرور جديدة، قم بتشفيرها
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } elseif (isset($data['password']) && empty($data['password'])) {
            // إذا كانت كلمة المرور فارغة، فلا تقم بتحديثها
            unset($data['password']);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * البحث عن مستخدم بالبريد الإلكتروني
     * 
     * @param string $email البريد الإلكتروني
     * @return array|false بيانات المستخدم أو false إذا لم يتم العثور عليه
     */
    public function findByEmail($email)
    {
        return $this->whereFirst('email', $email);
    }
    
    /**
     * البحث عن مستخدم باسم المستخدم
     * 
     * @param string $username اسم المستخدم
     * @return array|false بيانات المستخدم أو false إذا لم يتم العثور عليه
     */
    public function findByUsername($username)
    {
        return $this->whereFirst('username', $username);
    }
    
    /**
     * الحصول على دور المستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @return array|false بيانات الدور أو false إذا لم يتم العثور عليه
     */
    public function getRole($userId)
    {
        $query = "SELECT r.* FROM roles r
                 JOIN users u ON r.id = u.role_id
                 WHERE u.id = ?
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$userId]);
    }
    
    /**
     * الحصول على بيانات المستخدم مع الدور
     * 
     * @param int $userId معرّف المستخدم
     * @return array|false بيانات المستخدم والدور أو false إذا لم يتم العثور عليه
     */
    public function getUserWithRole($userId)
    {
        $query = "SELECT u.*, r.name as role_name, r.permissions 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.id = ? 
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$userId]);
    }
    
    /**
     * الحصول على مستخدمي مدرسة معينة
     * 
     * @param int $schoolId معرّف المدرسة
     * @param int|array $roleId معرّف الدور أو مصفوفة أدوار (اختياري)
     * @return array قائمة المستخدمين
     */
    public function getUsersBySchool($schoolId, $roleId = null)
    {
        $query = "SELECT u.*, r.name as role_name 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.school_id = ?";
        
        $params = [$schoolId];
        
        if ($roleId !== null) {
            if (is_array($roleId)) {
                $placeholders = implode(',', array_fill(0, count($roleId), '?'));
                $query .= " AND u.role_id IN ($placeholders)";
                $params = array_merge($params, $roleId);
            } else {
                $query .= " AND u.role_id = ?";
                $params[] = $roleId;
            }
        }
        
        $query .= " ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على قائمة المعلمين في مدرسة معينة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array قائمة المعلمين
     */
    public function getTeachersBySchool($schoolId)
    {
        $query = "SELECT u.*, t.specialization, t.id as teacher_id 
                 FROM users u 
                 JOIN teachers t ON u.id = t.user_id 
                 WHERE u.school_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'teacher') 
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * الحصول على قائمة الطلاب في صف معين
     * 
     * @param int $classId معرّف الصف
     * @return array قائمة الطلاب
     */
    public function getStudentsByClass($classId)
    {
        $query = "SELECT u.*, s.student_id, s.id as student_record_id 
                 FROM users u 
                 JOIN students s ON u.id = s.user_id 
                 WHERE s.class_id = ? 
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$classId]);
    }
    
    /**
     * البحث عن مستخدمين
     * 
     * @param string $searchTerm مصطلح البحث
     * @param int $schoolId معرّف المدرسة (اختياري)
     * @param int $roleId معرّف الدور (اختياري)
     * @return array نتائج البحث
     */
    public function searchUsers($searchTerm, $schoolId = null, $roleId = null)
    {
        $query = "SELECT u.*, r.name as role_name 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
        
        $searchParam = "%{$searchTerm}%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        
        if ($schoolId !== null) {
            $query .= " AND u.school_id = ?";
            $params[] = $schoolId;
        }
        
        if ($roleId !== null) {
            $query .= " AND u.role_id = ?";
            $params[] = $roleId;
        }
        
        $query .= " ORDER BY u.first_name, u.last_name LIMIT 100";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * تحديث حالة المستخدم (تفعيل/تعطيل)
     * 
     * @param int $userId معرّف المستخدم
     * @param bool $active الحالة الجديدة
     * @return bool نجاح العملية أم لا
     */
    public function updateStatus($userId, $active)
    {
        return $this->update($userId, ['active' => $active ? 1 : 0]);
    }
    
    /**
     * تحديث صورة الملف الشخصي
     * 
     * @param int $userId معرّف المستخدم
     * @param string $imagePath مسار الصورة
     * @return bool نجاح العملية أم لا
     */
    public function updateProfilePicture($userId, $imagePath)
    {
        return $this->update($userId, ['profile_picture' => $imagePath]);
    }
    
    /**
     * الحصول على أولياء الأمور للطالب
     * 
     * @param int $studentId معرّف الطالب
     * @return array قائمة أولياء الأمور
     */
    public function getParentsForStudent($studentId)
    {
        $query = "SELECT u.* 
                 FROM users u 
                 JOIN students s ON u.id = s.parent_id 
                 WHERE s.id = ?";
        
        return $this->db->fetchAll($query, [$studentId]);
    }
    
    /**
     * الحصول على الطلاب لولي الأمر
     * 
     * @param int $parentId معرّف ولي الأمر
     * @return array قائمة الطلاب
     */
    public function getStudentsForParent($parentId)
    {
        $query = "SELECT u.*, s.student_id, s.id as student_record_id, c.name as class_name 
                 FROM users u 
                 JOIN students s ON u.id = s.user_id 
                 JOIN classes c ON s.class_id = c.id 
                 WHERE s.parent_id = ? 
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$parentId]);
    }
    
    /**
     * تسجيل نشاط المستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @param string $action النشاط
     * @param string $entityType نوع الكيان (اختياري)
     * @param int $entityId معرّف الكيان (اختياري)
     * @param array $details تفاصيل إضافية (اختياري)
     * @return int|false معرّف السجل الجديد أو false في حالة الفشل
     */
    public function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null)
    {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        return $this->db->insert('system_logs', $data);
    }
}