<?php
/**
 * app/controllers/parent/CommunicationController.php
 * متحكم التواصل لولي الأمر
 * يدير إرسال واستقبال الرسائل والتواصل مع المعلمين وإدارة المدرسة
 */
class CommunicationController extends Controller
{
    private $parentModel;
    private $teacherModel;
    private $messageModel;
    private $childModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->parentModel = new ParentModel();
        $this->teacherModel = new Teacher();
        $this->messageModel = new Message();
        $this->childModel = new Student();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('parent');
    }
    
    /**
     * عرض قائمة المحادثات
     */
    public function index()
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // الحصول على قائمة الأبناء
        $children = $this->parentModel->getChildrenByParentId($parentId);
        
        // الحصول على قائمة المحادثات
        $conversations = $this->messageModel->getParentConversations($parentId);
        
        echo $this->render('parent/communication/index', [
            'children' => $children,
            'conversations' => $conversations,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض محادثة معينة
     * 
     * @param int $id معرّف المحادثة
     */
    public function show($id)
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // الحصول على تفاصيل المحادثة
        $conversation = $this->messageModel->getConversation($id);
        
        if (!$conversation) {
            $this->setFlash('error', 'لم يتم العثور على المحادثة المطلوبة.');
            $this->redirect('/parent/communication');
        }
        
        // التحقق من أحقية الوصول للمحادثة
        if ($conversation['parent_id'] != $parentId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المحادثة.');
            $this->redirect('/parent/communication');
        }
        
        // الحصول على رسائل المحادثة
        $messages = $this->messageModel->getConversationMessages($id);
        
        // تحديث حالة القراءة للرسائل
        $this->messageModel->markConversationAsRead($id, $this->auth->id());
        
        echo $this->render('parent/communication/show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * بدء محادثة جديدة
     */
    public function create()
    {
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // الحصول على قائمة الأبناء
        $children = $this->parentModel->getChildrenByParentId($parentId);
        
        if (empty($children)) {
            $this->setFlash('error', 'لا يمكن إنشاء محادثة جديدة حيث لا يوجد أبناء مرتبطين بحسابك.');
            $this->redirect('/parent/communication');
        }
        
        // استخراج معرّف الطالب إذا تم تحديده
        $childId = $this->request->get('child_id');
        
        // الحصول على قائمة معلمي الأبناء
        $teachers = [];
        
        if ($childId) {
            // التحقق من أن الطالب ينتمي لولي الأمر
            $isParentChild = $this->parentModel->isParentChild($parentId, $childId);
            
            if (!$isParentChild) {
                $this->setFlash('error', 'الطالب المحدد غير مرتبط بحسابك.');
                $this->redirect('/parent/communication/create');
            }
            
            // الحصول على معلمي الطالب المحدد
            $teachers = $this->teacherModel->getTeachersByStudent($childId);
        } else if (!empty($children)) {
            // الحصول على معلمي أول طالب افتراضياً
            $teachers = $this->teacherModel->getTeachersByStudent($children[0]['id']);
            $childId = $children[0]['id'];
        }
        
        echo $this->render('parent/communication/create', [
            'children' => $children,
            'teachers' => $teachers,
            'selectedChildId' => $childId,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة إنشاء محادثة جديدة
     */
    public function store()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/parent/communication');
        }
        
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // استخراج بيانات النموذج
        $childId = $this->request->post('child_id');
        $teacherId = $this->request->post('teacher_id');
        $subject = $this->request->post('subject');
        $content = $this->request->post('content');
        
        // التحقق من البيانات المدخلة
        $errors = $this->validate([
            'child_id' => $childId,
            'teacher_id' => $teacherId,
            'subject' => $subject,
            'content' => $content
        ], [
            'child_id' => 'required|numeric',
            'teacher_id' => 'required|numeric',
            'subject' => 'required|min:3|max:100',
            'content' => 'required|min:5'
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'يرجى التحقق من البيانات المدخلة.');
            $this->redirect('/parent/communication/create?child_id=' . $childId);
        }
        
        // التحقق من أن الطالب ينتمي لولي الأمر
        $isParentChild = $this->parentModel->isParentChild($parentId, $childId);
        
        if (!$isParentChild) {
            $this->setFlash('error', 'الطالب المحدد غير مرتبط بحسابك.');
            $this->redirect('/parent/communication/create');
        }
        
        // الحصول على معلومات الطالب
        $child = $this->childModel->find($childId);
        
        if (!$child) {
            $this->setFlash('error', 'لم يتم العثور على بيانات الطالب.');
            $this->redirect('/parent/communication/create');
        }
        
        // إنشاء محادثة جديدة
        $conversationData = [
            'parent_id' => $parentId,
            'teacher_id' => $teacherId,
            'student_id' => $childId,
            'subject' => $subject,
            'last_message_at' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];
        
        $conversationId = $this->messageModel->createConversation($conversationData);
        
        if (!$conversationId) {
            $this->setFlash('error', 'حدث خطأ أثناء إنشاء المحادثة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/parent/communication/create?child_id=' . $childId);
        }
        
        // إضافة الرسالة الأولى
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_id' => $this->auth->id(),
            'sender_type' => 'parent',
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];
        
        $messageId = $this->messageModel->createMessage($messageData);
        
        if (!$messageId) {
            $this->setFlash('error', 'حدث خطأ أثناء إرسال الرسالة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/parent/communication/create?child_id=' . $childId);
        }
        
        // تحديث توقيت آخر رسالة في المحادثة
        $this->messageModel->updateConversationLastMessageTime($conversationId);
        
        // إنشاء إشعار للمعلم
        $teacherUserId = $this->teacherModel->getTeacherUserId($teacherId);
        
        if ($teacherUserId) {
            $notificationModel = new Notification();
            $notificationData = [
                'user_id' => $teacherUserId,
                'type' => 'new_message',
                'title' => 'رسالة جديدة من ولي أمر',
                'content' => 'لديك رسالة جديدة من ولي أمر الطالب ' . $child['first_name'] . ' ' . $child['last_name'],
                'link' => '/teacher/communication/' . $conversationId,
                'is_read' => 0
            ];
            
            $notificationModel->create($notificationData);
        }
        
        $this->setFlash('success', 'تم إنشاء المحادثة وإرسال الرسالة بنجاح.');
        $this->redirect('/parent/communication/' . $conversationId);
    }
    
    /**
     * إضافة رسالة جديدة لمحادثة قائمة
     * 
     * @param int $id معرّف المحادثة
     */
    public function reply($id)
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->redirect('/parent/communication');
        }
        
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->setFlash('error', 'لم يتم العثور على بيانات ولي الأمر.');
            $this->redirect('/login');
        }
        
        // الحصول على تفاصيل المحادثة
        $conversation = $this->messageModel->getConversation($id);
        
        if (!$conversation) {
            $this->setFlash('error', 'لم يتم العثور على المحادثة المطلوبة.');
            $this->redirect('/parent/communication');
        }
        
        // التحقق من أحقية الوصول للمحادثة
        if ($conversation['parent_id'] != $parentId) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه المحادثة.');
            $this->redirect('/parent/communication');
        }
        
        // استخراج محتوى الرسالة
        $content = $this->request->post('content');
        
        // التحقق من المحتوى
        if (empty($content)) {
            $this->setFlash('error', 'لا يمكن إرسال رسالة فارغة.');
            $this->redirect('/parent/communication/' . $id);
        }
        
        // إضافة رسالة جديدة
        $messageData = [
            'conversation_id' => $id,
            'sender_id' => $this->auth->id(),
            'sender_type' => 'parent',
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];
        
        $messageId = $this->messageModel->createMessage($messageData);
        
        if (!$messageId) {
            $this->setFlash('error', 'حدث خطأ أثناء إرسال الرسالة. يرجى المحاولة مرة أخرى.');
            $this->redirect('/parent/communication/' . $id);
        }
        
        // تحديث توقيت آخر رسالة في المحادثة
        $this->messageModel->updateConversationLastMessageTime($id);
        
        // إنشاء إشعار للمعلم
        $teacherUserId = $this->teacherModel->getTeacherUserId($conversation['teacher_id']);
        
        if ($teacherUserId) {
            $notificationModel = new Notification();
            
            // الحصول على معلومات الطالب
            $student = $this->childModel->find($conversation['student_id']);
            $studentName = $student ? $student['first_name'] . ' ' . $student['last_name'] : 'طالب';
            
            $notificationData = [
                'user_id' => $teacherUserId,
                'type' => 'new_message',
                'title' => 'رد جديد على محادثة',
                'content' => 'لديك رد جديد من ولي أمر الطالب ' . $studentName . ' على محادثة "' . $conversation['subject'] . '"',
                'link' => '/teacher/communication/' . $id,
                'is_read' => 0
            ];
            
            $notificationModel->create($notificationData);
        }
        
        $this->setFlash('success', 'تم إرسال الرسالة بنجاح.');
        $this->redirect('/parent/communication/' . $id);
    }
    
    /**
     * الحصول على قائمة معلمي طالب محدد (للطلبات AJAX)
     */
    public function getTeachersByStudent()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'GET') {
            $this->json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
            return;
        }
        
        // الحصول على معرّف ولي الأمر
        $parentId = $this->getParentId();
        
        if (!$parentId) {
            $this->json(['success' => false, 'message' => 'لم يتم العثور على بيانات ولي الأمر']);
            return;
        }
        
        // استخراج معرّف الطالب
        $childId = $this->request->get('child_id');
        
        if (!$childId) {
            $this->json(['success' => false, 'message' => 'يجب تحديد الطالب']);
            return;
        }
        
        // التحقق من أن الطالب ينتمي لولي الأمر
        $isParentChild = $this->parentModel->isParentChild($parentId, $childId);
        
        if (!$isParentChild) {
            $this->json(['success' => false, 'message' => 'الطالب المحدد غير مرتبط بحسابك']);
            return;
        }
        
        // الحصول على معلمي الطالب
        $teachers = $this->teacherModel->getTeachersByStudent($childId);
        
        $this->json(['success' => true, 'teachers' => $teachers]);
    }
    
    /**
     * الحصول على معرّف ولي الأمر للمستخدم الحالي
     * 
     * @return int|false معرّف ولي الأمر أو false إذا لم يتم العثور عليه
     */
    private function getParentId()
    {
        $parent = $this->parentModel->getParentByUserId($this->auth->id());
        return $parent ? $parent['id'] : false;
    }
}