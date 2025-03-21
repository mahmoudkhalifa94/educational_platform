<?php
/**
 * app/controllers/teacher/CommunicationController.php
 * متحكم التواصل للمعلمين
 * يدير عمليات التواصل مع الطلاب وأولياء الأمور والمعلمين
 */
class CommunicationController extends Controller
{
    private $messageModel;
    private $userModel;
    private $studentModel;
    private $notificationModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->messageModel = new Message();
        $this->userModel = new User();
        $this->studentModel = new Student();
        $this->notificationModel = new Notification();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * عرض صفحة الرسائل
     */
    public function index()
    {
        // الحصول على معرّف المستخدم
        $userId = $this->auth->id();
        
        // استخراج معلمات التصفية
        $filter = $this->request->get('filter', 'inbox');
        
        // الحصول على الرسائل
        $messages = [];
        
        switch ($filter) {
            case 'inbox':
                $messages = $this->messageModel->getInboxMessages($userId);
                break;
                
            case 'sent':
                $messages = $this->messageModel->getSentMessages($userId);
                break;
                
            case 'important':
                $messages = $this->messageModel->getImportantMessages($userId);
                break;
                
            case 'unread':
                $messages = $this->messageModel->getUnreadMessages($userId);
                break;
                
            default:
                $messages = $this->messageModel->getInboxMessages($userId);
                break;
        }
        
        // عرض صفحة الرسائل
        echo $this->render('teacher/communications/index', [
            'messages' => $messages,
            'filter' => $filter,
            'unreadCount' => $this->messageModel->getUnreadCount($userId),
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض صفحة إنشاء رسالة جديدة
     */
    public function create()
    {
        // الحصول على معرّف المعلم
        $teacherId = $this->getTeacherId();
        
        if (!$teacherId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات المعلم.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على قائمة المستلمين المحتملين
        $recipients = $this->getPotentialRecipients($teacherId);
        
        // الحصول على معرّف المستلم إذا تم تحديده
        $recipientId = $this->request->get('recipient_id');
        $recipientType = $this->request->get('recipient_type');
        
        // عرض نموذج إنشاء رسالة
        echo $this->render('teacher/communications/create', [
            'recipients' => $recipients,
            'selectedRecipient' => $recipientId,
            'recipientType' => $recipientType,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إرسال رسالة جديدة
     */
    public function send()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/communications');
        }
        
        // الحصول على معرّف المستخدم
        $userId = $this->auth->id();
        
        // استخراج بيانات الرسالة
        $recipientType = $this->request->post('recipient_type');
        $recipientId = $this->request->post('recipient_id');
        $subject = $this->request->post('subject');
        $message = $this->request->post('message');
        
        // التحقق من البيانات
        $errors = $this->validate([
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'subject' => $subject,
            'message' => $message
        ], [
            'recipient_type' => 'required|in:teacher,parent,student,admin',
            'recipient_id' => 'required|numeric',
            'subject' => 'required',
            'message' => 'required'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/teacher/communications/create');
        }
        
        // الحصول على معرّف مستخدم المستلم
        $receiverId = $this->getReceiverUserId($recipientType, $recipientId);
        
        if (!$receiverId) {
            $this->setFlash('error', 'لم يتم العثور على المستلم.');
            $this->redirect('/teacher/communications/create');
        }
        
        // إرسال الرسالة
        $messageData = [
            'sender_id' => $userId,
            'receiver_id' => $receiverId,
            'subject' => $subject,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // إضافة معرّف الطالب إذا كانت الرسالة لولي أمر بخصوص طالب
        if ($recipientType === 'parent' && $this->request->post('student_id')) {
            $messageData['student_id'] = $this->request->post('student_id');
        }
        
        $messageId = $this->messageModel->send($messageData);
        
        if (!$messageId) {
            $this->setFlash('error', 'حدث خطأ أثناء إرسال الرسالة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/teacher/communications/create');
        }
        
        // إرسال إشعار للمستلم
        $senderName = $this->auth->user()['first_name'] . ' ' . $this->auth->user()['last_name'];
        
        $this->notificationModel->create([
            'user_id' => $receiverId,
            'title' => 'رسالة جديدة',
            'message' => 'لديك رسالة جديدة من ' . $senderName . ': ' . $subject,
            'type' => 'message',
            'entity_type' => 'message',
            'entity_id' => $messageId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // معالجة الملفات المرفقة إن وجدت
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $this->processAttachments($messageId);
        }
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم إرسال الرسالة بنجاح.');
        $this->redirect('/teacher/communications');
    }
    
    /**
     * عرض تفاصيل رسالة
     * 
     * @param int $id معرّف الرسالة
     */
    public function show($id)
    {
        // الحصول على معرّف المستخدم
        $userId = $this->auth->id();
        
        // الحصول على بيانات الرسالة
        $message = $this->messageModel->getMessage($id);
        
        if (!$message) {
            $this->setFlash('error', 'الرسالة غير موجودة.');
            $this->redirect('/teacher/communications');
        }
        
        // التحقق من صلاحية الوصول للرسالة
        if ($message['sender_id'] != $userId && $message['receiver_id'] != $userId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه الرسالة.');
            $this->redirect('/teacher/communications');
        }
        
        // تحديث حالة القراءة إذا كان المستخدم هو المستلم
        if ($message['receiver_id'] == $userId && !$message['is_read']) {
            $this->messageModel->markAsRead($id);
            $message['is_read'] = 1;
        }
        
        // الحصول على المرفقات
        $attachments = $this->messageModel->getAttachments($id);
        
        // الحصول على الرسائل ذات الصلة (المحادثة)
        $conversation = $this->messageModel->getConversation($message['sender_id'], $message['receiver_id']);
        
        // عرض تفاصيل الرسالة
        echo $this->render('teacher/communications/show', [
            'message' => $message,
            'attachments' => $attachments,
            'conversation' => $conversation,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * الرد على رسالة
     * 
     * @param int $id معرّف الرسالة
     */
    public function reply($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/teacher/communications');
        }
        
        // الحصول على معرّف المستخدم
        $userId = $this->auth->id();
        
        // الحصول على بيانات الرسالة الأصلية
        $originalMessage = $this->messageModel->getMessage($id);
        
        if (!$originalMessage) {
            $this->setFlash('error', 'الرسالة غير موجودة.');
            $this->redirect('/teacher/communications');
        }
        
        // التحقق من صلاحية الوصول للرسالة
        if ($originalMessage['sender_id'] != $userId && $originalMessage['receiver_id'] != $userId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه الرسالة.');
            $this->redirect('/teacher/communications');
        }
        
        // استخراج بيانات الرد
        $replyMessage = $this->request->post('message');
        
        // التحقق من البيانات
        if (empty($replyMessage)) {
            $this->setFlash('error', 'الرجاء إدخال نص الرد.');
            $this->redirect('/teacher/communications/show/' . $id);
        }
        
        // إنشاء عنوان الرد
        $replySubject = 'Re: ' . $originalMessage['subject'];
        if (strpos($originalMessage['subject'], 'Re: ') === 0) {
            $replySubject = $originalMessage['subject'];
        }
        
        // تحديد المرسل والمستلم للرد
        $receiverId = ($originalMessage['sender_id'] == $userId) ? $originalMessage['receiver_id'] : $originalMessage['sender_id'];
        
        // إرسال الرد
        $messageData = [
            'sender_id' => $userId,
            'receiver_id' => $receiverId,
            'subject' => $replySubject,
            'message' => $replyMessage,
            'parent_id' => $id, // ربط الرد بالرسالة الأصلية
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // إضافة معرّف الطالب إذا كان موجودًا في الرسالة الأصلية
        if ($originalMessage['student_id']) {
            $messageData['student_id'] = $originalMessage['student_id'];
        }
        
        $messageId = $this->messageModel->send($messageData);
        
        if (!$messageId) {
            $this->setFlash('error', 'حدث خطأ أثناء إرسال الرد. يرجى المحاولة مرة أخرى.');
            $this->redirect('/teacher/communications/show/' . $id);
        }
        
        // إرسال إشعار للمستلم
        $senderName = $this->auth->user()['first_name'] . ' ' . $this->auth->user()['last_name'];
        
        $this->notificationModel->create([
            'user_id' => $receiverId,
            'title' => 'رد جديد على رسالتك',
            'message' => 'قام ' . $senderName . ' بالرد على رسالتك: ' . $replySubject,
            'type' => 'message',
            'entity_type' => 'message',
            'entity_id' => $messageId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // معالجة الملفات المرفقة إن وجدت
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $this->processAttachments($messageId);
        }
        
        // رسالة نجاح وإعادة التوجيه
        $this->setFlash('success', 'تم إرسال الرد بنجاح.');
        $this->redirect('/teacher/communications/show/' . $id);
    }
    
    /**
     * تغيير حالة التمييز للرسالة
     * 
     * @param int $id معرّف الرسالة
     */
    public function toggleFlag($id)
    {
        // الحصول على معرّف المستخدم
        $userId = $this->auth->id();
        
        // الحصول على بيانات الرسالة
        $message = $this->messageModel->getMessage($id);
        
        if (!$message) {
            $this->json(['success' => false, 'message' => 'الرسالة غير موجودة.']);
            return;
        }
        
        // التحقق من صلاحية الوصول للرسالة
        if ($message['sender_id'] != $userId && $message['receiver_id'] != $userId) {
            $this->json(['success' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذه الرسالة.']);
            return;
        }
        
        // تغيير حالة التمييز
        $isFlagged = !$message['is_flagged'];
        $success = $this->messageModel->toggleFlag($id, $isFlagged);
        
        if ($success) {
            $this->json(['success' => true, 'flagged' => $isFlagged]);
        } else {
            $this->json(['success' => false, 'message' => 'حدث خطأ أثناء تغيير حالة التمييز.']);
        }
    }
    
    /**
     * حذف رسالة
     * 
     * @param int $id معرّف الرسالة
     */
    public function delete($id)
    {
        // الحصول على معرّف المستخدم
        $userId = $this->auth->id();
        
        // الحصول على بيانات الرسالة
        $message = $this->messageModel->getMessage($id);
        
        if (!$message) {
            $this->setFlash('error', 'الرسالة غير موجودة.');
            $this->redirect('/teacher/communications');
        }
        
        // التحقق من صلاحية الوصول للرسالة
        if ($message['sender_id'] != $userId && $message['receiver_id'] != $userId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه الرسالة.');
            $this->redirect('/teacher/communications');
        }
        
        // حذف الرسالة (بالنسبة للمستخدم الحالي)
        $isSender = ($message['sender_id'] == $userId);
        $success = $this->messageModel->deleteMessage($id, $isSender);
        
        if ($success) {
            $this->setFlash('success', 'تم حذف الرسالة بنجاح.');
        } else {
            $this->setFlash('error', 'حدث خطأ أثناء حذف الرسالة.');
        }
        
        $this->redirect('/teacher/communications');
    }
    
    /**
     * الحصول على قائمة المستلمين المحتملين
     * 
     * @param int $teacherId معرّف المعلم
     * @return array قائمة المستلمين
     */
    private function getPotentialRecipients($teacherId)
    {
        $recipients = [
            'teachers' => [],
            'parents' => [],
            'students' => [],
            'admins' => []
        ];
        
        // الحصول على المعلمين في نفس المدرسة
        $teacherModel = new Teacher();
        $recipients['teachers'] = $teacherModel->getColleagues($teacherId);
        
        // الحصول على الطلاب الذين يدرسهم المعلم
        $recipients['students'] = $this->studentModel->getStudentsByTeacher($teacherId);
        
        // الحصول على أولياء أمور الطلاب الذين يدرسهم المعلم
        $parentModel = new ParentModel();
        $recipients['parents'] = $parentModel->getParentsByTeacher($teacherId);
        
        // الحصول على مديري المدرسة
        $recipients['admins'] = $this->userModel->getSchoolAdmins($this->auth->user()['school_id']);
        
        return $recipients;
    }
    
    /**
     * الحصول على معرّف مستخدم المستلم
     * 
     * @param string $type نوع المستلم
     * @param int $id معرّف المستلم
     * @return int|null معرّف المستخدم
     */
    private function getReceiverUserId($type, $id)
    {
        switch ($type) {
            case 'teacher':
                $teacher = (new Teacher())->find($id);
                return $teacher['user_id'] ?? null;
                
            case 'parent':
                $parent = (new ParentModel())->find($id);
                return $parent['user_id'] ?? null;
                
            case 'student':
                $student = $this->studentModel->find($id);
                return $student['user_id'] ?? null;
                
            case 'admin':
                return $id; // معرّف المدير هو نفسه معرّف المستخدم
                
            default:
                return null;
        }
    }
    
    /**
     * معالجة الملفات المرفقة
     * 
     * @param int $messageId معرّف الرسالة
     */
    private function processAttachments($messageId)
    {
        $fileCount = count($_FILES['attachments']['name']);
        $uploadedAttachments = [];
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['attachments']['name'][$i],
                    'type' => $_FILES['attachments']['type'][$i],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                    'error' => $_FILES['attachments']['error'][$i],
                    'size' => $_FILES['attachments']['size'][$i]
                ];
                
                $uploadResult = $this->uploadFile($file, 'assets/uploads/messages', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'txt']);
                
                if ($uploadResult['success']) {
                    $attachmentData = [
                        'message_id' => $messageId,
                        'file_name' => $file['name'],
                        'file_path' => $uploadResult['path'],
                        'file_type' => $file['type'],
                        'file_size' => $file['size'],
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $this->messageModel->addAttachment($attachmentData);
                    $uploadedAttachments[] = $file['name'];
                }
            }
        }
        
        return $uploadedAttachments;
    }
    
    /**
     * عرض صفحة البحث في الرسائل
     */
    public function search()
    {
        // الحصول على معرّف المستخدم
        $userId = $this->auth->id();
        
        // استخراج معلمات البحث
        $query = $this->request->get('q');
        
        if (empty($query)) {
            $this->redirect('/teacher/communications');
        }
        
        // البحث في الرسائل
        $messages = $this->messageModel->searchMessages($userId, $query);
        
        // عرض نتائج البحث
        echo $this->render('teacher/communications/search', [
            'messages' => $messages,
            'query' => $query,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * الحصول على معرّف المعلم للمستخدم الحالي
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
}