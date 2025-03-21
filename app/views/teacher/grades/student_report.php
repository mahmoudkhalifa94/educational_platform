<!-- app/views/teacher/grades/student_report.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير درجات الطالب - المنصة التعليمية</title>
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
                    <a href="/teacher/grades/class-report/<?php echo $student['class_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm mb-1 inline-block">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة إلى تقرير الصف
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">تقرير درجات الطالب: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- Student Info -->
            <div class="bg-white rounded-md shadow-sm p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">معلومات الطالب</h3>
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                <span class="font-bold text-lg">
                                    <?php 
                                    echo mb_substr($student['first_name'], 0, 1) . 
                                         mb_substr($student['last_name'], 0, 1);
                                    ?>
                                </span>
                            </div>
                            <div class="mr-4">
                                <div class="text-xl font-medium text-gray-900">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="text-sm">
                                <span class="font-medium">الرقم الطلابي:</span>
                                <?php echo htmlspecialchars($student['student_id']); ?>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium">الصف:</span>
                                <?php echo htmlspecialchars($student['class_name']); ?>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium">المرحلة الدراسية:</span>
                                <?php echo htmlspecialchars($student['grade_level']); ?>
                            </div>
                            <?php if (!empty($student['parent_first_name'])): ?>
                                <div class="text-sm">
                                    <span class="font-medium">ولي الأمر:</span>
                                    <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">إحصائيات الأداء</h3>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <div class="w-32 text-sm font-medium text-gray-700">المهام المكتملة:</div>
                                <div class="flex-1 relative h-4 bg-gray-200 rounded-full">
                                    <?php 
                                    $completionRate = 0;
                                    if (isset($stats['total_assignments']) && $stats['total_assignments'] > 0) {
                                        $completionRate = ($stats['submitted_assignments'] / $stats['total_assignments']) * 100;
                                    }
                                    ?>
                                    <div class="absolute top-0 left-0 h-4 bg-green-500 rounded-full" style="width: <?php echo $completionRate; ?>%"></div>
                                </div>
                                <div class="w-12 text-sm text-gray-700 text-center">
                                    <?php echo round($completionRate); ?>%
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="w-32 text-sm font-medium text-gray-700">المعدل العام:</div>
                                <div class="flex-1 relative h-4 bg-gray-200 rounded-full">
                                    <?php 
                                    $overallAverage = isset($stats['overall_average']) ? $stats['overall_average'] : 0;
                                    
                                    $bgColor = 'bg-red-500';
                                    if ($overallAverage >= 90) {
                                        $bgColor = 'bg-green-500';
                                    } elseif ($overallAverage >= 80) {
                                        $bgColor = 'bg-blue-500';
                                    } elseif ($overallAverage >= 70) {
                                        $bgColor = 'bg-yellow-500';
                                    } elseif ($overallAverage >= 60) {
                                        $bgColor = 'bg-orange-500';
                                    }
                                    ?>
                                    <div class="absolute top-0 left-0 h-4 <?php echo $bgColor; ?> rounded-full" style="width: <?php echo $overallAverage; ?>%"></div>
                                </div>
                                <div class="w-12 text-sm text-gray-700 text-center">
                                    <?php echo round($overallAverage); ?>%
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="w-32 text-sm font-medium text-gray-700">المهام المتأخرة:</div>
                                <div class="flex-1 relative h-4 bg-gray-200 rounded-full">
                                    <?php 
                                    $lateRate = 0;
                                    if (isset($stats['submitted_assignments']) && $stats['submitted_assignments'] > 0) {
                                        $lateRate = ($stats['late_assignments'] / $stats['submitted_assignments']) * 100;
                                    }
                                    ?>
                                    <div class="absolute top-0 left-0 h-4 bg-yellow-500 rounded-full" style="width: <?php echo $lateRate; ?>%"></div>
                                </div>
                                <div class="w-12 text-sm text-gray-700 text-center">
                                    <?php echo round($lateRate); ?>%
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="bg-gray-50 p-3 rounded-md">
                                <div class="text-sm text-gray-500">المهام المسندة</div>
                                <div class="text-xl font-bold text-gray-900"><?php echo $stats['total_assignments'] ?? 0; ?></div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-md">
                                <div class="text-sm text-gray-500">المهام المقدمة</div>
                                <div class="text-xl font-bold text-green-600"><?php echo $stats['submitted_assignments'] ?? 0; ?></div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-md">
                                <div class="text-sm text-gray-500">المهام المصححة</div>
                                <div class="text-xl font-bold text-blue-600"><?php echo $stats['graded_assignments'] ?? 0; ?></div>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-md">
                                <div class="text-sm text-gray-500">المهام المتأخرة</div>
                                <div class="text-xl font-bold text-yellow-600"><?php echo $stats['late_assignments'] ?? 0; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">أداء المواد</h3>
                        <canvas id="subjectsChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Subject Grades -->
            <?php foreach ($gradesData as $subjectId => $data): ?>
                <div class="bg-white rounded-md shadow-sm overflow-hidden mb-6">
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($data['subject']['name']); ?> (<?php echo htmlspecialchars($data['subject']['code']); ?>)</h3>
                        
                        <?php if ($data['total_points'] > 0): ?>
                            <?php 
                            $average = $data['average'];
                            
                            $colorClass = 'text-red-600';
                            if ($average >= 90) {
                                $colorClass = 'text-green-600';
                            } elseif ($average >= 80) {
                                $colorClass = 'text-blue-600';
                            } elseif ($average >= 70) {
                                $colorClass = 'text-yellow-600';
                            } elseif ($average >= 60) {
                                $colorClass = 'text-orange-600';
                            }
                            ?>
                            <div class="text-sm font-bold <?php echo $colorClass; ?>">
                                متوسط الدرجات: <?php echo round($average, 1); ?>%
                                <span class="text-gray-500 font-normal">(<?php echo $data['earned_points']; ?>/<?php echo $data['total_points']; ?> نقطة)</span>
                            </div>
                        <?php else: ?>
                            <div class="text-sm text-gray-500">
                                لا توجد درجات بعد
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($data['grades'])): ?>
                        <div class="p-4 text-center text-gray-500">
                            <p>لا توجد مهام في هذه المادة.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            المهمة
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            الدرجة المستحقة
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            الدرجة المحصلة
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            النسبة المئوية
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ملاحظات
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($data['grades'] as $grade): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($grade['assignment']['title']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('Y-m-d', strtotime($grade['assignment']['due_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo $grade['assignment']['points']; ?> نقطة
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                                <?php if ($grade['points'] !== null): ?>
                                                    <?php 
                                                    $colorClass = 'text-red-600';
                                                    if ($grade['percentage'] >= 90) {
                                                        $colorClass = 'text-green-600';
                                                    } elseif ($grade['percentage'] >= 80) {
                                                        $colorClass = 'text-blue-600';
                                                    } elseif ($grade['percentage'] >= 70) {
                                                        $colorClass = 'text-yellow-600';
                                                    } elseif ($grade['percentage'] >= 60) {
                                                        $colorClass = 'text-orange-600';
                                                    }
                                                    ?>
                                                    <div class="text-sm font-medium <?php echo $colorClass; ?>">
                                                        <?php echo $grade['points']; ?> نقطة
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-sm text-gray-500">
                                                        لم يتم التصحيح
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                                <?php if ($grade['percentage'] !== null): ?>
                                                    <div class="inline-block w-12 h-12 rounded-full relative">
                                                        <svg class="w-12 h-12" viewBox="0 0 36 36">
                                                            <path
                                                                d="M18 2.0845
                                                                a 15.9155 15.9155 0 0 1 0 31.831
                                                                a 15.9155 15.9155 0 0 1 0 -31.831"
                                                                fill="none"
                                                                stroke="#eee"
                                                                stroke-width="3"
                                                                stroke-dasharray="100, 100"
                                                            />
                                                            <path
                                                                d="M18 2.0845
                                                                a 15.9155 15.9155 0 0 1 0 31.831
                                                                a 15.9155 15.9155 0 0 1 0 -31.831"
                                                                fill="none"
                                                                stroke="<?php echo $grade['percentage'] >= 90 ? '#48bb78' : ($grade['percentage'] >= 80 ? '#4299e1' : ($grade['percentage'] >= 70 ? '#ecc94b' : ($grade['percentage'] >= 60 ? '#ed8936' : '#e53e3e'))); ?>"
                                                                stroke-width="3"
                                                                stroke-dasharray="<?php echo $grade['percentage']; ?>, 100"
                                                            />
                                                        </svg>
                                                        <div class="absolute inset-0 flex items-center justify-center text-xs font-medium">
                                                            <?php echo round($grade['percentage'], 1); ?>%
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-sm text-gray-500">
                                                        -
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600 max-w-xs truncate">
                                                    <?php echo $grade['feedback'] ? htmlspecialchars($grade['feedback']) : '-'; ?>
                                                </div>
                                                <?php if ($grade['graded_at']): ?>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo date('Y-m-d', strtotime($grade['graded_at'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($gradesData)): ?>
                <div class="bg-white rounded-md shadow-sm p-6 text-center text-gray-500">
                    <p>لا توجد بيانات درجات لهذا الطالب.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/teacher/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        // Subject Performance Chart
        const subjectsChart = new Chart(
            document.getElementById('subjectsChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: [
                        <?php
                        $labels = [];
                        foreach ($gradesData as $subjectId => $data) {
                            $labels[] = "'" . htmlspecialchars($data['subject']['name']) . "'";
                        }
                        echo implode(', ', $labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'متوسط الدرجات (%)',
                        data: [
                            <?php
                            $averages = [];
                            foreach ($gradesData as $subjectId => $data) {
                                $averages[] = $data['average'];
                            }
                            echo implode(', ', $averages);
                            ?>
                        ],
                        backgroundColor: [
                            'rgba(72, 187, 120, 0.7)',
                            'rgba(66, 153, 225, 0.7)',
                            'rgba(236, 201, 75, 0.7)',
                            'rgba(237, 137, 54, 0.7)',
                            'rgba(229, 62, 62, 0.7)'
                        ],
                        borderColor: [
                            'rgba(72, 187, 120, 1)',
                            'rgba(66, 153, 225, 1)',
                            'rgba(236, 201, 75, 1)',
                            'rgba(237, 137, 54, 1)',
                            'rgba(229, 62, 62, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'النسبة المئوية (%)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            }
        );
    </script>
</body>
</html>