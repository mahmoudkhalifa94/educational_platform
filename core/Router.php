<?php
/**
 * core/Router.php
 * فئة المسار
 * تدير توجيه المسارات في النظام
 */
class Router
{
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => []
    ];
    
    private $notFoundHandler = null;
    private $errorHandler = null;
    
    /**
     * إضافة مسار جديد
     * 
     * @param string $method طريقة الطلب (GET, POST, PUT, DELETE)
     * @param string $path المسار
     * @param callable|array $handler المعالج
     * @return void
     */
    public function addRoute($method, $path, $handler)
    {
        $method = strtoupper($method);
        
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }
        
        // تحويل المسار إلى تعبير نمطي
        $pattern = $this->pathToPattern($path);
        
        $this->routes[$method][$pattern] = [
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    /**
     * إضافة مسار GET
     * 
     * @param string $path المسار
     * @param callable|array $handler المعالج
     * @return void
     */
    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * إضافة مسار POST
     * 
     * @param string $path المسار
     * @param callable|array $handler المعالج
     * @return void
     */
    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * إضافة مسار PUT
     * 
     * @param string $path المسار
     * @param callable|array $handler المعالج
     * @return void
     */
    public function put($path, $handler)
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * إضافة مسار DELETE
     * 
     * @param string $path المسار
     * @param callable|array $handler المعالج
     * @return void
     */
    public function delete($path, $handler)
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * إضافة معالج للمسارات غير الموجودة
     * 
     * @param callable|array $handler المعالج
     * @return void
     */
    public function setNotFoundHandler($handler)
    {
        $this->notFoundHandler = $handler;
    }
    
    /**
     * إضافة معالج للأخطاء
     * 
     * @param callable|array $handler المعالج
     * @return void
     */
    public function setErrorHandler($handler)
    {
        $this->errorHandler = $handler;
    }
    
    /**
     * تحويل المسار إلى تعبير نمطي
     * 
     * @param string $path المسار
     * @return string التعبير النمطي
     */
    private function pathToPattern($path)
    {
        // تحويل المعلمات مثل {id} إلى تعبير نمطي
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        
        // إضافة إشارة بداية ونهاية
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';
        
        return $pattern;
    }
    
    /**
     * مطابقة المسار مع نمط
     * 
     * @param string $pattern النمط
     * @param string $path المسار
     * @return array|false المعلمات المستخرجة أو false إذا لم يكن هناك تطابق
     */
    private function matchPath($pattern, $path)
    {
        $matches = [];
        
        if (preg_match($pattern, $path, $matches)) {
            // استخراج المعلمات المسماة فقط
            $params = array_filter($matches, function($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY);
            
            return $params;
        }
        
        return false;
    }
    
    /**
     * معالجة الطلب
     * 
     * @param string|null $method طريقة الطلب
     * @param string|null $path المسار
     * @return void
     */
    public function dispatch($method = null, $path = null)
    {
        // استخدام طريقة ومسار الطلب الحالي إذا لم يتم تحديدهما
        $method = $method ?: ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = $path ?: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // تنظيف المسار
        $path = rtrim($path, '/');
        if (empty($path)) {
            $path = '/';
        }
        
        $method = strtoupper($method);
        
        try {
            // البحث عن مسار مطابق
            if (isset($this->routes[$method])) {
                foreach ($this->routes[$method] as $pattern => $route) {
                    $params = $this->matchPath($pattern, $path);
                    
                    if ($params !== false) {
                        return $this->executeHandler($route['handler'], $params);
                    }
                }
            }
            
            // إذا لم يتم العثور على مسار مطابق
            if ($this->notFoundHandler) {
                return $this->executeHandler($this->notFoundHandler, []);
            }
            
            // رد افتراضي إذا لم يكن هناك معالج للمسارات غير الموجودة
            header('HTTP/1.0 404 Not Found');
            echo '404 Not Found';
            
        } catch (Exception $e) {
            // معالجة الأخطاء
            if ($this->errorHandler) {
                return $this->executeHandler($this->errorHandler, ['exception' => $e]);
            }
            
            // رد افتراضي إذا لم يكن هناك معالج للأخطاء
            header('HTTP/1.0 500 Internal Server Error');
            echo '500 Internal Server Error: ' . $e->getMessage();
        }
    }
    
    /**
     * تنفيذ المعالج
     * 
     * @param callable|array $handler المعالج
     * @param array $params المعلمات
     * @return mixed نتيجة تنفيذ المعالج
     */
    private function executeHandler($handler, $params)
    {
        if (is_callable($handler)) {
            // إذا كان المعالج دالة
            return call_user_func_array($handler, $params);
        } elseif (is_array($handler) && count($handler) === 2) {
            // إذا كان المعالج مصفوفة [ControllerClass, method]
            $controller = $handler[0];
            $method = $handler[1];
            
            if (is_string($controller)) {
                // إنشاء كائن من فئة المتحكم
                $controller = new $controller();
            }
            
            // تنفيذ الدالة في المتحكم مع تمرير المعلمات
            return call_user_func_array([$controller, $method], $params);
        }
        
        throw new Exception('Invalid handler');
    }
    
    /**
     * تحويل إلى مسار معين
     * 
     * @param string $path المسار
     * @param int $statusCode الرمز المستخدم (افتراضياً 302)
     * @return void
     */
    public static function redirect($path, $statusCode = 302)
    {
        header('Location: ' . $path, true, $statusCode);
        exit;
    }
    
    /**
     * توليد مسار عكسي
     * 
     * @param string $path قالب المسار
     * @param array $params المعلمات
     * @return string المسار المولد
     */
    public static function generateUrl($path, $params = [])
    {
        // استبدال المعلمات في المسار
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        
        return $path;
    }
    
    /**
     * إضافة مجموعة مسارات تحت مسار أساسي
     * 
     * @param string $prefix المسار الأساسي
     * @param callable $callback دالة إعداد المسارات
     * @return void
     */
    public function group($prefix, $callback)
    {
        // حفظ المسارات الحالية
        $currentRoutes = $this->routes;
        
        // إنشاء مسارات جديدة
        $this->routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => []
        ];
        
        // تنفيذ دالة إعداد المسارات
        call_user_func($callback, $this);
        
        // إضافة البادئة للمسارات الجديدة
        $newRoutes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => []
        ];
        
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $pattern => $route) {
                $newPath = rtrim($prefix, '/') . '/' . ltrim($route['path'], '/');
                $newPattern = $this->pathToPattern($newPath);
                
                $newRoutes[$method][$newPattern] = [
                    'path' => $newPath,
                    'handler' => $route['handler']
                ];
            }
        }
        
        // دمج المسارات الجديدة مع المسارات الحالية
        foreach ($newRoutes as $method => $routes) {
            $this->routes[$method] = $currentRoutes[$method] + $routes;
        }
    }
    
    /**
     * الحصول على كل المسارات المسجلة
     * 
     * @return array المسارات
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}