<?php
/**
 * core/Config.php
 * فئة التكوين
 * تدير إعدادات وتكوين التطبيق
 */
class Config
{
    private static $instance = null;
    private $config = [];
    
    /**
     * الحصول على التطبيق (Singleton)
     * 
     * @return Config
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * تهيئة التكوين
     */
    private function __construct()
    {
        $this->loadConfig();
    }
    
    /**
     * منع النسخ
     */
    private function __clone() {}
    
    /**
     * تحميل جميع ملفات التكوين
     */
    private function loadConfig()
    {
        $configPath = __DIR__ . '/../config/';
        $configFiles = glob($configPath . '*.php');
        
        foreach ($configFiles as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require_once $file;
        }
    }
    
    /**
     * الحصول على قيمة تكوين
     * 
     * @param string $key المفتاح (مثال: app.name أو database.host)
     * @param mixed $default القيمة الافتراضية في حالة عدم وجود المفتاح
     * @return mixed قيمة التكوين
     */
    public function get($key, $default = null)
    {
        $parts = explode('.', $key);
        
        if (empty($parts)) {
            return $default;
        }
        
        // الملف
        $file = array_shift($parts);
        
        if (!isset($this->config[$file])) {
            return $default;
        }
        
        $value = $this->config[$file];
        
        // الوصول للقيمة في المصفوفة المتداخلة
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return $default;
            }
            
            $value = $value[$part];
        }
        
        return $value;
    }
    
    /**
     * تعيين قيمة تكوين
     * 
     * @param string $key المفتاح
     * @param mixed $value القيمة
     * @return void
     */
    public function set($key, $value)
    {
        $parts = explode('.', $key);
        
        if (empty($parts)) {
            return;
        }
        
        // الملف
        $file = array_shift($parts);
        
        if (!isset($this->config[$file])) {
            $this->config[$file] = [];
        }
        
        if (empty($parts)) {
            // تعيين القيمة مباشرة للملف
            $this->config[$file] = $value;
            return;
        }
        
        // الوصول للمكان المناسب في المصفوفة المتداخلة
        $current = &$this->config[$file];
        
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                // آخر جزء، تعيين القيمة
                $current[$part] = $value;
            } else {
                // إنشاء المفتاح إذا لم يكن موجودًا
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                
                $current = &$current[$part];
            }
        }
    }
    
    /**
     * التحقق من وجود مفتاح
     * 
     * @param string $key المفتاح
     * @return bool هل المفتاح موجود
     */
    public function has($key)
    {
        $parts = explode('.', $key);
        
        if (empty($parts)) {
            return false;
        }
        
        // الملف
        $file = array_shift($parts);
        
        if (!isset($this->config[$file])) {
            return false;
        }
        
        $value = $this->config[$file];
        
        // الوصول للقيمة في المصفوفة المتداخلة
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return false;
            }
            
            $value = $value[$part];
        }
        
        return true;
    }
    
    /**
     * إعادة تحميل ملف تكوين معين
     * 
     * @param string $file اسم الملف
     * @return bool نجاح العملية
     */
    public function reload($file)
    {
        $configFile = __DIR__ . '/../config/' . $file . '.php';
        
        if (!file_exists($configFile)) {
            return false;
        }
        
        $this->config[$file] = require_once $configFile;
        return true;
    }
    
    /**
     * حفظ التكوين إلى ملف
     * 
     * @param string $file اسم الملف
     * @return bool نجاح العملية
     */
    public function save($file)
    {
        if (!isset($this->config[$file])) {
            return false;
        }
        
        $configFile = __DIR__ . '/../config/' . $file . '.php';
        $content = "<?php\n\nreturn " . $this->varExport($this->config[$file]) . ";\n";
        
        return file_put_contents($configFile, $content) !== false;
    }
    
    /**
     * تصدير متغير بتنسيق PHP
     * 
     * @param mixed $var المتغير
     * @param string $indent بادئة المسافات
     * @return string التمثيل النصي
     */
    private function varExport($var, $indent = '')
    {
        if (is_array($var)) {
            if (empty($var)) {
                return '[]';
            }
            
            $isAssoc = array_keys($var) !== range(0, count($var) - 1);
            $output = $isAssoc ? "[\n" : "[\n";
            
            foreach ($var as $key => $value) {
                if ($isAssoc) {
                    $output .= $indent . '    ' . var_export($key, true) . ' => ' . $this->varExport($value, $indent . '    ') . ",\n";
                } else {
                    $output .= $indent . '    ' . $this->varExport($value, $indent . '    ') . ",\n";
                }
            }
            
            $output .= $indent . ']';
            return $output;
        }
        
        return var_export($var, true);
    }
    
    /**
     * الحصول على جميع التكوينات
     * 
     * @return array التكوينات
     */
    public function all()
    {
        return $this->config;
    }
    
    /**
     * الحصول على تكوين ملف معين
     * 
     * @param string $file اسم الملف
     * @return array|null تكوين الملف
     */
    public function file($file)
    {
        return $this->config[$file] ?? null;
    }
    
    /**
     * إنشاء نسخة احتياطية من ملف تكوين
     * 
     * @param string $file اسم الملف
     * @return bool نجاح العملية
     */
    public function backup($file)
    {
        if (!isset($this->config[$file])) {
            return false;
        }
        
        $configFile = __DIR__ . '/../config/' . $file . '.php';
        $backupFile = __DIR__ . '/../config/backups/' . $file . '_' . date('Y-m-d_H-i-s') . '.php';
        
        // التأكد من وجود مجلد النسخ الاحتياطية
        if (!is_dir(dirname($backupFile))) {
            mkdir(dirname($backupFile), 0755, true);
        }
        
        return copy($configFile, $backupFile);
    }
}