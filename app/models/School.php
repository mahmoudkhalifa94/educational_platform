<?php
/**
 * app/models/School.php
 * نموذج المدرسة
 * يتعامل مع بيانات المدارس في النظام
 */
class School extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'schools';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'name', 'subdomain', 'subscription_type', 'subscription_start_date',
        'subscription_end_date', 'logo', 'theme', 'settings', 'max_students', 'active'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء مدرسة جديدة
     * 
     * @param array $data بيانات المدرسة
     * @return int|false معرّف المدرسة الجديدة أو false في حالة الفشل
     */
    public function createSchool($data)
    {
        // تحويل إعدادات JSON إلى نص إذا لزم الأمر
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }
        
        // إنشاء السجل
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات المدرسة
     * 
     * @param int $id معرّف المدرسة
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateSchool($id, $data)
    {
        // تحويل إعدادات JSON إلى نص إذا لزم الأمر
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * البحث عن مدرسة بالنطاق الفرعي
     * 
     * @param string $subdomain النطاق الفرعي
     * @return array|false بيانات المدرسة أو false إذا لم يتم العثور عليها
     */
    public function findBySubdomain($subdomain)
    {
        return $this->whereFirst('subdomain', $subdomain);
    }
    
    /**
     * التحقق من صلاحية الاشتراك
     * 
     * @param int $schoolId معرّف المدرسة
     * @return bool هل الاشتراك ساري أم لا
     */
    public function isSubscriptionActive($schoolId)
    {
        $school = $this->find($schoolId);
        
        if (!$school) {
            return false;
        }
        
        // التحقق من نشاط المدرسة
        if (!$school['active']) {
            return false;
        }
        
        // التحقق من تاريخ انتهاء الاشتراك
        $endDate = $school['subscription_end_date'];
        $today = date('Y-m-d');
        
        return $endDate >= $today;
    }
    
    /**
     * التحقق من عدد الطلاب
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array عدد الطلاب والحد الأقصى
     */
    public function checkStudentsCount($schoolId)
    {
        $school = $this->find($schoolId);
        
        if (!$school) {
            return [
                'current' => 0,
                'limit' => 0,
                'can_add' => false
            ];
        }
        
        // الحصول على عدد الطلاب الحالي
        $query = "SELECT COUNT(*) as count FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE u.school_id = ?";
        
        $result = $this->db->fetchOne($query, [$schoolId]);
        $currentCount = $result['count'] ?? 0;
        
        return [
            'current' => $currentCount,
            'limit' => $school['max_students'],
            'can_add' => $currentCount < $school['max_students']
        ];
    }
    
    /**
     * تحديث نوع الاشتراك
     * 
     * @param int $schoolId معرّف المدرسة
     * @param string $subscriptionType نوع الاشتراك
     * @param string $startDate تاريخ البدء
     * @param string $endDate تاريخ الانتهاء
     * @param int $maxStudents الحد الأقصى لعدد الطلاب
     * @return bool نجاح العملية أم لا
     */
    public function updateSubscription($schoolId, $subscriptionType, $startDate, $endDate, $maxStudents)
    {
        // تحديث بيانات الاشتراك
        $data = [
            'subscription_type' => $subscriptionType,
            'subscription_start_date' => $startDate,
            'subscription_end_date' => $endDate,
            'max_students' => $maxStudents
        ];
        
        // تحديث سجل المدرسة
        $success = $this->update($schoolId, $data);
        
        if ($success) {
            // إضافة سجل في جدول الاشتراكات
            $this->db->insert('subscriptions', [
                'school_id' => $schoolId,
                'plan_type' => $subscriptionType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'max_students' => $maxStudents,
                'status' => 'active'
            ]);
        }
        
        return $success;
    }
    
    /**
     * الحصول على المدارس النشطة
     * 
     * @param string|null $subscriptionType نوع الاشتراك (اختياري)
     * @return array قائمة المدارس
     */
    public function getActiveSchools($subscriptionType = null)
    {
        $query = "SELECT * FROM {$this->table} WHERE active = 1";
        $params = [];
        
        if ($subscriptionType) {
            $query .= " AND subscription_type = ?";
            $params[] = $subscriptionType;
        }
        
        $query .= " ORDER BY name";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * البحث عن مدارس
     * 
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchSchools($searchTerm)
    {
        $query = "SELECT * FROM {$this->table} 
                 WHERE name LIKE ? OR subdomain LIKE ? 
                 ORDER BY name 
                 LIMIT 100";
        
        $searchParam = "%{$searchTerm}%";
        
        return $this->db->fetchAll($query, [$searchParam, $searchParam]);
    }
    
    /**
     * تفعيل/تعطيل مدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @param bool $active الحالة الجديدة
     * @return bool نجاح العملية أم لا
     */
    public function toggleActive($schoolId, $active)
    {
        return $this->update($schoolId, ['active' => $active ? 1 : 0]);
    }
    
    /**
     * الحصول على إحصائيات المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array الإحصائيات
     */
    public function getStats($schoolId)
    {
        $stats = [];
        
        // عدد الطلاب
        $studentsQuery = "SELECT COUNT(*) as count FROM students s 
                         JOIN users u ON s.user_id = u.id 
                         WHERE u.school_id = ?";
        $studentsResult = $this->db->fetchOne($studentsQuery, [$schoolId]);
        $stats['students_count'] = $studentsResult['count'] ?? 0;
        
        // عدد المعلمين
        $teachersQuery = "SELECT COUNT(*) as count FROM teachers t 
                         JOIN users u ON t.user_id = u.id 
                         WHERE u.school_id = ?";
        $teachersResult = $this->db->fetchOne($teachersQuery, [$schoolId]);
        $stats['teachers_count'] = $teachersResult['count'] ?? 0;
        
        // عدد الصفوف
        $classesQuery = "SELECT COUNT(*) as count FROM classes WHERE school_id = ?";
        $classesResult = $this->db->fetchOne($classesQuery, [$schoolId]);
        $stats['classes_count'] = $classesResult['count'] ?? 0;
        
        // عدد المواد
        $subjectsQuery = "SELECT COUNT(*) as count FROM subjects WHERE school_id = ?";
        $subjectsResult = $this->db->fetchOne($subjectsQuery, [$schoolId]);
        $stats['subjects_count'] = $subjectsResult['count'] ?? 0;
        
        // عدد المهام
        $assignmentsQuery = "SELECT COUNT(*) as count FROM assignments a 
                            JOIN classes c ON a.class_id = c.id 
                            WHERE c.school_id = ?";
        $assignmentsResult = $this->db->fetchOne($assignmentsQuery, [$schoolId]);
        $stats['assignments_count'] = $assignmentsResult['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على تاريخ اشتراكات المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array تاريخ الاشتراكات
     */
    public function getSubscriptionHistory($schoolId)
    {
        $query = "SELECT * FROM subscriptions 
                 WHERE school_id = ? 
                 ORDER BY start_date DESC";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
}