<?php
/**
 * app/controllers/parent/NotificationController.php
 * متحكم إشعارات ولي الأمر
 * يدير عرض وإدارة الإشعارات لولي الأمر
 */
class NotificationController extends Controller
{
    private $notificationModel;
    private $parentModel;
    
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new Notification();
        $this->parentModel = new ParentModel();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('parent');
    }
    
    /**
     * عرض قائمة الإشعارات
     */
    public function index()
    {
        // استخراج معلمات التصفية
        $type = $this->request->get('type', 'all');
        $isRead = $this->request->get('is_read', 'all');
        
        // بناء شروط التصفية
        $conditions = ['user_id' => $this->auth->id()];
        
        if ($type !== 'all') {
            $conditions['type'] = $type;
        }
        
        if ($isRead !== 'all') {
            $conditions['is_read'] = ($isRead === 'read') ? 1 : 0;
        }
        
        // الحصول على الإشعارات
        $notifications = $this->notificationModel->getNotifications($conditions);
        
        // الحصول على عدد الإشعارات غير المقروءة
        $unreadCount = $this->notificationModel->getUnreadCount($this->auth->id());
        
        echo $this->render('parent/notifications/index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'selectedType' => $type,
            'selectedIsRead' => $isRead,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * عرض إشعار معين
     * 
     * @param int $id معرّف الإشعار
     */
    public function show($id)
    {
        // الحصول على تفاصيل الإشعار
        $notification = $this->notificationModel->find($id);
        
        if (!$notification) {
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الإشعار.');
            $this->redirect('/parent/notifications');
        }
        
        // تحديث حالة القراءة
        if (!$notification['is_read']) {
            $this->notificationModel->markAsRead($id);
        }
        
        echo $this->render('parent/notifications/show', [
            'notification' => $notification,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * تعليم إشعار كمقروء
     * 
     * @param int $id معرّف الإشعار
     */
    public function markAsRead($id)
    {
        // الحصول على تفاصيل الإشعار
        $notification = $this->notificationModel->find($id);
        
        if (!$notification) {
            $this->json(['success' => false, 'message' => 'لم يتم العثور على الإشعار المطلوب']);
            return;
        }
        
        // التحقق من أحقية الوصول للإشعار
        if ($notification['user_id'] != $this->auth->id()) {
            $this->json(['success' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذا الإشعار']);
            return;
        }
        
        // تحديث حالة القراءة
        $success = $this->notificationModel->markAsRead($id);
        
        $this->json([
            'success' => $success,
            'message' => $success ? 'تم تعليم الإشعار كمقروء بنجاح' : 'حدث خطأ أثناء تحديث حالة الإشعار'
        ]);
    }
    
    /**
     * تعليم جميع الإشعارات كمقروءة
     */
    public function markAllAsRead()
    {
        // تحديث حالة جميع الإشعارات
        $success = $this->notificationModel->markAllAsRead($this->auth->id());
        
        if ($this->request->isAjax()) {
            $this->json([
                'success' => $success,
                'message' => $success ? 'تم تعليم جميع الإشعارات كمقروءة بنجاح' : 'حدث خطأ أثناء تحديث حالة الإشعارات'
            ]);
        } else {
            if ($success) {
                $this->setFlash('success', 'تم تعليم جميع الإشعارات كمقروءة بنجاح.');
            } else {
                $this->setFlash('error', 'حدث خطأ أثناء تحديث حالة الإشعارات.');
            }
            
            $this->redirect('/parent/notifications');
        }
    }
    
    /**
     * حذف إشعار
     * 
     * @param int $id معرّف الإشعار
     */
    public function delete($id)
    {
        // الحصول على تفاصيل الإشعار
        $notification = $this->notificationModel->find($id);
        
        if (!$notification) {
            if ($this->request->isAjax()) {
                $this->json(['success' => false, 'message' => 'لم يتم العثور على الإشعار المطلوب']);
                return;
            }
            
            $this->setFlash('error', 'لم يتم العثور على الإشعار المطلوب.');
            $this->redirect('/parent/notifications');
        }
        
        // التحقق من أحقية الوصول للإشعار
        if ($notification['user_id'] != $this->auth->id()) {
            if ($this->request->isAjax()) {
                $this->json(['success' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذا الإشعار']);
                return;
            }
            
            $this->setFlash('error', 'ليس لديك صلاحية للوصول إلى هذا الإشعار.');
            $this->redirect('/parent/notifications');
        }
        
        // حذف الإشعار
        $success = $this->notificationModel->delete($id);
        
        if ($this->request->isAjax()) {
            $this->json([
                'success' => $success,
                'message' => $success ? 'تم حذف الإشعار بنجاح' : 'حدث خطأ أثناء حذف الإشعار'
            ]);
        } else {
            if ($success) {
                $this->setFlash('success', 'تم حذف الإشعار بنجاح.');
            } else {
                $this->setFlash('error', 'حدث خطأ أثناء حذف الإشعار.');
            }
            
            $this->redirect('/parent/notifications');
        }
    }
    
    /**
     * الحصول على عدد الإشعارات غير المقروءة (AJAX)
     */
    public function getUnreadCount()
    {
        $count = $this->notificationModel->getUnreadCount($this->auth->id());
        
        $this->json([
            'success' => true,
            'count' => $count
        ]);
    }
    
    /**
     * الحصول على آخر الإشعارات (AJAX)
     */
    public function getLatest()
    {
        // الحصول على آخر 5 إشعارات
        $notifications = $this->notificationModel->getLatestNotificationsByUser($this->auth->id(), 5);
        
        // الحصول على عدد الإشعارات غير المقروءة
        $unreadCount = $this->notificationModel->getUnreadCount($this->auth->id());
        
        $this->json([
            'success' => true,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount
        ]);
    }
    
    public function click($id)
    {
        // التحقق من وجود الإشعار
        $notification = $this->notificationModel->find($id);
        
        if (!$notification) {
            $this->setFlash('error', 'الإشعار غير موجود.');
            $this->redirect('/parent/notifications');
        }
        
        // التحقق من ملكية الإشعار للمستخدم
        if ($notification['user_id'] != $this->auth->id()) {
            $this->setFlash('error', 'ليس لديك صلاحية الوصول إلى هذا الإشعار.');
            $this->redirect('/parent/notifications');
        }
        
        // تحديث حالة الإشعار كمقروء
        $this->notificationModel->markAsRead($id);
        
        // توجيه المستخدم بناءً على نوع الإشعار
        switch ($notification['type']) {
            case 'grade':
                // الانتقال إلى صفحة عرض درجة الطالب
                $submissionId = $notification['entity_id'];
                $this->redirect("/parent/grades/view-submission/{$submissionId}");
                break;
            
            case 'assignment':
                // الانتقال إلى تفاصيل المهمة
                $assignmentId = $notification['entity_id'];
                $this->redirect("/parent/assignments/show/{$assignmentId}");
                break;
            
            case 'communication':
                // الانتقال إلى رسالة التواصل
                $communicationId = $notification['entity_id'];
                $this->redirect("/parent/communication/show/{$communicationId}");
                break;
            
            case 'school_announcement':
                // الانتقال إلى صفحة الإعلانات
                $this->redirect("/parent/announcements");
                break;
            
            case 'behavior':
                // الانتقال إلى تقرير سلوك الطالب
                $studentId = $notification['entity_id'];
                $this->redirect("/parent/students/behavior/{$studentId}");
                break;
            
            case 'attendance':
                // الانتقال إلى تقرير حضور الطالب
                $studentId = $notification['entity_id'];
                $this->redirect("/parent/students/attendance/{$studentId}");
                break;
            
            default:
                // توجيه عام إلى لوحة التحكم الرئيسية
                $this->redirect('/parent/dashboard');
                break;
        }
    }
}