<?php
/**
 * app/models/Grade.php
 * نموذج الدرجة
 * يتعامل مع بيانات تصحيح إجابات الطلاب في النظام
 */
class Grade extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'grades';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'submission_id', 'points', 'feedback', 'graded_by', 'graded_at'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء درجة جديدة (تصحيح)
     * 
     * @param array $data بيانات الدرجة
     * @return int|false معرّف الدرجة الجديدة أو false في حالة الفشل
     */
    public function createGrade($data)
    {
        // إضافة تاريخ التصحيح إذا لم يتم توفيره
        if (!isset($data['graded_at'])) {
            $data['graded_at'] = date('Y-m-d H:i:s');
        }
        
        // التحقق من وجود درجة سابقة
        $existingGrade = $this->whereFirst('submission_id', $data['submission_id']);
        
        if ($existingGrade) {
            // تحديث الدرجة الموجودة
            return $this->update($existingGrade['id'], $data) ? $existingGrade['id'] : false;
        }
        
        // إنشاء درجة جديدة
        $gradeId = $this->create($data);
        
        if ($gradeId) {
            // تحديث حالة الإجابة
            $this->db->update(
                'submissions',
                ['status' => 'graded'],
                'id = ?',
                [$data['submission_id']]
            );
        }
        
        return $gradeId;
    }
    
    /**
     * تحديث بيانات الدرجة
     * 
     * @param int $id معرّف الدرجة
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateGrade($id, $data)
    {
        // تحديث تاريخ التصحيح
        if (!isset($data['graded_at'])) {
            $data['graded_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على درجة مع التفاصيل
     * 
     * @param int $gradeId معرّف الدرجة
     * @return array|false بيانات الدرجة أو false إذا لم يتم العثور عليها
     */
    public function getGradeWithDetails($gradeId)
    {
        $query = "SELECT g.*, 
                 sub.id as submission_id, sub.content as submission_content, 
                 sub.file_path as submission_file, sub.status as submission_status, 
                 sub.submitted_at,
                 a.id as assignment_id, a.title as assignment_title, 
                 a.points as assignment_points, a.due_date as assignment_due_date,
                 s.name as subject_name, s.code as subject_code,
                 c.name as class_name, c.grade_level,
                 st.id as student_id, st.student_id as student_number,
                 u.first_name as student_first_name, u.last_name as student_last_name,
                 gu.first_name as grader_first_name, gu.last_name as grader_last_name
                 FROM {$this->table} g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 JOIN students st ON sub.student_id = st.id 
                 JOIN users u ON st.user_id = u.id 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN classes c ON a.class_id = c.id 
                 JOIN users gu ON g.graded_by = gu.id 
                 WHERE g.id = ? 
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$gradeId]);
    }
    
    /**
     * الحصول على درجات مهمة معينة
     * 
     * @param int $assignmentId معرّف المهمة
     * @return array قائمة الدرجات
     */
    public function getGradesByAssignment($assignmentId)
    {
        $query = "SELECT g.*, 
                 sub.status as submission_status, sub.submitted_at,
                 st.student_id as student_number,
                 u.first_name as student_first_name, u.last_name as student_last_name,
                 gu.first_name as grader_first_name, gu.last_name as grader_last_name
                 FROM {$this->table} g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 JOIN students st ON sub.student_id = st.id 
                 JOIN users u ON st.user_id = u.id 
                 JOIN users gu ON g.graded_by = gu.id 
                 WHERE sub.assignment_id = ? 
                 ORDER BY u.first_name, u.last_name";
        
        return $this->db->fetchAll($query, [$assignmentId]);
    }
    
    /**
     * الحصول على درجات طالب معين
     * 
     * @param int $studentId معرّف الطالب
     * @param int|null $subjectId معرّف المادة (اختياري)
     * @return array قائمة الدرجات
     */
    public function getGradesByStudent($studentId, $subjectId = null)
    {
        $query = "SELECT g.*, 
                 sub.status as submission_status, sub.submitted_at,
                 a.id as assignment_id, a.title as assignment_title, 
                 a.points as assignment_points, a.due_date as assignment_due_date,
                 s.id as subject_id, s.name as subject_name, s.code as subject_code,
                 gu.first_name as grader_first_name, gu.last_name as grader_last_name
                 FROM {$this->table} g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN users gu ON g.graded_by = gu.id 
                 WHERE sub.student_id = ?";
        
        $params = [$studentId];
        
        if ($subjectId !== null) {
            $query .= " AND s.id = ?";
            $params[] = $subjectId;
        }
        
        $query .= " ORDER BY g.graded_at DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * حساب متوسط درجات الطلاب في مهمة
     * 
     * @param int $assignmentId معرّف المهمة
     * @return array إحصائيات الدرجات
     */
    public function calculateAssignmentGradeStats($assignmentId)
    {
        $stats = [];
        
        // الحصول على معلومات المهمة
        $query = "SELECT a.points as total_points, COUNT(sub.id) as submissions_count
                 FROM assignments a
                 LEFT JOIN submissions sub ON a.id = sub.assignment_id
                 WHERE a.id = ?";
        $assignment = $this->db->fetchOne($query, [$assignmentId]);
        
        if (!$assignment) {
            return $stats;
        }
        
        $stats['total_points'] = $assignment['total_points'];
        $stats['submissions_count'] = $assignment['submissions_count'];
        
        // متوسط الدرجات
        $query = "SELECT 
                 COUNT(g.id) as grades_count,
                 MIN(g.points) as min_points,
                 MAX(g.points) as max_points,
                 AVG(g.points) as avg_points,
                 STD(g.points) as std_dev
                 FROM {$this->table} g 
                 JOIN submissions sub ON g.submission_id = sub.id 
                 WHERE sub.assignment_id = ?";
        
        $gradeStats = $this->db->fetchOne($query, [$assignmentId]);
        
        if ($gradeStats && $gradeStats['grades_count'] > 0) {
            $stats['grades_count'] = $gradeStats['grades_count'];
            $stats['min_points'] = $gradeStats['min_points'];
            $stats['max_points'] = $gradeStats['max_points'];
            $stats['avg_points'] = $gradeStats['avg_points'];
            $stats['std_dev'] = $gradeStats['std_dev'];
            
            // النسب المئوية
            $stats['min_percentage'] = ($stats['min_points'] / $stats['total_points']) * 100;
            $stats['max_percentage'] = ($stats['max_points'] / $stats['total_points']) * 100;
            $stats['avg_percentage'] = ($stats['avg_points'] / $stats['total_points']) * 100;
            
            // توزيع الدرجات
            $query = "SELECT 
                     COUNT(CASE WHEN (g.points / ?) >= 0.9 THEN 1 END) as a_count,
                     COUNT(CASE WHEN (g.points / ?) >= 0.8 AND (g.points / ?) < 0.9 THEN 1 END) as b_count,
                     COUNT(CASE WHEN (g.points / ?) >= 0.7 AND (g.points / ?) < 0.8 THEN 1 END) as c_count,
                     COUNT(CASE WHEN (g.points / ?) >= 0.6 AND (g.points / ?) < 0.7 THEN 1 END) as d_count,
                     COUNT(CASE WHEN (g.points / ?) < 0.6 THEN 1 END) as f_count
                     FROM {$this->table} g 
                     JOIN submissions sub ON g.submission_id = sub.id 
                     WHERE sub.assignment_id = ?";
            
            $distribution = $this->db->fetchOne($query, [
                $stats['total_points'], $stats['total_points'], $stats['total_points'],
                $stats['total_points'], $stats['total_points'], $stats['total_points'],
                $stats['total_points'], $stats['total_points'], $assignmentId
            ]);
            
            $stats['distribution'] = [
                'A' => $distribution['a_count'] ?? 0,
                'B' => $distribution['b_count'] ?? 0,
                'C' => $distribution['c_count'] ?? 0,
                'D' => $distribution['d_count'] ?? 0,
                'F' => $distribution['f_count'] ?? 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * استيراد درجات متعددة دفعة واحدة
     * 
     * @param array $gradesData مصفوفة من بيانات الدرجات
     * @param int $gradedBy معرّف المستخدم الذي قام بالتصحيح
     * @return array نتائج العملية [نجاح => عدد العمليات الناجحة، فشل => عدد العمليات الفاشلة]
     */
    public function bulkImportGrades($gradesData, $gradedBy)
    {
        $results = [
            'success' => 0,
            'fail' => 0
        ];
        
        // بدء معاملة قاعدة البيانات
        $this->db->beginTransaction();
        
        try {
            foreach ($gradesData as $gradeData) {
                if (!isset($gradeData['submission_id']) || !isset($gradeData['points'])) {
                    $results['fail']++;
                    continue;
                }
                
                // إضافة معرّف المصحح وتاريخ التصحيح
                $gradeData['graded_by'] = $gradedBy;
                $gradeData['graded_at'] = date('Y-m-d H:i:s');
                
                // التحقق من وجود درجة سابقة
                $existingGrade = $this->whereFirst('submission_id', $gradeData['submission_id']);
                
                if ($existingGrade) {
                    // تحديث الدرجة الموجودة
                    $success = $this->update($existingGrade['id'], $gradeData);
                } else {
                    // إنشاء درجة جديدة
                    $newGradeId = $this->create($gradeData);
                    $success = $newGradeId !== false;
                }
                
                if ($success) {
                    // تحديث حالة الإجابة
                    $this->db->update(
                        'submissions',
                        ['status' => 'graded'],
                        'id = ?',
                        [$gradeData['submission_id']]
                    );
                    
                    $results['success']++;
                } else {
                    $results['fail']++;
                }
            }
            
            // تأكيد المعاملة
            $this->db->commit();
        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة الخطأ
            $this->db->rollback();
            error_log("خطأ في استيراد الدرجات: " . $e->getMessage());
            $results['fail'] = count($gradesData);
            $results['success'] = 0;
        }
        
        return $results;
    }
}