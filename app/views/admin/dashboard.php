<!-- app/views/admin/dashboard.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - المنصة التعليمية</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
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
                <h1 class="text-2xl font-bold text-gray-800">لوحة التحكم</h1>
                <div class="text-sm text-gray-600">
                    <i class="far fa-calendar ml-1"></i>
                    <?php echo date('Y-m-d'); ?>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600 ml-4">
                            <i class="fas fa-school text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">إجمالي المدارس</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['total_schools'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 ml-4">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">إجمالي المستخدمين</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['total_users'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 ml-4">
                            <i class="fas fa-user-graduate text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">إجمالي الطلاب</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['total_students'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600 ml-4">
                            <i class="fas fa-chalkboard-teacher text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">إجمالي المعلمين</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['total_teachers'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Schools Monthly Chart -->
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">المدارس المضافة شهريًا</h2>
                    <div class="h-64">
                        <canvas id="schoolsChart"></canvas>
                    </div>
                </div>
                
                <!-- Schools By Subscription Type -->
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">توزيع المدارس حسب نوع الاشتراك</h2>
                    <div class="h-64">
                        <canvas id="subscriptionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Latest Schools -->
                <div class="lg:col-span-2 bg-white rounded-md shadow-sm p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-gray-800">آخر المدارس المضافة</h2>
                        <a href="/admin/schools" class="text-blue-600 hover:text-blue-800 text-sm">
                            عرض الكل
                            <i class="fas fa-arrow-left mr-1"></i>
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        المدرسة
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        النطاق الفرعي
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الاشتراك
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الحالة
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        تاريخ الإنشاء
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($latestSchools)): ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                            لا توجد مدارس مضافة حاليًا
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($latestSchools as $school): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <a href="/admin/schools/<?php echo $school['id']; ?>" class="hover:text-blue-600">
                                                        <?php echo htmlspecialchars($school['name']); ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($school['subdomain']); ?>.platform.com
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <?php
                                                $subscriptionClass = 'bg-blue-100 text-blue-800';
                                                $subscriptionText = 'تجريبي';
                                                
                                                if ($school['subscription_type'] === 'limited') {
                                                    $subscriptionClass = 'bg-yellow-100 text-yellow-800';
                                                    $subscriptionText = 'محدود';
                                                } elseif ($school['subscription_type'] === 'unlimited') {
                                                    $subscriptionClass = 'bg-green-100 text-green-800';
                                                    $subscriptionText = 'غير محدود';
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $subscriptionClass; ?>">
                                                    <?php echo $subscriptionText; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <?php if ($school['active']): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        نشطة
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        غير نشطة
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('Y-m-d', strtotime($school['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">إجراءات سريعة</h2>
                    <div class="space-y-4">
                        <a href="/admin/schools/create" class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-md transition">
                            <div class="p-2 rounded-md bg-blue-200 text-blue-700 ml-3">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-blue-700">إضافة مدرسة جديدة</h3>
                                <p class="text-xs text-blue-600">إنشاء حساب مدرسة جديدة وتعيين مديرها</p>
                            </div>
                        </a>
                        
                        <a href="/admin/users/create" class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-md transition">
                            <div class="p-2 rounded-md bg-green-200 text-green-700 ml-3">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-green-700">إضافة مستخدم</h3>
                                <p class="text-xs text-green-600">إنشاء حساب مستخدم جديد في النظام</p>
                            </div>
                        </a>
                        
                        <a href="/admin/subscriptions" class="flex items-center p-3 bg-yellow-50 hover:bg-yellow-100 rounded-md transition">
                            <div class="p-2 rounded-md bg-yellow-200 text-yellow-700 ml-3">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-yellow-700">إدارة الاشتراكات</h3>
                                <p class="text-xs text-yellow-600">تحديث اشتراكات المدارس وخطط الأسعار</p>
                            </div>
                        </a>
                        
                        <a href="/admin/reports" class="flex items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-md transition">
                            <div class="p-2 rounded-md bg-purple-200 text-purple-700 ml-3">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-purple-700">التقارير والإحصائيات</h3>
                                <p class="text-xs text-purple-600">عرض تقارير مفصّلة عن استخدام النظام</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- System Logs -->
            <div class="bg-white rounded-md shadow-sm p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-gray-800">آخر نشاطات النظام</h2>
                    <a href="/admin/logs" class="text-blue-600 hover:text-blue-800 text-sm">
                        عرض الكل
                        <i class="fas fa-arrow-left mr-1"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    المستخدم
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    النشاط
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    التفاصيل
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    التاريخ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($latestLogs)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                        لا توجد سجلات نشاط حاليًا
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($latestLogs as $log): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php if ($log['user_id']): ?>
                                                    <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-gray-500">النظام</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($log['email']): ?>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($log['action']); ?></div>
                                            <?php if ($log['entity_type']): ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo htmlspecialchars($log['entity_type']); ?>
                                                    <?php if ($log['entity_id']): ?>
                                                        #<?php echo $log['entity_id']; ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($log['details']): ?>
                                                <?php
                                                $details = json_decode($log['details'], true);
                                                if (is_array($details)) {
                                                    foreach ($details as $key => $value) {
                                                        echo htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '<br>';
                                                    }
                                                } else {
                                                    echo htmlspecialchars($log['details']);
                                                }
                                                ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/admin/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script>
        // Schools Monthly Chart
        const schoolsData = <?php echo json_encode($charts['schools_monthly'] ?? []); ?>;
        const schoolsLabels = schoolsData.map(item => item.month);
        const schoolsCounts = schoolsData.map(item => item.count);
        
        new Chart(document.getElementById('schoolsChart'), {
            type: 'line',
            data: {
                labels: schoolsLabels,
                datasets: [{
                    label: 'المدارس المضافة',
                    data: schoolsCounts,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Schools By Subscription Type Chart
        const subscriptionData = <?php echo json_encode($charts['schools_by_subscription'] ?? []); ?>;
        const subscriptionLabels = subscriptionData.map(item => item.type);
        const subscriptionCounts = subscriptionData.map(item => item.count);
        
        new Chart(document.getElementById('subscriptionChart'), {
            type: 'doughnut',
            data: {
                labels: subscriptionLabels,
                datasets: [{
                    data: subscriptionCounts,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(16, 185, 129, 0.7)'
                    ],
                    borderColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(16, 185, 129, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>