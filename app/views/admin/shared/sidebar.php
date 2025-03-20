<!-- app/views/admin/shared/sidebar.php -->
<aside class="bg-gray-800 text-white w-64 min-h-screen hidden md:block">
    <div class="p-6">
        <div class="flex items-center mb-6">
            <img src="/assets/images/logo-white.svg" alt="المنصة التعليمية" class="h-8">
            <h2 class="text-xl font-bold mr-3">المنصة التعليمية</h2>
        </div>
        
        <div class="text-center mb-6">
            <div class="inline-block p-2 rounded-full bg-gray-700">
                <?php if ($this->auth->user()['profile_picture']): ?>
                    <img src="<?php echo $this->auth->user()['profile_picture']; ?>" alt="Profile" class="h-16 w-16 rounded-full">
                <?php else: ?>
                    <div class="h-16 w-16 rounded-full bg-blue-600 flex items-center justify-center">
                        <span class="text-xl font-bold">
                            <?php 
                            $firstName = $this->auth->user()['first_name'] ?? '';
                            $lastName = $this->auth->user()['last_name'] ?? '';
                            echo mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <h3 class="text-base font-medium mt-2"><?php echo $this->auth->user()['first_name'] . ' ' . $this->auth->user()['last_name']; ?></h3>
            <p class="text-xs text-gray-400">مدير المنصة</p>
        </div>
        
        <nav class="space-y-1">
            <a href="/admin/dashboard" class="flex items-center py-2 px-3 rounded-md <?php echo ($this->request->getPath() === '/admin/dashboard') ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span class="mr-3">لوحة التحكم</span>
            </a>
            
            <a href="/admin/schools" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/schools') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-school w-5 text-center"></i>
                <span class="mr-3">إدارة المدارس</span>
            </a>
            
            <a href="/admin/users" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/users') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-users w-5 text-center"></i>
                <span class="mr-3">إدارة المستخدمين</span>
            </a>
            
            <a href="/admin/subscriptions" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/subscriptions') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-credit-card w-5 text-center"></i>
                <span class="mr-3">إدارة الاشتراكات</span>
            </a>
            
            <a href="/admin/reports" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/reports') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-chart-bar w-5 text-center"></i>
                <span class="mr-3">التقارير والإحصائيات</span>
            </a>
            
            <a href="/admin/settings" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/settings') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-cog w-5 text-center"></i>
                <span class="mr-3">إعدادات النظام</span>
            </a>
            
            <a href="/admin/logs" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/logs') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-history w-5 text-center"></i>
                <span class="mr-3">سجلات النظام</span>
            </a>
        </nav>
    </div>
    
    <div class="border-t border-gray-700 p-4">
        <a href="/logout" class="flex items-center py-2 px-3 rounded-md hover:bg-gray-700 text-red-400">
            <i class="fas fa-sign-out-alt w-5 text-center"></i>
            <span class="mr-3">تسجيل الخروج</span>
        </a>
    </div>
</aside>

<!-- Mobile Sidebar -->
<div class="md:hidden bg-gray-800 p-4 text-white flex justify-between items-center">
    <button id="mobileSidebarToggle" class="text-xl">
        <i class="fas fa-bars"></i>
    </button>
    <img src="/assets/images/logo-white.svg" alt="المنصة التعليمية" class="h-8">
    <div class="relative">
        <button id="mobileUserMenuToggle" class="flex items-center">
            <?php if ($this->auth->user()['profile_picture']): ?>
                <img src="<?php echo $this->auth->user()['profile_picture']; ?>" alt="Profile" class="h-8 w-8 rounded-full">
            <?php else: ?>
                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center">
                    <span class="text-sm font-bold">
                        <?php 
                        $firstName = $this->auth->user()['first_name'] ?? '';
                        $lastName = $this->auth->user()['last_name'] ?? '';
                        echo mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </button>
        
        <div id="mobileUserMenu" class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden">
            <a href="/admin/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">الملف الشخصي</a>
            <a href="/admin/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">الإعدادات</a>
            <div class="border-t border-gray-100"></div>
            <a href="/logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">تسجيل الخروج</a>
        </div>
    </div>
