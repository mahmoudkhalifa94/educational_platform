<!-- app/views/admin/schools/create.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مدرسة جديدة - المنصة التعليمية</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-cairo">
    <!-- Header -->
    <?php include_once 'app/views/admin/shared/header.php'; ?>
    
    <div class="flex">
        <!-- Sidebar -->
        <?php include_once 'app/views/admin/shared/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">إضافة مدرسة جديدة</h1>
                <a href="/admin/schools" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md inline-flex items-center">
                    <i class="fas fa-arrow-right ml-2"></i>
                    العودة
                </a>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <div class="bg-white rounded-md shadow-sm overflow-hidden p-6">
                <form action="/admin/schools/store" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Multi-step form -->
                    <div class="steps">
                        <!-- Step 1: School Information -->
                        <div class="step" id="step1">
                            <h2 class="text-lg font-bold text-gray-800 mb-4">معلومات المدرسة</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">اسم المدرسة <span class="text-red-600">*</span></label>
                                    <input type="text" id="name" name="name" required 
                                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="أدخل اسم المدرسة">
                                </div>
                                
                                <div>
                                    <label for="subdomain" class="block text-sm font-medium text-gray-700 mb-1">النطاق الفرعي <span class="text-red-600">*</span></label>
                                    <div class="flex items-center">
                                        <input type="text" id="subdomain" name="subdomain" required 
                                               class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               placeholder="النطاق-الفرعي">
                                        <span class="mr-2 text-gray-600">.platform.com</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">يمكن استخدام الأحرف الإنجليزية الصغيرة والأرقام والشرطات فقط</p>
                                </div>
                                
                                <div>
                                    <label for="subscription_type" class="block text-sm font-medium text-gray-700 mb-1">نوع الاشتراك <span class="text-red-600">*</span></label>
                                    <select id="subscription_type" name="subscription_type" required 
                                            class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="trial">تجريبي (3 أشهر، 50 طالب)</option>
                                        <option value="limited">محدود (سنة، 500 طالب)</option>
                                        <option value="unlimited">غير محدود (سنة، عدد غير محدود من الطلاب)</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="logo" class="block text-sm font-medium text-gray-700 mb-1">شعار المدرسة</label>
                                    <input type="file" id="logo" name="logo" 
                                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           accept="image/png, image/jpeg, image/svg+xml">
                                    <p class="text-xs text-gray-500 mt-1">الحد الأقصى: 2 ميجابايت. الأنواع المدعومة: JPG، PNG، SVG</p>
                                </div>
                            </div>
                            
                            <div class="mt-6 text-center">
                                <button type="button" class="next-step bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md">
                                    التالي: معلومات مدير المدرسة
                                    <i class="fas fa-arrow-left mr-2"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 2: School Admin Information -->
                        <div class="step hidden" id="step2">
                            <h2 class="text-lg font-bold text-gray-800 mb-4">معلومات مدير المدرسة</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="admin_first_name" class="block text-sm font-medium text-gray-700 mb-1">الاسم الأول <span class="text-red-600">*</span></label>
                                    <input type="text" id="admin_first_name" name="admin_first_name" required 
                                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="الاسم الأول">
                                </div>
                                
                                <div>
                                    <label for="admin_last_name" class="block text-sm font-medium text-gray-700 mb-1">الاسم الأخير <span class="text-red-600">*</span></label>
                                    <input type="text" id="admin_last_name" name="admin_last_name" required 
                                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="الاسم الأخير">
                                </div>
                                
                                <div>
                                    <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-600">*</span></label>
                                    <input type="email" id="admin_email" name="admin_email" required 
                                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="البريد الإلكتروني">
                                </div>
                                
                                <div>
                                    <label for="admin_phone" class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                    <input type="tel" id="admin_phone" name="admin_phone" 
                                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="رقم الهاتف">
                                </div>
                                
                                <div>
                                    <label for="admin_username" class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم <span class="text-red-600">*</span></label>
                                    <input type="text" id="admin_username" name="admin_username" required 
                                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="اسم المستخدم">
                                </div>
                                
                                <div>
                                    <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور <span class="text-red-600">*</span></label>
                                    <div class="relative">
                                        <input type="password" id="admin_password" name="admin_password" required 
                                               class="w-full border border-gray-300 rounded-md p-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               placeholder="كلمة المرور">
                                        <button type="button" class="toggle-password absolute inset-y-0 left-0 pl-3 flex items-center focus:outline-none">
                                            <i class="fas fa-eye text-gray-400"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">يجب أن تحتوي على الأقل 8 أحرف</p>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-between">
                                <button type="button" class="prev-step bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-6 rounded-md">
                                    <i class="fas fa-arrow-right ml-2"></i>
                                    السابق
                                </button>
                                
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-md">
                                    <i class="fas fa-check ml-2"></i>
                                    إنشاء المدرسة
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/admin/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        // Multi-step form navigation
        $('.next-step').on('click', function() {
            // Validate current step
            const currentStep = $(this).closest('.step');
            const inputs = currentStep.find('input[required], select[required]');
            let isValid = true;
            
            inputs.each(function() {
                if (!$(this).val()) {
                    $(this).addClass('border-red-500');
                    isValid = false;
                } else {
                    $(this).removeClass('border-red-500');
                }
            });
            
            if (!isValid) {
                alert('يرجى ملء جميع الحقول المطلوبة');
                return;
            }
            
            // Move to next step
            currentStep.addClass('hidden');
            currentStep.next('.step').removeClass('hidden');
        });
        
        $('.prev-step').on('click', function() {
            // Move to previous step
            const currentStep = $(this).closest('.step');
            currentStep.addClass('hidden');
            currentStep.prev('.step').removeClass('hidden');
        });
        
        // Toggle password visibility
        $('.toggle-password').on('click', function() {
            const passwordInput = $(this).siblings('input');
            const icon = $(this).find('i');
            
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
        
        // Format subdomain input
        $('#subdomain').on('input', function() {
            // Replace spaces and special characters with hyphens
            let value = $(this).val().toLowerCase();
            value = value.replace(/[^a-z0-9-]/g, '-');
            
            // Replace multiple hyphens with a single hyphen
            value = value.replace(/-+/g, '-');
            
            // Update the input value
            $(this).val(value);
        });
        
        // Auto-generate username from email
        $('#admin_email').on('blur', function() {
            const email = $(this).val();
            const username = email.split('@')[0];
            
            if (!$('#admin_username').val()) {
                $('#admin_username').val(username);
            }
        });
    </script>
</body>
</html>