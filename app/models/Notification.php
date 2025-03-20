<?php
/**
 * app/models/Notification.php
 * نموذج الإشعار
 * يتعامل مع بيانات الإشعارات في النظام
 */
class Notification extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'notifications';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'user_id', 'title', 'content', 'type', 'reference_id', 
        'reference_type', 'is_read', 'read_at'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء إشعار جديد
     * 
     * @param array $data بيانات الإشعار
     * @return int|false معرّف الإشعار الجديد أو false في حالة الفشل
     */
    public function createNotification($data)
    {
        // التأكد من تعيين حالة القراءة
        if (!isset($data['is_read'])) {
            $data['is_read'] = 0;
        }
        
        return $this->create($data);
    }
    
    /**
     * تعيين الإشعار كمقروء
     * 
     * @param int $notificationId معرّف الإشعار
     * @return bool نجاح العملية أم لا
     */
    public function markAsRead($notificationId)
    {
        return $this->update($notificationId, [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * تعيين جميع إشعارات المستخدم كمقروءة
     * 
     * @param int $userId معرّف المستخدم
     * @return bool نجاح العملية أم لا
     */
    public function markAllAsRead($userId)
    {
        $query = "UPDATE {$this->table} SET is_read = 1, read_at = ? WHERE user_id = ? AND is_read = 0";
        $this->db->query($query, [date('Y-m-d H:i:s'), $userId]);
        
        return true;
    }
    
    /**
     * الحصول على إشعارات مستخدم معين
     * 
     * @param int $userId معرّف المستخدم
     * @param bool $unreadOnly جلب الإشعارات غير المقروءة فقط
     * @param int $limit عدد الإشعارات للجلب
     * @param int $offset موضع البداية
     * @return array قائمة الإشعارات
     */
    public function getUserNotifications($userId, $unreadOnly = false, $limit = 20, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على عدد الإشعارات غير المقروءة لمستخدم معين
     * 
     * @param int $userId معرّف المستخدم
     * @return int عدد الإشعارات غير المقروءة
     */
    public function countUnreadNotifications($userId)
    {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ? AND is_read = 0";
        $result = $this->db->fetchOne($query, [$userId]);
        
        return $result['count'] ?? 0;
    }
    
    /**
     * إرسال إشعار إلى مستخدم واحد
     * 
     * @param int $userId معرّف المستخدم
     * @param string $title عنوان الإشعار
     * @param string $content محتوى الإشعار
     * @param string $type نوع الإشعار (اختياري)
     * @param int $referenceId معرّف المرجع (اختياري)
     * @param string $referenceType نوع المرجع (اختياري)
     * @return int|false معرّف الإشعار الجديد أو false في حالة الفشل
     */
    public function sendToUser($userId, $title, $content, $type = null, $referenceId = null, $referenceType = null)
    {
        $data = [
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'is_read' => 0
        ];
        
        return $this->createNotification($data);
    }
    
    /**
     * إرسال إشعار إلى مجموعة من المستخدمين
     * 
     * @param array $userIds مصفوفة معرّفات المستخدمين
     * @param string $title عنوان الإشعار
     * @param string $content محتوى الإشعار
     * @param string $type نوع الإشعار (اختياري)
     * @param int $referenceId معرّف المرجع (اختياري)
     * @param string $referenceType نوع المرجع (اختياري)
     * @return array نتائج العملية [نجاح => عدد العمليات الناجحة، فشل => عدد العمليات الفاشلة]
     */
    public function sendToUsers($userIds, $title, $content, $type = null, $referenceId = null, $referenceType = null)
    {
        $results = [
            'success' => 0,
            'fail' => 0
        ];
        
        foreach ($userIds as $userId) {
            $success = $this->sendToUser($userId, $title, $content, $type, $referenceId, $referenceType);
            
            if ($success) {
                $results['success']++;
            } else {
                $results['fail']++;
            }
        }
        
        return $results;
    }
    
    /**
     * إرسال إشعار إلى جميع طلاب صف معين
     * 
     * @param int $classId معرّف الصف
     * @param string $title عنوان الإشعار
     * @param string $content محتوى الإشعار
     * @param string $type نوع الإشعار (اختياري)
     * @param int $referenceId معرّف المرجع (اختياري)
     * @param string $referenceType نوع المرجع (اختياري)
     * @return array نتائج العملية
     */
    public function sendToClass($classId, $title, $content, $type = null, $referenceId = null, $referenceType = null)
    {
        // الحصول على معرّفات المستخدمين للطلاب في الصف
        $query = "SELECT u.id 
                 FROM users u 
                 JOIN students s ON u.id = s.user_id 
                 WHERE s.class_id = ?";
        
        $students = $this->db->fetchAll($query, [$classId]);
        
        // استخراج معرّفات المستخدمين
        $userIds = array_column($students, 'id');
        
        // إرسال الإشعارات
        return $this->sendToUsers($userIds, $title, $content, $type, $referenceId, $referenceType);
    }
    
    /**
     * إرسال إشعار إلى جميع أولياء أمور طلاب صف معين
     * 
     * @param int $classId معرّف الصف
     * @param string $title عنوان الإشعار
     * @param string $content محتوى الإشعار
     * @param string $type نوع الإشعار (اختياري)
     * @param int $referenceId معرّف المرجع (اختياري)
     * @param string $referenceType نوع المرجع (اختياري)
     * @return array نتائج العملية
     */
    public function sendToClassParents($classId, $title, $content, $type = null, $referenceId = null, $referenceType = null)
    {
        // الحصول على معرّفات أولياء الأمور للطلاب في الصف
        $query = "SELECT DISTINCT s.parent_id 
                 FROM students s 
                 WHERE s.class_id = ? AND s.parent_id IS NOT NULL";
        
        $parents = $this->db->fetchAll($query, [$classId]);
        
        // استخراج معرّفات أولياء الأمور
        $userIds = array_column($parents, 'parent_id');
        
        // إرسال الإشعارات
        return $this->sendToUsers($userIds, $title, $content, $type, $referenceId, $referenceType);
    }
    
    /**
     * إرسال إشعار إلى معلمي صف معين
     * 
     * @param int $classId معرّف الصف
     * @param string $title عنوان الإشعار
     * @param string $content محتوى الإشعار
     * @param string $type نوع الإشعار (اختياري)
     * @param int $referenceId معرّف المرجع (اختياري)
     * @param string $referenceType نوع المرجع (اختياري)
     * @return array نتائج العملية
     */
    public function sendToClassTeachers($classId, $title, $content, $type = null, $referenceId = null, $referenceType = null)
    {
        // الحصول على معرّفات المستخدمين للمعلمين في الصف
        $query = "SELECT DISTINCT u.id 
                 FROM users u 
                 JOIN teachers t ON u.id = t.user_id 
                 JOIN teacher_assignments ta ON t.id = ta.teacher_id 
                 WHERE ta.class_id = ?";
        
        $teachers = $this->db->fetchAll($query, [$classId]);
        
        // استخراج معرّفات المستخدمين
        $userIds = array_column($teachers, 'id');
        
        // إرسال الإشعارات
        return $this->sendToUsers($userIds, $title, $content, $type, $referenceId, $referenceType);
    }
    
    /**
     * إرسال إشعار إلى جميع مستخدمي مدرسة معينة
     * 
     * @param int $schoolId معرّف المدرسة
     * @param string $title عنوان الإشعار
     * @param string $content محتوى الإشعار
     * @param string $type نوع الإشعار (اختياري)
     * @param int $referenceId معرّف المرجع (اختياري)
     * @param string $referenceType نوع المرجع (اختياري)
     * @param array|null $roleIds معرّفات الأدوار المستهدفة (اختياري)
     * @return array نتائج العملية
     */
    public function sendToSchool($schoolId, $title, $content, $type = null, $referenceId = null, $referenceType = null, $roleIds = null)
    {
        // بناء الاستعلام
        $query = "SELECT id FROM users WHERE school_id = ?";
        $params = [$schoolId];
        
        // إضافة تصفية الأدوار إذا تم تحديدها
        if ($roleIds !== null && is_array($roleIds) && !empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $query .= " AND role_id IN ({$placeholders})";
            $params = array_merge($params, $roleIds);
        }
        
        // جلب المستخدمين
        $users = $this->db->fetchAll($query, $params);
        
        // استخراج معرّفات المستخدمين
        $userIds = array_column($users, 'id');
        
        // إرسال الإشعارات
        return $this->sendToUsers($userIds, $title, $content, $type, $referenceId, $referenceType);
    }
    
    /**
     * إنشاء إشعار عند إضافة مهمة جديدة
     * 
     * @param int $assignmentId معرّف المهمة
     * @return array نتائج العملية
     */
    public function createAssignmentNotification($assignmentId)
    {
        // الحصول على معلومات المهمة
        $query = "SELECT a.id, a.title, a.class_id, s.name as subject_name, c.name as class_name
                 FROM assignments a 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN classes c ON a.class_id = c.id 
                 WHERE a.id = ? 
                 LIMIT 1";
        
        $assignment = $this->db->fetchOne($query, [$assignmentId]);
        
        if (!$assignment) {
            return ['success' => 0, 'fail' => 0];
        }
        
        // إنشاء محتوى الإشعار
        $title = 'مهمة جديدة';
        $content = "تم إضافة مهمة جديدة في مادة {$assignment['subject_name']}: {$assignment['title']}";
        
        // إرسال إشعار إلى طلاب الصف
        $results = $this->sendToClass(
            $assignment['class_id'],
            $title,
            $content,
            'assignment',
            $assignmentId,
            'assignment'
        );
        
        return $results;
    }
    
    /**
     * إنشاء إشعار عند تصحيح إجابة
     * 
     * @param int $submissionId معرّف الإجابة
     * @param float $points الدرجة
     * @return int|false معرّف الإشعار الجديد أو false في حالة الفشل
     */
    public function createGradeNotification($submissionId, $points)
    {
        // الحصول على معلومات الإجابة والمهمة والطالب
        $query = "SELECT sub.id, sub.student_id, a.title as assignment_title, s.name as subject_name,
                 st.user_id as student_user_id
                 FROM submissions sub 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN students st ON sub.student_id = st.id 
                 WHERE sub.id = ? 
                 LIMIT 1";
        
        $submission = $this->db->fetchOne($query, [$submissionId]);
        
        if (!$submission) {
            return false;
        }
        
        // إنشاء محتوى الإشعار
        $title = 'تم تصحيح إجابتك';
        $content = "تم تصحيح إجابتك على مهمة {$submission['assignment_title']} في مادة {$submission['subject_name']} وحصلت على {$points} نقطة.";
        
        // إرسال إشعار إلى الطالب
        return $this->sendToUser(
            $submission['student_user_id'],
            $title,
            $content,
            'grade',
            $submissionId,
            'submission'
        );
    }
    
    /**
     * حذف الإشعارات القديمة
     * 
     * @param int $days عدد الأيام للاحتفاظ بالإشعارات
     * @return int عدد الإشعارات المحذوفة
     */
    public function deleteOldNotifications($days = 60)
    {
        $query = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $this->db->query($query, [$days]);
        
        return $this->db->rowCount();
    }
}