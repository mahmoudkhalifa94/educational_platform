<!-- app/views/admin/schools/show.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($school['name']); ?> - المنصة التعليمية</title>
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
                <div>
                    <a href="/admin/schools" class="text-blue-600 hover:text-blue-800 text-sm mb-1 inline-block">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة إلى قائمة المدارس
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($school['name']); ?></h1>
                </div>
                
                <div class="flex space-x-3 space-x-reverse">
                    <a href="/admin/schools/<?php echo $school['id']; ?>/edit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-2 px-4 rounded-md inline-flex items-center">
                        <i class="fas fa-edit ml-2"></i>
                        تعديل
                    </a>
                    
                    <?php if ($school['active']): ?>
                        <button type="button" class="toggle-status bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md inline-flex items-center"
                               data-id="<?php echo $school['id']; ?>" 
                               data-active="1">
                            <i class="fas fa-ban ml-2"></i>
                            تعطيل
                        </button>
                    <?php else: ?>
                        <button type="button" class="toggle-status bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md inline-flex items-center"
                               data-id="<?php echo $school['id']; ?>" 
                               data-active="0">
                            <i class="fas fa-check ml-2"></i>
                            تفعيل
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- School Information -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Main Information -->
                <div class="bg-white rounded-md shadow-sm p-6 lg:col-span-2">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">معلومات المدرسة</h2>
                    
                    <div class="flex flex-wrap -mx-4">
                        <div class="w-full md:w-1/3 px-4 mb-4">
                            <div class="text-center">
                                <?php if (!empty($school['logo'])): ?>
                                    <img src="<?php echo $school['logo']; ?>" alt="<?php echo htmlspecialchars($school['name']); ?>" class="h-32 mx-auto mb-2">
                                <?php else: ?>
                                    <div class="h-32 w-32 rounded-full mx-auto bg-gray-200 flex items-center justify-center mb-2">
                                        <i class="fas fa-school text-4xl text-gray-500"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $school['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $school['active'] ? 'نشطة' : 'غير نشطة'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="w-full md:w-2/3 px-4">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">اسم المدرسة</label>
                                <div class="text-gray-900"><?php echo htmlspecialchars($school['name']); ?></div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">النطاق الفرعي</label>
                                <div class="text-gray-900"><?php echo htmlspecialchars($school['subdomain']); ?>.platform.com</div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">رابط المدرسة</label>
                                <div class="text-blue-600">
                                    <a href="https://<?php echo htmlspecialchars($school['subdomain']); ?>.platform.com" target="_blank">
                                        https://<?php echo htmlspecialchars($school['subdomain']); ?>.platform.com
                                        <i class="fas fa-external-link-alt mr-1 text-xs"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الإنشاء</label>
                                <div class="text-gray-900"><?php echo date('Y-m-d', strtotime($school['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Subscription Information -->
                <div class="bg-white rounded-md shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">معلومات الاشتراك</h2>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">نوع الاشتراك</label>
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
                        <div>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $subscriptionClass; ?>">
                                <?php echo $subscriptionText; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ البدء</label>
                        <div class="text-gray-900"><?php echo date('Y-m-d', strtotime($school['subscription_start_date'])); ?></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الانتهاء</label>
                        <?php 
                        $endDate = new DateTime($school['subscription_end_date']);
                        $today = new DateTime();
                        $interval = $today->diff($endDate);
                        $daysLeft = $interval->invert ? -$interval->days : $interval->days;
                        ?>
                        <div class="text-gray-900">
                            <?php echo date('Y-m-d', strtotime($school['subscription_end_date'])); ?>
                            
                            <?php if ($daysLeft <= 0): ?>
                                <span class="mr-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">
                                    منتهي
                                </span>
                            <?php elseif ($daysLeft <= 30): ?>
                                <span class="mr-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">
                                    متبقي <?php echo $daysLeft; ?> يوم
                                </span>
                            <?php else: ?>
                                <span class="mr-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                    متبقي <?php echo $daysLeft; ?> يوم
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">الحد الأقصى للطلاب</label>
                        <div class="text-gray-900">
                            <?php echo number_format($school['max_students']); ?> طالب
                            
                            <?php if (isset($stats['students_count'])): ?>
                                <span class="text-sm text-gray-500">
                                    (المستخدم: <?php echo number_format($stats['students_count']); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="button" id="updateSubscriptionBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md flex justify-center items-center">
                            <i class="fas fa-sync-alt ml-2"></i>
                            تحديث الاشتراك
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Statistics and Administrator Information -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- School Statistics -->
                <div class="bg-white rounded-md shadow-sm p-6 lg:col-span-2">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">إحصائيات المدرسة</h2>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 rounded-md p-4 text-center">
                            <div class="text-blue-600 text-3xl font-bold mb-1"><?php echo $stats['students_count'] ?? 0; ?></div>
                            <div class="text-blue-800 text-sm">الطلاب</div>
                        </div>
                        
                        <div class="bg-green-50 rounded-md p-4 text-center">
                            <div class="text-green-600 text-3xl font-bold mb-1"><?php echo $stats['teachers_count'] ?? 0; ?></div>
                            <div class="text-green-800 text-sm">المعلمين</div>
                        </div>
                        
                        <div class="bg-yellow-50 rounded-md p-4 text-center">
                            <div class="text-yellow-600 text-3xl font-bold mb-1"><?php echo $stats['classes_count'] ?? 0; ?></div>
                            <div class="text-yellow-800 text-sm">الصفوف</div>
                        </div>
                        
                        <div class="bg-purple-50 rounded-md p-4 text-center">
                            <div class="text-purple-600 text-3xl font-bold mb-1"><?php echo $stats['subjects_count'] ?? 0; ?></div>
                            <div class="text-purple-800 text-sm">المواد</div>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <h3 class="text-md font-bold text-gray-700 mb-2">المهام والنشاطات</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-50 rounded-md p-4 text-center">
                                <div class="text-gray-600 text-2xl font-bold mb-1"><?php echo $stats['assignments_count'] ?? 0; ?></div>
                                <div class="text-gray-800 text-sm">المهام</div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-md p-4 text-center">
                                <div class="text-gray-600 text-2xl font-bold mb-1">
                                    <?php 
                                    // تمثيل لمعدل النشاط
                                    $activityRate = rand(50, 100);
                                    echo $activityRate . '%';
                                    ?>
                                </div>
                                <div class="text-gray-800 text-sm">معدل النشاط</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Administrator Information -->
                <div class="bg-white rounded-md shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">مدير المدرسة</h2>
                    
                    <?php if (isset($schoolAdmin) && $schoolAdmin): ?>
                        <div class="text-center mb-4">
                            <div class="h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center text-white mx-auto">
                                <span class="text-xl font-bold">
                                    <?php 
                                    $firstName = $schoolAdmin['first_name'] ?? '';
                                    $lastName = $schoolAdmin['last_name'] ?? '';
                                    echo mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
                            <div class="text-gray-900"><?php echo htmlspecialchars($schoolAdmin['first_name'] . ' ' . $schoolAdmin['last_name']); ?></div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                            <div class="text-gray-900"><?php echo htmlspecialchars($schoolAdmin['email']); ?></div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                            <div class="text-gray-900"><?php echo !empty($schoolAdmin['phone']) ? htmlspecialchars($schoolAdmin['phone']) : '-'; ?></div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">آخر تسجيل دخول</label>
                            <div class="text-gray-900">
                                <?php echo !empty($schoolAdmin['last_login']) ? date('Y-m-d H:i', strtotime($schoolAdmin['last_login'])) : 'لم يسجل الدخول بعد'; ?>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex space-x-3 space-x-reverse">
                            <a href="/admin/users/<?php echo $schoolAdmin['id']; ?>" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-center font-medium py-2 px-4 rounded-md">
                                عرض الملف
                            </a>
                            
                            <button type="button" id="resetPasswordBtn" data-id="<?php echo $schoolAdmin['id']; ?>" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white text-center font-medium py-2 px-4 rounded-md">
                                إعادة تعيين كلمة المرور
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-gray-500 py-4">
                            لا يوجد مدير معين للمدرسة
                        </div>
                        
                        <div class="mt-6">
                            <a href="/admin/users/create?school_id=<?php echo $school['id']; ?>&role=school_admin" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-medium py-2 px-4 rounded-md block">
                                تعيين مدير للمدرسة
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Subscription History -->
            <div class="bg-white rounded-md shadow-sm p-6 mb-8">
                <h2 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">تاريخ الاشتراكات</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    نوع الاشتراك
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    تاريخ البدء
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    تاريخ الانتهاء
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    الحد الأقصى للطلاب
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    الحالة
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($subscriptions)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        لا يوجد تاريخ اشتراكات
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $subscriptionClass = 'bg-blue-100 text-blue-800';
                                            $subscriptionText = 'تجريبي';
                                            
                                            if ($subscription['plan_type'] === 'limited') {
                                                $subscriptionClass = 'bg-yellow-100 text-yellow-800';
                                                $subscriptionText = 'محدود';
                                            } elseif ($subscription['plan_type'] === 'unlimited') {
                                                $subscriptionClass = 'bg-green-100 text-green-800';
                                                $subscriptionText = 'غير محدود';
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $subscriptionClass; ?>">
                                                <?php echo $subscriptionText; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('Y-m-d', strtotime($subscription['start_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('Y-m-d', strtotime($subscription['end_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($subscription['max_students']); ?> طالب
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $statusText = 'نشط';
                                            
                                            if ($subscription['status'] === 'expired') {
                                                $statusClass = 'bg-red-100 text-red-800';
                                                $statusText = 'منتهي';
                                            } elseif ($subscription['status'] === 'cancelled') {
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                $statusText = 'ملغي';
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
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
    
    <!-- Update Subscription Modal -->
    <div id="updateSubscriptionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">تحديث الاشتراك</h3>
            
            <form action="/admin/schools/<?php echo $school['id']; ?>/update-subscription" method="POST" class="space-y-4">
                <div>
                    <label for="subscription_type" class="block text-sm font-medium text-gray-700 mb-1">نوع الاشتراك</label>
                    <select id="subscription_type" name="subscription_type" required 
                            class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="trial" <?php echo $school['subscription_type'] === 'trial' ? 'selected' : ''; ?>>تجريبي (50 طالب)</option>
                        <option value="limited" <?php echo $school['subscription_type'] === 'limited' ? 'selected' : ''; ?>>محدود (500 طالب)</option>
                        <option value="unlimited" <?php echo $school['subscription_type'] === 'unlimited' ? 'selected' : ''; ?>>غير محدود</option>
                    </select>
                </div>
                
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">تاريخ البدء</label>
                    <input type="date" id="start_date" name="start_date" required 
                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           value="<?php echo date('Y-m-d', strtotime($school['subscription_start_date'])); ?>">
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">تاريخ الانتهاء</label>
                    <input type="date" id="end_date" name="end_date" required 
                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           value="<?php echo date('Y-m-d', strtotime($school['subscription_end_date'])); ?>">
                </div>
                
                <div>
                    <label for="max_students" class="block text-sm font-medium text-gray-700 mb-1">الحد الأقصى للطلاب</label>
                    <input type="number" id="max_students" name="max_students" required 
                           class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                           value="<?php echo $school['max_students']; ?>" min="1">
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" id="cancelUpdateSubscription" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                        إلغاء
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                        تحديث
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">إعادة تعيين كلمة المرور</h3>
            <p class="text-gray-700 mb-6">هل أنت متأكد من إعادة تعيين كلمة المرور لمدير المدرسة؟ سيتم إرسال رابط إعادة التعيين إلى بريده الإلكتروني.</p>
            
            <div class="flex justify-end space-x-4 space-x-reverse">
                <button id="cancelResetPassword" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                    إلغاء
                </button>
                <form id="resetPasswordForm" action="/admin/users/reset-password" method="POST">
                    <input type="hidden" name="user_id" id="reset_user_id" value="">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                        إعادة تعيين
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/admin/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        // Toggle school status
        $('.toggle-status').on('click', function() {
            const schoolId = $(this).data('id');
            const isActive = $(this).data('active');
            const button = $(this);
            
            $.ajax({
                url: `/admin/schools/${schoolId}/toggle-status`,
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload the page to reflect changes
                        location.reload();
                    } else {
                        // Show error message
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('حدث خطأ أثناء تحديث حالة المدرسة');
                }
            });
        });
        
        // Update Subscription Modal
        $('#updateSubscriptionBtn').on('click', function() {
            $('#updateSubscriptionModal').removeClass('hidden');
        });
        
        $('#cancelUpdateSubscription').on('click', function() {
            $('#updateSubscriptionModal').addClass('hidden');
        });
        
        // Update max students based on subscription type
        $('#subscription_type').on('change', function() {
            const type = $(this).val();
            let maxStudents = $('#max_students').val();
            
            if (type === 'trial' && maxStudents > 50) {
                $('#max_students').val(50);
            } else if (type === 'limited' && maxStudents > 500) {
                $('#max_students').val(500);
            }
            
            // Update end date based on subscription type and start date
            const startDate = new Date($('#start_date').val());
            let endDate = new Date(startDate);
            
            if (type === 'trial') {
                endDate.setMonth(endDate.getMonth() + 3);
            } else {
                endDate.setFullYear(endDate.getFullYear() + 1);
            }
            
            $('#end_date').val(endDate.toISOString().split('T')[0]);
        });
        
        // Reset Password Modal
        $('#resetPasswordBtn').on('click', function() {
            $('#reset_user_id').val($(this).data('id'));
            $('#resetPasswordModal').removeClass('hidden');
        });
        
        $('#cancelResetPassword').on('click', function() {
            $('#resetPasswordModal').addClass('hidden');
        });
        
        // Close modals when clicking outside
        $('#updateSubscriptionModal, #resetPasswordModal').on('click', function(e) {
            if (e.target === this) {
                $(this).addClass('hidden');
            }
        });
    </script>
</body>
</html>