<?php
/**
 * core/Session.php
 * فئة الجلسة
 * تدير جلسات المستخدمين
 */
class Session
{
    private $sessionStarted = false;
    
    /**
     * تهيئة الجلسة
     */
    public function __construct()
    {
        $this->start();
    }
    
    /**
     * بدء الجلسة
     * 
     * @return bool نجاح العملية
     */
    public function start()
    {
        if ($this->sessionStarted) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->sessionStarted = true;
            return true;
        }
        
        // تكوين الجلسة
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params(
            $cookieParams["lifetime"],
            $cookieParams["path"],
            $cookieParams["domain"],
            isset($_SERVER['HTTPS']),  // تأمين الكوكي في HTTPS
            true  // httponly flag
        );
        
        // بدء الجلسة
        $this->sessionStarted = session_start();
        
        return $this->sessionStarted;
    }
    
    /**
     * إيقاف الجلسة
     * 
     * @return bool نجاح العملية
     */
    public function close()
    {
        if ($this->sessionStarted) {
            $this->sessionStarted = !session_write_close();
            return !$this->sessionStarted;
        }
        
        return true;
    }
    
    /**
     * تدمير الجلسة
     * 
     * @return bool نجاح العملية
     */
    public function destroy()
    {
        if ($this->sessionStarted) {
            $this->sessionStarted = false;
            
            // مسح مصفوفة الجلسة
            $_SESSION = [];
            
            // مسح كوكي الجلسة
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            // تدمير الجلسة
            return session_destroy();
        }
        
        return true;
    }
    
    /**
     * إعادة توليد معرف الجلسة
     * 
     * @param bool $deleteOldSession حذف الجلسة القديمة
     * @return bool نجاح العملية
     */
    public function regenerateId($deleteOldSession = true)
    {
        if ($this->sessionStarted) {
            return session_regenerate_id($deleteOldSession);
        }
        
        return false;
    }
    
    /**
     * وضع قيمة في الجلسة
     * 
     * @param string $key المفتاح
     * @param mixed $value القيمة
     * @return void
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }
    
    /**
     * الحصول على قيمة من الجلسة
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function get($key, $default = null)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * التحقق من وجود مفتاح في الجلسة
     * 
     * @param string $key المفتاح
     * @return bool هل المفتاح موجود
     */
    public function has($key)
    {
        return isset($_SESSION[$key]);
    }
    
    /**
     * حذف مفتاح من الجلسة
     * 
     * @param string $key المفتاح
     * @return void
     */
    public function remove($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * الحصول على جميع بيانات الجلسة
     * 
     * @return array بيانات الجلسة
     */
    public function all()
    {
        return $_SESSION;
    }
    
    /**
     * وضع قيمة مؤقتة في الجلسة (flash message)
     * 
     * @param string $key المفتاح
     * @param mixed $value القيمة
     * @return void
     */
    public function flash($key, $value)
    {
        // وضع القيمة في الجلسة
        $this->set($key, $value);
        
        // تتبع الرسائل المؤقتة
        $flash = $this->get('_flash', []);
        $flash[] = $key;
        $this->set('_flash', $flash);
    }
    
    /**
     * الحصول على قيمة مؤقتة من الجلسة
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function getFlash($key, $default = null)
    {
        // الحصول على القيمة من الجلسة
        if ($this->has($key)) {
            $value = $this->get($key);
            
            // حذف المفتاح من الجلسة
            $this->remove($key);
            
            // تحديث قائمة الرسائل المؤقتة
            $flash = $this->get('_flash', []);
            $flash = array_filter($flash, function($item) use ($key) {
                return $item !== $key;
            });
            $this->set('_flash', $flash);
            
            return $value;
        }
        
        return $default;
    }
    
    /**
     * حذف جميع القيم المؤقتة من الجلسة
     * 
     * @return void
     */
    public function clearFlash()
    {
        $flash = $this->get('_flash', []);
        
        foreach ($flash as $key) {
            $this->remove($key);
        }
        
        $this->remove('_flash');
    }
    
    /**
     * تعيين رسالة تنبيه مؤقتة في الجلسة
     * 
     * @param string $type نوع التنبيه
     * @param string $message رسالة التنبيه
     * @return void
     */
    public function setFlashAlert($type, $message)
    {
        $this->flash('alert', [
            'type' => $type,
            'message' => $message
        ]);
    }
    
    /**
     * الحصول على رسالة تنبيه مؤقتة من الجلسة
     * 
     * @return array|null رسالة التنبيه
     */
    public function getFlashAlert()
    {
        return $this->getFlash('alert');
    }
    
    /**
     * وضع المتغير في جلسة أمنة
     * تستخدم للبيانات الحساسة مثل معرّف المستخدم
     * 
     * @param string $key المفتاح
     * @param mixed $value القيمة
     * @return void
     */
    public function setSecure($key, $value)
    {
        $this->set('_secure_' . $key, $value);
    }
    
    /**
     * الحصول على قيمة آمنة من الجلسة
     * 
     * @param string $key المفتاح
     * @param mixed $default القيمة الافتراضية
     * @return mixed القيمة
     */
    public function getSecure($key, $default = null)
    {
        return $this->get('_secure_' . $key, $default);
    }
    
    /**
     * التحقق من وجود القيمة الآمنة في الجلسة
     * 
     * @param string $key المفتاح
     * @return bool هل القيمة موجودة
     */
    public function hasSecure($key)
    {
        return $this->has('_secure_' . $key);
    }
    
    /**
     * حذف متغير آمن من الجلسة
     * 
     * @param string $key المفتاح
     * @return void
     */
    public function removeSecure($key)
    {
        $this->remove('_secure_' . $key);
    }
    
    /**
     * تأمين الجلسة من سرقة الجلسة
     * 
     * @param string $userAgent متصفح المستخدم
     * @param string $ipAddress عنوان IP للمستخدم
     * @return void
     */
    public function secure($userAgent, $ipAddress)
    {
        // تخزين البصمة الأمنية
        $fingerprint = $this->generateFingerprint($userAgent, $ipAddress);
        $this->setSecure('fingerprint', $fingerprint);
    }
    
    /**
     * التحقق من أمان الجلسة
     * 
     * @param string $userAgent متصفح المستخدم
     * @param string $ipAddress عنوان IP للمستخدم
     * @return bool هل الجلسة آمنة
     */
    public function isSecure($userAgent, $ipAddress)
    {
        if (!$this->hasSecure('fingerprint')) {
            return false;
        }
        
        $storedFingerprint = $this->getSecure('fingerprint');
        $currentFingerprint = $this->generateFingerprint($userAgent, $ipAddress);
        
        return $storedFingerprint === $currentFingerprint;
    }
    
    /**
     * إنشاء بصمة أمنية للجلسة
     * 
     * @param string $userAgent متصفح المستخدم
     * @param string $ipAddress عنوان IP للمستخدم
     * @return string البصمة
     */
    private function generateFingerprint($userAgent, $ipAddress)
    {
        return hash('sha256', $userAgent . $ipAddress . session_id());
    }
    
    /**
     * تعيين معلومات المستخدم المسجل
     * 
     * @param array $user معلومات المستخدم
     * @return void
     */
    public function setUser($user)
    {
        $this->setSecure('user_id', $user['id']);
        $this->setSecure('user_role', $user['role_id']);
        $this->setSecure('user_school', $user['school_id'] ?? null);
        $this->setSecure('user_data', $user);
    }
    
    /**
     * الحصول على معلومات المستخدم المسجل
     * 
     * @return array|null معلومات المستخدم
     */
    public function getUser()
    {
        return $this->getSecure('user_data');
    }
    
    /**
     * الحصول على معرّف المستخدم المسجل
     * 
     * @return int|null معرّف المستخدم
     */
    public function getUserId()
    {
        return $this->getSecure('user_id');
    }
    
    /**
     * الحصول على دور المستخدم المسجل
     * 
     * @return int|null دور المستخدم
     */
    public function getUserRole()
    {
        return $this->getSecure('user_role');
    }
    
    /**
     * الحصول على معرّف مدرسة المستخدم المسجل
     * 
     * @return int|null معرّف المدرسة
     */
    public function getUserSchool()
    {
        return $this->getSecure('user_school');
    }
    
    /**
     * التحقق مما إذا كان المستخدم مسجل الدخول
     * 
     * @return bool هل المستخدم مسجل الدخول
     */
    public function isLoggedIn()
    {
        return $this->hasSecure('user_id');
    }
    
    /**
     * تسجيل خروج المستخدم
     * 
     * @return void
     */
    public function logout()
    {
        $this->removeSecure('user_id');
        $this->removeSecure('user_role');
        $this->removeSecure('user_school');
        $this->removeSecure('user_data');
        $this->regenerateId();
    }
}