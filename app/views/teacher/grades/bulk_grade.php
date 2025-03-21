<?php
/**
 * app/views/teacher/bulk_grade.php
 * صفحة التصحيح الجماعي للمهام
 * تتيح للمعلم تصحيح إجابات متعددة دفعة واحدة
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التصحيح الجماعي - المنصة التعليمية</title>
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
                <div>
                    <a href="/teacher/assignments/<?php echo $assignment['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm mb-1 inline-block">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة إلى المهمة
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">التصحيح الجماعي: <?php echo htmlspecialchars($assignment['title']); ?></h1>
                    <p class="text-gray-600 mt-1">
                        <span class="ml-4">
                            <i class="fas fa-book ml-1"></i>
                            <?php echo htmlspecialchars($assignment['subject_name']); ?>
                        </span>
                        <span class="ml-4">
                            <i class="fas fa-users ml-1"></i>
                            <?php echo htmlspecialchars($assignment['class_name']); ?>
                        </span>
                        <span>
                            <i class="fas fa-calendar-alt ml-1"></i>
                            تاريخ التسليم: <?php echo date('Y-m-d', strtotime($assignment['due_date'])); ?>
                        </span>
                    </p>
                </div>
                
                <div>
                    <button id="saveAllBtn" type="button" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md inline-flex items-center">
                        <i class="fas fa-save ml-2"></i>
                        حفظ جميع التصحيحات
                    </button>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- Assignment Info Card -->
            <div class="bg-white rounded-md shadow-sm p-4 mb-6">
                <div class="flex flex-wrap items-center">
                    <div class="w-full md:w-1/3 mb-4 md:mb-0">
                        <h3 class="text-lg font-bold text-gray-800 mb-1">معلومات المهمة</h3>
                        <p class="text-gray-600 text-sm">الدرجة الكاملة: <?php echo $assignment['points']; ?> نقطة</p>
                    </div>
                    
                    <div class="w-full md:w-1/3 mb-4 md:mb-0">
                        <div class="flex items-center">
                            <span class="inline-block w-3 h-3 rounded-full bg-blue-500 ml-2"></span>
                            <span class="text-gray-700">إجمالي الإجابات: <strong><?php echo count($submissions); ?></strong></span>
                        </div>
                        <div class="flex items-center mt-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-green-500 ml-2"></span>
                            <span class="text-gray-700">تم تصحيحها: <strong id="gradedCount"><?php echo $stats['graded_count']; ?></strong></span>
                        </div>
                    </div>
                    
                    <div class="w-full md:w-1/3">
                        <div class="flex items-center">
                            <span class="inline-block w-3 h-3 rounded-full bg-yellow-500 ml-2"></span>
                            <span class="text-gray-700">لم يتم تصحيحها: <strong id="ungradedCount"><?php echo count($submissions) - $stats['graded_count']; ?></strong></span>
                        </div>
                        <div class="flex items-center mt-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-red-500 ml-2"></span>
                            <span class="text-gray-700">متأخرة: <strong><?php echo $stats['late_count']; ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Grading Tools -->
            <div class="bg-white rounded-md shadow-sm p-4 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">أدوات التصحيح السريع</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="commonFeedback" class="block text-sm font-medium text-gray-700 mb-1">ملاحظات شائعة</label>
                        <select id="commonFeedback" class="w-full border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">اختر ملاحظة...</option>
                            <option value="أحسنت! إجابة صحيحة ومكتملة.">أحسنت! إجابة صحيحة ومكتملة.</option>
                            <option value="إجابة جيدة ولكن تحتاج إلى مزيد من التفاصيل.">إجابة جيدة ولكن تحتاج إلى مزيد من التفاصيل.</option>
                            <option value="يرجى مراجعة المفاهيم الأساسية وإعادة المحاولة.">يرجى مراجعة المفاهيم الأساسية وإعادة المحاولة.</option>
                            <option value="إجابة غير مكتملة، يرجى التأكد من الإجابة على جميع الأسئلة.">إجابة غير مكتملة، يرجى التأكد من الإجابة على جميع الأسئلة.</option>
                            <option value="أحتاج إلى رؤية المزيد من العمل الخاص بك وليس فقط الإجابة النهائية.">أحتاج إلى رؤية المزيد من العمل الخاص بك وليس فقط الإجابة النهائية.</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="applyPoints" class="block text-sm font-medium text-gray-700 mb-1">تطبيق درجة على المحدد</label>
                        <div class="flex items-center">
                            <input type="number" id="bulkPoints" min="0" max="<?php echo $assignment['points']; ?>" 
                                   class="w-20 border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="الدرجة">
                            <span class="mx-2">من <?php echo $assignment['points']; ?></span>
                            <button id="applyPointsBtn" type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                                تطبيق
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="selectAll" class="block text-sm font-medium text-gray-700 mb-1">تحديد الكل</label>
                        <div class="flex items-center">
                            <button id="selectAllBtn" type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md ml-2">
                                تحديد الكل
                            </button>
                            <button id="unselectAllBtn" type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md">
                                إلغاء التحديد
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submissions -->
            <div class="bg-white rounded-md shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800">إجابات الطلاب</h3>
                </div>
                
                <form id="bulkGradeForm" method="POST" action="/teacher/assignments/bulk-grade/<?php echo $assignment['id']; ?>">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" id="selectAll" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الطالب
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        وقت التسليم
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الحالة
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الدرجة
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ملاحظات
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        إجراءات
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($submissions)): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-4 text-center text-gray-500">
                                            لا توجد إجابات مقدمة لهذه المهمة
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($submissions as $submission): ?>
                                        <tr class="submission-row <?php echo ($submission['status'] === 'graded') ? 'bg-green-50' : ''; ?>">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="selected[]" value="<?php echo $submission['id']; ?>" class="submission-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></div>
                                                <div class="text-gray-500 text-sm"><?php echo htmlspecialchars($submission['email']); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <?php 
                                                $submittedAt = new DateTime($submission['submitted_at']);
                                                $dueDate = new DateTime($assignment['due_date']);
                                                $isLate = $submittedAt > $dueDate;
                                                ?>
                                                <div class="text-gray-900"><?php echo $submittedAt->format('Y-m-d'); ?></div>
                                                <div class="text-gray-500 text-sm"><?php echo $submittedAt->format('H:i'); ?></div>
                                                <?php if ($isLate): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                        متأخر
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <?php
                                                $statusClass = '';
                                                $statusText = '';
                                                
                                                switch ($submission['status']) {
                                                    case 'submitted':
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                        $statusText = 'مقدم';
                                                        break;
                                                    case 'late':
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        $statusText = 'متأخر';
                                                        break;
                                                    case 'graded':
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        $statusText = 'تم التصحيح';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-gray-100 text-gray-800';
                                                        $statusText = $submission['status'];
                                                }
                                                ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <input type="number" name="points[<?php echo $submission['id']; ?>]" 
                                                       class="points-input w-20 border border-gray-300 rounded-md py-1 px-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                       min="0" max="<?php echo $assignment['points']; ?>" 
                                                       value="<?php echo isset($submission['grade_id']) ? $submission['points'] : ''; ?>"
                                                       placeholder="0-<?php echo $assignment['points']; ?>">
                                            </td>
                                            <td class="px-4 py-4">
                                                <textarea name="feedback[<?php echo $submission['id']; ?>]" 
                                                          class="feedback-input w-full border border-gray-300 rounded-md py-1 px-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                          rows="2" placeholder="ملاحظات التصحيح..."><?php echo isset($submission['grade_id']) ? htmlspecialchars($submission['feedback']) : ''; ?></textarea>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm space-x-2 space-x-reverse">
                                                <a href="/teacher/submissions/<?php echo $submission['id']; ?>" class="text-blue-600 hover:text-blue-900 ml-2">
                                                    <i class="fas fa-eye"></i>
                                                    عرض
                                                </a>
                                                <button type="button" data-id="<?php echo $submission['id']; ?>" class="save-single-btn text-green-600 hover:text-green-900">
                                                    <i class="fas fa-check"></i>
                                                    حفظ
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-800 font-medium">جاري حفظ التصحيحات...</p>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/teacher/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Select all checkbox
            $('#selectAll').change(function() {
                $('.submission-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Select all button
            $('#selectAllBtn').click(function() {
                $('.submission-checkbox').prop('checked', true);
                $('#selectAll').prop('checked', true);
            });
            
            // Unselect all button
            $('#unselectAllBtn').click(function() {
                $('.submission-checkbox').prop('checked', false);
                $('#selectAll').prop('checked', false);
            });
            
            // Apply common feedback
            $('#commonFeedback').change(function() {
                const feedbackText = $(this).val();
                if (feedbackText) {
                    $('.submission-checkbox:checked').each(function() {
                        const submissionId = $(this).val();
                        $(`textarea[name="feedback[${submissionId}]"]`).val(feedbackText);
                    });
                }
            });
            
            // Apply points to selected
            $('#applyPointsBtn').click(function() {
                const points = $('#bulkPoints').val();
                if (points !== '') {
                    $('.submission-checkbox:checked').each(function() {
                        const submissionId = $(this).val();
                        $(`input[name="points[${submissionId}]"]`).val(points);
                    });
                }
            });
            
            // Save all button
            $('#saveAllBtn').click(function() {
                // Show loading overlay
                $('#loadingOverlay').removeClass('hidden');
                
                // Get form data
                const formData = $('#bulkGradeForm').serialize();
                
                // Send AJAX request
                $.ajax({
                    url: '/teacher/assignments/bulk-grade/<?php echo $assignment['id']; ?>',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                // Update UI
                                const gradedCount = result.graded_count;
                                $('#gradedCount').text(gradedCount);
                                $('#ungradedCount').text(<?php echo count($submissions); ?> - gradedCount);
                                
                                // Highlight rows
                                result.graded_submissions.forEach(id => {
                                    const row = $(`input[value="${id}"]`).closest('tr');
                                    row.addClass('bg-green-50');
                                    row.find('td:eq(3) span').removeClass().addClass('inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800').text('تم التصحيح');
                                });
                                
                                // Show success message
                                alert(result.message);
                            } else {
                                alert(result.message || 'حدث خطأ أثناء حفظ التصحيحات');
                            }
                        } catch (e) {
                            alert('حدث خطأ أثناء معالجة الاستجابة');
                        }
                        
                        // Hide loading overlay
                        $('#loadingOverlay').addClass('hidden');
                    },
                    error: function() {
                        alert('حدث خطأ أثناء الاتصال بالخادم');
                        $('#loadingOverlay').addClass('hidden');
                    }
                });
            });
            
            // Save single submission
            $('.save-single-btn').click(function() {
                const submissionId = $(this).data('id');
                const points = $(`input[name="points[${submissionId}]"]`).val();
                const feedback = $(`textarea[name="feedback[${submissionId}]"]`).val();
                
                if (!points) {
                    alert('يرجى إدخال الدرجة');
                    return;
                }
                
                // Show loading overlay
                $('#loadingOverlay').removeClass('hidden');
                
                // Send AJAX request
                $.ajax({
                    url: '/teacher/submissions/grade',
                    type: 'POST',
                    data: {
                        submission_id: submissionId,
                        points: points,
                        feedback: feedback
                    },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                // Update UI
                                const row = $(`button[data-id="${submissionId}"]`).closest('tr');
                                row.addClass('bg-green-50');
                                row.find('td:eq(3) span').removeClass().addClass('inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800').text('تم التصحيح');
                                
                                // Update counters
                                const gradedCount = parseInt($('#gradedCount').text()) + 1;
                                $('#gradedCount').text(gradedCount);
                                $('#ungradedCount').text(<?php echo count($submissions); ?> - gradedCount);
                                
                                // Show success message
                                alert('تم حفظ التصحيح بنجاح');
                            } else {
                                alert(result.message || 'حدث خطأ أثناء حفظ التصحيح');
                            }
                        } catch (e) {
                            alert('حدث خطأ أثناء معالجة الاستجابة');
                        }
                        
                        // Hide loading overlay
                        $('#loadingOverlay').addClass('hidden');
                    },
                    error: function() {
                        alert('حدث خطأ أثناء الاتصال بالخادم');
                        $('#loadingOverlay').addClass('hidden');
                    }
                });
            });
            
            // Update counter when checking/unchecking
            $('.submission-checkbox').change(function() {
                const checkedCount = $('.submission-checkbox:checked').length;
                const totalCount = $('.submission-checkbox').length;
            });
        });
    </script>
</body>
</html>