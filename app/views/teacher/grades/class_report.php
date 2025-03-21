<!-- app/views/teacher/grades/class_report.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير درجات الصف - المنصة التعليمية</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <!-- Custom Styles -->
    <link href="/assets/css/teacher.css" rel="stylesheet">
    <style>
        .table-fixed-header {
            overflow-y: auto;
            max-height: 600px;
        }
        .table-fixed-header thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background-color: #f9fafb;
        }
    </style>
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
                    <a href="/teacher/grades" class="text-blue-600 hover:text-blue-800 text-sm mb-1 inline-block">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة إلى إدارة الدرجات
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">تقرير درجات الصف: <?php echo htmlspecialchars($class['name']); ?></h1>
                </div>
                
                <div class="flex space-x-3 space-x-reverse">
                    <a href="/teacher/grades/export-class-grades/<?php echo $class['id']; ?>?subject_id=<?php echo $selectedSubject; ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md">
                        <i class="fas fa-file-excel ml-1"></i>
                        تصدير إلى إكسل
                    </a>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- Filter & Info Section -->
            <div class="bg-white rounded-md shadow-sm p-4 mb-6">
                <div class="flex flex-wrap items-center justify-between">
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <div class="text-gray-700">
                            <span class="font-bold">الصف:</span>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </div>
                        <div class="text-gray-700">
                            <span class="font-bold">عدد الطلاب:</span>
                            <?php echo $class['students_count']; ?>
                        </div>
                    </div>
                    
                    <div>
                        <form method="GET" class="flex items-end space-x-3 space-x-reverse">
                            <div>
                                <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">المادة</label>
                                <select id="subject_id" name="subject_id" class="border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubject == $subject['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <!-- Assignments Count -->
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600 ml-4">
                            <i class="fas fa-tasks text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">عدد المهام</p>
                            <h3 class="text-2xl font-bold"><?php echo count($assignments); ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Participation Rate -->
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 ml-4">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">متوسط نسبة المشاركة</p>
                            <?php
                            $totalParticipation = 0;
                            $validAssignments = 0;
                            
                            foreach ($assignments as $assignment) {
                                if (isset($assignment['submissions_count']) && isset($assignment['students_count']) && $assignment['students_count'] > 0) {
                                    $totalParticipation += ($assignment['submissions_count'] / $assignment['students_count']) * 100;
                                    $validAssignments++;
                                }
                            }
                            
                            $avgParticipation = $validAssignments > 0 ? round($totalParticipation / $validAssignments, 1) : 0;
                            ?>
                            <h3 class="text-2xl font-bold"><?php echo $avgParticipation; ?>%</h3>
                        </div>
                    </div>
                </div>
                
                <!-- Average Score -->
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 ml-4">
                            <i class="fas fa-star text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">متوسط الدرجات</p>
                            <?php
                            $totalAverage = 0;
                            $validStudents = 0;
                            
                            foreach ($gradesData as $studentId => $data) {
                                if ($data['total_points'] > 0) {
                                    $totalAverage += $data['average'];
                                    $validStudents++;
                                }
                            }
                            
                            $classAverage = $validStudents > 0 ? round($totalAverage / $validStudents, 1) : 0;
                            ?>
                            <h3 class="text-2xl font-bold"><?php echo $classAverage; ?>%</h3>
                        </div>
                    </div>
                </div>
                
                <!-- Completion Rate -->
                <div class="bg-white rounded-md shadow-sm p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600 ml-4">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">متوسط نسبة الإكمال</p>
                            <?php
                            $totalGraded = 0;
                            $totalSubmissions = 0;
                            
                            foreach ($assignments as $assignment) {
                                if (isset($assignment['graded_count']) && isset($assignment['submissions_count'])) {
                                    $totalGraded += $assignment['graded_count'];
                                    $totalSubmissions += $assignment['submissions_count'];
                                }
                            }
                            
                            $gradingRate = $totalSubmissions > 0 ? round(($totalGraded / $totalSubmissions) * 100, 1) : 0;
                            ?>
                            <h3 class="text-2xl font-bold"><?php echo $gradingRate; ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Average Distribution Chart -->
                <div class="bg-white rounded-md shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">توزيع متوسط الدرجات</h3>
                    <canvas id="averageDistributionChart" height="250"></canvas>
                </div>
                
                <!-- Assignment Performance Chart -->
                <div class="bg-white rounded-md shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">أداء الطلاب في المهام</h3>
                    <canvas id="assignmentPerformanceChart" height="250"></canvas>
                </div>
            </div>
            
            <!-- Grades Table -->
            <div class="bg-white rounded-md shadow-sm overflow-hidden">
                <h3 class="text-lg font-bold text-gray-800 p-4 border-b border-gray-200">سجل الدرجات</h3>
                
                <?php if (empty($assignments)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <p>لا توجد مهام لهذه المادة في هذا الصف.</p>
                    </div>
                <?php else: ?>
                    <div class="table-fixed-header">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider sticky-header">
                                        الطالب
                                    </th>
                                    
                                    <?php foreach ($assignments as $assignment): ?>
                                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider sticky-header">
                                            <div class="whitespace-nowrap"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                            <div class="text-xxs font-normal"><?php echo $assignment['points']; ?> نقطة</div>
                                        </th>
                                    <?php endforeach; ?>
                                    
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider sticky-header">
                                        المتوسط
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($gradesData)): ?>
                                    <tr>
                                        <td colspan="<?php echo count($assignments) + 2; ?>" class="px-4 py-4 text-center text-gray-500">
                                            لا يوجد طلاب في هذا الصف.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($gradesData as $studentId => $data): ?>
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                                        <span class="font-bold text-xs">
                                                            <?php 
                                                            echo mb_substr($data['student']['first_name'], 0, 1) . 
                                                                 mb_substr($data['student']['last_name'], 0, 1);
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <div class="mr-3">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <a href="/teacher/grades/student-report/<?php echo $studentId; ?>" class="hover:text-blue-600">
                                                                <?php echo htmlspecialchars($data['student']['first_name'] . ' ' . $data['student']['last_name']); ?>
                                                            </a>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <?php echo htmlspecialchars($data['student']['student_id']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <?php foreach ($assignments as $assignment): ?>
                                                <td class="px-4 py-4 text-center">
                                                    <?php if (isset($data['grades'][$assignment['id']])): ?>
                                                        <?php 
                                                        $grade = $data['grades'][$assignment['id']];
                                                        if ($grade !== null): 
                                                            $percentage = $grade['percentage'];
                                                            
                                                            $colorClass = 'text-red-600';
                                                            if ($percentage >= 90) {
                                                                $colorClass = 'text-green-600';
                                                            } elseif ($percentage >= 80) {
                                                                $colorClass = 'text-blue-600';
                                                            } elseif ($percentage >= 70) {
                                                                $colorClass = 'text-yellow-600';
                                                            } elseif ($percentage >= 60) {
                                                                $colorClass = 'text-orange-600';
                                                            }
                                                        ?>
                                                            <div class="text-sm font-medium <?php echo $colorClass; ?>">
                                                                <?php echo $grade['points']; ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo round($percentage, 1); ?>%
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-xs text-gray-400">لم يقدم</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-xs text-gray-400">لم يقدم</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            
                                            <td class="px-4 py-4 text-center">
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
                                                        <?php echo round($average, 1); ?>%
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo $data['earned_points']; ?>/<?php echo $data['total_points']; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">لا توجد درجات</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/teacher/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        // Average Distribution Chart
        const averageDistributionChart = new Chart(
            document.getElementById('averageDistributionChart').getContext('2d'),
            {
                type: 'doughnut',
                data: {
                    labels: ['ممتاز (90-100%)', 'جيد جداً (80-89%)', 'جيد (70-79%)', 'مقبول (60-69%)', 'ضعيف (أقل من 60%)'],
                    datasets: [{
                        data: [
                            <?php
                            $distribution = [0, 0, 0, 0, 0];
                            
                            foreach ($gradesData as $studentId => $data) {
                                if ($data['total_points'] > 0) {
                                    $avg = $data['average'];
                                    if ($avg >= 90) $distribution[0]++;
                                    else if ($avg >= 80) $distribution[1]++;
                                    else if ($avg >= 70) $distribution[2]++;
                                    else if ($avg >= 60) $distribution[3]++;
                                    else $distribution[4]++;
                                }
                            }
                            
                            echo implode(', ', $distribution);
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
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            }
        );
        
        // Assignment Performance Chart
        const assignmentPerformanceChart = new Chart(
            document.getElementById('assignmentPerformanceChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: [
                        <?php
                        $labels = [];
                        foreach ($assignments as $assignment) {
                            $labels[] = "'" . htmlspecialchars($assignment['title']) . "'";
                        }
                        echo implode(', ', $labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'متوسط الدرجات (%)',
                        data: [
                            <?php
                            $averages = [];
                            foreach ($assignments as $assignment) {
                                $totalPoints = 0;
                                $totalSubmissions = 0;
                                
                                foreach ($gradesData as $studentId => $data) {
                                    if (isset($data['grades'][$assignment['id']]) && $data['grades'][$assignment['id']] !== null) {
                                        $totalPoints += $data['grades'][$assignment['id']]['percentage'];
                                        $totalSubmissions++;
                                    }
                                }
                                
                                $avg = $totalSubmissions > 0 ? round($totalPoints / $totalSubmissions, 1) : 0;
                                $averages[] = $avg;
                            }
                            
                            echo implode(', ', $averages);
                            ?>
                        ],
                        backgroundColor: 'rgba(66, 153, 225, 0.5)',
                        borderColor: 'rgba(66, 153, 225, 1)',
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
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'المهام'
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