</div>

<div id="mobileSidebar" class="md:hidden fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-black opacity-50"></div>
    
    <div class="absolute inset-y-0 right-0 max-w-xs w-full bg-gray-800 text-white shadow-xl">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold">المنصة التعليمية</h2>
                <button id="closeMobileSidebar" class="text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="text-center mb-6">
                <div class="inline-block p-2 rounded-full bg-gray-700">
                    <?php if ($this->auth->user()['profile_picture']): ?>
                        <img src="<?php echo $this->auth->user()['profile_picture']; ?>" alt="Profile" class="h-16 w-16 rounded-full">
                    <?php else: ?>
                        <div class="h-16 w-16 rounded-full bg-blue-600 flex items-center justify-center">
                            <span class="text-xl font-bold">
                                <?php 
                                $firstName = $this->auth->user()['first_name'] ?? '';
                                $lastName = $this->auth->user()['last_name'] ?? '';
                                echo mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="text-base font-medium mt-2"><?php echo $this->auth->user()['first_name'] . ' ' . $this->auth->user()['last_name']; ?></h3>
                <p class="text-xs text-gray-400">مدير المنصة</p>
            </div>
            
            <nav class="space-y-1">
                <a href="/admin/dashboard" class="flex items-center py-2 px-3 rounded-md <?php echo ($this->request->getPath() === '/admin/dashboard') ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                    <i class="fas fa-tachometer-alt w-5 text-center"></i>
                    <span class="mr-3">لوحة التحكم</span>
                </a>
                
                <a href="/admin/schools" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/schools') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                    <i class="fas fa-school w-5 text-center"></i>
                    <span class="mr-3">إدارة المدارس</span>
                </a>
                
                <a href="/admin/users" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/users') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span class="mr-3">إدارة المستخدمين</span>
                </a>
                
                <a href="/admin/subscriptions" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/subscriptions') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                    <i class="fas fa-credit-card w-5 text-center"></i>
                    <span class="mr-3">إدارة الاشتراكات</span>
                </a>
                
                <a href="/admin/reports" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/reports') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                    <i class="fas fa-chart-bar w-5 text-center"></i>
                    <span class="mr-3">التقارير والإحصائيات</span>
                </a>
                
                <a href="/admin/settings" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/settings') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span class="mr-3">إعدادات النظام</span>
                </a>
                
                <a href="/admin/logs" class="flex items-center py-2 px-3 rounded-md <?php echo (strpos($this->request->getPath(), '/admin/logs') === 0) ? 'bg-gray-700 text-blue-400' : 'hover:bg-gray-700'; ?>">
                    <i class="fas fa-history w-5 text-center"></i>
                    <span class="mr-3">سجلات النظام</span>
                </a>
            </nav>
        </div>
        
        <div class="border-t border-gray-700 p-4">
            <a href="/logout" class="flex items-center py-2 px-3 rounded-md hover:bg-gray-700 text-red-400">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span class="mr-3">تسجيل الخروج</span>
            </a>
        </div>
    </div>
</div>

<script>
    // Mobile sidebar toggle
    document.getElementById('mobileSidebarToggle').addEventListener('click', function() {
        document.getElementById('mobileSidebar').classList.remove('hidden');
    });
    
    document.getElementById('closeMobileSidebar').addEventListener('click', function() {
        document.getElementById('mobileSidebar').classList.add('hidden');
    });
    
    // Click outside to close
    document.getElementById('mobileSidebar').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Mobile user menu toggle
    document.getElementById('mobileUserMenuToggle').addEventListener('click', function() {
        const menu = document.getElementById('mobileUserMenu');
        menu.classList.toggle('hidden');
    });
    
    // Close user menu when clicking outside
    document.addEventListener('click', function(e) {
        const menu = document.getElementById('mobileUserMenu');
        const toggle = document.getElementById('mobileUserMenuToggle');
        
        if (!menu.contains(e.target) && !toggle.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
</script>