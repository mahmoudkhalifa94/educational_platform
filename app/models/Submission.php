<?php
/**
 * app/models/Submission.php
 * نموذج الإجابة
 * يتعامل مع بيانات إجابات الطلاب في النظام
 */
class Submission extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'submissions';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'assignment_id', 'student_id', 'content', 'file_path', 'status', 'submitted_at'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء إجابة جديدة
     * 
     * @param array $data بيانات الإجابة
     * @return int|false معرّف الإجابة الجديدة أو false في حالة الفشل
     */
    public function createSubmission($data)
    {
        // التحقق من عدم وجود إجابة سابقة لنفس الطالب ونفس المهمة
        $existingSubmission = $this->whereFirst('assignment_id', $data['assignment_id'], 'student_id', $data['student_id']);
        
        if ($existingSubmission) {
            // تحديث الإجابة الموجودة
            $updateData = $data;
            
            // إذا تم تحديث الحالة إلى مقدم، نضيف تاريخ التقديم
            if (isset($updateData['status']) && $updateData['status'] === 'submitted' && !isset($updateData['submitted_at'])) {
                $updateData['submitted_at'] = date('Y-m-d H:i:s');
            }
            
            return $this->update($existingSubmission['id'], $updateData) ? $existingSubmission['id'] : false;
        }
        
        // إضافة تاريخ التقديم إذا كانت الحالة مقدم ولم يتم توفير تاريخ
        if (isset($data['status']) && $data['status'] === 'submitted' && !isset($data['submitted_at'])) {
            $data['submitted_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات الإجابة
     * 
     * @param int $id معرّف الإجابة
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateSubmission($id, $data)
    {
        // إضافة تاريخ التقديم إذا تم تغيير الحالة إلى مقدم
        if (isset($data['status']) && $data['status'] === 'submitted' && !isset($data['submitted_at'])) {
            $data['submitted_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على إجابة مع التفاصيل الكاملة
     * 
     * @param int $submissionId معرّف الإجابة
     * @return array|false بيانات الإجابة أو false إذا لم يتم العثور عليها
     */
    public function getSubmissionWithDetails($submissionId)
    {
        $query = "SELECT sub.*, 
                 a.id as assignment_id, a.title as assignment_title, a.description as assignment_description,
                 a.type as assignment_type, a.content as assignment_content, a.points as assignment_points,
                 a.due_date as assignment_due_date,
                 s.id as subject_id, s.name as subject_name, s.code as subject_code,
                 c.id as class_id, c.name as class_name, c.grade_level,
                 u.first_name as student_first_name, u.last_name as student_last_name, u.email as student_email,
                 t.id as teacher_id, tu.first_name as teacher_first_name, tu.last_name as teacher_last_name,
                 g.id as grade_id, g.points as grade_points, g.feedback, g.graded_at,
                 gu.first_name as grader_first_name, gu.last_name as grader_last_name
                 FROM {$this->table} sub 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN classes c ON a.class_id = c.id 
                 JOIN students st ON sub.student_id = st.id 
                 JOIN users u ON st.user_id = u.id 
                 JOIN teachers t ON a.teacher_id = t.id 
                 JOIN users tu ON t.user_id = tu.id 
                 LEFT JOIN grades g ON sub.id = g.submission_id 
                 LEFT JOIN users gu ON g.graded_by = gu.id 
                 WHERE sub.id = ? 
                 LIMIT 1";
        
        $submission = $this->db->fetchOne($query, [$submissionId]);
        
        // تفكيك المحتوى من JSON إذا وجد
        if ($submission) {
            if (isset($submission['assignment_content'])) {
                $submission['assignment_content'] = json_decode($submission['assignment_content'], true);
            }
        }
        
        return $submission;
    }
    
    /**
     * الحصول على إجابة طالب لمهمة معينة
     * 
     * @param int $assignmentId معرّف المهمة
     * @param int $studentId معرّف الطالب
     * @return array|false بيانات الإجابة أو false إذا لم يتم العثور عليها
     */
    public function getStudentSubmission($assignmentId, $studentId)
    {
        $query = "SELECT sub.*, 
                 g.id as grade_id, g.points, g.feedback, g.graded_at
                 FROM {$this->table} sub 
                 LEFT JOIN grades g ON sub.id = g.submission_id 
                 WHERE sub.assignment_id = ? AND sub.student_id = ? 
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$assignmentId, $studentId]);
    }
    
    /**
     * الحصول على إجابات طالب معين
     * 
     * @param int $studentId معرّف الطالب
     * @param string|null $status حالة الإجابة (اختياري)
     * @return array قائمة الإجابات
     */
    public function getStudentSubmissions($studentId, $status = null)
    {
        $query = "SELECT sub.*, 
                 a.title as assignment_title, a.type as assignment_type, a.points as assignment_points,
                 a.due_date as assignment_due_date,
                 s.name as subject_name, s.code as subject_code,
                 g.id as grade_id, g.points, g.feedback, g.graded_at
                 FROM {$this->table} sub 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 LEFT JOIN grades g ON sub.id = g.submission_id 
                 WHERE sub.student_id = ?";
        
        $params = [$studentId];
        
        if ($status !== null) {
            $query .= " AND sub.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY sub.submitted_at DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على إجابات تحتاج إلى تصحيح
     * 
     * @param int $teacherId معرّف المعلم
     * @param int|null $classId معرّف الصف (اختياري)
     * @param int|null $subjectId معرّف المادة (اختياري)
     * @return array قائمة الإجابات
     */
    public function getPendingSubmissions($teacherId, $classId = null, $subjectId = null)
    {
        $query = "SELECT sub.*, 
                 a.title as assignment_title, a.type as assignment_type, a.points as assignment_points,
                 a.due_date as assignment_due_date,
                 s.name as subject_name, s.code as subject_code,
                 c.name as class_name,
                 u.first_name as student_first_name, u.last_name as student_last_name
                 FROM {$this->table} sub 
                 JOIN assignments a ON sub.assignment_id = a.id 
                 JOIN subjects s ON a.subject_id = s.id 
                 JOIN classes c ON a.class_id = c.id 
                 JOIN students st ON sub.student_id = st.id 
                 JOIN users u ON st.user_id = u.id 
                 WHERE a.teacher_id = ? AND sub.status = 'submitted' 
                 AND NOT EXISTS (SELECT 1 FROM grades g WHERE g.submission_id = sub.id)";
        
        $params = [$teacherId];
        
        if ($classId !== null) {
            $query .= " AND a.class_id = ?";
            $params[] = $classId;
        }
        
        if ($subjectId !== null) {
            $query .= " AND a.subject_id = ?";
            $params[] = $subjectId;
        }
        
        $query .= " ORDER BY sub.submitted_at ASC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * تقديم إجابة (تغيير الحالة إلى مقدم)
     * 
     * @param int $submissionId معرّف الإجابة
     * @return bool نجاح العملية أم لا
     */
    public function submitSubmission($submissionId)
    {
        // التحقق من موعد تسليم المهمة
        $submission = $this->getSubmissionWithDetails($submissionId);
        
        if (!$submission) {
            return false;
        }
        
        $dueDate = new DateTime($submission['assignment_due_date']);
        $now = new DateTime();
        
        $status = ($now > $dueDate) ? 'late' : 'submitted';
        
        return $this->update($submissionId, [
            'status' => $status,
            'submitted_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * إضافة ملف إلى إجابة
     * 
     * @param int $submissionId معرّف الإجابة
     * @param string $filePath مسار الملف
     * @return bool نجاح العملية أم لا
     */
    public function addFileToSubmission($submissionId, $filePath)
    {
        return $this->update($submissionId, ['file_path' => $filePath]);
    }
    
    /**
     * تصحيح إجابة
     * 
     * @param int $submissionId معرّف الإجابة
     * @param float $points الدرجة
     * @param string $feedback ملاحظات التصحيح
     * @param int $gradedBy معرّف المستخدم الذي قام بالتصحيح
     * @return int|false معرّف التصحيح الجديد أو false في حالة الفشل
     */
    public function gradeSubmission($submissionId, $points, $feedback, $gradedBy)
    {
        // التحقق من وجود الإجابة
        $submission = $this->find($submissionId);
        
        if (!$submission) {
            return false;
        }
        
        // التحقق من عدم وجود تصحيح سابق
        $query = "SELECT id FROM grades WHERE submission_id = ? LIMIT 1";
        $existingGrade = $this->db->fetchOne($query, [$submissionId]);
        
        // بدء معاملة قاعدة البيانات
        $this->db->beginTransaction();
        
        try {
            if ($existingGrade) {
                // تحديث التصحيح الموجود
                $gradeUpdated = $this->db->update(
                    'grades',
                    [
                        'points' => $points,
                        'feedback' => $feedback,
                        'graded_by' => $gradedBy,
                        'graded_at' => date('Y-m-d H:i:s')
                    ],
                    'id = ?',
                    [$existingGrade['id']]
                );
                
                $gradeId = $existingGrade['id'];
            } else {
                // إنشاء تصحيح جديد
                $gradeId = $this->db->insert('grades', [
                    'submission_id' => $submissionId,
                    'points' => $points,
                    'feedback' => $feedback,
                    'graded_by' => $gradedBy,
                    'graded_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // تحديث حالة الإجابة
            $submissionUpdated = $this->update($submissionId, ['status' => 'graded']);
            
            if ($gradeId && $submissionUpdated) {
                $this->db->commit();
                return $gradeId;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("خطأ في تصحيح الإجابة: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تحليل إحصائيات إجابات المهام لطالب معين
     * 
     * @param int $studentId معرّف الطالب
     * @return array الإحصائيات
     */
    public function analyzeStudentSubmissions($studentId)
    {
        $stats = [];
        
        // إجمالي عدد الإجابات
        $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['total_submissions'] = $result['total'] ?? 0;
        
        // عدد الإجابات حسب الحالة
        $query = "SELECT status, COUNT(*) as count FROM {$this->table} WHERE student_id = ? GROUP BY status";
        $statusCounts = $this->db->fetchAll($query, [$studentId]);
        
        $stats['status_counts'] = [
            'draft' => 0,
            'submitted' => 0,
            'late' => 0,
            'graded' => 0
        ];
        
        foreach ($statusCounts as $statusCount) {
            $stats['status_counts'][$statusCount['status']] = $statusCount['count'];
        }
        
        // متوسط الدرجات
        $query = "SELECT AVG(g.points / a.points * 100) as avg_percentage
                 FROM {$this->table} sub
                 JOIN grades g ON sub.id = g.submission_id
                 JOIN assignments a ON sub.assignment_id = a.id
                 WHERE sub.student_id = ?";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['average_grade'] = $result['avg_percentage'] !== null ? round($result['avg_percentage'], 1) : null;
        
        // متوسط وقت التسليم قبل الموعد النهائي (بالساعات)
        $query = "SELECT AVG(TIMESTAMPDIFF(HOUR, sub.submitted_at, a.due_date)) as avg_hours_before_deadline
                 FROM {$this->table} sub
                 JOIN assignments a ON sub.assignment_id = a.id
                 WHERE sub.student_id = ? AND sub.status != 'late'";
        $result = $this->db->fetchOne($query, [$studentId]);
        $stats['avg_hours_before_deadline'] = $result['avg_hours_before_deadline'] !== null ? 
            round($result['avg_hours_before_deadline'], 1) : null;
        
        return $stats;
    }
}