<?php
/**
 * app/views/teacher/student_progress.php
 * صفحة تحليل تقدم الطلاب
 * تعرض إحصائيات ورسوم بيانية لأداء الطلاب في الصف
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحليل تقدم الطلاب - المنصة التعليمية</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
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
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">تحليل تقدم الطلاب</h1>
                    <p class="text-gray-600 mt-1">
                        <span class="ml-4">
                            <i class="fas fa-chalkboard-teacher ml-1"></i>
                            الصف: <?php echo htmlspecialchars($class['name']); ?>
                        </span>
                        <span>
                            <i class="fas fa-book ml-1"></i>
                            المادة: <?php echo htmlspecialchars($subject['name']); ?>
                        </span>
                    </p>
                </div>
                
                <div class="flex space-x-3 space-x-reverse">
                    <a href="/teacher/classes/<?php echo $class['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md inline-flex items-center">
                        <i class="fas fa-arrow-right ml-2"></i>
                        العودة للصف
                    </a>
                    <button id="exportReportBtn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md inline-flex items-center">
                        <i class="fas fa-file-export ml-2"></i>
                        تصدير التقرير
                    </button>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- Filter Controls -->
            <div class="bg-white rounded-md shadow-sm p-4 mb-6">
                <form id="filterForm" method="GET" class="flex flex-wrap items-end space-y-4 md:space-y-0 space-x-0 md:space-x-4 space-x-reverse">
                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                    
                    <div class="w-full md:w-auto mb-4 md:mb-0">
                        <label for="time_period" class="block text-sm font-medium text-gray-700 mb-1">الفترة الزمنية</label>
                        <select id="time_period" name="time_period" class="w-full md:w-48 border border-gray-300 rounded-md py-2 px-3">
                            <option value="all" <?php echo $filters['time_period'] === 'all' ? 'selected' : ''; ?>>جميع الفترات</option>
                            <option value="term1" <?php echo $filters['time_period'] === 'term1' ? 'selected' : ''; ?>>الفصل الدراسي الأول</option>
                            <option value="term2" <?php echo $filters['time_period'] === 'term2' ? 'selected' : ''; ?>>الفصل الدراسي الثاني</option>
                            <option value="last_month" <?php echo $filters['time_period'] === 'last_month' ? 'selected' : ''; ?>>الشهر الماضي</option>
                        </select>
                    </div>
                    
                    <div class="w-full md:w-auto mb-4 md:mb-0">
                        <label for="assignment_type" class="block text-sm font-medium text-gray-700 mb-1">نوع المهمة</label>
                        <select id="assignment_type" name="assignment_type" class="w-full md:w-48 border border-gray-300 rounded-md py-2 px-3">
                            <option value="all" <?php echo $filters['assignment_type'] === 'all' ? 'selected' : ''; ?>>جميع المهام</option>
                            <option value="quiz" <?php echo $filters['assignment_type'] === 'quiz' ? 'selected' : ''; ?>>اختبار قصير</option>
                            <option value="homework" <?php echo $filters['assignment_type'] === 'homework' ? 'selected' : ''; ?>>واجب منزلي</option>
                            <option value="project" <?php echo $filters['assignment_type'] === 'project' ? 'selected' : ''; ?>>مشروع</option>
                            <option value="exam" <?php echo $filters['assignment_type'] === 'exam' ? 'selected' : ''; ?>>اختبار</option>
                        </select>
                    </div>
                    
                    <div class="w-full md:w-auto mb-4 md:mb-0">
                        <label for="student_filter" class="block text-sm font-medium text-gray-700 mb-1">ترتيب الطلاب</label>
                        <select id="student_filter" name="student_filter" class="w-full md:w-48 border border-gray-300 rounded-md py-2 px-3">
                            <option value="alphabetical" <?php echo $filters['student_filter'] === 'alphabetical' ? 'selected' : ''; ?>>أبجدي</option>
                            <option value="highest" <?php echo $filters['student_filter'] === 'highest' ? 'selected' : ''; ?>>الأعلى أداءً</option>
                            <option value="lowest" <?php echo $filters['student_filter'] === 'lowest' ? 'selected' : ''; ?>>الأدنى أداءً</option>
                            <option value="most_improved" <?php echo $filters['student_filter'] === 'most_improved' ? 'selected' : ''; ?>>الأكثر تحسنًا</option>
                            <option value="needs_attention" <?php echo $filters['student_filter'] === 'needs_attention' ? 'selected' : ''; ?>>يحتاج اهتمام</option>
                        </select>
                    </div>
                    
                    <div class="w-full md:w-auto">
                        <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                            <i class="fas fa-filter ml-2"></i>
                            تصفية
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Class Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-2">متوسط الصف</h2>
                    <div class="flex items-center">
                        <div class="relative w-24 h-24">
                            <svg class="w-24 h-24" viewBox="0 0 36 36">
                                <path
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="#eee"
                                    stroke-width="3"
                                />
                                <path
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="<?php echo $classAvg >= 80 ? '#48bb78' : ($classAvg >= 60 ? '#ed8936' : '#f56565'); ?>"
                                    stroke-width="3"
                                    stroke-dasharray="<?php echo $classAvg; ?>, 100"
                                />
                            </svg>
                            <div class="absolute top-0 left-0 right-0 bottom-0 flex items-center justify-center">
                                <span class="text-2xl font-bold"><?php echo round($classAvg); ?>%</span>
                            </div>
                        </div>
                        <div class="mr-4">
                            <p class="text-sm text-gray-600">عدد المهام: <strong><?php echo $classStats['total_assignments']; ?></strong></p>
                            <p class="text-sm text-gray-600">أعلى درجة: <strong><?php echo $classStats['highest_grade']; ?>%</strong></p>
                            <p class="text-sm text-gray-600">أدنى درجة: <strong><?php echo $classStats['lowest_grade']; ?>%</strong></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-2">مستويات الأداء</h2>
                    <div class="flex flex-wrap">
                        <div class="w-1/2 flex items-center mb-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-green-500 ml-2"></span>
                            <span class="text-gray-700 text-sm">متفوق (90%+): <strong><?php echo $performanceLevels['excellent']; ?></strong></span>
                        </div>
                        <div class="w-1/2 flex items-center mb-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-blue-500 ml-2"></span>
                            <span class="text-gray-700 text-sm">جيد جدًا (80-89%): <strong><?php echo $performanceLevels['very_good']; ?></strong></span>
                        </div>
                        <div class="w-1/2 flex items-center mb-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-yellow-500 ml-2"></span>
                            <span class="text-gray-700 text-sm">جيد (70-79%): <strong><?php echo $performanceLevels['good']; ?></strong></span>
                        </div>
                        <div class="w-1/2 flex items-center mb-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-orange-500 ml-2"></span>
                            <span class="text-gray-700 text-sm">مقبول (60-69%): <strong><?php echo $performanceLevels['fair']; ?></strong></span>
                        </div>
                        <div class="w-1/2 flex items-center">
                            <span class="inline-block w-3 h-3 rounded-full bg-red-500 ml-2"></span>
                            <span class="text-gray-700 text-sm">ضعيف (<60%): <strong><?php echo $performanceLevels['poor']; ?></strong></span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-2">إحصائيات المهام</h2>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-blue-50 rounded-md p-2 text-center">
                            <p class="text-sm text-gray-600">نسبة التسليم</p>
                            <p class="text-xl font-bold text-blue-700"><?php echo round($classStats['submission_rate']); ?>%</p>
                        </div>
                        <div class="bg-green-50 rounded-md p-2 text-center">
                            <p class="text-sm text-gray-600">نسبة النجاح</p>
                            <p class="text-xl font-bold text-green-700"><?php echo round($classStats['success_rate']); ?>%</p>
                        </div>
                        <div class="bg-yellow-50 rounded-md p-2 text-center">
                            <p class="text-sm text-gray-600">معدل التحسن</p>
                            <p class="text-xl font-bold text-yellow-700"><?php echo $classStats['improvement_rate'] > 0 ? '+' : ''; ?><?php echo round($classStats['improvement_rate'], 1); ?>%</p>
                        </div>
                        <div class="bg-red-50 rounded-md p-2 text-center">
                            <p class="text-sm text-gray-600">تسليم متأخر</p>
                            <p class="text-xl font-bold text-red-700"><?php echo round($classStats['late_submission_rate']); ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">تطور درجات الصف</h2>
                    <div class="h-64">
                        <canvas id="classProgressChart"></canvas>
                    </div>
                </div>
                
                <div class="bg-white rounded-md shadow-sm p-4">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">توزيع الدرجات</h2>
                    <div class="h-64">
                        <canvas id="gradesDistributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Students Performance Table -->
            <div class="bg-white rounded-md shadow-sm overflow-hidden mb-6">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-lg font-bold text-gray-800">أداء الطلاب</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    الطالب
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    المتوسط العام
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    نسبة التسليم
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    التسليم المتأخر
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    التطور
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    إجراءات
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                        لا يوجد طلاب في هذا الصف
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <?php 
                                    $avgClass = '';
                                    if ($student['average'] >= 90) {
                                        $avgClass = 'text-green-600';
                                    } elseif ($student['average'] >= 80) {
                                        $avgClass = 'text-blue-600';
                                    } elseif ($student['average'] >= 70) {
                                        $avgClass = 'text-yellow-600';
                                    } elseif ($student['average'] >= 60) {
                                        $avgClass = 'text-orange-600';
                                    } else {
                                        $avgClass = 'text-red-600';
                                    }
                                    ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <?php if ($student['profile_picture']): ?>
                                                        <img class="h-10 w-10 rounded-full" src="<?php echo $student['profile_picture']; ?>" alt="Student">
                                                    <?php else: ?>
                                                        <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center text-white">
                                                            <?php 
                                                            $initials = mb_substr($student['first_name'], 0, 1) . mb_substr($student['last_name'], 0, 1);
                                                            echo $initials;
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mr-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-lg font-bold <?php echo $avgClass; ?>"><?php echo round($student['average'], 1); ?>%</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo round($student['submission_rate']); ?>%</div>
                                            <div class="text-xs text-gray-500"><?php echo $student['submitted_count']; ?> من <?php echo $student['assignment_count']; ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <?php if ($student['late_count'] > 0): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    <?php echo $student['late_count']; ?> مهام
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    لا يوجد
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <?php if ($student['improvement'] > 5): ?>
                                                <span class="inline-flex items-center text-green-600">
                                                    <i class="fas fa-arrow-up ml-1"></i>
                                                    <?php echo round($student['improvement'], 1); ?>%
                                                </span>
                                            <?php elseif ($student['improvement'] < -5): ?>
                                                <span class="inline-flex items-center text-red-600">
                                                    <i class="fas fa-arrow-down ml-1"></i>
                                                    <?php echo round(abs($student['improvement']), 1); ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center text-gray-600">
                                                    <i class="fas fa-minus ml-1"></i>
                                                    مستقر
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="/teacher/student-profile/<?php echo $student['student_id']; ?>?subject_id=<?php echo $subject['id']; ?>" class="text-blue-600 hover:text-blue-900 ml-3">
                                                <i class="fas fa-chart-line"></i>
                                                تفاصيل الأداء
                                            </a>
                                            <a href="/teacher/communication/create?student_id=<?php echo $student['id']; ?>" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-comment"></i>
                                                تواصل
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Assignment Performance -->
            <div class="bg-white rounded-md shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-lg font-bold text-gray-800">أداء المهام</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    المهمة
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    النوع
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    تاريخ التسليم
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    متوسط الدرجات
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    نسبة التسليم
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    إجراءات
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                        لا توجد مهام للعرض
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php 
                                    $avgClass = '';
                                    if ($assignment['avg_percentage'] >= 90) {
                                        $avgClass = 'text-green-600';
                                    } elseif ($assignment['avg_percentage'] >= 80) {
                                        $avgClass = 'text-blue-600';
                                    } elseif ($assignment['avg_percentage'] >= 70) {
                                        $avgClass = 'text-yellow-600';
                                    } elseif ($assignment['avg_percentage'] >= 60) {
                                        $avgClass = 'text-orange-600';
                                    } else {
                                        $avgClass = 'text-red-600';
                                    }
                                    ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $assignment['points']; ?> نقطة</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <?php
                                            $typeText = '';
                                            $typeClass = '';
                                            
                                            switch ($assignment['type']) {
                                                case 'quiz':
                                                    $typeText = 'اختبار قصير';
                                                    $typeClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'homework':
                                                    $typeText = 'واجب منزلي';
                                                    $typeClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'project':
                                                    $typeText = 'مشروع';
                                                    $typeClass = 'bg-purple-100 text-purple-800';
                                                    break;
                                                case 'exam':
                                                    $typeText = 'اختبار';
                                                    $typeClass = 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    $typeText = $assignment['type'];
                                                    $typeClass = 'bg-gray-100 text-gray-800';
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $typeClass; ?>">
                                                <?php echo $typeText; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('Y-m-d', strtotime($assignment['due_date'])); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium <?php echo $avgClass; ?>"><?php echo round($assignment['avg_percentage'], 1); ?>%</div>
                                            <div class="text-xs text-gray-500"><?php echo round($assignment['avg_points'], 1); ?> / <?php echo $assignment['points']; ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="relative pt-1">
                                                <div class="flex mb-2 items-center justify-between">
                                                    <div>
                                                        <span class="text-xs font-semibold inline-block text-blue-600">
                                                            <?php echo $assignment['submissions_count']; ?> / <?php echo $assignment['students_count']; ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="text-xs font-semibold inline-block text-blue-600">
                                                            <?php echo round(($assignment['submissions_count'] / $assignment['students_count']) * 100); ?>%
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="overflow-hidden h-2 text-xs flex rounded bg-blue-200">
                                                    <div style="width: <?php echo ($assignment['submissions_count'] / $assignment['students_count']) * 100; ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="/teacher/assignments/<?php echo $assignment['id']; ?>" class="text-blue-600 hover:text-blue-900 ml-3">
                                                <i class="fas fa-eye"></i>
                                                عرض
                                            </a>
                                            <a href="/teacher/assignments/<?php echo $assignment['id']; ?>/analytics" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-chart-bar"></i>
                                                تحليل
                                            </a>
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
    
    <!-- Export Report Modal -->
    <div id="exportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">تصدير تقرير الأداء</h3>
            
            <form id="exportForm" class="space-y-4">
                <div>
                    <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">نوع التقرير</label>
                    <select id="report_type" name="report_type" class="w-full border border-gray-300 rounded-md py-2 px-3">
                        <option value="pdf">ملف PDF</option>
                        <option value="excel">ملف Excel</option>
                        <option value="csv">ملف CSV</option>
                    </select>
                </div>
                
                <div>
                    <label for="report_content" class="block text-sm font-medium text-gray-700 mb-1">محتوى التقرير</label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="include_class_summary" name="include_class_summary" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="include_class_summary" class="mr-2 block text-sm text-gray-700">ملخص الصف</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="include_student_details" name="include_student_details" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="include_student_details" class="mr-2 block text-sm text-gray-700">تفاصيل الطلاب</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="include_assignment_details" name="include_assignment_details" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="include_assignment_details" class="mr-2 block text-sm text-gray-700">تفاصيل المهام</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="include_charts" name="include_charts" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="include_charts" class="mr-2 block text-sm text-gray-700">الرسوم البيانية</label>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" id="cancelExport" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                        إلغاء
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                        تصدير
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/teacher/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Class Progress Chart
            const classProgressCtx = document.getElementById('classProgressChart').getContext('2d');
            const classProgressChart = new Chart(classProgressCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($progressData, 'date')); ?>,
                    datasets: [{
                        label: 'متوسط الصف',
                        data: <?php echo json_encode(array_column($progressData, 'average')); ?>,
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
                            beginAtZero: false,
                            min: Math.max(0, <?php echo $classStats['lowest_grade'] - 10; ?>),
                            max: 100,
                            ticks: {
                                stepSize: 10
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
            
            // Grades Distribution Chart
            const distributionCtx = document.getElementById('gradesDistributionChart').getContext('2d');
            const distributionChart = new Chart(distributionCtx, {
                type: 'bar',
                data: {
                    labels: ['90-100', '80-89', '70-79', '60-69', '0-59'],
                    datasets: [{
                        label: 'عدد الطلاب',
                        data: [
                            <?php echo $performanceLevels['excellent']; ?>,
                            <?php echo $performanceLevels['very_good']; ?>,
                            <?php echo $performanceLevels['good']; ?>,
                            <?php echo $performanceLevels['fair']; ?>,
                            <?php echo $performanceLevels['poor']; ?>
                        ],
                        backgroundColor: [
                            'rgba(72, 187, 120, 0.7)',
                            'rgba(66, 153, 225, 0.7)',
                            'rgba(246, 173, 85, 0.7)',
                            'rgba(237, 137, 54, 0.7)',
                            'rgba(245, 101, 101, 0.7)'
                        ],
                        borderColor: [
                            'rgba(72, 187, 120, 1)',
                            'rgba(66, 153, 225, 1)',
                            'rgba(246, 173, 85, 1)',
                            'rgba(237, 137, 54, 1)',
                            'rgba(245, 101, 101, 1)'
                        ],
                        borderWidth: 1
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
            
            // Export Modal
            $('#exportReportBtn').click(function() {
                $('#exportModal').removeClass('hidden');
            });
            
            $('#cancelExport').click(function() {
                $('#exportModal').addClass('hidden');
            });
            
            // Close modal when clicking outside
            $('#exportModal').click(function(e) {
                if (e.target === this) {
                    $(this).addClass('hidden');
                }
            });
            
            // Handle export form submission
            $('#exportForm').submit(function(e) {
                e.preventDefault();
                
                const reportType = $('#report_type').val();
                const includeClassSummary = $('#include_class_summary').prop('checked');
                const includeStudentDetails = $('#include_student_details').prop('checked');
                const includeAssignmentDetails = $('#include_assignment_details').prop('checked');
                const includeCharts = $('#include_charts').prop('checked');
                
                // Get current filter parameters
                const classId = <?php echo $class['id']; ?>;
                const subjectId = <?php echo $subject['id']; ?>;
                const timePeriod = $('#time_period').val();
                const assignmentType = $('#assignment_type').val();
                
                // Create URL for export
                const url = `/teacher/progress/export?class_id=${classId}&subject_id=${subjectId}&time_period=${timePeriod}&assignment_type=${assignmentType}&report_type=${reportType}&include_class_summary=${includeClassSummary ? 1 : 0}&include_student_details=${includeStudentDetails ? 1 : 0}&include_assignment_details=${includeAssignmentDetails ? 1 : 0}&include_charts=${includeCharts ? 1 : 0}`;
                
                // Redirect to export URL
                window.location.href = url;
                
                // Close modal
                $('#exportModal').addClass('hidden');
            });
        });
    </script>
</body>
</html>