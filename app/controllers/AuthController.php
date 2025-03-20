<?php
/**
 * app/controllers/AuthController.php
 * متحكم المصادقة
 * يدير عمليات تسجيل الدخول والخروج والتسجيل
 */
class AuthController extends Controller
{
    private $userModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }
    
    /**
     * عرض صفحة تسجيل الدخول
     */
    public function showLogin()
    {
        // التحقق مما إذا كان المستخدم مسجل الدخول بالفعل
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->getRedirectPathByRole());
        }
        
        // التحقق من وجود رسالة انتهاء الجلسة
        $expired = isset($_GET['expired']) && $_GET['expired'] == 1;
        
        // عرض صفحة تسجيل الدخول
        echo $this->render('auth/login', [
            'expired' => $expired,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة عملية تسجيل الدخول
     */
    public function login()
    {
        // التحقق مما إذا كان طلب POST
        if ($this->request->method() !== 'POST') {
            $this->redirect('/login');
        }
        
        // استخراج بيانات الطلب
        $email = $this->request->post('email');
        $password = $this->request->post('password');
        $remember = $this->request->post('remember') ? true : false;
        
        // التحقق من صحة البيانات
        $errors = $this->validate([
            'email' => $email,
            'password' => $password
        ], [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من بيانات تسجيل الدخول.');
            $this->redirect('/login');
        }
        
        // محاولة تسجيل الدخول
        if ($this->auth->login($email, $password, $remember)) {
            // نجاح تسجيل الدخول
            
            // التحقق من وجود مسار إعادة توجيه مخزن
            $redirectPath = isset($_SESSION['redirect_after_login']) 
                ? $_SESSION['redirect_after_login'] 
                : $this->getRedirectPathByRole();
            
            // مسح مسار إعادة التوجيه من الجلسة
            unset($_SESSION['redirect_after_login']);
            
            $this->redirect($redirectPath);
        } else {
            // فشل تسجيل الدخول
            $this->setFlash('error', 'البريد الإلكتروني أو كلمة المرور غير صحيحة.');
            $this->redirect('/login');
        }
    }
    
    /**
     * تسجيل الخروج
     */
    public function logout()
    {
        $this->auth->logout();
        $this->redirect('/login');
    }
    
    /**
     * عرض صفحة نسيان كلمة المرور
     */
    public function showForgotPassword()
    {
        echo $this->render('auth/forgot-password', [
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة طلب إعادة تعيين كلمة المرور
     */
    public function forgotPassword()
    {
        // التحقق مما إذا كان طلب POST
        if ($this->request->method() !== 'POST') {
            $this->redirect('/forgot-password');
        }
        
        // استخراج بيانات الطلب
        $email = $this->request->post('email');
        
        // التحقق من صحة البيانات
        $errors = $this->validate([
            'email' => $email
        ], [
            'email' => 'required|email'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى إدخال بريد إلكتروني صحيح.');
            $this->redirect('/forgot-password');
        }
        
        // التحقق من وجود المستخدم
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            // لا نكشف ما إذا كان البريد موجودًا أم لا، لأسباب أمنية
            $this->setFlash('success', 'تم إرسال تعليمات إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.');
            $this->redirect('/forgot-password');
            return;
        }
        
        // إنشاء رمز إعادة التعيين
        $token = $this->auth->resetPassword($email);
        
        if (!$token) {
            $this->setFlash('error', 'حدث خطأ أثناء إنشاء رمز إعادة التعيين. يرجى المحاولة مرة أخرى لاحقًا.');
            $this->redirect('/forgot-password');
            return;
        }
        
        // إرسال رابط إعادة التعيين عبر البريد الإلكتروني
        $resetLink = "http://{$_SERVER['HTTP_HOST']}/reset-password?token={$token}";
        
        // هنا يتم إرسال البريد الإلكتروني (سنقوم بتنفيذ هذا لاحقًا)
        // sendResetEmail($email, $resetLink);
        
        $this->setFlash('success', 'تم إرسال تعليمات إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.');
        $this->redirect('/forgot-password');
    }
    
    /**
     * عرض صفحة إعادة تعيين كلمة المرور
     */
    public function showResetPassword()
    {
        $token = $this->request->get('token');
        
        if (empty($token)) {
            $this->redirect('/login');
        }
        
        // التحقق من صحة الرمز
        $reset = $this->auth->verifyResetToken($token);
        
        if (!$reset) {
            $this->setFlash('error', 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية.');
            $this->redirect('/login');
        }
        
        echo $this->render('auth/reset-password', [
            'token' => $token,
            'email' => $reset['email'],
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إعادة تعيين كلمة المرور
     */
    public function resetPassword()
    {
        // التحقق مما إذا كان طلب POST
        if ($this->request->method() !== 'POST') {
            $this->redirect('/login');
        }
        
        // استخراج بيانات الطلب
        $token = $this->request->post('token');
        $password = $this->request->post('password');
        $passwordConfirm = $this->request->post('password_confirm');
        
        // التحقق من صحة البيانات
        $errors = $this->validate([
            'password' => $password,
            'password_confirm' => $passwordConfirm
        ], [
            'password' => 'required|min:8',
            'password_confirm' => 'required|matches:password'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من كلمة المرور الجديدة.');
            $this->redirect("/reset-password?token={$token}");
        }
        
        // إعادة تعيين كلمة المرور
        if ($this->auth->completeReset($token, $password)) {
            $this->setFlash('success', 'تم إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.');
            $this->redirect('/login');
        } else {
            $this->setFlash('error', 'حدث خطأ أثناء إعادة تعيين كلمة المرور. يرجى المحاولة مرة أخرى.');
            $this->redirect("/reset-password?token={$token}");
        }
    }
    
    /**
     * تحديد مسار إعادة التوجيه بناءً على دور المستخدم
     */
    private function getRedirectPathByRole()
    {
        $user = $this->auth->user();
        
        if (!$user) {
            return '/login';
        }
        
        switch ($user['role_name']) {
            case 'super_admin':
                return '/admin/dashboard';
            case 'school_admin':
                return '/school/dashboard';
            case 'teacher':
                return '/teacher/dashboard';
            case 'parent':
                return '/parent/dashboard';
            case 'student':
                return '/student/dashboard';
            default:
                return '/login';
        }
    }
    
    /**
     * صفحة الوصول المحظور
     */
    public function forbidden()
    {
        echo $this->render('errors/403', [
            'message' => 'ليس لديك صلاحية للوصول إلى هذه الصفحة.'
        ]);
    }
}