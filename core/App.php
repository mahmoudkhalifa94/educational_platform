<?php
/**
 * core/App.php
 * فئة التطبيق
 * النقطة المركزية للتطبيق
 */
class App
{
    private static $instance = null;
    private $router;
    private $request;
    private $response;
    private $session;
    private $auth;
    private $db;
    private $config = [];
    
    /**
     * الحصول على التطبيق (Singleton)
     * 
     * @return App
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * تهيئة التطبيق
     */
    private function __construct()
    {
        // تحميل التكوين
        $this->loadConfig();
        
        // تهيئة المكونات
        $this->initializeComponents();
        
        // تسجيل معالجات الأخطاء
        $this->registerErrorHandlers();
    }
    
    /**
     * منع النسخ
     */
    private function __clone() {}
    
    /**
     * تحميل ملفات التكوين
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
     * تهيئة مكونات التطبيق
     */
    private function initializeComponents()
    {
        // تهيئة قاعدة البيانات
        $this->db = Database::getInstance();
        
        // تهيئة الطلب
        $this->request = new Request();
        
        // تهيئة الاستجابة
        $this->response = new Response();
        
        // تهيئة الجلسة
        $this->session = new Session();
        
        // تهيئة المصادقة
        $this->auth = Auth::getInstance();
        
        // تهيئة الموجه
        $this->router = new Router();
    }
    
    /**
     * تسجيل معالجات الأخطاء
     */
    private function registerErrorHandlers()
    {
        // تعيين معالج للأخطاء
        set_error_handler([$this, 'handleError']);
        
        // تعيين معالج للاستثناءات
        set_exception_handler([$this, 'handleException']);
    }
    
    /**
     * معالجة الأخطاء
     * 
     * @param int $level مستوى الخطأ
     * @param string $message رسالة الخطأ
     * @param string $file الملف الذي حدث فيه الخطأ
     * @param int $line رقم السطر
     * @return bool
     */
    public function handleError($level, $message, $file, $line)
    {
        if (error_reporting() & $level) {
            $error = [
                'level' => $level,
                'message' => $message,
                'file' => $file,
                'line' => $line
            ];
            
            // تسجيل الخطأ
            $this->logError($error);
            
            // عرض الخطأ في وضع التطوير
            if ($this->config['app']['debug']) {
                $this->displayError($error);
            }
        }
        
        // إرجاع false للسماح لمعالج الأخطاء الافتراضي بالتنفيذ
        return false;
    }
    
