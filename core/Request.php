<?php
/**
 * core/Request.php
 * فئة الطلب
 * تدير بيانات طلب HTTP
 */
class Request
{
    private $params = [];
    private $query = [];
    private $body = [];
    private $files = [];
    private $cookies = [];
    private $headers = [];
    private $method = '';
    private $path = '';
    
    /**
     * تهيئة الطلب
     */
    public function __construct()
    {
        $this->initializeFromGlobals();
    }
    
    /**
     * تهيئة البيانات من المتغيرات العامة
     * 
     * @return void
     */
    private function initializeFromGlobals()
    {
        // استخراج المسار
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // استخراج طريقة الطلب
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // معالجة طريقة PUT/DELETE المحاكاة
        if ($this->method === 'POST' && isset($_POST['_method'])) {
            $this->method = strtoupper($_POST['_method']);
        }
        
        // استخراج معلمات الاستعلام
        $this->query = $_GET ?? [];
        
        // استخراج بيانات الجسم
        $this->body = $_POST ?? [];
        
        // للطلبات من نوع PUT/DELETE
        if (in_array($this->method, ['PUT', 'DELETE']) && empty($this->body)) {
            parse_str(file_get_contents('php://input'), $this->body);
        }
        
        // للطلبات من نوع JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $jsonBody = json_decode(file_get_contents('php://input'), true);
            if ($jsonBody) {
                $this->body = array_merge($this->body, $jsonBody);
            }
        }
        
        // استخراج الملفات
        $this->files = $_FILES ?? [];
        
        // استخراج ملفات تعريف الارتباط
        $this->cookies = $_COOKIE ?? [];
        
        // استخراج الترويسات
        $this->headers = $this->getRequestHeaders();
    }
    
    /**
     * استخراج ترويسات الطلب
     * 
     * @return array الترويسات
     */
    private function getRequestHeaders()
    {
        $headers = [];
        
        // استخدام getallheaders() إذا كانت متاحة
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
            return $headers;
        }
        
        // الاستخراج اليدوي للترويسات
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[strtolower($name)] = $value;
            } elseif ($name === 'CONTENT_TYPE' || $name === 'CONTENT_LENGTH') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[strtolower($name)] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * الحصول على طريقة الطلب
     * 
     * @return string طريقة الطلب
     */
    public function method()
    {
        return $this->method;
    }
    
    /**
     * التحقق من طريقة الطلب
     * 
     * @param string $method الطريقة المراد التحقق منها
     * @return bool هل الطريقة مطابقة؟
     */
    public function isMethod($method)
    {
        return strtoupper($this->method) === strtoupper($method);
    }
    
    /**
     * الحصول على مسار الطلب
     * 
     * @return string المسار
     */
    public function getPath()
    {
        return $this->path;
    }
    
    /**
     * الحصول على قيمة من معلمات الاستعلام
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function get($key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }
    
    /**
     * الحصول على قيمة من بيانات الجسم
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->body;
        }
        
        return $this->body[$key] ?? $default;
    }
    
    /**
     * الحصول على قيمة من معلمات الاستعلام أو بيانات الجسم
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function input($key, $default = null)
    {
        return $this->post($key) ?? $this->get($key) ?? $default;
    }
    
    /**
     * الحصول على جميع معلمات الاستعلام
     * 
     * @return array معلمات الاستعلام
     */
    public function query()
    {
        return $this->query;
    }
    
    /**
     * الحصول على جميع بيانات الجسم
     * 
     * @return array بيانات الجسم
     */
    public function all()
    {
        return array_merge($this->query, $this->body);
    }
    
    /**
     * الحصول على قيمة ملف تعريف ارتباط
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function cookie($key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }
    
    /**
     * الحصول على ترويسة
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function header($key, $default = null)
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }
    
    /**
     * الحصول على ملف
     * 
     * @param string $key المفتاح
     * @return array|null الملف
     */
    public function file($key)
    {
        return $this->files[$key] ?? null;
    }
    
    /**
     * التحقق من وجود الملف
     * 
     * @param string $key المفتاح
     * @return bool هل يوجد الملف؟
     */
    public function hasFile($key)
    {
        return isset($this->files[$key]) && 
               $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }
    
    /**
     * الحصول على حقول محددة من البيانات
     * 
     * @param array $keys المفاتيح
     * @return array البيانات المختارة
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $results = [];
        
        $data = $this->all();
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $results[$key] = $data[$key];
            }
        }
        
        return $results;
    }
    
    /**
     * الحصول على جميع البيانات باستثناء حقول محددة
     * 
     * @param array $keys المفاتيح المستثناة
     * @return array البيانات المفلترة
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $results = $this->all();
        
        foreach ($keys as $key) {
            unset($results[$key]);
        }
        
        return $results;
    }
    
    /**
     * التحقق من وجود حقل
     * 
     * @param string $key المفتاح
     * @return bool هل يوجد الحقل؟
     */
    public function has($key)
    {
        $data = $this->all();
        return isset($data[$key]);
    }
    
    /**
     * التحقق مما إذا كان الطلب Ajax
     * 
     * @return bool هل الطلب Ajax؟
     */
    public function isAjax()
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }
    
    /**
     * التحقق مما إذا كان الطلب Secure (HTTPS)
     * 
     * @return bool هل الطلب آمن؟
     */
    public function isSecure()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               $_SERVER['SERVER_PORT'] == 443;
    }
    
    /**
     * الحصول على عنوان IP للزائر
     * 
     * @return string عنوان IP
     */
    public function ip()
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * الحصول على User Agent
     * 
     * @return string User Agent
     */
    public function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * الحصول على URL الكامل للطلب
     * 
     * @return string URL الكامل
     */
    public function fullUrl()
    {
        $protocol = $this->isSecure() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $protocol . $host . $uri;
    }
    
    /**
     * الحصول على المجال الفرعي
     * 
     * @return string المجال الفرعي
     */
    public function getSubdomain()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // استخراج المجال الفرعي
        $parts = explode('.', $host);
        
        // التحقق من أن الاسم يحتوي على مجال فرعي
        if (count($parts) < 3) {
            return '';
        }
        
        // إذا كان المجال يحتوي على بورت، نتجاهله
        if (strpos($parts[count($parts) - 1], ':') !== false) {
            $portParts = explode(':', $parts[count($parts) - 1]);
            $parts[count($parts) - 1] = $portParts[0];
        }
        
        // المجال الفرعي هو أول جزء
        return $parts[0];
    }
}