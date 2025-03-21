<?php
/**
 * app/controllers/admin/DashboardController.php
 * متحكم لوحة التحكم للمدير الرئيسي
 */
class DashboardController extends Controller
{
    private $schoolModel;
    private $userModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->schoolModel = new School();
        $this->userModel = new User();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('super_admin');
    }
    
    /**
     * عرض لوحة التحكم
     */
    public function index()
    {
        // الحصول على إحصائيات النظام
        $stats = $this->getSystemStats();
        
        // الحصول على آخر المدارس
        $latestSchools = $this->getLatestSchools();
        
        // الحصول على آخر سجلات النظام
        $latestLogs = $this->getLatestLogs();
        
        // الحصول على بيانات الرسوم البيانية
        $charts = $this->getChartsData();
        
        echo $this->render('admin/dashboard', [
            'stats' => $stats,
            'latestSchools' => $latestSchools,
            'latestLogs' => $latestLogs,
            'charts' => $charts,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * الحصول على إحصائيات النظام
     * 
     * @return array الإحصائيات
     */
    private function getSystemStats()
    {
        $stats = [];
        
        // إجمالي المدارس
        $query = "SELECT COUNT(*) as count FROM schools";
        $result = $this->db->fetchOne($query);
        $stats['total_schools'] = $result['count'] ?? 0;
        
        // المدارس النشطة
        $query = "SELECT COUNT(*) as count FROM schools WHERE active = 1";
        $result = $this->db->fetchOne($query);
        $stats['active_schools'] = $result['count'] ?? 0;
        
        // المدارس المنتهية
        $query = "SELECT COUNT(*) as count FROM schools WHERE subscription_end_date < CURDATE()";
        $result = $this->db->fetchOne($query);
        $stats['expired_schools'] = $result['count'] ?? 0;
        
        // المدارس حسب نوع الاشتراك
        $query = "SELECT subscription_type, COUNT(*) as count FROM schools GROUP BY subscription_type";
        $result = $this->db->fetchAll($query);
        $stats['schools_by_subscription'] = [];
        
        foreach ($result as $row) {
            $stats['schools_by_subscription'][$row['subscription_type']] = $row['count'];
        }
        
        // إجمالي المستخدمين
        $query = "SELECT COUNT(*) as count FROM users";
        $result = $this->db->fetchOne($query);
        $stats['total_users'] = $result['count'] ?? 0;
        
        // المستخدمين حسب الدور
        $query = "SELECT r.name as role, COUNT(*) as count 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 GROUP BY r.name";
        $result = $this->db->fetchAll($query);
        $stats['users_by_role'] = [];
        
        foreach ($result as $row) {
            $stats['users_by_role'][$row['role']] = $row['count'];
        }
        
        // إجمالي الطلاب
        $query = "SELECT COUNT(*) as count FROM students";
        $result = $this->db->fetchOne($query);
        $stats['total_students'] = $result['count'] ?? 0;
        
        // إجمالي المعلمين
        $query = "SELECT COUNT(*) as count FROM teachers";
        $result = $this->db->fetchOne($query);
        $stats['total_teachers'] = $result['count'] ?? 0;
        
        // إجمالي الصفوف
        $query = "SELECT COUNT(*) as count FROM classes";
        $result = $this->db->fetchOne($query);
        $stats['total_classes'] = $result['count'] ?? 0;
        
        // إجمالي المواد
        $query = "SELECT COUNT(*) as count FROM subjects";
        $result = $this->db->fetchOne($query);
        $stats['total_subjects'] = $result['count'] ?? 0;
        
        // إجمالي المهام
        $query = "SELECT COUNT(*) as count FROM assignments";
        $result = $this->db->fetchOne($query);
        $stats['total_assignments'] = $result['count'] ?? 0;
        
        return $stats;
    }
    
    /**
     * الحصول على آخر المدارس المضافة
     * 
     * @param int $limit عدد المدارس
     * @return array المدارس
     */
    private function getLatestSchools($limit = 5)
    {
        $query = "SELECT id, name, subdomain, subscription_type, subscription_end_date, active, created_at 
                 FROM schools 
                 ORDER BY created_at DESC 
                 LIMIT ?";
        
        return $this->db->fetchAll($query, [$limit]);
    }
    
    /**
     * الحصول على آخر سجلات النظام
     * 
     * @param int $limit عدد السجلات
     * @return array السجلات
     */
    private function getLatestLogs($limit = 10)
    {
        $query = "SELECT sl.*, u.first_name, u.last_name, u.email
                 FROM system_logs sl
                 LEFT JOIN users u ON sl.user_id = u.id
                 ORDER BY sl.created_at DESC
                 LIMIT ?";
        
        return $this->db->fetchAll($query, [$limit]);
    }
    
    /**
     * الحصول على بيانات الرسوم البيانية
     * 
     * @return array البيانات
     */
    private function getChartsData()
    {
        $charts = [];
        
        // عدد المدارس المضافة شهريًا خلال العام الحالي
        $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                 FROM schools 
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) 
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                 ORDER BY month";
        
        $charts['schools_monthly'] = $this->db->fetchAll($query);
        
        // عدد المستخدمين المضافين شهريًا خلال العام الحالي
        $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                 FROM users 
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) 
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                 ORDER BY month";
        
        $charts['users_monthly'] = $this->db->fetchAll($query);
        
        // توزيع المدارس حسب نوع الاشتراك
        $query = "SELECT 
                    CASE 
                        WHEN subscription_type = 'trial' THEN 'تجريبي'
                        WHEN subscription_type = 'limited' THEN 'محدود'
                        WHEN subscription_type = 'unlimited' THEN 'غير محدود'
                        ELSE subscription_type
                    END as type,
                    COUNT(*) as count 
                 FROM schools 
                 GROUP BY subscription_type 
                 ORDER BY count DESC";
        
        $charts['schools_by_subscription'] = $this->db->fetchAll($query);
        
        return $charts;
    }
}