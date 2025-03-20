<!-- app/views/teacher/grades/grade.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تصحيح الإجابة - المنصة التعليمية</title>
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
                    <a href="/teacher/grades" class="text-blue-600 hover:text-blue-800 text-sm mb-1 inline-block">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة إلى قائمة الإجابات
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">تصحيح إجابة الطالب</h1>
                </div>
            </div>
            
            <?php if (isset($flash) && $flash): ?>
                <?php echo $this->showFlash($flash); ?>
            <?php endif; ?>
            
            <!-- Student & Assignment Info -->
            <div class="bg-white rounded-md shadow-sm p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">معلومات الطالب</h3>
                        <div class="flex items-center mb-2">
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
                                <div class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($submission['student_email']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 p-2 rounded-md mt-2">
                            <a href="/teacher/grades/student-report/<?php echo $submission['student_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-chart-line ml-1"></i>
                                عرض تقرير الطالب
                            </a>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">معلومات المهمة</h3>
                        <div class="text-sm mb-1">
                            <span class="font-medium">العنوان:</span>
                            <?php echo htmlspecialchars($submission['assignment_title']); ?>
                        </div>
                        <div class="text-sm mb-1">
                            <span class="font-medium">المادة:</span>
                            <?php echo htmlspecialchars($submission['subject_name']); ?> (<?php echo htmlspecialchars($submission['subject_code']); ?>)
                        </div>
                        <div class="text-sm mb-1">
                            <span class="font-medium">الصف:</span>
                            <?php echo htmlspecialchars($submission['class_name']); ?>
                        </div>
                        <div class="text-sm mb-1">
                            <span class="font-medium">تاريخ التسليم:</span>
                            <?php echo date('Y-m-d', strtotime($submission['assignment_due_date'])); ?>
                        </div>
                        <div class="text-sm mb-1">
                            <span class="font-medium">الدرجة الكاملة:</span>
                            <span class="font-bold"><?php echo $submission['assignment_points']; ?></span> نقطة
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">معلومات الإجابة</h3>
                        <div class="text-sm mb-1">
                            <span class="font-medium">حالة الإجابة:</span>
                            <?php if ($submission['status'] === 'submitted'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">مقدمة</span>
                            <?php elseif ($submission['status'] === 'late'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">متأخرة</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm mb-1">
                            <span class="font-medium">تاريخ التقديم:</span>
                            <?php echo date('Y-m-d H:i', strtotime($submission['submitted_at'])); ?>
                        </div>
                        <?php if ($submission['status'] === 'late'): ?>
                            <div class="bg-yellow-50 p-2 rounded-md mt-2 text-yellow-700 text-sm">
                                <i class="fas fa-exclamation-circle ml-1"></i>
                                تم تقديم هذه الإجابة بعد الموعد المحدد للتسليم.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Submission Content -->
            <div class="bg-white rounded-md shadow-sm p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">محتوى الإجابة</h3>
                
                <?php if (!empty($submission['file_path'])): ?>
                    <div class="mb-4">
                        <h4 class="text-md font-bold text-gray-700 mb-2">الملف المرفق</h4>
                        <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                            <div class="flex items-center">
                                <?php
                                $extension = pathinfo($submission['file_path'], PATHINFO_EXTENSION);
                                $iconClass = 'fas fa-file';
                                
                                switch (strtolower($extension)) {
                                    case 'pdf':
                                        $iconClass = 'fas fa-file-pdf text-red-500';
                                        break;
                                    case 'doc':
                                    case 'docx':
                                        $iconClass = 'fas fa-file-word text-blue-500';
                                        break;
                                    case 'xls':
                                    case 'xlsx':
                                        $iconClass = 'fas fa-file-excel text-green-500';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        $iconClass = 'fas fa-file-powerpoint text-orange-500';
                                        break;
                                    case 'jpg':
                                    case 'jpeg':
                                    case 'png':
                                    case 'gif':
                                        $iconClass = 'fas fa-file-image text-purple-500';
                                        break;
                                }
                                ?>
                                <i class="<?php echo $iconClass; ?> text-3xl ml-4"></i>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars(basename($submission['file_path'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php
                                        if (file_exists($submission['file_path'])) {
                                            echo Helper::formatFileSize(filesize($submission['file_path']));
                                        }
                                        ?>
                                    </div>
                                </div>
                                <a href="/teacher/submissions/download/<?php echo $submission['id']; ?>" class="mr-auto bg-blue-600 hover:bg-blue-700 text-white font-medium py-1 px-3 rounded-md text-sm">
                                    <i class="fas fa-download ml-1"></i>
                                    تنزيل
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($submission['content'])): ?>
                    <div class="mb-4">
                        <h4 class="text-md font-bold text-gray-700 mb-2">النص</h4>
                        <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                            <div class="text-gray-800 whitespace-pre-wrap">
                                <?php echo htmlspecialchars($submission['content']); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($submission['file_path']) && empty($submission['content'])): ?>
                    <div class="text-center text-gray-500 py-4">
                        لا يوجد محتوى للإجابة
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Grading Form -->
            <div class="bg-white rounded-md shadow-sm overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">نموذج التصحيح</h3>
                    
                    <form action="/teacher/grades/save-grade/<?php echo $submission['id']; ?>" method="POST" class="space-y-6">
                        <div>
                            <label for="points" class="block text-sm font-medium text-gray-700 mb-1">الدرجة <span class="text-red-600">*</span></label>
                            <div class="flex items-center">
                                <input type="number" id="points" name="points" required 
                                       class="w-24 border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="0"
                                       min="0" max="<?php echo $submission['assignment_points']; ?>" step="0.5">
                                <span class="mr-2 text-gray-600">من <?php echo $submission['assignment_points']; ?> نقطة</span>
                            </div>
                        </div>
                        
                        <div>
                            <label for="feedback" class="block text-sm font-medium text-gray-700 mb-1">ملاحظات التصحيح <span class="text-red-600">*</span></label>
                            <textarea id="feedback" name="feedback" required
                                     class="w-full h-32 border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                     placeholder="أدخل ملاحظاتك على إجابة الطالب..."></textarea>
                        </div>
                        
                        <div class="flex items-center justify-between pt-4">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-info-circle ml-1"></i>
                                سيتم إرسال إشعار للطالب عند حفظ التصحيح.
                            </div>
                            
                            <div class="flex space-x-2 space-x-reverse">
                                <a href="/teacher/grades" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md">
                                    إلغاء
                                </a>
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md">
                                    <i class="fas fa-check ml-1"></i>
                                    حفظ التصحيح
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once 'app/views/teacher/shared/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
</body>
</html>