<?php
/**
 * app/models/Conversation.php
 * نموذج المحادثة
 * يتعامل مع بيانات المحادثات في النظام
 */
class Conversation extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'conversations';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'subject', 'created_by', 'created_at', 'updated_at'
    ];
    
    /**
     * إنشاء محادثة جديدة
     * 
     * @param array $data بيانات المحادثة
     * @param array $participants مصفوفة معرّفات المشاركين
     * @return int|false معرّف المحادثة الجديدة أو false في حالة الفشل
     */
    public function createConversation($data, $participants = [])
    {
        // التأكد من وجود منشئ المحادثة
        if (!isset($data['created_by'])) {
            return false;
        }
        
        // إضافة منشئ المحادثة إلى المشاركين إذا لم يكن موجودًا
        if (!in_array($data['created_by'], $participants)) {
            $participants[] = $data['created_by'];
        }
        
        // بدء معاملة قاعدة البيانات
        $this->db->beginTransaction();
        
        try {
            // إنشاء المحادثة
            $conversationId = $this->create($data);
            
            if (!$conversationId) {
                $this->db->rollback();
                return false;
            }
            
            // إضافة المشاركين
            foreach ($participants as $participantId) {
                $this->db->insert('conversation_participants', [
                    'conversation_id' => $conversationId,
                    'user_id' => $participantId
                ]);
            }
            
            $this->db->commit();
            return $conversationId;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creating conversation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على محادثة مع التفاصيل
     * 
     * @param int $conversationId معرّف المحادثة
     * @return array|false بيانات المحادثة أو false إذا لم يتم العثور عليها
     */
    public function getConversationWithDetails($conversationId)
    {
        $query = "SELECT c.*,
                 u.first_name as creator_first_name, u.last_name as creator_last_name,
                 (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as messages_count,
                 (SELECT MAX(created_at) FROM messages WHERE conversation_id = c.id) as last_message_at
                 FROM {$this->table} c
                 JOIN users u ON c.created_by = u.id
                 WHERE c.id = ?
                 LIMIT 1";
        
        $conversation = $this->db->fetchOne($query, [$conversationId]);
        
        if (!$conversation) {
            return false;
        }
        
        // جلب المشاركين
        $query = "SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name
                 FROM conversation_participants cp
                 JOIN users u ON cp.user_id = u.id
                 JOIN roles r ON u.role_id = r.id
                 WHERE cp.conversation_id = ?";
        
        $participants = $this->db->fetchAll($query, [$conversationId]);
        
        $conversation['participants'] = $participants;
        
        return $conversation;
    }
    
    /**
     * الحصول على محادثات مستخدم معين
     * 
     * @param int $userId معرّف المستخدم
     * @return array قائمة المحادثات
     */
    public function getUserConversations($userId)
    {
        $query = "SELECT c.*,
                 u.first_name as creator_first_name, u.last_name as creator_last_name,
                 (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as messages_count,
                 (SELECT MAX(created_at) FROM messages WHERE conversation_id = c.id) as last_message_at,
                 (SELECT COUNT(*) FROM message_status ms
                  JOIN messages m ON ms.message_id = m.id
                  WHERE m.conversation_id = c.id AND ms.user_id = ? AND ms.is_read = 0) as unread_count,
                 (SELECT GROUP_CONCAT(CONCAT(u2.first_name, ' ', u2.last_name) SEPARATOR ', ')
                  FROM conversation_participants cp
                  JOIN users u2 ON cp.user_id = u2.id
                  WHERE cp.conversation_id = c.id AND cp.user_id != ?) as other_participants
                 FROM {$this->table} c
                 JOIN users u ON c.created_by = u.id
                 JOIN conversation_participants cp ON c.id = cp.conversation_id
                 WHERE cp.user_id = ?
                 GROUP BY c.id
                 ORDER BY last_message_at DESC, c.created_at DESC";
        
        return $this->db->fetchAll($query, [$userId, $userId, $userId]);
    }
    
    /**
     * إضافة مشارك إلى محادثة
     * 
     * @param int $conversationId معرّف المحادثة
     * @param int $userId معرّف المستخدم
     * @return bool نجاح العملية أم لا
     */
    public function addParticipant($conversationId, $userId)
    {
        // التحقق من عدم وجود المستخدم في المحادثة بالفعل
        $query = "SELECT id FROM conversation_participants
                 WHERE conversation_id = ? AND user_id = ?
                 LIMIT 1";
        
        $existing = $this->db->fetchOne($query, [$conversationId, $userId]);
        
        if ($existing) {
            return true; // المستخدم موجود بالفعل
        }
        
        // إضافة المستخدم
        return $this->db->insert('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $userId
        ]) !== false;
    }
    
    /**
     * حذف مشارك من محادثة
     * 
     * @param int $conversationId معرّف المحادثة
     * @param int $userId معرّف المستخدم
     * @return bool نجاح العملية أم لا
     */
    public function removeParticipant($conversationId, $userId)
    {
        return $this->db->delete(
            'conversation_participants',
            'conversation_id = ? AND user_id = ?',
            [$conversationId, $userId]
        );
    }
    
    /**
     * إضافة رسالة إلى محادثة
     * 
     * @param int $conversationId معرّف المحادثة
     * @param int $senderId معرّف المرسل
     * @param string $content محتوى الرسالة
     * @param array|null $attachments المرفقات (اختياري)
     * @return int|false معرّف الرسالة الجديدة أو false في حالة الفشل
     */
    public function addMessage($conversationId, $senderId, $content, $attachments = null)
    {
        // التحقق من وجود المرسل في المحادثة
        $query = "SELECT id FROM conversation_participants
                 WHERE conversation_id = ? AND user_id = ?
                 LIMIT 1";
        
        $isParticipant = $this->db->fetchOne($query, [$conversationId, $senderId]);
        
        if (!$isParticipant) {
            return false;
        }
        
        // تحويل المرفقات إلى JSON إذا وجدت
        $attachmentsJson = null;
        if ($attachments !== null) {
            $attachmentsJson = json_encode($attachments);
        }
        
        // إنشاء الرسالة
        $messageId = $this->db->insert('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'content' => $content,
            'attachments' => $attachmentsJson,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$messageId) {
            return false;
        }
        
        // تحديث وقت آخر تعديل للمحادثة
        $this->update($conversationId, [
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // إضافة حالة قراءة للمشاركين
        $query = "SELECT user_id FROM conversation_participants
                 WHERE conversation_id = ?";
        
        $participants = $this->db->fetchAll($query, [$conversationId]);
        
        foreach ($participants as $participant) {
            $this->db->insert('message_status', [
                'message_id' => $messageId,
                'user_id' => $participant['user_id'],
                'is_read' => $participant['user_id'] == $senderId ? 1 : 0, // مقروءة للمرسل
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $messageId;
    }
    
    /**
     * الحصول على رسائل محادثة معينة
     * 
     * @param int $conversationId معرّف المحادثة
     * @param int $userId معرّف المستخدم الحالي (لتحديث حالة القراءة)
     * @param int $limit عدد الرسائل
     * @param int $offset موضع البداية
     * @return array قائمة الرسائل
     */
    public function getConversationMessages($conversationId, $userId = null, $limit = 50, $offset = 0)
    {
        $query = "SELECT m.*,
                 u.first_name as sender_first_name, u.last_name as sender_last_name,
                 r.name as sender_role,
                 ms.is_read
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 JOIN roles r ON u.role_id = r.id
                 LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                 WHERE m.conversation_id = ?
                 ORDER BY m.created_at DESC
                 LIMIT ? OFFSET ?";
        
        $messages = $this->db->fetchAll($query, [$userId, $conversationId, $limit, $offset]);
        
        // تحديث حالة القراءة للمستخدم الحالي
        if ($userId !== null) {
            $this->markMessagesAsRead($conversationId, $userId);
        }
        
        // تفكيك المرفقات من JSON
        foreach ($messages as &$message) {
            if (isset($message['attachments']) && !empty($message['attachments'])) {
                $message['attachments'] = json_decode($message['attachments'], true);
            }
        }
        
        return array_reverse($messages); // ترتيب الرسائل من الأقدم إلى الأحدث
    }
    
    /**
     * تعيين رسائل محادثة كمقروءة لمستخدم معين
     * 
     * @param int $conversationId معرّف المحادثة
     * @param int $userId معرّف المستخدم
     * @return bool نجاح العملية أم لا
     */
    public function markMessagesAsRead($conversationId, $userId)
    {
        $query = "UPDATE message_status ms
                 JOIN messages m ON ms.message_id = m.id
                 SET ms.is_read = 1, ms.read_at = ?
                 WHERE m.conversation_id = ? AND ms.user_id = ? AND ms.is_read = 0";
        
        $this->db->query($query, [date('Y-m-d H:i:s'), $conversationId, $userId]);
        
        return true;
    }
    
    /**
     * حساب عدد الرسائل غير المقروءة لمستخدم معين
     * 
     * @param int $userId معرّف المستخدم
     * @return int عدد الرسائل غير المقروءة
     */
    public function countUnreadMessages($userId)
    {
        $query = "SELECT COUNT(*) as count
                 FROM message_status ms
                 JOIN messages m ON ms.message_id = m.id
                 JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                 WHERE cp.user_id = ? AND ms.user_id = ? AND ms.is_read = 0";
        
        $result = $this->db->fetchOne($query, [$userId, $userId]);
        
        return $result['count'] ?? 0;
    }
    
    /**
     * البحث عن محادثات
     * 
     * @param int $userId معرّف المستخدم
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchConversations($userId, $searchTerm)
    {
        $query = "SELECT c.*,
                 u.first_name as creator_first_name, u.last_name as creator_last_name,
                 (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as messages_count,
                 (SELECT MAX(created_at) FROM messages WHERE conversation_id = c.id) as last_message_at,
                 (SELECT COUNT(*) FROM message_status ms
                  JOIN messages m ON ms.message_id = m.id
                  WHERE m.conversation_id = c.id AND ms.user_id = ? AND ms.is_read = 0) as unread_count
                 FROM {$this->table} c
                 JOIN users u ON c.created_by = u.id
                 JOIN conversation_participants cp ON c.id = cp.conversation_id
                 LEFT JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                 LEFT JOIN users u2 ON cp2.user_id = u2.id
                 LEFT JOIN messages m ON c.id = m.conversation_id
                 WHERE cp.user_id = ?
                 AND (c.subject LIKE ? OR u2.first_name LIKE ? OR u2.last_name LIKE ? OR m.content LIKE ?)
                 GROUP BY c.id
                 ORDER BY last_message_at DESC, c.created_at DESC
                 LIMIT 20";
        
        $searchParam = "%{$searchTerm}%";
        
        return $this->db->fetchAll($query, [
            $userId, $userId, $searchParam, $searchParam, $searchParam, $searchParam
        ]);
    }
    
    /**
     * إنشاء محادثة بين معلم وطالب
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $studentId معرّف الطالب
     * @param string $subject موضوع المحادثة
     * @param string $initialMessage الرسالة الأولية (اختياري)
     * @return int|false معرّف المحادثة الجديدة أو false في حالة الفشل
     */
    public function createTeacherStudentConversation($teacherId, $studentId, $subject, $initialMessage = null)
    {
        // الحصول على معرّفات المستخدمين
        $query = "SELECT user_id FROM teachers WHERE id = ? LIMIT 1";
        $teacher = $this->db->fetchOne($query, [$teacherId]);
        
        $query = "SELECT user_id FROM students WHERE id = ? LIMIT 1";
        $student = $this->db->fetchOne($query, [$studentId]);
        
        if (!$teacher || !$student) {
            return false;
        }
        
        $teacherUserId = $teacher['user_id'];
        $studentUserId = $student['user_id'];
        
        // إنشاء المحادثة
        $conversationId = $this->createConversation(
            [
                'subject' => $subject,
                'created_by' => $teacherUserId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [$teacherUserId, $studentUserId]
        );
        
        if (!$conversationId || empty($initialMessage)) {
            return $conversationId;
        }
        
        // إضافة الرسالة الأولية
        $this->addMessage($conversationId, $teacherUserId, $initialMessage);
        
        return $conversationId;
    }
    
    /**
     * إنشاء محادثة بين معلم وولي أمر
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $parentId معرّف ولي الأمر
     * @param string $subject موضوع المحادثة
     * @param string $initialMessage الرسالة الأولية (اختياري)
     * @return int|false معرّف المحادثة الجديدة أو false في حالة الفشل
     */
    public function createTeacherParentConversation($teacherId, $parentId, $subject, $initialMessage = null)
    {
        // الحصول على معرّف المستخدم للمعلم
        $query = "SELECT user_id FROM teachers WHERE id = ? LIMIT 1";
        $teacher = $this->db->fetchOne($query, [$teacherId]);
        
        if (!$teacher) {
            return false;
        }
        
        $teacherUserId = $teacher['user_id'];
        
        // إنشاء المحادثة
        $conversationId = $this->createConversation(
            [
                'subject' => $subject,
                'created_by' => $teacherUserId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [$teacherUserId, $parentId]
        );
        
        if (!$conversationId || empty($initialMessage)) {
            return $conversationId;
        }
        
        // إضافة الرسالة الأولية
        $this->addMessage($conversationId, $teacherUserId, $initialMessage);
        
        return $conversationId;
    }
    
    /**
     * إنشاء محادثة صفية (مجموعة)
     * 
     * @param int $teacherId معرّف المعلم
     * @param int $classId معرّف الصف
     * @param string $subject موضوع المحادثة
     * @param string $initialMessage الرسالة الأولية (اختياري)
     * @param bool $includeParents تضمين أولياء الأمور (اختياري)
     * @return int|false معرّف المحادثة الجديدة أو false في حالة الفشل
     */
    public function createClassConversation($teacherId, $classId, $subject, $initialMessage = null, $includeParents = false)
    {
        // الحصول على معرّف المستخدم للمعلم
        $query = "SELECT user_id FROM teachers WHERE id = ? LIMIT 1";
        $teacher = $this->db->fetchOne($query, [$teacherId]);
        
        if (!$teacher) {
            return false;
        }
        
        $teacherUserId = $teacher['user_id'];
        
        // الحصول على معرّفات المستخدمين للطلاب
        $query = "SELECT user_id FROM students WHERE class_id = ?";
        $students = $this->db->fetchAll($query, [$classId]);
        
        $participants = [$teacherUserId];
        
        foreach ($students as $student) {
            $participants[] = $student['user_id'];
        }
        
        // إضافة أولياء الأمور إذا تم الطلب
        if ($includeParents) {
            $query = "SELECT DISTINCT parent_id FROM students WHERE class_id = ? AND parent_id IS NOT NULL";
            $parents = $this->db->fetchAll($query, [$classId]);
            
            foreach ($parents as $parent) {
                $participants[] = $parent['parent_id'];
            }
        }
        
        // إنشاء المحادثة
        $conversationId = $this->createConversation(
            [
                'subject' => $subject,
                'created_by' => $teacherUserId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            $participants
        );
        
        if (!$conversationId || empty($initialMessage)) {
            return $conversationId;
        }
        
        // إضافة الرسالة الأولية
        $this->addMessage($conversationId, $teacherUserId, $initialMessage);
        
        return $conversationId;
    }
}