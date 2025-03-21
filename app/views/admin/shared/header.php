<!-- app/views/admin/shared/header.php -->
<header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center">
    <div class="flex items-center">
        <h1 class="text-xl font-bold text-gray-800 hidden md:block">المنصة التعليمية المتكاملة</h1>
    </div>
    
    <div class="flex items-center space-x-4 space-x-reverse">
        <!-- Notifications -->
        <div class="relative">
            <button id="notificationsDropdown" class="text-gray-600 hover:text-gray-800 focus:outline-none">
                <div class="relative">
                    <i class="fas fa-bell text-xl"></i>
                    <?php
                    // Get unread notifications count (would be implemented later)
                    $unreadCount = 3; // Placeholder
                    if ($unreadCount > 0):
                    ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                        <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </button>
            
            <div id="notificationsMenu" class="absolute left-0 mt-2 w-80 bg-white rounded-md shadow-lg py-1 z-10 hidden transform -translate-x-3/4">
                <div class="px-4 py-2 border-b border-gray-100">
                    <div class="flex justify-between items-center">
                        <h3 class="text-base font-medium text-gray-800">الإشعارات</h3>
                        <a href="/admin/notifications" class="text-blue-600 hover:text-blue-800 text-xs">عرض الكل</a>
                    </div>
                </div>
                
                <div class="max-h-60 overflow-y-auto">
                    <!-- Sample notifications -->
                    <a href="#" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100">
                        <div class="flex">
                            <div class="flex-shrink-0 ml-3">
                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-school text-blue-600"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-800">تم إضافة مدرسة جديدة: <strong>مدرسة النور الدولية</strong></p>
                                <p class="text-xs text-gray-500 mt-1">منذ 10 دقائق</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="#" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100">
                        <div class="flex">
                            <div class="flex-shrink-0 ml-3">
                                <div class="h-8 w-8 rounded-full bg-yellow-100 flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-800">اشتراك <strong>مدرسة المستقبل</strong> على وشك الانتهاء</p>
                                <p class="text-xs text-gray-500 mt-1">منذ ساعتين</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="#" class="block px-4 py-3 hover:bg-gray-50">
                        <div class="flex">
                            <div class="flex-shrink-0 ml-3">
                                <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-credit-card text-green-600"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-800">تم تحديث اشتراك <strong>مدرسة الرواد</strong> إلى النوع غير المحدود</p>
                                <p class="text-xs text-gray-500 mt-1">منذ 5 ساعات</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="px-4 py-2 border-t border-gray-100 text-center">
                    <a href="/admin/notifications/mark-all-read" class="text-sm text-blue-600 hover:text-blue-800">تعيين الكل كمقروء</a>
                </div>
            </div>
        </div>
        
        <!-- User Menu -->
        <div class="relative">
            <button id="userDropdown" class="flex items-center focus:outline-none">
                <?php if ($this->auth->user()['profile_picture']): ?>
                    <img src="<?php echo $this->auth->user()['profile_picture']; ?>" alt="Profile" class="h-8 w-8 rounded-full">
                <?php else: ?>
                    <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white">
                        <span class="text-sm font-bold">
                            <?php 
                            $firstName = $this->auth->user()['first_name'] ?? '';
                            $lastName = $this->auth->user()['last_name'] ?? '';
                            echo mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                <span class="ml-2 text-sm text-gray-700 hidden md:block"><?php echo $this->auth->user()['first_name']; ?></span>
                <i class="fas fa-chevron-down ml-1 text-gray-400 text-xs"></i>
            </button>
            
            <div id="userMenu" class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden">
                <a href="/admin/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-user mr-2 text-gray-500"></i>
                    الملف الشخصي
                </a>
                <a href="/admin/settings/account" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-cog mr-2 text-gray-500"></i>
                    الإعدادات
                </a>
                <div class="border-t border-gray-100"></div>
                <a href="https://support.platform.com" target="_blank" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-question-circle mr-2 text-gray-500"></i>
                    مركز المساعدة
                </a>
                <div class="border-t border-gray-100"></div>
                <a href="/logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    تسجيل الخروج
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    // Notifications dropdown
    document.getElementById('notificationsDropdown').addEventListener('click', function() {
        document.getElementById('notificationsMenu').classList.toggle('hidden');
    });
    
    // User dropdown
    document.getElementById('userDropdown').addEventListener('click', function() {
        document.getElementById('userMenu').classList.toggle('hidden');
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        // Notifications dropdown
        const notificationsMenu = document.getElementById('notificationsMenu');
        const notificationsToggle = document.getElementById('notificationsDropdown');
        
        if (notificationsMenu && !notificationsMenu.contains(e.target) && !notificationsToggle.contains(e.target)) {
            notificationsMenu.classList.add('hidden');
        }
        
        // User dropdown
        const userMenu = document.getElementById('userMenu');
        const userToggle = document.getElementById('userDropdown');
        
        if (userMenu && !userMenu.contains(e.target) && !userToggle.contains(e.target)) {
            userMenu.classList.add('hidden');
        }
    });
</script>