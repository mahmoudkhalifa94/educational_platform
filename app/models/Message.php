<?php
/**
 * app/models/Message.php
 * نموذج الرسالة
 * يتعامل مع بيانات رسائل المحادثات في النظام
 */
class Message extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'messages';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'conversation_id', 'sender_id', 'content', 'attachments', 'created_at'
    ];
    
    /**
     * إنشاء رسالة جديدة
     * 
     * @param array $data بيانات الرسالة
     * @return int|false معرّف الرسالة الجديدة أو false في حالة الفشل
     */
    public function createMessage($data)
    {
        // التأكد من تحويل المرفقات إلى JSON
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $data['attachments'] = json_encode($data['attachments']);
        }
        
        // إضافة تاريخ الإنشاء
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        
        // إنشاء الرسالة
        $messageId = $this->create($data);
        
        if (!$messageId) {
            return false;
        }
        
        // تحديث وقت آخر تعديل للمحادثة
        $this->db->update(
            'conversations',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$data['conversation_id']]
        );
        
        // إضافة حالة قراءة للمشاركين
        $query = "SELECT user_id FROM conversation_participants
                 WHERE conversation_id = ?";
        
        $participants = $this->db->fetchAll($query, [$data['conversation_id']]);
        
        foreach ($participants as $participant) {
            $this->db->insert('message_status', [
                'message_id' => $messageId,
                'user_id' => $participant['user_id'],
                'is_read' => $participant['user_id'] == $data['sender_id'] ? 1 : 0, // مقروءة للمرسل
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $messageId;
    }
    
    /**
     * الحصول على رسالة مع التفاصيل
     * 
     * @param int $messageId معرّف الرسالة
     * @return array|false بيانات الرسالة أو false إذا لم يتم العثور عليها
     */
    public function getMessageWithDetails($messageId)
    {
        $query = "SELECT m.*,
                 u.first_name as sender_first_name, u.last_name as sender_last_name,
                 r.name as sender_role
                 FROM {$this->table} m
                 JOIN users u ON m.sender_id = u.id
                 JOIN roles r ON u.role_id = r.id
                 WHERE m.id = ?
                 LIMIT 1";
        
        $message = $this->db->fetchOne($query, [$messageId]);
        
        // تفكيك المرفقات من JSON
        if ($message && isset($message['attachments']) && !empty($message['attachments'])) {
            $message['attachments'] = json_decode($message['attachments'], true);
        }
        
        return $message;
    }
    
    /**
     * تعيين رسالة كمقروءة لمستخدم معين
     * 
     * @param int $messageId معرّف الرسالة
     * @param int $userId معرّف المستخدم
     * @return bool نجاح العملية أم لا
     */
    public function markAsRead($messageId, $userId)
    {
        $query = "UPDATE message_status
                 SET is_read = 1, read_at = ?
                 WHERE message_id = ? AND user_id = ? AND is_read = 0";
        
        $this->db->query($query, [date('Y-m-d H:i:s'), $messageId, $userId]);
        
        return true;
    }
    
    /**
     * الحصول على رسائل محادثة معينة
     * 
     * @param int $conversationId معرّف المحادثة
     * @param int|null $userId معرّف المستخدم (للتحقق من حالة القراءة)
     * @param int $limit عدد الرسائل
     * @param int $offset موضع البداية
     * @return array قائمة الرسائل
     */
    public function getConversationMessages($conversationId, $userId = null, $limit = 50, $offset = 0)
    {
        $query = "SELECT m.*,
                 u.first_name as sender_first_name, u.last_name as sender_last_name,
                 r.name as sender_role";
        
        if ($userId !== null) {
            $query .= ", ms.is_read";
        }
        
        $query .= " FROM {$this->table} m
                   JOIN users u ON m.sender_id = u.id
                   JOIN roles r ON u.role_id = r.id";
        
        if ($userId !== null) {
            $query .= " LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?";
        }
        
        $query .= " WHERE m.conversation_id = ?
                   ORDER BY m.created_at ASC
                   LIMIT ? OFFSET ?";
        
        $params = $userId !== null 
            ? [$userId, $conversationId, $limit, $offset]
            : [$conversationId, $limit, $offset];
        
        $messages = $this->db->fetchAll($query, $params);
        
        // تفكيك المرفقات من JSON
        foreach ($messages as &$message) {
            if (isset($message['attachments']) && !empty($message['attachments'])) {
                $message['attachments'] = json_decode($message['attachments'], true);
            }
        }
        
        return $messages;
    }
    
    /**
     * الحصول على الرسائل غير المقروءة للمستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @param int $limit عدد الرسائل
     * @return array قائمة الرسائل
     */
    public function getUnreadMessages($userId, $limit = 20)
    {
        $query = "SELECT m.*,
                 u.first_name as sender_first_name, u.last_name as sender_last_name,
                 c.subject as conversation_subject
                 FROM {$this->table} m
                 JOIN users u ON m.sender_id = u.id
                 JOIN conversations c ON m.conversation_id = c.id
                 JOIN message_status ms ON m.id = ms.message_id
                 WHERE ms.user_id = ? AND ms.is_read = 0
                 ORDER BY m.created_at DESC
                 LIMIT ?";
        
        $messages = $this->db->fetchAll($query, [$userId, $limit]);
        
        // تفكيك المرفقات من JSON
        foreach ($messages as &$message) {
            if (isset($message['attachments']) && !empty($message['attachments'])) {
                $message['attachments'] = json_decode($message['attachments'], true);
            }
        }
        
        return $messages;
    }
    
    /**
     * البحث في رسائل المستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @param string $searchTerm مصطلح البحث
     * @return array نتائج البحث
     */
    public function searchUserMessages($userId, $searchTerm)
    {
        $query = "SELECT m.*,
                 u.first_name as sender_first_name, u.last_name as sender_last_name,
                 c.subject as conversation_subject
                 FROM {$this->table} m
                 JOIN users u ON m.sender_id = u.id
                 JOIN conversations c ON m.conversation_id = c.id
                 JOIN conversation_participants cp ON c.id = cp.conversation_id
                 WHERE cp.user_id = ? AND m.content LIKE ?
                 ORDER BY m.created_at DESC
                 LIMIT 100";
        
        $searchParam = "%{$searchTerm}%";
        
        $messages = $this->db->fetchAll($query, [$userId, $searchParam]);
        
        // تفكيك المرفقات من JSON
        foreach ($messages as &$message) {
            if (isset($message['attachments']) && !empty($message['attachments'])) {
                $message['attachments'] = json_decode($message['attachments'], true);
            }
        }
        
        return $messages;
    }
    
    /**
     * إضافة مرفق إلى رسالة
     * 
     * @param int $messageId معرّف الرسالة
     * @param array $attachment بيانات المرفق
     * @return bool نجاح العملية أم لا
     */
    public function addAttachment($messageId, $attachment)
    {
        // الحصول على الرسالة
        $message = $this->find($messageId);
        
        if (!$message) {
            return false;
        }
        
        // تحديث المرفقات
        $attachments = [];
        
        if (!empty($message['attachments'])) {
            $attachments = json_decode($message['attachments'], true);
        }
        
        $attachments[] = $attachment;
        
        return $this->update($messageId, [
            'attachments' => json_encode($attachments)
        ]);
    }
    
    /**
     * حذف مرفق من رسالة
     * 
     * @param int $messageId معرّف الرسالة
     * @param int $attachmentIndex فهرس المرفق
     * @return bool نجاح العملية أم لا
     */
    public function removeAttachment($messageId, $attachmentIndex)
    {
        // الحصول على الرسالة
        $message = $this->find($messageId);
        
        if (!$message || empty($message['attachments'])) {
            return false;
        }
        
        // تحديث المرفقات
        $attachments = json_decode($message['attachments'], true);
        
        if (!isset($attachments[$attachmentIndex])) {
            return false;
        }
        
        // حذف المرفق
        array_splice($attachments, $attachmentIndex, 1);
        
        return $this->update($messageId, [
            'attachments' => empty($attachments) ? null : json_encode($attachments)
        ]);
    }
    
    /**
     * حذف رسالة
     * 
     * @param int $messageId معرّف الرسالة
     * @param int $userId معرّف المستخدم (للتحقق من الصلاحيات)
     * @return bool نجاح العملية أم لا
     */
    public function deleteMessage($messageId, $userId)
    {
        // الحصول على الرسالة
        $message = $this->find($messageId);
        
        if (!$message) {
            return false;
        }
        
        // التحقق من أن المستخدم هو مرسل الرسالة
        if ($message['sender_id'] != $userId) {
            return false;
        }
        
        // حذف حالات القراءة
        $this->db->delete('message_status', 'message_id = ?', [$messageId]);
        
        // حذف الرسالة
        return $this->delete($messageId);
    }
    
    /**
     * إحصائيات الرسائل
     * 
     * @param int $conversationId معرّف المحادثة (اختياري)
     * @param int $userId معرّف المستخدم (اختياري)
     * @return array الإحصائيات
     */
    public function getMessageStats($conversationId = null, $userId = null)
    {
        $stats = [];
        $params = [];
        
        // بناء شرط WHERE
        $whereConditions = [];
        
        if ($conversationId !== null) {
            $whereConditions[] = "m.conversation_id = ?";
            $params[] = $conversationId;
        }
        
        if ($userId !== null) {
            $whereConditions[] = "m.sender_id = ?";
            $params[] = $userId;
        }
        
        $whereClause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);
        
        // إجمالي عدد الرسائل
        $query = "SELECT COUNT(*) as count FROM {$this->table} m {$whereClause}";
        $result = $this->db->fetchOne($query, $params);
        $stats['total_messages'] = $result['count'] ?? 0;
        
        // متوسط طول الرسالة
        $query = "SELECT AVG(LENGTH(content)) as avg_length FROM {$this->table} m {$whereClause}";
        $result = $this->db->fetchOne($query, $params);
        $stats['avg_message_length'] = $result['avg_length'] ? round($result['avg_length'], 1) : 0;
        
        // الفترة الزمنية
        if ($conversationId !== null) {
            $query = "SELECT MIN(created_at) as first_message, MAX(created_at) as last_message 
                     FROM {$this->table} m 
                     WHERE m.conversation_id = ?";
            $result = $this->db->fetchOne($query, [$conversationId]);
            
            if ($result) {
                $stats['first_message'] = $result['first_message'];
                $stats['last_message'] = $result['last_message'];
                
                if ($result['first_message'] && $result['last_message']) {
                    $first = new DateTime($result['first_message']);
                    $last = new DateTime($result['last_message']);
                    $interval = $first->diff($last);
                    
                    $stats['conversation_duration_days'] = $interval->days;
                }
            }
        }
        
        return $stats;
    }
}