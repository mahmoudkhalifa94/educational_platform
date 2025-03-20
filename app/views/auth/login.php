<!-- app/views/auth/login.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - المنصة التعليمية</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="/assets/css/main.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-cairo">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden max-w-md w-full p-8">
            <div class="text-center mb-8">
                <img src="/assets/images/logo.svg" alt="المنصة التعليمية" class="h-20 mx-auto mb-2">
                <h2 class="text-2xl font-bold text-gray-800">تسجيل الدخول</h2>
                <p class="text-gray-600">أدخل بيانات الدخول للوصول إلى حسابك</p>
            </div>
            
            <?php if (isset($expired) && $expired): ?>
            <div class="bg-yellow-100 border-r-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded" role="alert">
                <p class="font-bold">انتهت صلاحية الجلسة</p>
                <p>تم تسجيل خروجك تلقائيًا بسبب عدم النشاط. يرجى تسجيل الدخول مرة أخرى.</p>
            </div>
            <?php endif; ?>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <form action="/login" method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" required 
                            class="w-full pr-10 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="أدخل بريدك الإلكتروني">
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between mb-1">
                        <label for="password" class="block text-sm font-medium text-gray-700">كلمة المرور</label>
                        <a href="/forgot-password" class="text-sm text-blue-600 hover:text-blue-800">نسيت كلمة المرور؟</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required 
                            class="w-full pr-10 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="أدخل كلمة المرور">
                        <button type="button" id="togglePassword" 
                            class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="mr-2 block text-sm text-gray-700">تذكرني</label>
                </div>
                
                <div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                        تسجيل الدخول
                    </button>
                </div>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    واجهت مشكلة في تسجيل الدخول؟
                    <a href="#" class="text-blue-600 hover:text-blue-800 font-medium">تواصل مع الدعم</a>
                </p>
            </div>
            
            <div class="mt-8 border-t border-gray-200 pt-4 text-center">
                <p class="text-xs text-gray-500">
                    &copy; <?php echo date('Y'); ?> المنصة التعليمية المتكاملة. جميع الحقوق محفوظة.
                </p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });