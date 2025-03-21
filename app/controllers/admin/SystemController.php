<?php
/**
 * app/controllers/admin/SystemController.php
 * متحكم إعدادات النظام للمدير الرئيسي
 * يدير عمليات الضبط والإعدادات العامة للنظام
 */
class SystemController extends Controller
{
    private $settingModel;
    private $userModel;
    private $schoolModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->settingModel = new Setting();
        $this->userModel = new User();
        $this->schoolModel = new School();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('super_admin');
    }
    
    /**
     * عرض صفحة إعدادات النظام
     */
    public function index()
    {
        // الحصول على جميع إعدادات النظام
        $settings = $this->settingModel->getAllSettings();
        
        // تنظيم الإعدادات حسب المجموعة
        $groupedSettings = [];
        foreach ($settings as $setting) {
            $groupedSettings[$setting['group']][] = $setting;
        }
        
        // الحصول على إحصائيات النظام
        $stats = $this->getSystemStats();
        
        echo $this->render('admin/system/index', [
            'groupedSettings' => $groupedSettings,
            'stats' => $stats,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تحديث إعدادات النظام
     */
    public function updateSettings()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system');
        }
        
        // استخراج البيانات المرسلة
        $settings = $this->request->post('settings', []);
        
        if (empty($settings)) {
            $this->setFlash('error', 'لم يتم إرسال أي إعدادات للتحديث.');
            $this->redirect('/admin/system');
        }
        
        // تحديث الإعدادات
        $success = $this->settingModel->updateBulkSettings($settings);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث الإعدادات.');
            $this->redirect('/admin/system');
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تحديث إعدادات النظام',
            'system',
            0,
            ['details' => 'تم تحديث إعدادات النظام']
        );
        
        $this->setFlash('success', 'تم تحديث الإعدادات بنجاح.');
        $this->redirect('/admin/system');
    }
    
    /**
     * عرض صفحة الصيانة
     */
    public function maintenance()
    {
        // الحصول على حالة وضع الصيانة
        $maintenanceMode = $this->settingModel->getValue('maintenance_mode');
        
        // الحصول على تاريخ ووقت آخر تشغيل للصيانة
        $lastMaintenance = $this->settingModel->getValue('last_maintenance');
        
        // عرض صفحة الصيانة
        echo $this->render('admin/system/maintenance', [
            'maintenanceMode' => $maintenanceMode,
            'lastMaintenance' => $lastMaintenance,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تفعيل/تعطيل وضع الصيانة
     */
    public function toggleMaintenance()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/maintenance');
        }
        
        // الحصول على حالة وضع الصيانة الحالية
        $currentStatus = $this->settingModel->getValue('maintenance_mode');
        
        // تبديل الحالة
        $newStatus = ($currentStatus == '1') ? '0' : '1';
        
        // تحديث حالة وضع الصيانة
        $success = $this->settingModel->updateSetting('maintenance_mode', $newStatus);
        
        if (!$success) {
            $this->setFlash('error', 'حدث خطأ أثناء تحديث حالة وضع الصيانة.');
            $this->redirect('/admin/system/maintenance');
        }
        
        // تسجيل النشاط
        $actionText = ($newStatus == '1') ? 'تفعيل وضع الصيانة' : 'تعطيل وضع الصيانة';
        $this->userModel->logActivity(
            $this->auth->id(),
            $actionText,
            'system',
            0,
            ['status' => $newStatus]
        );
        
        // إرسال إشعار للمدارس إذا تم تفعيل وضع الصيانة
        if ($newStatus == '1') {
            $maintenanceMessage = $this->request->post('maintenance_message', 'النظام قيد الصيانة حالياً. نعتذر عن الإزعاج.');
            $this->notifySchoolsAboutMaintenance($maintenanceMessage);
        }
        
        $message = ($newStatus == '1') ? 'تم تفعيل وضع الصيانة بنجاح.' : 'تم تعطيل وضع الصيانة بنجاح.';
        $this->setFlash('success', $message);
        $this->redirect('/admin/system/maintenance');
    }
    
    /**
     * تشغيل عمليات الصيانة الدورية
     */
    public function runMaintenance()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/maintenance');
        }
        
        // إجراء عمليات الصيانة
        $this->performSystemMaintenance();
        
        // تحديث تاريخ آخر صيانة
        $this->settingModel->updateSetting('last_maintenance', date('Y-m-d H:i:s'));
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تشغيل صيانة النظام',
            'system',
            0,
            ['details' => 'تم تشغيل عمليات الصيانة الدورية']
        );
        
        $this->setFlash('success', 'تم تنفيذ عمليات الصيانة بنجاح.');
        $this->redirect('/admin/system/maintenance');
    }
    
    /**
     * عرض صفحة النسخ الاحتياطي
     */
    public function backup()
    {
        // الحصول على قائمة النسخ الاحتياطية
        $backups = $this->getBackupsList();
        
        // الحصول على تاريخ آخر نسخة احتياطية
        $lastBackup = $this->settingModel->getValue('last_backup');
        
        // عرض صفحة النسخ الاحتياطي
        echo $this->render('admin/system/backup', [
            'backups' => $backups,
            'lastBackup' => $lastBackup,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * إنشاء نسخة احتياطية جديدة
     */
    public function createBackup()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/backup');
        }
        
        // استخراج البيانات
        $includeFiles = $this->request->post('include_files', 'no') === 'yes';
        
        try {
            // تنفيذ النسخ الاحتياطي
            $backupFileName = $this->performDatabaseBackup($includeFiles);
            
            // تحديث تاريخ آخر نسخة احتياطية
            $this->settingModel->updateSetting('last_backup', date('Y-m-d H:i:s'));
            
            // تسجيل النشاط
            $this->userModel->logActivity(
                $this->auth->id(),
                'إنشاء نسخة احتياطية',
                'system',
                0,
                ['file' => $backupFileName, 'include_files' => $includeFiles]
            );
            
            $this->setFlash('success', 'تم إنشاء النسخة الاحتياطية بنجاح.');
        } catch (Exception $e) {
            $this->setFlash('error', 'حدث خطأ أثناء إنشاء النسخة الاحتياطية: ' . $e->getMessage());
        }
        
        $this->redirect('/admin/system/backup');
    }
    
    /**
     * تنزيل نسخة احتياطية
     *
     * @param string $fileName اسم ملف النسخة الاحتياطية
     */
    public function downloadBackup($fileName)
    {
        $backupDir = $this->getBackupDirectory();
        $filePath = $backupDir . '/' . $fileName;
        
        if (!file_exists($filePath)) {
            $this->setFlash('error', 'الملف المطلوب غير موجود.');
            $this->redirect('/admin/system/backup');
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تنزيل نسخة احتياطية',
            'system',
            0,
            ['file' => $fileName]
        );
        
        // تنزيل الملف
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
    
    /**
     * حذف نسخة احتياطية
     *
     * @param string $fileName اسم ملف النسخة الاحتياطية
     */
    public function deleteBackup($fileName)
    {
        $backupDir = $this->getBackupDirectory();
        $filePath = $backupDir . '/' . $fileName;
        
        if (!file_exists($filePath)) {
            $this->setFlash('error', 'الملف المطلوب غير موجود.');
            $this->redirect('/admin/system/backup');
        }
        
        if (unlink($filePath)) {
            // تسجيل النشاط
            $this->userModel->logActivity(
                $this->auth->id(),
                'حذف نسخة احتياطية',
                'system',
                0,
                ['file' => $fileName]
            );
            
            $this->setFlash('success', 'تم حذف النسخة الاحتياطية بنجاح.');
        } else {
            $this->setFlash('error', 'حدث خطأ أثناء حذف النسخة الاحتياطية.');
        }
        
        $this->redirect('/admin/system/backup');
    }
    
    /**
     * عرض صفحة سجلات النظام
     */
    public function logs()
    {
        // استخراج معلمات التصفية
        $type = $this->request->get('type', 'all');
        $startDate = $this->request->get('start_date', date('Y-m-d', strtotime('-7 days')));
        $endDate = $this->request->get('end_date', date('Y-m-d'));
        $user = $this->request->get('user', '');
        $page = $this->request->get('page', 1);
        
        // الحصول على سجلات النظام
        $logsData = $this->getSystemLogs($type, $startDate, $endDate, $user, $page);
        
        // الحصول على قائمة المستخدمين
        $users = $this->userModel->getAdminUsers();
        
        echo $this->render('admin/system/logs', [
            'logs' => $logsData['logs'],
            'pagination' => $logsData['pagination'],
            'users' => $users,
            'filters' => [
                'type' => $type,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user' => $user
            ],
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تنظيف سجلات النظام
     */
    public function clearLogs()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/logs');
        }
        
        // استخراج الفترة المطلوب حذفها
        $period = $this->request->post('period', '');
        
        switch ($period) {
            case 'all':
                $query = "TRUNCATE TABLE system_logs";
                break;
            case 'month':
                $query = "DELETE FROM system_logs WHERE created_at < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
            case 'week':
                $query = "DELETE FROM system_logs WHERE created_at < DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
                break;
            default:
                $this->setFlash('error', 'الفترة المحددة غير صالحة.');
                $this->redirect('/admin/system/logs');
        }
        
        // تنفيذ الاستعلام
        $this->db->query($query);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تنظيف سجلات النظام',
            'system',
            0,
            ['period' => $period]
        );
        
        $this->setFlash('success', 'تم تنظيف سجلات النظام بنجاح.');
        $this->redirect('/admin/system/logs');
    }
    
    /**
     * إجراء عمليات الصيانة الدورية للنظام
     */
    private function performSystemMaintenance()
    {
        // تنظيف الجلسات القديمة
        $this->db->query("DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // تنظيف رموز إعادة تعيين كلمة المرور المنتهية
        $this->db->query("DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        // تحديث حالة الاشتراكات المنتهية
        $this->db->query("UPDATE schools SET subscription_status = 'expired' WHERE subscription_end_date < CURDATE() AND subscription_status = 'active'");
        
        // تنظيف الملفات المؤقتة
        $this->cleanTempFiles();
        
        // تحسين أداء قواعد البيانات
        $this->optimizeDatabases();
    }
    
    /**
     * تنظيف الملفات المؤقتة
     */
    private function cleanTempFiles()
    {
        $tempDir = 'storage/temp';
        
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file) > 24 * 3600)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * تحسين أداء قواعد البيانات
     */
    private function optimizeDatabases()
    {
        // الحصول على قائمة الجداول
        $query = "SHOW TABLES";
        $tables = $this->db->fetchAll($query);
        
        if (!empty($tables)) {
            $tableNames = [];
            
            foreach ($tables as $table) {
                $tableName = reset($table);
                $tableNames[] = $tableName;
            }
            
            // تحسين الجداول
            $query = "OPTIMIZE TABLE " . implode(', ', $tableNames);
            $this->db->query($query);
        }
    }
    
    /**
     * إجراء عملية النسخ الاحتياطي لقاعدة البيانات
     *
     * @param bool $includeFiles تضمين الملفات في النسخ الاحتياطي
     * @return string اسم ملف النسخ الاحتياطي
     */
    private function performDatabaseBackup($includeFiles = false)
    {
        $backupDir = $this->getBackupDirectory();
        
        // التأكد من وجود المجلد
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        // إنشاء اسم ملف النسخ الاحتياطي
        $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filePath = $backupDir . '/' . $fileName;
        
        // الحصول على إعدادات قاعدة البيانات
        $dbConfig = Config::get('database');
        
        // تنفيذ النسخ الاحتياطي باستخدام mysqldump
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($dbConfig['username']),
            escapeshellarg($dbConfig['password']),
            escapeshellarg($dbConfig['host']),
            escapeshellarg($dbConfig['database']),
            escapeshellarg($filePath)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception('فشل في إنشاء نسخة احتياطية لقاعدة البيانات.');
        }
        
        // إذا كان مطلوب تضمين الملفات
        if ($includeFiles) {
            $zipFileName = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
            $zipFilePath = $backupDir . '/' . $zipFileName;
            
            $zip = new ZipArchive();
            
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // إضافة ملف النسخ الاحتياطي لقاعدة البيانات
                $zip->addFile($filePath, basename($filePath));
                
                // إضافة مجلدات الملفات
                $this->addDirToZip($zip, 'storage/uploads', 'storage/uploads');
                
                $zip->close();
                
                // حذف ملف SQL بعد إضافته للأرشيف
                unlink($filePath);
                
                return $zipFileName;
            } else {
                throw new Exception('فشل في إنشاء أرشيف ZIP.');
            }
        }
        
        return $fileName;
    }
    
    /**
     * إضافة محتويات مجلد للأرشيف
     *
     * @param ZipArchive $zip كائن الأرشيف
     * @param string $dir المجلد المراد إضافته
     * @param string $zipDir المسار داخل الأرشيف
     */
    private function addDirToZip($zip, $dir, $zipDir)
    {
        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $filePath = $dir . '/' . $file;
                        $zipFilePath = $zipDir . '/' . $file;
                        
                        if (is_file($filePath)) {
                            $zip->addFile($filePath, $zipFilePath);
                        } elseif (is_dir($filePath)) {
                            $zip->addEmptyDir($zipFilePath);
                            $this->addDirToZip($zip, $filePath, $zipFilePath);
                        }
                    }
                }
                closedir($handle);
            }
        }
    }
    
    /**
     * الحصول على مجلد النسخ الاحتياطية
     *
     * @return string مسار مجلد النسخ الاحتياطية
     */
    private function getBackupDirectory()
    {
        return 'storage/backups';
    }
    
    /**
     * الحصول على قائمة النسخ الاحتياطية
     *
     * @return array قائمة النسخ الاحتياطية
     */
    private function getBackupsList()
    {
        $backupDir = $this->getBackupDirectory();
        $backups = [];
        
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/{*.sql,*.zip}', GLOB_BRACE);
            
            foreach ($files as $file) {
                $fileInfo = pathinfo($file);
                $backups[] = [
                    'name' => $fileInfo['basename'],
                    'size' => filesize($file),
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => $fileInfo['extension']
                ];
            }
            
            // ترتيب النسخ الاحتياطية حسب التاريخ (الأحدث أولاً)
            usort($backups, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }
        
        return $backups;
    }
    
    /**
     * إخطار المدارس بوضع الصيانة
     *
     * @param string $message رسالة الصيانة
     */
    private function notifySchoolsAboutMaintenance($message)
    {
        // الحصول على قائمة مدراء المدارس
        $schoolAdmins = $this->userModel->getSchoolAdmins();
        
        // إنشاء كائن الإشعارات
        $notificationModel = new Notification();
        
        // إرسال إشعار لكل مدير مدرسة
        foreach ($schoolAdmins as $admin) {
            $notificationModel->createNotification(
                $admin['id'],
                'system_maintenance',
                'النظام قيد الصيانة',
                $message,
                'system',
                0
            );
        }
    }
    
    /**
     * الحصول على سجلات النظام
     *
     * @param string $type نوع السجل
     * @param string $startDate تاريخ البداية
     * @param string $endDate تاريخ النهاية
     * @param string $user معرّف المستخدم
     * @param int $page رقم الصفحة
     * @return array سجلات النظام ومعلومات الترقيم
     */
    private function getSystemLogs($type, $startDate, $endDate, $user, $page)
    {
        // تحديد عدد السجلات في الصفحة
        $perPage = 30;
        $offset = ($page - 1) * $perPage;
        
        // بناء استعلام الحصول على السجلات
        $query = "SELECT sl.*, u.first_name, u.last_name, u.email
                 FROM system_logs sl
                 LEFT JOIN users u ON sl.user_id = u.id
                 WHERE 1=1";
        $params = [];
        
        // إضافة شرط نوع السجل
        if ($type !== 'all') {
            $query .= " AND sl.entity_type = ?";
            $params[] = $type;
        }
        
        // إضافة شرط تاريخ البداية
        if (!empty($startDate)) {
            $query .= " AND DATE(sl.created_at) >= ?";
            $params[] = $startDate;
        }
        
        // إضافة شرط تاريخ النهاية
        if (!empty($endDate)) {
            $query .= " AND DATE(sl.created_at) <= ?";
            $params[] = $endDate;
        }
        
        // إضافة شرط المستخدم
        if (!empty($user)) {
            $query .= " AND sl.user_id = ?";
            $params[] = $user;
        }
        
        // الحصول على إجمالي عدد السجلات
        $countQuery = str_replace('sl.*, u.first_name, u.last_name, u.email', 'COUNT(*) as count', $query);
        $totalResult = $this->db->fetchOne($countQuery, $params);
        $total = $totalResult['count'] ?? 0;
        
        // إضافة الترتيب والترقيم
        $query .= " ORDER BY sl.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        // تنفيذ الاستعلام
        $logs = $this->db->fetchAll($query, $params);
        
        // إعداد معلومات الترقيم
        $pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
        
        return [
            'logs' => $logs,
            'pagination' => $pagination
        ];
    }
    
    /**
     * الحصول على إحصائيات النظام
     *
     * @return array إحصائيات النظام
     */
    private function getSystemStats()
    {
        $stats = [];
        
        // إجمالي المدارس
        $query = "SELECT COUNT(*) as count FROM schools";
        $result = $this->db->fetchOne($query);
        $stats['total_schools'] = $result['count'] ?? 0;
        
        // إجمالي المستخدمين
        $query = "SELECT COUNT(*) as count FROM users";
        $result = $this->db->fetchOne($query);
        $stats['total_users'] = $result['count'] ?? 0;
        
        // إجمالي الطلاب
        $query = "SELECT COUNT(*) as count FROM students";
        $result = $this->db->fetchOne($query);
        $stats['total_students'] = $result['count'] ?? 0;
        
        // إجمالي المعلمين
        $query = "SELECT COUNT(*) as count FROM teachers";
        $result = $this->db->fetchOne($query);
        $stats['total_teachers'] = $result['count'] ?? 0;
        
        // إحصائيات المساحة والذاكرة
        $stats['disk_usage'] = $this->getDiskUsage();
        $stats['memory_usage'] = $this->getMemoryUsage();
        
        // إحصائيات النظام
        $stats['php_version'] = phpversion();
        $stats['mysql_version'] = $this->db->getVersion();
        $stats['system_version'] = $this->settingModel->getValue('system_version', '1.0.0');
        
        return $stats;
    }
    
    /**
     * حساب استخدام القرص
     *
     * @return array معلومات استخدام القرص
     */
    private function getDiskUsage()
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;
        
        return [
            'total' => $totalSpace,
            'used' => $usedSpace,
            'free' => $freeSpace,
            'percent' => round(($usedSpace / $totalSpace) * 100, 2)
        ];
    }
    
    /**
     * الحصول على معلومات استخدام الذاكرة
     *
     * @return array معلومات استخدام الذاكرة
     */
    /**
     * الحصول على معلومات استخدام الذاكرة
     *
     * @return array معلومات استخدام الذاكرة
     */
    private function getMemoryUsage()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $serverLoad = $load[0];
        } else {
            $serverLoad = 0;
        }
        
        $memInfo = [];
        if (function_exists('shell_exec') && PHP_OS !== 'WIN') {
            $memInfoContent = shell_exec('cat /proc/meminfo');
            if ($memInfoContent) {
                foreach (explode("\n", $memInfoContent) as $line) {
                    if (preg_match('/^(\w+):\s+(\d+)\s+kB$/', $line, $matches)) {
                        $memInfo[$matches[1]] = $matches[2] * 1024; // Convert to bytes
                    }
                }
            }
        }
        
        if (!empty($memInfo) && isset($memInfo['MemTotal']) && isset($memInfo['MemFree']) && isset($memInfo['Buffers']) && isset($memInfo['Cached'])) {
            $totalMem = $memInfo['MemTotal'];
            $freeMem = $memInfo['MemFree'] + $memInfo['Buffers'] + $memInfo['Cached'];
            $usedMem = $totalMem - $freeMem;
            
            return [
                'total' => $totalMem,
                'used' => $usedMem,
                'free' => $freeMem,
                'percent' => round(($usedMem / $totalMem) * 100, 2),
                'server_load' => $serverLoad
            ];
        }
        
        // Fallback if unable to get memory info
        return [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'percent' => 0,
            'server_load' => $serverLoad
        ];
    }
    
    /**
     * عرض صفحة تحديثات النظام
     */
    public function updates()
    {
        // الحصول على معلومات النظام الحالي
        $currentVersion = $this->settingModel->getValue('system_version', '1.0.0');
        $lastUpdateCheck = $this->settingModel->getValue('last_update_check');
        $updateAvailable = $this->settingModel->getValue('update_available', '0');
        $latestVersion = $this->settingModel->getValue('latest_version', $currentVersion);
        
        // عرض صفحة التحديثات
        echo $this->render('admin/system/updates', [
            'currentVersion' => $currentVersion,
            'lastUpdateCheck' => $lastUpdateCheck,
            'updateAvailable' => $updateAvailable == '1',
            'latestVersion' => $latestVersion,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * التحقق من وجود تحديثات جديدة
     */
    public function checkForUpdates()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/updates');
        }
        
        try {
            // في بيئة حقيقية، سيتم الاتصال بسيرفر التحديثات للتحقق من وجود تحديثات جديدة
            // هنا سنقوم بمحاكاة عملية التحقق
            
            $currentVersion = $this->settingModel->getValue('system_version', '1.0.0');
            
            // محاكاة عملية التحقق من التحديثات
            $latestVersion = $this->simulateCheckForUpdates($currentVersion);
            
            // تحديث إعدادات النظام
            $this->settingModel->updateSetting('last_update_check', date('Y-m-d H:i:s'));
            $this->settingModel->updateSetting('latest_version', $latestVersion);
            
            $updateAvailable = version_compare($latestVersion, $currentVersion, '>') ? '1' : '0';
            $this->settingModel->updateSetting('update_available', $updateAvailable);
            
            // تسجيل النشاط
            $this->userModel->logActivity(
                $this->auth->id(),
                'التحقق من التحديثات',
                'system',
                0,
                [
                    'current_version' => $currentVersion,
                    'latest_version' => $latestVersion,
                    'update_available' => $updateAvailable
                ]
            );
            
            $message = $updateAvailable == '1' ? 
                "تم العثور على تحديث جديد (الإصدار {$latestVersion})." : 
                "النظام محدث إلى أحدث إصدار ({$currentVersion}).";
            
            $this->setFlash('success', $message);
        } catch (Exception $e) {
            $this->setFlash('error', 'حدث خطأ أثناء التحقق من التحديثات: ' . $e->getMessage());
        }
        
        $this->redirect('/admin/system/updates');
    }
    
    /**
     * تثبيت التحديثات الجديدة
     */
    public function installUpdate()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/updates');
        }
        
        // التحقق من وجود تحديثات
        $updateAvailable = $this->settingModel->getValue('update_available', '0');
        
        if ($updateAvailable != '1') {
            $this->setFlash('error', 'لا توجد تحديثات متاحة للتثبيت.');
            $this->redirect('/admin/system/updates');
        }
        
        try {
            // في بيئة حقيقية، سيتم تنزيل وتثبيت التحديث من سيرفر التحديثات
            // هنا سنقوم بمحاكاة عملية التثبيت
            
            $currentVersion = $this->settingModel->getValue('system_version', '1.0.0');
            $latestVersion = $this->settingModel->getValue('latest_version', $currentVersion);
            
            // محاكاة عملية تثبيت التحديث
            $this->simulateUpdateInstallation($currentVersion, $latestVersion);
            
            // تحديث إعدادات النظام
            $this->settingModel->updateSetting('system_version', $latestVersion);
            $this->settingModel->updateSetting('update_available', '0');
            $this->settingModel->updateSetting('last_update', date('Y-m-d H:i:s'));
            
            // تسجيل النشاط
            $this->userModel->logActivity(
                $this->auth->id(),
                'تثبيت تحديث النظام',
                'system',
                0,
                [
                    'from_version' => $currentVersion,
                    'to_version' => $latestVersion
                ]
            );
            
            $this->setFlash('success', "تم تحديث النظام بنجاح إلى الإصدار {$latestVersion}.");
        } catch (Exception $e) {
            $this->setFlash('error', 'حدث خطأ أثناء تثبيت التحديث: ' . $e->getMessage());
        }
        
        $this->redirect('/admin/system/updates');
    }
    
    /**
     * محاكاة عملية التحقق من التحديثات
     *
     * @param string $currentVersion الإصدار الحالي
     * @return string أحدث إصدار
     */
    private function simulateCheckForUpdates($currentVersion)
    {
        // تقسيم الإصدار الحالي إلى أجزاء
        $versionParts = explode('.', $currentVersion);
        
        // زيادة الإصدار الفرعي
        $versionParts[2] = (int)$versionParts[2] + 1;
        
        // في 20% من الحالات، زيادة الإصدار الثانوي أيضًا
        if (rand(1, 5) === 1) {
            $versionParts[1] = (int)$versionParts[1] + 1;
            $versionParts[2] = 0;
        }
        
        // إعادة بناء الإصدار
        $latestVersion = implode('.', $versionParts);
        
        return $latestVersion;
    }
    
    /**
     * محاكاة عملية تثبيت التحديث
     *
     * @param string $currentVersion الإصدار الحالي
     * @param string $latestVersion الإصدار الجديد
     */
    private function simulateUpdateInstallation($currentVersion, $latestVersion)
    {
        // محاكاة تأخير لعملية التثبيت
        sleep(2);
        
        // في 10% من الحالات، إثارة استثناء لمحاكاة فشل التثبيت
        if (rand(1, 10) === 1) {
            throw new Exception('فشل في تثبيت التحديث بسبب خطأ في الإعدادات.');
        }
        
        // محاكاة تسجيل ملاحظات الإصدار في قاعدة البيانات
        $releaseNotes = "تحديث من الإصدار {$currentVersion} إلى الإصدار {$latestVersion}.\n";
        $releaseNotes .= "- تحسينات في الأداء\n";
        $releaseNotes .= "- إصلاح مشكلات الأمان\n";
        $releaseNotes .= "- إضافة ميزات جديدة";
        
        $this->db->insert('system_updates', [
            'from_version' => $currentVersion,
            'to_version' => $latestVersion,
            'release_notes' => $releaseNotes,
            'updated_by' => $this->auth->id(),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * عرض صفحة معلومات النظام
     */
    public function info()
    {
        // جمع معلومات النظام
        $systemInfo = $this->getSystemInfo();
        
        // عرض صفحة معلومات النظام
        echo $this->render('admin/system/info', [
            'systemInfo' => $systemInfo,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * جمع معلومات مفصلة عن النظام
     *
     * @return array معلومات النظام
     */
    private function getSystemInfo()
    {
        $info = [];
        
        // معلومات PHP
        $info['php'] = [
            'version' => phpversion(),
            'extensions' => get_loaded_extensions(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => ini_get('error_reporting')
        ];
        
        // معلومات قاعدة البيانات
        $info['database'] = [
            'version' => $this->db->getVersion(),
            'driver' => $this->db->getDriverName(),
            'connection' => $this->db->isConnected() ? 'متصل' : 'غير متصل'
        ];
        
        // معلومات الخادم
        $info['server'] = [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'غير معروف',
            'name' => $_SERVER['SERVER_NAME'] ?? 'غير معروف',
            'address' => $_SERVER['SERVER_ADDR'] ?? 'غير معروف',
            'os' => PHP_OS,
            'port' => $_SERVER['SERVER_PORT'] ?? 'غير معروف',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'غير معروف'
        ];
        
        // معلومات الملفات
        $info['file_system'] = [
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'غير معروف',
            'disk_usage' => $this->getDiskUsage(),
            'temp_dir' => sys_get_temp_dir(),
            'is_writable' => is_writable('storage')
        ];
        
        // معلومات النظام
        $info['system'] = [
            'version' => $this->settingModel->getValue('system_version', '1.0.0'),
            'maintenance_mode' => $this->settingModel->getValue('maintenance_mode') == '1' ? 'مفعل' : 'غير مفعل',
            'last_maintenance' => $this->settingModel->getValue('last_maintenance'),
            'last_update' => $this->settingModel->getValue('last_update'),
            'installation_date' => $this->settingModel->getValue('installation_date')
        ];
        
        return $info;
    }
    
    /**
     * تصدير معلومات النظام
     */
    public function exportSystemInfo()
    {
        // جمع معلومات النظام
        $systemInfo = $this->getSystemInfo();
        
        // تحويل المعلومات إلى تنسيق JSON
        $json = json_encode($systemInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تصدير معلومات النظام',
            'system',
            0,
            ['date' => date('Y-m-d H:i:s')]
        );
        
        // تنزيل الملف
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="system_info_' . date('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }
    
    /**
     * تنظيف ذاكرة التخزين المؤقت
     */
    public function clearCache()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system');
        }
        
        // تنظيف ملفات التخزين المؤقت
        $this->cleanCacheFiles();
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تنظيف ذاكرة التخزين المؤقت',
            'system',
            0,
            ['date' => date('Y-m-d H:i:s')]
        );
        
        $this->setFlash('success', 'تم تنظيف ذاكرة التخزين المؤقت بنجاح.');
        $this->redirect('/admin/system');
    }
    
    /**
     * تنظيف ملفات التخزين المؤقت
     */
    private function cleanCacheFiles()
    {
        $cacheDirs = [
            'storage/cache',
            'storage/views',
            'storage/logs/debug'
        ];
        
        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }
    }
    
    /**
     * عرض صفحة إدارة الأذونات
     */
    public function permissions()
    {
        // الحصول على قائمة الأدوار
        $roles = $this->db->fetchAll("SELECT * FROM roles ORDER BY id");
        
        // الحصول على قائمة الأذونات
        $permissions = $this->db->fetchAll("SELECT * FROM permissions ORDER BY module, name");
        
        // الحصول على مصفوفة الأدوار والأذونات
        $rolePermissions = [];
        
        foreach ($roles as $role) {
            $rolePermissions[$role['id']] = $this->getRolePermissions($role['id']);
        }
        
        // تنظيم الأذونات حسب الوحدة
        $permissionsByModule = [];
        
        foreach ($permissions as $permission) {
            $permissionsByModule[$permission['module']][] = $permission;
        }
        
        echo $this->render('admin/system/permissions', [
            'roles' => $roles,
            'permissionsByModule' => $permissionsByModule,
            'rolePermissions' => $rolePermissions,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تحديث أذونات الدور
     */
    public function updateRolePermissions()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/permissions');
        }
        
        // استخراج البيانات
        $roleId = $this->request->post('role_id');
        $permissions = $this->request->post('permissions', []);
        
        if (empty($roleId)) {
            $this->setFlash('error', 'يجب تحديد الدور.');
            $this->redirect('/admin/system/permissions');
        }
        
        // حذف الأذونات الحالية للدور
        $this->db->query("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
        
        // إضافة الأذونات الجديدة
        if (!empty($permissions)) {
            $values = [];
            $placeholders = [];
            
            foreach ($permissions as $permissionId) {
                $values[] = $roleId;
                $values[] = $permissionId;
                $placeholders[] = "(?, ?)";
            }
            
            $query = "INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(', ', $placeholders);
            $this->db->query($query, $values);
        }
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'تحديث أذونات الدور',
            'system',
            $roleId,
            ['permissions_count' => count($permissions)]
        );
        
        $this->setFlash('success', 'تم تحديث أذونات الدور بنجاح.');
        $this->redirect('/admin/system/permissions');
    }
    
    /**
     * الحصول على أذونات دور معين
     *
     * @param int $roleId معرّف الدور
     * @return array معرّفات الأذونات
     */
    private function getRolePermissions($roleId)
    {
        $query = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
        $result = $this->db->fetchAll($query, [$roleId]);
        
        return array_column($result, 'permission_id');
    }
    
    /**
     * عرض صفحة سجل رسائل البريد الإلكتروني
     */
    public function emailLogs()
    {
        // استخراج معلمات التصفية
        $recipient = $this->request->get('recipient', '');
        $status = $this->request->get('status', 'all');
        $startDate = $this->request->get('start_date', date('Y-m-d', strtotime('-7 days')));
        $endDate = $this->request->get('end_date', date('Y-m-d'));
        $page = $this->request->get('page', 1);
        
        // الحصول على سجلات البريد
        $logsData = $this->getEmailLogs($recipient, $status, $startDate, $endDate, $page);
        
        echo $this->render('admin/system/email_logs', [
            'logs' => $logsData['logs'],
            'pagination' => $logsData['pagination'],
            'filters' => [
                'recipient' => $recipient,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * الحصول على سجلات البريد الإلكتروني
     *
     * @param string $recipient البريد الإلكتروني للمستلم
     * @param string $status حالة الإرسال
     * @param string $startDate تاريخ البداية
     * @param string $endDate تاريخ النهاية
     * @param int $page رقم الصفحة
     * @return array سجلات البريد ومعلومات الترقيم
     */
    private function getEmailLogs($recipient, $status, $startDate, $endDate, $page)
    {
        // تحديد عدد السجلات في الصفحة
        $perPage = 30;
        $offset = ($page - 1) * $perPage;
        
        // بناء استعلام الحصول على السجلات
        $query = "SELECT * FROM email_logs WHERE 1=1";
        $params = [];
        
        // إضافة شرط المستلم
        if (!empty($recipient)) {
            $query .= " AND recipient LIKE ?";
            $params[] = "%{$recipient}%";
        }
        
        // إضافة شرط الحالة
        if ($status !== 'all') {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        
        // إضافة شرط تاريخ البداية
        if (!empty($startDate)) {
            $query .= " AND DATE(sent_at) >= ?";
            $params[] = $startDate;
        }
        
        // إضافة شرط تاريخ النهاية
        if (!empty($endDate)) {
            $query .= " AND DATE(sent_at) <= ?";
            $params[] = $endDate;
        }
        
        // الحصول على إجمالي عدد السجلات
        $countQuery = str_replace('*', 'COUNT(*) as count', $query);
        $totalResult = $this->db->fetchOne($countQuery, $params);
        $total = $totalResult['count'] ?? 0;
        
        // إضافة الترتيب والترقيم
        $query .= " ORDER BY sent_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        // تنفيذ الاستعلام
        $logs = $this->db->fetchAll($query, $params);
        
        // إعداد معلومات الترقيم
        $pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
        
        return [
            'logs' => $logs,
            'pagination' => $pagination
        ];
    }
    
    /**
     * عرض تفاصيل رسالة بريد إلكتروني
     *
     * @param int $id معرّف الرسالة
     */
    public function viewEmail($id)
    {
        // الحصول على تفاصيل الرسالة
        $email = $this->db->fetchOne("SELECT * FROM email_logs WHERE id = ?", [$id]);
        
        if (!$email) {
            $this->setFlash('error', 'لم يتم العثور على رسالة البريد الإلكتروني المطلوبة.');
            $this->redirect('/admin/system/email-logs');
        }
        
        echo $this->render('admin/system/view_email', [
            'email' => $email,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * إعادة إرسال رسالة بريد إلكتروني
     *
     * @param int $id معرّف الرسالة
     */
    public function resendEmail($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/admin/system/email-logs');
        }
        
        // الحصول على تفاصيل الرسالة
        $email = $this->db->fetchOne("SELECT * FROM email_logs WHERE id = ?", [$id]);
        
        if (!$email) {
            $this->setFlash('error', 'لم يتم العثور على رسالة البريد الإلكتروني المطلوبة.');
            $this->redirect('/admin/system/email-logs');
        }
        
        // في بيئة حقيقية، سيتم استدعاء خدمة إرسال البريد الإلكتروني هنا
        // هنا سنقوم بمحاكاة عملية الإرسال
        
        // تحديث سجل البريد الإلكتروني
        $this->db->update('email_logs', $id, [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'attempts' => $email['attempts'] + 1
        ]);
        
        // تسجيل النشاط
        $this->userModel->logActivity(
            $this->auth->id(),
            'إعادة إرسال بريد إلكتروني',
            'system',
            $id,
            ['recipient' => $email['recipient']]
        );
        
        $this->setFlash('success', 'تم إعادة إرسال البريد الإلكتروني بنجاح.');
        $this->redirect('/admin/system/view-email/' . $id);
    }
}