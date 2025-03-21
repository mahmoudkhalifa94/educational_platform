<?php
/**
 * app/controllers/teacher/ProgressController.php
 * متحكم تحليل تقدم الطلاب للمعلمين
 * يدير عمليات عرض وتحليل أداء الطلاب في الصفوف والمواد
 */
class ProgressController extends Controller
{
    private $classModel;
    private $subjectModel;
    private $studentModel;
    private $assignmentModel;
    private $submissionModel;
    private $gradeModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->classModel = new ClassModel();
        $this->subjectModel = new Subject();
        $this->studentModel = new Student();
        $this->assignmentModel = new Assignment();
        $this->submissionModel = new Submission();
        $this->gradeModel = new Grade();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * عرض صفحة تحليل تقدم الطلاب
     */
    public function index()
    {
        // استخراج المعلمات
        $classId = $this->request->get('class_id');
        $subjectId = $this->request->get('subject_id');
        
        if (!$classId || !$subjectId) {
            $this->setFlash('error', 'يرجى تحديد الصف والمادة.');
            $this->redirect('/teacher/classes');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        $teacherId = $this->getTeacherId();
        $hasAccess = $this->checkTeacherAccess($teacherId, $classId, $subjectId);
        
        if (!$hasAccess) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف أو المادة.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على معلومات الصف والمادة
        $class = $this->classModel->find($classId);
        $subject = $this->subjectModel->find($subjectId);
        
        // استخراج معلمات التصفية
        $filters = [
            'time_period' => $this->request->get('time_period', 'all'),
            'assignment_type' => $this->request->get('assignment_type', 'all'),
            'student_filter' => $this->request->get('student_filter', 'alphabetical')
        ];
        
        // الحصول على بيانات أداء الطلاب والصف
        $data = $this->getClassPerformanceData($classId, $subjectId, $filters);
        
        echo $this->render('teacher/student_progress', [
            'class' => $class,
            'subject' => $subject,
            'students' => $data['students'],
            'assignments' => $data['assignments'],
            'classAvg' => $data['classAvg'],
            'classStats' => $data['classStats'],
            'performanceLevels' => $data['performanceLevels'],
            'progressData' => $data['progressData'],
            'filters' => $filters,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تصدير تقرير أداء الطلاب
     */
    public function export()
    {
        // استخراج المعلمات
        $classId = $this->request->get('class_id');
        $subjectId = $this->request->get('subject_id');
        $reportType = $this->request->get('report_type', 'pdf');
        
        // استخراج خيارات المحتوى
        $includeClassSummary = $this->request->get('include_class_summary', 1);
        $includeStudentDetails = $this->request->get('include_student_details', 1);
        $includeAssignmentDetails = $this->request->get('include_assignment_details', 1);
        $includeCharts = $this->request->get('include_charts', 1);
        
        if (!$classId || !$subjectId) {
            $this->setFlash('error', 'يرجى تحديد الصف والمادة.');
            $this->redirect('/teacher/classes');
        }
        
        // التحقق من صلاحية الوصول للمعلم
        $teacherId = $this->getTeacherId();
        $hasAccess = $this->checkTeacherAccess($teacherId, $classId, $subjectId);
        
        if (!$hasAccess) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الصف أو المادة.');
            $this->redirect('/teacher/classes');
        }
        
        // الحصول على معلومات الصف والمادة
        $class = $this->classModel->find($classId);
        $subject = $this->subjectModel->find($subjectId);
        
        // استخراج معلمات التصفية
        $filters = [
            'time_period' => $this->request->get('time_period', 'all'),
            'assignment_type' => $this->request->get('assignment_type', 'all')
        ];
        
        // الحصول على بيانات الأداء
        $data = $this->getClassPerformanceData($classId, $subjectId, $filters);
        
        // إنشاء اسم الملف
        $fileName = 'تقرير_أداء_' . str_replace(' ', '_', $class['name']) . '_' . str_replace(' ', '_', $subject['name']) . '_' . date('Y-m-d');
        
        // تصدير التقرير بالتنسيق المطلوب
        switch ($reportType) {
            case 'excel':
                $this->exportExcel($fileName, $class, $subject, $data, [
                    'includeClassSummary' => $includeClassSummary,
                    'includeStudentDetails' => $includeStudentDetails,
                    'includeAssignmentDetails' => $includeAssignmentDetails
                ]);
                break;
                
            case 'csv':
                $this->exportCSV($fileName, $class, $subject, $data, [
                    'includeClassSummary' => $includeClassSummary,
                    'includeStudentDetails' => $includeStudentDetails,
                    'includeAssignmentDetails' => $includeAssignmentDetails
                ]);
                break;
                
            case 'pdf':
            default:
                $this->exportPDF($fileName, $class, $subject, $data, [
                    'includeClassSummary' => $includeClassSummary,
                    'includeStudentDetails' => $includeStudentDetails,
                    'includeAssignmentDetails' => $includeAssignmentDetails,
                    'includeCharts' => $includeCharts
                ]);
                break;
        }
    }
    
    /**
     * الحصول على بيانات أداء الصف والطلاب
     * 
     * @param int $classId معرّف الصف
     * @param int $subjectId معرّف المادة
     * @param array $filters معلمات التصفية
     * @return array بيانات الأداء
     */
    private function getClassPerformanceData($classId, $subjectId, $filters)
    {
        // الحصول على قائمة المهام
        $assignments = $this->getFilteredAssignments($classId, $subjectId, $filters);
        
        // الحصول على قائمة الطلاب
        $students = $this->getStudentsWithPerformance($classId, $subjectId, $assignments, $filters);
        
        // حساب متوسط الصف
        $classAvg = 0;
        $studentCount = count($students);
        
        if ($studentCount > 0) {
            $totalAvg = array_sum(array_column($students, 'average'));
            $classAvg = $totalAvg / $studentCount;
        }
        
        // حساب إحصائيات الصف
        $classStats = $this->calculateClassStats($students, $assignments);
        
        // حساب مستويات الأداء
        $performanceLevels = $this->calculatePerformanceLevels($students);
        
        // إعداد بيانات تطور الصف
        $progressData = $this->getClassProgressData($classId, $subjectId, $filters);
        
        return [
            'students' => $students,
            'assignments' => $assignments,
            'classAvg' => $classAvg,
            'classStats' => $classStats,
            'performanceLevels' => $performanceLevels,
            'progressData' => $progressData
        ];
    }
    
    /**
     * الحصول على قائمة المهام المصفاة
     * 
     * @param int $classId معرّف الصف
     * @param int $subjectId معرّف المادة
     * @param array $filters معلمات التصفية
     * @return array قائمة المهام
     */
    private function getFilteredAssignments($classId, $subjectId, $filters)
    {
        // بناء شرط التصفية الزمنية
        $timeCondition = '';
        $timeParams = [];
        
        switch ($filters['time_period']) {
            case 'term1':
                // الفصل الدراسي الأول (سبتمبر إلى يناير)
                $timeCondition = " AND ((MONTH(a.due_date) >= 9 AND MONTH(a.due_date) <= 12) OR MONTH(a.due_date) = 1)";
                break;
                
            case 'term2':
                // الفصل الدراسي الثاني (فبراير إلى يونيو)
                $timeCondition = " AND MONTH(a.due_date) >= 2 AND MONTH(a.due_date) <= 6";
                break;
                
            case 'last_month':
                // الشهر الماضي
                $timeCondition = " AND a.due_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
        }
        
        // بناء شرط نوع المهمة
        $typeCondition = '';
        $typeParams = [];
        
        if ($filters['assignment_type'] !== 'all') {
            $typeCondition = " AND a.type = ?";
            $typeParams = [$filters['assignment_type']];
        }
        
        // الحصول على قائمة المهام
        $query = "SELECT a.*, 
                 (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id) as submissions_count,
                 (SELECT COUNT(*) FROM students st WHERE st.class_id = a.class_id) as students_count,
                 (SELECT AVG(g.points) FROM grades g JOIN submissions sub ON g.submission_id = sub.id WHERE sub.assignment_id = a.id) as avg_points,
                 (SELECT AVG(g.points / a.points * 100) FROM grades g JOIN submissions sub ON g.submission_id = sub.id WHERE sub.assignment_id = a.id) as avg_percentage
                 FROM assignments a 
                 WHERE a.class_id = ? AND a.subject_id = ? AND a.is_published = 1" . $timeCondition . $typeCondition . "
                 ORDER BY a.due_date DESC";
        
        $params = array_merge([$classId, $subjectId], $typeParams);
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على قائمة الطلاب مع بيانات الأداء
     * 
     * @param int $classId معرّف الصف
     * @param int $subjectId معرّف المادة
     * @param array $assignments قائمة المهام
     * @param array $filters معلمات التصفية
     * @return array قائمة الطلاب
     */
    private function getStudentsWithPerformance($classId, $subjectId, $assignments, $filters)
    {
        // الحصول على قائمة الطلاب في الصف
        $students = $this->studentModel->getStudentsByClass($classId);
        
        if (empty($students) || empty($assignments)) {
            return $students;
        }
        
        // معرّفات المهام
        $assignmentIds = array_column($assignments, 'id');
        
        // لكل طالب، حساب المعدل ونسبة التسليم
        foreach ($students as &$student) {
            // الحصول على إجابات الطالب
            $submissions = $this->submissionModel->getStudentSubmissions($student['id']);
            
            // تصفية الإجابات حسب المهام المحددة
            $filteredSubmissions = array_filter($submissions, function($sub) use ($assignmentIds) {
                return in_array($sub['assignment_id'], $assignmentIds);
            });
            
            // حساب نسبة التسليم
            $student['assignment_count'] = count($assignments);
            $student['submitted_count'] = count($filteredSubmissions);
            $student['submission_rate'] = $student['assignment_count'] > 0 ? 
                ($student['submitted_count'] / $student['assignment_count']) * 100 : 0;
            
            // حساب عدد التسليمات المتأخرة
            $student['late_count'] = count(array_filter($filteredSubmissions, function($sub) {
                return $sub['status'] === 'late';
            }));
            
            // حساب المعدل العام
            $grades = array_filter($filteredSubmissions, function($sub) {
                return isset($sub['grade_id']) && $sub['grade_id'] !== null;
            });
            
            if (!empty($grades)) {
                $totalPercentage = 0;
                $gradedCount = 0;
                
                foreach ($grades as $grade) {
                    // البحث عن المهمة المطابقة
                    $assignment = array_filter($assignments, function($a) use ($grade) {
                        return $a['id'] == $grade['assignment_id'];
                    });
                    
                    if (!empty($assignment)) {
                        $assignment = reset($assignment);
                        $totalPercentage += ($grade['points'] / $assignment['points']) * 100;
                        $gradedCount++;
                    }
                }
                
                $student['average'] = $gradedCount > 0 ? $totalPercentage / $gradedCount : 0;
            } else {
                $student['average'] = 0;
            }
            
            // حساب نسبة التحسن
            $student['improvement'] = $this->calculateStudentImprovement($student['id'], $subjectId);
        }
        
        // ترتيب الطلاب حسب المعيار المحدد
        switch ($filters['student_filter']) {
            case 'highest':
                usort($students, function($a, $b) {
                    return $b['average'] - $a['average'];
                });
                break;
                
            case 'lowest':
                usort($students, function($a, $b) {
                    return $a['average'] - $b['average'];
                });
                break;
                
            case 'most_improved':
                usort($students, function($a, $b) {
                    return $b['improvement'] - $a['improvement'];
                });
                break;
                
            case 'needs_attention':
                usort($students, function($a, $b) {
                    // ترتيب الطلاب الذين لديهم معدل منخفض ونسبة تسليم منخفضة
                    $aScore = ($a['average'] < 60 ? 1 : 0) + ($a['submission_rate'] < 70 ? 1 : 0);
                    $bScore = ($b['average'] < 60 ? 1 : 0) + ($b['submission_rate'] < 70 ? 1 : 0);
                    
                    if ($aScore === $bScore) {
                        return $a['average'] - $b['average'];
                    }
                    
                    return $bScore - $aScore;
                });
                break;
                
            case 'alphabetical':
            default:
                usort($students, function($a, $b) {
                    return strcmp($a['first_name'] . ' ' . $a['last_name'], $b['first_name'] . ' ' . $b['last_name']);
                });
                break;
        }
        
        return $students;
    }
    
    /**
     * حساب إحصائيات الصف
     * 
     * @param array $students قائمة الطلاب
     * @param array $assignments قائمة المهام
     * @return array الإحصائيات
     */
    private function calculateClassStats($students, $assignments)
    {
        $stats = [
            'total_assignments' => count($assignments),
            'highest_grade' => 0,
            'lowest_grade' => 100,
            'submission_rate' => 0,
            'success_rate' => 0,
            'improvement_rate' => 0,
            'late_submission_rate' => 0
        ];
        
        if (empty($students)) {
            return $stats;
        }
        
        // حساب أعلى وأدنى درجة
        foreach ($students as $student) {
            $stats['highest_grade'] = max($stats['highest_grade'], $student['average']);
            $stats['lowest_grade'] = min($stats['lowest_grade'], $student['average']);
        }
        
        // حساب متوسط نسبة التسليم
        $stats['submission_rate'] = array_sum(array_column($students, 'submission_rate')) / count($students);
        
        // حساب نسبة النجاح
        $passingStudents = array_filter($students, function($student) {
            return $student['average'] >= 60;
        });
        $stats['success_rate'] = count($passingStudents) / count($students) * 100;
        
        // حساب متوسط نسبة التحسن
        $stats['improvement_rate'] = array_sum(array_column($students, 'improvement')) / count($students);
        
        // حساب نسبة التسليم المتأخر
        $totalSubmissions = array_sum(array_column($students, 'submitted_count'));
        $totalLate = array_sum(array_column($students, 'late_count'));
        $stats['late_submission_rate'] = $totalSubmissions > 0 ? ($totalLate / $totalSubmissions) * 100 : 0;
        
        return $stats;
    }
    
    /**
     * حساب مستويات الأداء
     * 
     * @param array $students قائمة الطلاب
     * @return array إحصائيات المستويات
     */
    private function calculatePerformanceLevels($students)
    {
        $levels = [
            'excellent' => 0,  // 90-100
            'very_good' => 0,  // 80-89
            'good' => 0,       // 70-79
            'fair' => 0,       // 60-69
            'poor' => 0        // أقل من 60
        ];
        
        foreach ($students as $student) {
            if ($student['average'] >= 90) {
                $levels['excellent']++;
            } elseif ($student['average'] >= 80) {
                $levels['very_good']++;
            } elseif ($student['average'] >= 70) {
                $levels['good']++;
            } elseif ($student['average'] >= 60) {
                $levels['fair']++;
            } else {
                $levels['poor']++;
            }
        }
        
        return $levels;
    }
    
    /**
     * حساب نسبة تحسن الطالب
     * 
     * @param int $studentId معرّف الطالب
     * @param int $subjectId معرّف المادة
     * @return float نسبة التحسن
     */
    private function calculateStudentImprovement($studentId, $subjectId)
    {
        // الحصول على درجات الطالب مرتبة حسب التاريخ
        $query = "SELECT g.points, a.points as total_points, a.due_date
                 FROM grades g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 WHERE sub.student_id = ? AND a.subject_id = ? 
                 ORDER BY a.due_date ASC";
        
        $grades = $this->db->fetchAll($query, [$studentId, $subjectId]);
        
        if (count($grades) < 2) {
            return 0; // لا يوجد تحسن إذا كان هناك أقل من درجتين
        }
        
        // تقسيم الدرجات إلى نصفين: النصف الأول والنصف الثاني
        $midpoint = floor(count($grades) / 2);
        $firstHalf = array_slice($grades, 0, $midpoint);
        $secondHalf = array_slice($grades, $midpoint);
        
        // حساب متوسط النصف الأول
        $firstHalfTotal = 0;
        $firstHalfPoints = 0;
        foreach ($firstHalf as $grade) {
            $firstHalfTotal += $grade['total_points'];
            $firstHalfPoints += $grade['points'];
        }
        $firstHalfAvg = $firstHalfTotal > 0 ? ($firstHalfPoints / $firstHalfTotal) * 100 : 0;
        
        // حساب متوسط النصف الثاني
        $secondHalfTotal = 0;
        $secondHalfPoints = 0;
        foreach ($secondHalf as $grade) {
            $secondHalfTotal += $grade['total_points'];
            $secondHalfPoints += $grade['points'];
        }
        $secondHalfAvg = $secondHalfTotal > 0 ? ($secondHalfPoints / $secondHalfTotal) * 100 : 0;
        
        // حساب نسبة التحسن
        return $secondHalfAvg - $firstHalfAvg;
    }
    
    /**
     * الحصول على بيانات تطور الصف
     * 
     * @param int $classId معرّف الصف
     * @param int $subjectId معرّف المادة
     * @param array $filters معلمات التصفية
     * @return array بيانات التطور
     */
    private function getClassProgressData($classId, $subjectId, $filters)
    {
        // بناء شرط التصفية
        $typeCondition = '';
        $typeParams = [];
        
        if ($filters['assignment_type'] !== 'all') {
            $typeCondition = " AND a.type = ?";
            $typeParams = [$filters['assignment_type']];
        }
        
        // الحصول على المهام مرتبة حسب تاريخ التسليم
        $query = "SELECT a.id, a.title, a.due_date, a.points,
                 (SELECT AVG(g.points / a.points * 100) 
                  FROM grades g 
                  JOIN submissions sub ON g.submission_id = sub.id 
                  WHERE sub.assignment_id = a.id) as average
                 FROM assignments a 
                 WHERE a.class_id = ? AND a.subject_id = ? AND a.is_published = 1" . $typeCondition . "
                 ORDER BY a.due_date ASC";
        
        $params = array_merge([$classId, $subjectId], $typeParams);
        $assignments = $this->db->fetchAll($query, $params);
        
        // تنسيق البيانات للرسم البياني
        $progressData = [];
        
        foreach ($assignments as $assignment) {
            if ($assignment['average'] !== null) {
                $progressData[] = [
                    'date' => date('Y-m-d', strtotime($assignment['due_date'])),
                    'title' => $assignment['title'],
                    'average' => round($assignment['average'], 1)
                ];
            }
        }
        
        return $progressData;
    }
    
    /**
     * تصدير التقرير بتنسيق PDF
     * 
     * @param string $fileName اسم الملف
     * @param array $class بيانات الصف
     * @param array $subject بيانات المادة
     * @param array $data بيانات الأداء
     * @param array $options خيارات التصدير
     */
    private function exportPDF($fileName, $class, $subject, $data, $options)
    {
        // التحقق من وجود مكتبة TCPDF
        if (!class_exists('TCPDF')) {
            // إذا لم تكن المكتبة موجودة، سنقوم بإنشاء وتنزيل ملف HTML بدلاً من ذلك
            $this->exportHTML($fileName, $class, $subject, $data, $options);
            return;
        }
        
        // إنشاء كائن PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        
        // إعداد معلومات المستند
        $pdf->SetCreator('المنصة التعليمية');
        $pdf->SetAuthor('المنصة التعليمية');
        $pdf->SetTitle('تقرير أداء الطلاب');
        $pdf->SetSubject('تقرير أداء الطلاب - ' . $class['name'] . ' - ' . $subject['name']);
        
        // إعداد الهامش
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // إضافة صفحة جديدة
        $pdf->AddPage();
        
        // إعداد الخط العربي
        $pdf->SetFont('aealarabiya', '', 14);
        
        // إنشاء الترويسة
        $pdf->Cell(0, 10, 'تقرير أداء الطلاب', 0, 1, 'C');
        $pdf->Cell(0, 10, 'الصف: ' . $class['name'] . ' - المادة: ' . $subject['name'], 0, 1, 'C');
        $pdf->Cell(0, 10, 'تاريخ التقرير: ' . date('Y-m-d'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // إضافة قسم ملخص الصف
        if ($options['includeClassSummary']) {
            $pdf->SetFont('aealarabiya', 'B', 14);
            $pdf->Cell(0, 10, 'ملخص أداء الصف', 0, 1, 'R');
            $pdf->SetFont('aealarabiya', '', 12);
            
            $pdf->Cell(90, 8, 'متوسط الصف: ' . round($data['classAvg'], 1) . '%', 0, 0, 'R');
            $pdf->Cell(90, 8, 'عدد الطلاب: ' . count($data['students']), 0, 1, 'R');
            
            $pdf->Cell(90, 8, 'عدد المهام: ' . $data['classStats']['total_assignments'], 0, 0, 'R');
            $pdf->Cell(90, 8, 'نسبة النجاح: ' . round($data['classStats']['success_rate']) . '%', 0, 1, 'R');
            
            $pdf->Cell(90, 8, 'نسبة التسليم: ' . round($data['classStats']['submission_rate']) . '%', 0, 0, 'R');
            $pdf->Cell(90, 8, 'نسبة التسليم المتأخر: ' . round($data['classStats']['late_submission_rate']) . '%', 0, 1, 'R');
            
            $pdf->Ln(5);
            
            // مستويات الأداء
            $pdf->SetFont('aealarabiya', 'B', 12);
            $pdf->Cell(0, 8, 'توزيع مستويات الأداء:', 0, 1, 'R');
            $pdf->SetFont('aealarabiya', '', 12);
            
            $pdf->Cell(90, 6, 'متفوق (90-100%): ' . $data['performanceLevels']['excellent'] . ' طالب', 0, 0, 'R');
            $pdf->Cell(90, 6, 'جيد جدًا (80-89%): ' . $data['performanceLevels']['very_good'] . ' طالب', 0, 1, 'R');
            
            $pdf->Cell(90, 6, 'جيد (70-79%): ' . $data['performanceLevels']['good'] . ' طالب', 0, 0, 'R');
            $pdf->Cell(90, 6, 'مقبول (60-69%): ' . $data['performanceLevels']['fair'] . ' طالب', 0, 1, 'R');
            
            $pdf->Cell(0, 6, 'ضعيف (أقل من 60%): ' . $data['performanceLevels']['poor'] . ' طالب', 0, 1, 'R');
            
            $pdf->Ln(10);
        }
        
        // إضافة قسم تفاصيل الطلاب
        if ($options['includeStudentDetails'] && !empty($data['students'])) {
            $pdf->SetFont('aealarabiya', 'B', 14);
            $pdf->Cell(0, 10, 'تفاصيل أداء الطلاب', 0, 1, 'R');
            
            // إنشاء جدول الطلاب
            $pdf->SetFont('aealarabiya', 'B', 10);
            
            // ترويسة الجدول
            $pdf->Cell(35, 8, 'المتوسط', 1, 0, 'C');
            $pdf->Cell(30, 8, 'نسبة التسليم', 1, 0, 'C');
            $pdf->Cell(25, 8, 'المتأخر', 1, 0, 'C');
            $pdf->Cell(20, 8, 'التطور', 1, 0, 'C');
            $pdf->Cell(70, 8, 'الطالب', 1, 1, 'C');
            
            // صفوف الجدول
            $pdf->SetFont('aealarabiya', '', 10);
            
            foreach ($data['students'] as $student) {
                $pdf->Cell(35, 8, round($student['average'], 1) . '%', 1, 0, 'C');
                $pdf->Cell(30, 8, round($student['submission_rate']) . '%', 1, 0, 'C');
                $pdf->Cell(25, 8, $student['late_count'] . ' من ' . $student['submitted_count'], 1, 0, 'C');
                
                $improvementText = $student['improvement'] > 5 ? '+' . round($student['improvement'], 1) . '%' : 
                    ($student['improvement'] < -5 ? round($student['improvement'], 1) . '%' : 'مستقر');
                
                fputcsv($output, [
                    $student['first_name'] . ' ' . $student['last_name'],
                    round($student['average'], 1) . '%',
                    round($student['submission_rate']) . '%',
                    $student['late_count'] . ' من ' . $student['submitted_count'],
                    $improvementText
                ]);
            }
            
            fputcsv($output, []); // سطر فارغ
        }
        
        // إضافة قسم تفاصيل المهام
        if ($options['includeAssignmentDetails'] && !empty($data['assignments'])) {
            fputcsv($output, ['تفاصيل أداء المهام']);
            fputcsv($output, ['المهمة', 'النوع', 'تاريخ التسليم', 'نسبة التسليم', 'متوسط الدرجات']);
            
            foreach ($data['assignments'] as $assignment) {
                $typeText = '';
                switch ($assignment['type']) {
                    case 'quiz': $typeText = 'اختبار قصير'; break;
                    case 'homework': $typeText = 'واجب منزلي'; break;
                    case 'project': $typeText = 'مشروع'; break;
                    case 'exam': $typeText = 'اختبار'; break;
                    default: $typeText = $assignment['type'];
                }
                
                $submissionRate = $assignment['students_count'] > 0 ? 
                    round(($assignment['submissions_count'] / $assignment['students_count']) * 100) . '%' : '-';
                
                $avgPercentage = isset($assignment['avg_percentage']) ? round($assignment['avg_percentage'], 1) . '%' : '-';
                
                fputcsv($output, [
                    $assignment['title'],
                    $typeText,
                    date('Y-m-d', strtotime($assignment['due_date'])),
                    $submissionRate,
                    $avgPercentage
                ]);
            }
        }
        
        // إغلاق المخرج
        fclose($output);
        exit();
    }
    
    /**
     * تصدير التقرير بتنسيق HTML (بديل لـ PDF)
     * 
     * @param string $fileName اسم الملف
     * @param array $class بيانات الصف
     * @param array $subject بيانات المادة
     * @param array $data بيانات الأداء
     * @param array $options خيارات التصدير
     */
    private function exportHTML($fileName, $class, $subject, $data, $options)
    {
        // إنشاء محتوى HTML
        $html = '<!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>تقرير أداء الطلاب</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2 { text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                table, th, td { border: 1px solid #ddd; }
                th, td { padding: 8px; text-align: right; }
                th { background-color: #f2f2f2; }
                .excellent { color: #48bb78; }
                .very-good { color: #4299e1; }
                .good { color: #f6ad55; }
                .fair { color: #ed8936; }
                .poor { color: #f56565; }
            </style>
        </head>
        <body>
            <h1>تقرير أداء الطلاب</h1>
            <h2>الصف: ' . htmlspecialchars($class['name']) . ' - المادة: ' . htmlspecialchars($subject['name']) . '</h2>
            <p style="text-align: center;">تاريخ التقرير: ' . date('Y-m-d') . '</p>';
        
        // إضافة قسم ملخص الصف
        if ($options['includeClassSummary']) {
            $html .= '
            <h2>ملخص أداء الصف</h2>
            <table>
                <tr>
                    <td>متوسط الصف:</td>
                    <td>' . round($data['classAvg'], 1) . '%</td>
                    <td>عدد الطلاب:</td>
                    <td>' . count($data['students']) . '</td>
                </tr>
                <tr>
                    <td>عدد المهام:</td>
                    <td>' . $data['classStats']['total_assignments'] . '</td>
                    <td>نسبة النجاح:</td>
                    <td>' . round($data['classStats']['success_rate']) . '%</td>
                </tr>
                <tr>
                    <td>نسبة التسليم:</td>
                    <td>' . round($data['classStats']['submission_rate']) . '%</td>
                    <td>نسبة التسليم المتأخر:</td>
                    <td>' . round($data['classStats']['late_submission_rate']) . '%</td>
                </tr>
            </table>
            
            <h3>توزيع مستويات الأداء:</h3>
            <table>
                <tr>
                    <td>متفوق (90-100%):</td>
                    <td>' . $data['performanceLevels']['excellent'] . ' طالب</td>
                    <td>جيد جدًا (80-89%):</td>
                    <td>' . $data['performanceLevels']['very_good'] . ' طالب</td>
                </tr>
                <tr>
                    <td>جيد (70-79%):</td>
                    <td>' . $data['performanceLevels']['good'] . ' طالب</td>
                    <td>مقبول (60-69%):</td>
                    <td>' . $data['performanceLevels']['fair'] . ' طالب</td>
                </tr>
                <tr>
                    <td>ضعيف (أقل من 60%):</td>
                    <td>' . $data['performanceLevels']['poor'] . ' طالب</td>
                    <td></td>
                    <td></td>
                </tr>
            </table>';
        }
        
        // إضافة قسم تفاصيل الطلاب
        if ($options['includeStudentDetails'] && !empty($data['students'])) {
            $html .= '
            <h2>تفاصيل أداء الطلاب</h2>
            <table>
                <tr>
                    <th>الطالب</th>
                    <th>المتوسط</th>
                    <th>نسبة التسليم</th>
                    <th>المتأخر</th>
                    <th>التطور</th>
                </tr>';
            
            foreach ($data['students'] as $student) {
                $averageClass = '';
                if ($student['average'] >= 90) {
                    $averageClass = 'excellent';
                } elseif ($student['average'] >= 80) {
                    $averageClass = 'very-good';
                } elseif ($student['average'] >= 70) {
                    $averageClass = 'good';
                } elseif ($student['average'] >= 60) {
                    $averageClass = 'fair';
                } else {
                    $averageClass = 'poor';
                }
                
                $improvementText = $student['improvement'] > 5 ? '+' . round($student['improvement'], 1) . '%' : 
                    ($student['improvement'] < -5 ? round($student['improvement'], 1) . '%' : 'مستقر');
                
                $improvementClass = $student['improvement'] > 5 ? 'excellent' : 
                    ($student['improvement'] < -5 ? 'poor' : '');
                
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</td>
                    <td class="' . $averageClass . '">' . round($student['average'], 1) . '%</td>
                    <td>' . round($student['submission_rate']) . '%</td>
                    <td>' . $student['late_count'] . ' من ' . $student['submitted_count'] . '</td>
                    <td class="' . $improvementClass . '">' . $improvementText . '</td>
                </tr>';
            }
            
            $html .= '</table>';
        }
        
        // إضافة قسم تفاصيل المهام
        if ($options['includeAssignmentDetails'] && !empty($data['assignments'])) {
            $html .= '
            <h2>تفاصيل أداء المهام</h2>
            <table>
                <tr>
                    <th>المهمة</th>
                    <th>النوع</th>
                    <th>تاريخ التسليم</th>
                    <th>نسبة التسليم</th>
                    <th>متوسط الدرجات</th>
                </tr>';
            
            foreach ($data['assignments'] as $assignment) {
                $typeText = '';
                switch ($assignment['type']) {
                    case 'quiz': $typeText = 'اختبار قصير'; break;
                    case 'homework': $typeText = 'واجب منزلي'; break;
                    case 'project': $typeText = 'مشروع'; break;
                    case 'exam': $typeText = 'اختبار'; break;
                    default: $typeText = $assignment['type'];
                }
                
                $submissionRate = $assignment['students_count'] > 0 ? 
                    round(($assignment['submissions_count'] / $assignment['students_count']) * 100) . '%' : '-';
                
                $avgPercentage = isset($assignment['avg_percentage']) ? round($assignment['avg_percentage'], 1) . '%' : '-';
                
                $avgClass = '';
                if (isset($assignment['avg_percentage'])) {
                    if ($assignment['avg_percentage'] >= 90) {
                        $avgClass = 'excellent';
                    } elseif ($assignment['avg_percentage'] >= 80) {
                        $avgClass = 'very-good';
                    } elseif ($assignment['avg_percentage'] >= 70) {
                        $avgClass = 'good';
                    } elseif ($assignment['avg_percentage'] >= 60) {
                        $avgClass = 'fair';
                    } else {
                        $avgClass = 'poor';
                    }
                }
                
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($assignment['title']) . '</td>
                    <td>' . $typeText . '</td>
                    <td>' . date('Y-m-d', strtotime($assignment['due_date'])) . '</td>
                    <td>' . $submissionRate . '</td>
                    <td class="' . $avgClass . '">' . $avgPercentage . '</td>
                </tr>';
            }
            
            $html .= '</table>';
        }
        
        $html .= '
        </body>
        </html>';
        
        // إرسال الترويسات للتنزيل
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $fileName . '.html"');
        
        // إخراج الملف
        echo $html;
        exit();
    }
    
    /**
     * الحصول على معرّف المعلم الحالي
     * 
     * @return int|null معرّف المعلم
     */
    private function getTeacherId()
    {
        $userId = $this->auth->id();
        
        if (!$userId) {
            return null;
        }
        
        $teacherModel = new Teacher();
        $teacher = $teacherModel->getTeacherByUserId($userId);
        
        return $teacher ? $teacher['id'] : null;
    }
    
    /**
     * التحقق من صلاحية المعلم للوصول إلى صف ومادة
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $classId معرّف الصف
     * @param int $subjectId معرّف المادة
     * @return bool هل لديه صلاحية الوصول
     */
    private function checkTeacherAccess($teacherId, $classId, $subjectId)
    {
        if (!$teacherId) {
            return false;
        }
        
        $query = "SELECT COUNT(*) as count 
                 FROM teacher_assignments 
                 WHERE teacher_id = ? AND class_id = ? AND subject_id = ?";
        
        $result = $this->db->fetchOne($query, [$teacherId, $classId, $subjectId]);
        
        return $result['count'] > 0;
    }
} : 'مستقر');
                $pdf->Cell(20, 8, $improvementText, 1, 0, 'C');
                
                $pdf->Cell(70, 8, $student['first_name'] . ' ' . $student['last_name'], 1, 1, 'R');
            }
            
            $pdf->Ln(10);
        }
        
        // إضافة قسم تفاصيل المهام
        if ($options['includeAssignmentDetails'] && !empty($data['assignments'])) {
            $pdf->AddPage();
            $pdf->SetFont('aealarabiya', 'B', 14);
            $pdf->Cell(0, 10, 'تفاصيل أداء المهام', 0, 1, 'R');
            
            // إنشاء جدول المهام
            $pdf->SetFont('aealarabiya', 'B', 10);
            
            // ترويسة الجدول
            $pdf->Cell(30, 8, 'متوسط الدرجات', 1, 0, 'C');
            $pdf->Cell(25, 8, 'نسبة التسليم', 1, 0, 'C');
            $pdf->Cell(30, 8, 'تاريخ التسليم', 1, 0, 'C');
            $pdf->Cell(25, 8, 'النوع', 1, 0, 'C');
            $pdf->Cell(70, 8, 'المهمة', 1, 1, 'C');
            
            // صفوف الجدول
            $pdf->SetFont('aealarabiya', '', 10);
            
            foreach ($data['assignments'] as $assignment) {
                $avgPercentage = isset($assignment['avg_percentage']) ? round($assignment['avg_percentage'], 1) . '%' : '-';
                $pdf->Cell(30, 8, $avgPercentage, 1, 0, 'C');
                
                $submissionRate = $assignment['students_count'] > 0 ? 
                    round(($assignment['submissions_count'] / $assignment['students_count']) * 100) . '%' : '-';
                $pdf->Cell(25, 8, $submissionRate, 1, 0, 'C');
                
                $pdf->Cell(30, 8, date('Y-m-d', strtotime($assignment['due_date'])), 1, 0, 'C');
                
                $typeText = '';
                switch ($assignment['type']) {
                    case 'quiz': $typeText = 'اختبار قصير'; break;
                    case 'homework': $typeText = 'واجب منزلي'; break;
                    case 'project': $typeText = 'مشروع'; break;
                    case 'exam': $typeText = 'اختبار'; break;
                    default: $typeText = $assignment['type'];
                }
                $pdf->Cell(25, 8, $typeText, 1, 0, 'C');
                
                $pdf->Cell(70, 8, $assignment['title'], 1, 1, 'R');
            }
        }
        
        // إخراج الملف
        $pdf->Output($fileName . '.pdf', 'D');
        exit();
    }
    
    /**
     * تصدير التقرير بتنسيق Excel
     * 
     * @param string $fileName اسم الملف
     * @param array $class بيانات الصف
     * @param array $subject بيانات المادة
     * @param array $data بيانات الأداء
     * @param array $options خيارات التصدير
     */
    private function exportExcel($fileName, $class, $subject, $data, $options)
    {
        // التحقق من وجود مكتبة PhpSpreadsheet
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // إذا لم تكن المكتبة موجودة، سنقوم بتصدير ملف CSV بدلاً من ذلك
            $this->exportCSV($fileName, $class, $subject, $data, $options);
            return;
        }
        
        // إنشاء مستند Excel جديد
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // إعداد الترويسة
        $sheet->setCellValue('A1', 'تقرير أداء الطلاب');
        $sheet->setCellValue('A2', 'الصف: ' . $class['name'] . ' - المادة: ' . $subject['name']);
        $sheet->setCellValue('A3', 'تاريخ التقرير: ' . date('Y-m-d'));
        
        $row = 5;
        
        // إضافة قسم ملخص الصف
        if ($options['includeClassSummary']) {
            $sheet->setCellValue('A' . $row, 'ملخص أداء الصف');
            $row += 2;
            
            $sheet->setCellValue('A' . $row, 'متوسط الصف:');
            $sheet->setCellValue('B' . $row, round($data['classAvg'], 1) . '%');
            $sheet->setCellValue('C' . $row, 'عدد الطلاب:');
            $sheet->setCellValue('D' . $row, count($data['students']));
            $row++;
            
            $sheet->setCellValue('A' . $row, 'عدد المهام:');
            $sheet->setCellValue('B' . $row, $data['classStats']['total_assignments']);
            $sheet->setCellValue('C' . $row, 'نسبة النجاح:');
            $sheet->setCellValue('D' . $row, round($data['classStats']['success_rate']) . '%');
            $row++;
            
            $sheet->setCellValue('A' . $row, 'نسبة التسليم:');
            $sheet->setCellValue('B' . $row, round($data['classStats']['submission_rate']) . '%');
            $sheet->setCellValue('C' . $row, 'نسبة التسليم المتأخر:');
            $sheet->setCellValue('D' . $row, round($data['classStats']['late_submission_rate']) . '%');
            $row += 2;
            
            $sheet->setCellValue('A' . $row, 'توزيع مستويات الأداء:');
            $row++;
            $sheet->setCellValue('A' . $row, 'متفوق (90-100%):');
            $sheet->setCellValue('B' . $row, $data['performanceLevels']['excellent'] . ' طالب');
            $sheet->setCellValue('C' . $row, 'جيد جدًا (80-89%):');
            $sheet->setCellValue('D' . $row, $data['performanceLevels']['very_good'] . ' طالب');
            $row++;
            $sheet->setCellValue('A' . $row, 'جيد (70-79%):');
            $sheet->setCellValue('B' . $row, $data['performanceLevels']['good'] . ' طالب');
            $sheet->setCellValue('C' . $row, 'مقبول (60-69%):');
            $sheet->setCellValue('D' . $row, $data['performanceLevels']['fair'] . ' طالب');
            $row++;
            $sheet->setCellValue('A' . $row, 'ضعيف (أقل من 60%):');
            $sheet->setCellValue('B' . $row, $data['performanceLevels']['poor'] . ' طالب');
            $row += 2;
        }
        
        // إضافة قسم تفاصيل الطلاب
        if ($options['includeStudentDetails'] && !empty($data['students'])) {
            $sheet->setCellValue('A' . $row, 'تفاصيل أداء الطلاب');
            $row += 2;
            
            // ترويسة الجدول
            $sheet->setCellValue('A' . $row, 'الطالب');
            $sheet->setCellValue('B' . $row, 'المتوسط');
            $sheet->setCellValue('C' . $row, 'نسبة التسليم');
            $sheet->setCellValue('D' . $row, 'المتأخر');
            $sheet->setCellValue('E' . $row, 'التطور');
            $row++;
            
            // بيانات الطلاب
            foreach ($data['students'] as $student) {
                $sheet->setCellValue('A' . $row, $student['first_name'] . ' ' . $student['last_name']);
                $sheet->setCellValue('B' . $row, round($student['average'], 1) . '%');
                $sheet->setCellValue('C' . $row, round($student['submission_rate']) . '%');
                $sheet->setCellValue('D' . $row, $student['late_count'] . ' من ' . $student['submitted_count']);
                
                $improvementText = $student['improvement'] > 5 ? '+' . round($student['improvement'], 1) . '%' : 
                    ($student['improvement'] < -5 ? round($student['improvement'], 1) . '%' : 'مستقر');
                $sheet->setCellValue('E' . $row, $improvementText);
                $row++;
            }
            
            $row += 2;
        }
        
        // إضافة قسم تفاصيل المهام
        if ($options['includeAssignmentDetails'] && !empty($data['assignments'])) {
            $sheet->setCellValue('A' . $row, 'تفاصيل أداء المهام');
            $row += 2;
            
            // ترويسة الجدول
            $sheet->setCellValue('A' . $row, 'المهمة');
            $sheet->setCellValue('B' . $row, 'النوع');
            $sheet->setCellValue('C' . $row, 'تاريخ التسليم');
            $sheet->setCellValue('D' . $row, 'نسبة التسليم');
            $sheet->setCellValue('E' . $row, 'متوسط الدرجات');
            $row++;
            
            // بيانات المهام
            foreach ($data['assignments'] as $assignment) {
                $sheet->setCellValue('A' . $row, $assignment['title']);
                
                $typeText = '';
                switch ($assignment['type']) {
                    case 'quiz': $typeText = 'اختبار قصير'; break;
                    case 'homework': $typeText = 'واجب منزلي'; break;
                    case 'project': $typeText = 'مشروع'; break;
                    case 'exam': $typeText = 'اختبار'; break;
                    default: $typeText = $assignment['type'];
                }
                $sheet->setCellValue('B' . $row, $typeText);
                
                $sheet->setCellValue('C' . $row, date('Y-m-d', strtotime($assignment['due_date'])));
                
                $submissionRate = $assignment['students_count'] > 0 ? 
                    round(($assignment['submissions_count'] / $assignment['students_count']) * 100) . '%' : '-';
                $sheet->setCellValue('D' . $row, $submissionRate);
                
                $avgPercentage = isset($assignment['avg_percentage']) ? round($assignment['avg_percentage'], 1) . '%' : '-';
                $sheet->setCellValue('E' . $row, $avgPercentage);
                $row++;
            }
        }
        
        // تعديل اتجاه الخلايا للغة العربية
        $sheet->setRightToLeft(true);
        
        // إعداد الترويسة للتنزيل
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        // إخراج الملف
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }
    /**
     * تصدير التقرير بتنسيق CSV
     * 
     * @param string $fileName اسم الملف
     * @param array $class بيانات الصف
     * @param array $subject بيانات المادة
     * @param array $data بيانات الأداء
     * @param array $options خيارات التصدير
     */
    private function exportCSV($fileName, $class, $subject, $data, $options)
    {
        // فتح مخرج للكتابة
        $output = fopen('php://output', 'w');
        
        // إعداد ترويسة الملف
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $fileName . '.csv"');
        
        // إضافة BOM لدعم Unicode
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // كتابة سطر الترويسة
        fputcsv($output, ['تقرير أداء الطلاب']);
        fputcsv($output, ['الصف: ' . $class['name'] . ' - المادة: ' . $subject['name']]);
        fputcsv($output, ['تاريخ التقرير: ' . date('Y-m-d')]);
        fputcsv($output, []); // سطر فارغ
        
        // إضافة قسم ملخص الصف
        if ($options['includeClassSummary']) {
            fputcsv($output, ['ملخص أداء الصف']);
            fputcsv($output, ['متوسط الصف:', round($data['classAvg'], 1) . '%', 'عدد الطلاب:', count($data['students'])]);
            fputcsv($output, ['عدد المهام:', $data['classStats']['total_assignments'], 'نسبة النجاح:', round($data['classStats']['success_rate']) . '%']);
            fputcsv($output, ['نسبة التسليم:', round($data['classStats']['submission_rate']) . '%', 'نسبة التسليم المتأخر:', round($data['classStats']['late_submission_rate']) . '%']);
            fputcsv($output, []); // سطر فارغ
            
            fputcsv($output, ['توزيع مستويات الأداء:']);
            fputcsv($output, ['متفوق (90-100%):', $data['performanceLevels']['excellent'] . ' طالب', 'جيد جدًا (80-89%):', $data['performanceLevels']['very_good'] . ' طالب']);
            fputcsv($output, ['جيد (70-79%):', $data['performanceLevels']['good'] . ' طالب', 'مقبول (60-69%):', $data['performanceLevels']['fair'] . ' طالب']);
            fputcsv($output, ['ضعيف (أقل من 60%):', $data['performanceLevels']['poor'] . ' طالب']);
            fputcsv($output, []); // سطر فارغ
        }
        
        // إضافة قسم تفاصيل الطلاب
        if ($options['includeStudentDetails'] && !empty($data['students'])) {
            fputcsv($output, ['تفاصيل أداء الطلاب']);
            fputcsv($output, ['الطالب', 'المتوسط', 'نسبة التسليم', 'المتأخر', 'التطور']);
            
            foreach ($data['students'] as $student) {
                $improvementText = $student['improvement'] > 5 ? '+' . round($student['improvement'], 1) . '%' : 
                    ($student['improvement'] < -5 ? round($student['improvement'], 1) . '%' : 'مستقر');
                
                fputcsv($output, [
                    $student['first_name'] . ' ' . $student['last_name'],
                    round($student['average'], 1) . '%',
                    round($student['submission_rate']) . '%',
                    $student['late_count'] . ' من ' . $student['submitted_count'],
                    $improvementText
                ]);
            }
            
            fputcsv($output, []); // سطر فارغ
        }
        
        // إضافة قسم تفاصيل المهام
        if ($options['includeAssignmentDetails'] && !empty($data['assignments'])) {
            fputcsv($output, ['تفاصيل أداء المهام']);
            fputcsv($output, ['المهمة', 'النوع', 'تاريخ التسليم', 'نسبة التسليم', 'متوسط الدرجات']);
            
            foreach ($data['assignments'] as $assignment) {
                $typeText = '';
                switch ($assignment['type']) {
                    case 'quiz': $typeText = 'اختبار قصير'; break;
                    case 'homework': $typeText = 'واجب منزلي'; break;
                    case 'project': $typeText = 'مشروع'; break;
                    case 'exam': $typeText = 'اختبار'; break;
                    default: $typeText = $assignment['type'];
                }
                
                $submissionRate = $assignment['students_count'] > 0 ? 
                    round(($assignment['submissions_count'] / $assignment['students_count']) * 100) . '%' : '-';
                
                $avgPercentage = isset($assignment['avg_percentage']) ? round($assignment['avg_percentage'], 1) . '%' : '-';
                
                fputcsv($output, [
                    $assignment['title'],
                    $typeText,
                    date('Y-m-d', strtotime($assignment['due_date'])),
                    $submissionRate,
                    $avgPercentage
                ]);
            }
        }
        
        // إغلاق المخرج
        fclose($output);
        exit();
    }
