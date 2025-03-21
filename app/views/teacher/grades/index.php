<!-- app/views/teacher/grades/index.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدرجات - المنصة التعليمية</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/<!-- app/views/teacher/grades/index.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدرجات - المنصة التعليمية</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="/assets/css/teacher.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-cairo">
    <!-- Header -->
    <?php include_once 'app/views/teacher/shared/header.php'; ?>
    
    <div class="flex">
        <!-- Sidebar -->
        <?php include_once 'app/views/teacher/shared/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">إدارة الدرجات</h1>
                <div class="flex space-x-3 space-x-reverse">
                    <a href="/teacher/dashboard" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md">
                        <i class="fas fa-arrow-right ml-1"></i>
                        عودة للوحة التحكم
                    </a>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="bg-white rounded-md shadow-sm p-4 mb-6">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div class="w-full md:w-1/4">
                        <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
                        <select id="class_id" name="class_id" class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">جميع الصفوف</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClass == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="w-full md:w-1/4">
                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">المادة</label>
                        <select id="subject_id" name="subject_id" class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">جميع المواد</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubject == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                            <i class="fas fa-filter ml-1"></i>
                            تصفية
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Submissions Table -->
            <div class="bg-white rounded-md shadow-sm overflow-hidden">
                <h2 class="text-lg font-bold text-gray-800 p-4 border-b border-gray-200">الإجابات التي تحتاج إلى تصحيح</h2>
                
                <?php if (empty($pendingSubmissions)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-2"></i>
                        <p>لا توجد إجابات تحتاج إلى تصحيح حالياً.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الطالب
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        المهمة
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        المادة
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الصف
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        تاريخ التقديم
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pendingSubmissions as $submission): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                                    <span class="font-bold text-sm">
                                                        <?php 
                                                        echo mb_substr($submission['student_first_name'], 0, 1) . 
                                                             mb_substr($submission['student_last_name'], 0, 1);
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="mr-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($submission['student_first_name'] . ' ' . $submission['student_last_name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($submission['assignment_title']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $submission['assignment_points']; ?> نقطة</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($submission['subject_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($submission['subject_code']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($submission['class_name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('Y-m-d', strtotime($submission['submitted_at'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('h:i A', strtotime($submission['submitted_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="/teacher/grades/grade/<?php echo $submission['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-md text-sm">
                                                <i class="fas fa-check-square ml-1"></i>
                                                تصحيح
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Reports Section -->
            <div class="mt-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">تقارير الدرجات</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($classes as $class): ?>
                        <div class="bg-white rounded-md shadow-sm p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($class['name']); ?></h3>
                            <p class="text-gray-600 text-sm mb-4">تقارير الدرجات للصف</p>
                            <a href="/teacher/grades/class-report/<?php echo $class['id']; ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                <span>عرض التقرير</span>
                                <i class="fas fa-chevron-left mr-1 text-xs"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/teacher/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        // Submit form when filter changes
        document.querySelectorAll('#class_id, #subject_id').forEach(select => {
            select.addEventListener('change', function() {
                setTimeout(() => this.form.submit(), 100);
            });
        });
    </script>
</body>
</html>