    /**
     * معالجة الاستثناءات
     * 
     * @param \Throwable $exception الاستثناء
     * @return void
     */
    public function handleException($exception)
    {
        $error = [
            'level' => E_ERROR,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        // تسجيل الخطأ
        $this->logError($error);
        
        // عرض الخطأ في وضع التطوير
        if ($this->config['app']['debug']) {
            $this->displayError($error);
        } else {
            // في وضع الإنتاج، عرض صفحة خطأ عامة
            $this->response->setStatusCode(500);
            $this->response->setContent($this->renderErrorPage('500'));
            $this->response->send();
        }
    }
    
    /**
     * تسجيل الخطأ
     * 
     * @param array $error معلومات الخطأ
     * @return void
     */
    private function logError($error)
    {
        $logPath = __DIR__ . '/../logs/';
        
        // التأكد من وجود مجلد السجلات
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
        
        $logFile = $logPath . 'error-' . date('Y-m-d') . '.log';
        
        // تنسيق رسالة السجل
        $message = sprintf(
            "[%s] %s: %s in %s on line %d\n",
            date('Y-m-d H:i:s'),
            $this->getErrorLevelName($error['level']),
            $error['message'],
            $error['file'],
            $error['line']
        );
        
        // إضافة التتبع إذا كان موجودًا
        if (isset($error['trace'])) {
            $message .= "Stack Trace:\n" . $error['trace'] . "\n";
        }
        
        // كتابة السجل
        file_put_contents($logFile, $message, FILE_APPEND);
    }
    
    /**
     * الحصول على اسم مستوى الخطأ
     * 
     * @param int $level مستوى الخطأ
     * @return string اسم المستوى
     */
    private function getErrorLevelName($level)
    {
        $levels = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $levels[$level] ?? 'Unknown';
    }
    
    /**
     * عرض الخطأ في وضع التطوير
     * 
     * @param array $error معلومات الخطأ
     * @return void
     */
    private function displayError($error)
    {
        $html = '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;">';
        $html .= '<h2 style="margin-top: 0;">Error: ' . $this->getErrorLevelName($error['level']) . '</h2>';
        $html .= '<p><strong>Message:</strong> ' . htmlspecialchars($error['message']) . '</p>';
        $html .= '<p><strong>File:</strong> ' . htmlspecialchars($error['file']) . '</p>';
        $html .= '<p><strong>Line:</strong> ' . $error['line'] . '</p>';
        
        if (isset($error['trace'])) {
            $html .= '<h3>Stack Trace:</h3>';
            $html .= '<pre style="background: #f1f1f1; padding: 10px; overflow: auto; font-size: 12px;">' . htmlspecialchars($error['trace']) . '</pre>';
        }
        
        $html .= '</div>';
        
        echo $html;
    }
    
    /**
     * عرض صفحة خطأ معينة
     * 
     * @param string $code رمز الخطأ
     * @return string محتوى صفحة الخطأ
     */
    private function renderErrorPage($code)
    {
        $errorPage = __DIR__ . '/../app/views/errors/' . $code . '.php';
        
        if (file_exists($errorPage)) {
            ob_start();
            include $errorPage;
            return ob_get_clean();
        }
        
        // صفحة خطأ افتراضية
        return '<h1>Error ' . $code . '</h1><p>An error occurred. Please try again later.</p>';
    }
    
    /**
     * تشغيل التطبيق
     * 
     * @return void
     */
    public function run()
    {
        try {
            // تهيئة النظام
            $this->bootstrap();
            
            // تسجيل المسارات
            $this->registerRoutes();
            
            // معالجة الطلب
            $this->router->dispatch();
            
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }
    
    /**
     * تهيئة النظام
     * 
     * @return void
     */
    private function bootstrap()
    {
        // التحقق من المجال الفرعي
        $this->checkSubdomain();
        
        // تحميل الوظائف المساعدة
        $this->loadHelpers();
        
        // تحميل الترجمات
        $this->loadTranslations();
    }
    
    /**
     * التحقق من المجال الفرعي للمدرسة
     * 
     * @return void
     */
    private function checkSubdomain()
    {
        $subdomain = $this->request->getSubdomain();
        
        if (!empty($subdomain) && $subdomain !== 'www') {
            // البحث عن المدرسة بالمجال الفرعي
            $schoolModel = new School();
            $school = $schoolModel->findBySubdomain($subdomain);
            
            if ($school) {
                // تعيين المدرسة الحالية في الجلسة
                $this->session->set('current_school_id', $school['id']);
                $this->session->set('current_school_name', $school['name']);
                $this->session->set('current_school_theme', $school['theme'] ?? 'default');
            } else {
                // إعادة التوجيه للصفحة الرئيسية
                header('Location: http://' . $_SERVER['HTTP_HOST']);
                exit;
            }
        }
    }
    
    /**
     * تحميل الوظائف المساعدة
     * 
     * @return void
     */
    private function loadHelpers()
    {
        $helperFiles = glob(__DIR__ . '/../app/helpers/*.php');
        
        foreach ($helperFiles as $file) {
            require_once $file;
        }
    }
    
    /**
     * تحميل ملفات الترجمة
     * 
     * @return void
     */
    private function loadTranslations()
    {
        // يمكن تنفيذ نظام ترجمة هنا
    }
    
    /**
     * تسجيل مسارات التطبيق
     * 
     * @return void
     */
    private function registerRoutes()
    {
        require_once __DIR__ . '/../config/routes.php';
    }
    
    /**
     * الحصول على الموجه
     * 
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }
    
    /**
     * الحصول على الطلب
     * 
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }
    
    /**
     * الحصول على الاستجابة
     * 
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    /**
     * الحصول على الجلسة
     * 
     * @return Session
     */
    public function getSession()
    {
        return $this->session;
    }
    
    /**
     * الحصول على المصادقة
     * 
     * @return Auth
     */
    public function getAuth()
    {
        return $this->auth;
    }
    
    /**
     * الحصول على قاعدة البيانات
     * 
     * @return Database
     */
    public function getDB()
    {
        return $this->db;
    }
    
    /**
     * الحصول على تكوين معين
     * 
     * @param string $key المفتاح في صيغة file.key
     * @param mixed $default قيمة افتراضية
     * @return mixed قيمة التكوين
     */
    public function getConfig($key, $default = null)
    {
        $parts = explode('.', $key);
        
        if (count($parts) !== 2) {
            return $default;
        }
        
        $file = $parts[0];
        $configKey = $parts[1];
        
        return $this->config[$file][$configKey] ?? $default;
    }
}