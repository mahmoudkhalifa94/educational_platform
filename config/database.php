<?php
/**
 * فئة قاعدة البيانات
 * تدير الاتصال بقاعدة البيانات والعمليات الأساسية عليها
 */
class Database
{
    private $host;
    private $username;
    private $password;
    private $dbname;
    private $charset;
    private $conn;
    private static $instance = null;
    
    /**
     * الكائن الوحيد المسموح به (Singleton)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * التهيئة الخاصة
     */
    private function __construct()
    {
        // تحميل بيانات الاتصال من ملف التكوين
        $config = require_once __DIR__ . '/../config/database.php';
        
        $this->host = $config['host'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->dbname = $config['dbname'];
        $this->charset = $config['charset'] ?? 'utf8mb4';
        
        // إنشاء الاتصال
        $this->connect();
    }
    
    /**
     * منع النسخ
     */
    private function __clone() {}
    
    /**
     * إنشاء اتصال بقاعدة البيانات
     */
    private function connect()
    {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
        ];
        
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // تسجيل الخطأ وإظهار رسالة مناسبة
            error_log("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
            throw new Exception("لا يمكن الاتصال بقاعدة البيانات. يرجى التحقق من الإعدادات أو الاتصال بمسؤول النظام.");
        }
    }
    
    /**
     * تنفيذ استعلام مع وسائط مرتبطة
     * 
     * @param string $query استعلام SQL
     * @param array $params وسائط للربط
     * @return PDOStatement
     */
    public function query($query, $params = [])
    {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("خطأ في تنفيذ الاستعلام: " . $e->getMessage() . " SQL: " . $query);
            throw new Exception("حدث خطأ أثناء معالجة طلبك.");
        }
    }
    
    /**
     * استرجاع صف واحد من نتائج الاستعلام
     * 
     * @param string $query استعلام SQL
     * @param array $params وسائط للربط
     * @return array|false صف واحد أو false إذا لم يتم العثور على نتائج
     */
    public function fetchOne($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }
    
    /**
     * استرجاع جميع الصفوف من نتائج الاستعلام
     * 
     * @param string $query استعلام SQL
     * @param array $params وسائط للربط
     * @return array مصفوفة من الصفوف
     */
    public function fetchAll($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * إدخال بيانات جديدة إلى جدول
     * 
     * @param string $table اسم الجدول
     * @param array $data البيانات المراد إدخالها [field => value]
     * @return int|false معرّف الصف المدخل أو false في حالة الفشل
     */
    public function insert($table, $data)
    {
        try {
            // إنشاء استعلام الإدخال ديناميكيًا
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            
            $fieldsStr = implode(', ', $fields);
            $placeholdersStr = implode(', ', $placeholders);
            
            $query = "INSERT INTO {$table} ({$fieldsStr}) VALUES ({$placeholdersStr})";
            
            $this->query($query, array_values($data));
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("خطأ في عملية الإدخال: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تحديث بيانات في جدول
     * 
     * @param string $table اسم الجدول
     * @param array $data البيانات المراد تحديثها [field => value]
     * @param string $where شرط التحديث
     * @param array $params وسائط للربط في شرط التحديث
     * @return bool نجاح العملية أو فشلها
     */
    public function update($table, $data, $where, $params = [])
    {
        try {
            // إنشاء جزء SET في الاستعلام
            $setParts = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                $setParts[] = "{$field} = ?";
                $values[] = $value;
            }
            
            $setStr = implode(', ', $setParts);
            
            $query = "UPDATE {$table} SET {$setStr} WHERE {$where}";
            
            // دمج قيم التحديث مع وسائط شرط WHERE
            $allParams = array_merge($values, $params);
            
            $stmt = $this->query($query, $allParams);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("خطأ في عملية التحديث: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف بيانات من جدول
     * 
     * @param string $table اسم الجدول
     * @param string $where شرط الحذف
     * @param array $params وسائط للربط
     * @return bool نجاح العملية أو فشلها
     */
    public function delete($table, $where, $params = [])
    {
        try {
            $query = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->query($query, $params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("خطأ في عملية الحذف: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * عدد الصفوف المتأثرة بآخر عملية
     * 
     * @return int عدد الصفوف
     */
    public function rowCount($stmt)
    {
        return $stmt->rowCount();
    }
    
    /**
     * بدء معاملة (Transaction)
     */
    public function beginTransaction()
    {
        return $this->conn->beginTransaction();
    }
    
    /**
     * تأكيد المعاملة (Commit)
     */
    public function commit()
    {
        return $this->conn->commit();
    }
    
    /**
     * التراجع عن المعاملة (Rollback)
     */
    public function rollback()
    {
        return $this->conn->rollBack();
    }
    
    /**
     * جلب آخر معرّف تم إدخاله
     * 
     * @return string معرّف آخر صف تم إدخاله
     */
    public function lastInsertId()
    {
        return $this->conn->lastInsertId();
    }
    
    /**
     * التنظيف عند انتهاء الكائن من الاستخدام
     */
    public function __destruct()
    {
        // إغلاق الاتصال
        $this->conn = null;
    }
}