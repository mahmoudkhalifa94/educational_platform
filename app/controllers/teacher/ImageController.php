<?php
/**
 * app/controllers/teacher/ImageController.php
 * متحكم الصور للمعلمين
 * يدير عمليات رفع ومعالجة الصور
 */
class ImageController extends Controller
{
    /**
     * تهيئة المتحكم
     */
    public function __construct()
    {
        parent::__construct();
        
        // التحقق من صلاحية الوصول
        $this->requireRole('teacher');
    }
    
    /**
     * رفع صورة عبر AJAX
     */
    public function upload()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.']);
            return;
        }
        
        // التحقق من وجود ملف
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'لم يتم تحديد ملف صورة صالح.']);
            return;
        }
        
        // تحديد مجلد الوجهة
        $folder = $this->request->post('folder', 'general');
        $destination = 'assets/uploads/';
        
        switch ($folder) {
            case 'assignments':
                $destination .= 'assignments/';
                break;
            case 'profile':
                $destination .= 'profile_pictures/';
                break;
            case 'materials':
                $destination .= 'materials/';
                break;
            default:
                $destination .= 'images/';
                break;
        }
        
        // التأكد من وجود المجلد
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        // تنفيذ عملية الرفع
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        $maxSize = 5 * 1024 * 1024; // 5 ميجابايت
        
        $uploadResult = $this->uploadFile('image', $destination, $allowedExtensions, $maxSize);
        
        if ($uploadResult['success']) {
            $this->json([
                'success' => true, 
                'message' => 'تم رفع الصورة بنجاح.',
                'url' => '/' . $uploadResult['path'],
                'path' => $uploadResult['path']
            ]);
        } else {
            $this->json(['success' => false, 'message' => $uploadResult['message']]);
        }
    }
    
    /**
     * تحميل صورة
     * 
     * @param string $path مسار الصورة
     */
    public function download($path)
    {
        // فك تشفير المسار
        $path = base64_decode($path);
        
        // التحقق من وجود الملف
        if (!file_exists($path)) {
            $this->setFlash('error', 'الصورة غير موجودة.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على نوع الملف
        $mimeType = mime_content_type($path);
        
        // التحقق من أن الملف صورة
        if (strpos($mimeType, 'image/') !== 0) {
            $this->setFlash('error', 'الملف المطلوب ليس صورة.');
            $this->redirect('/teacher/dashboard');
        }
        
        // تعيين رأس الاستجابة
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        
        // قراءة الملف وإرساله
        readfile($path);
        exit;
    }
    
    /**
     * حذف صورة
     */
    public function delete()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->json(['success' => false, 'message' => 'طريقة الطلب غير صحيحة.']);
            return;
        }
        
        // الحصول على مسار الصورة
        $path = $this->request->post('path');
        
        if (empty($path)) {
            $this->json(['success' => false, 'message' => 'لم يتم تحديد مسار الصورة.']);
            return;
        }
        
        // التحقق من أن المسار آمن (داخل مجلد الرفع)
        if (strpos($path, 'assets/uploads/') !== 0) {
            $this->json(['success' => false, 'message' => 'مسار الصورة غير صالح.']);
            return;
        }
        
        // التحقق من وجود الملف
        if (!file_exists($path)) {
            $this->json(['success' => false, 'message' => 'الصورة غير موجودة.']);
            return;
        }
        
        // حذف الملف
        if (unlink($path)) {
            $this->json(['success' => true, 'message' => 'تم حذف الصورة بنجاح.']);
        } else {
            $this->json(['success' => false, 'message' => 'حدث خطأ أثناء حذف الصورة.']);
        }
    }
    
    /**
     * عرض محرر الصور
     */
    public function editor()
    {
        // الحصول على معلمات الإدخال
        $imagePath = $this->request->get('path');
        $returnUrl = $this->request->get('return', '/teacher/dashboard');
        
        // التحقق من وجود مسار الصورة
        if (empty($imagePath) || !file_exists($imagePath)) {
            $this->setFlash('error', 'الصورة غير موجودة.');
            $this->redirect($returnUrl);
        }
        
        // التحقق من أن الملف صورة
        $mimeType = mime_content_type($imagePath);
        if (strpos($mimeType, 'image/') !== 0) {
            $this->setFlash('error', 'الملف المحدد ليس صورة.');
            $this->redirect($returnUrl);
        }
        
        // عرض صفحة المحرر
        echo $this->render('teacher/images/editor', [
            'imagePath' => $imagePath,
            'returnUrl' => $returnUrl,
            'flash' => $this->getFlash()
        ]);
    }
    
    /**
     * معالجة الصورة (تدوير، قص، تغيير الحجم)
     */
    public function process()
    {
        // التحقق من طريقة الطلب
        if ($this->request->method() !== 'POST') {
            $this->setFlash('error', 'طريقة الطلب غير صحيحة.');
            $this->redirect('/teacher/dashboard');
        }
        
        // الحصول على معلمات الإدخال
        $imagePath = $this->request->post('image_path');
        $action = $this->request->post('action');
        $returnUrl = $this->request->post('return_url', '/teacher/dashboard');
        
        // التحقق من وجود مسار الصورة
        if (empty($imagePath) || !file_exists($imagePath)) {
            $this->setFlash('error', 'الصورة غير موجودة.');
            $this->redirect($returnUrl);
        }
        
        // التحقق من نوع العملية
        if (!in_array($action, ['rotate', 'crop', 'resize'])) {
            $this->setFlash('error', 'العملية غير صالحة.');
            $this->redirect('/teacher/images/editor?path=' . urlencode($imagePath) . '&return=' . urlencode($returnUrl));
        }
        
        // تحضير مكتبة معالجة الصور
        $image = new ImageProcessor($imagePath);
        
        try {
            switch ($action) {
                case 'rotate':
                    $angle = $this->request->post('angle', 90);
                    $image->rotate($angle);
                    break;
                    
                case 'crop':
                    $x = $this->request->post('x', 0);
                    $y = $this->request->post('y', 0);
                    $width = $this->request->post('width', 100);
                    $height = $this->request->post('height', 100);
                    $image->crop($x, $y, $width, $height);
                    break;
                    
                case 'resize':
                    $width = $this->request->post('width', 800);
                    $height = $this->request->post('height', 600);
                    $image->resize($width, $height);
                    break;
            }
            
            // حفظ الصورة
            $image->save();
            
            $this->setFlash('success', 'تم معالجة الصورة بنجاح.');
        } catch (Exception $e) {
            $this->setFlash('error', 'حدث خطأ أثناء معالجة الصورة: ' . $e->getMessage());
        }
        
        // العودة إلى المحرر أو الصفحة المحددة
        if ($this->request->post('continue_editing', 0)) {
            $this->redirect('/teacher/images/editor?path=' . urlencode($imagePath) . '&return=' . urlencode($returnUrl));
        } else {
            $this->redirect($returnUrl);
        }
    }
}

/**
 * فئة مساعدة لمعالجة الصور
 */
class ImageProcessor
{
    private $image;
    private $path;
    private $type;
    
    /**
     * تهيئة المعالج
     * 
     * @param string $path مسار الصورة
     */
    public function __construct($path)
    {
        $this->path = $path;
        
        // تحديد نوع الصورة
        $info = getimagesize($path);
        $this->type = $info[2];
        
        // إنشاء مصدر الصورة
        switch ($this->type) {
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($path);
                break;
            default:
                throw new Exception('نوع الصورة غير مدعوم.');
        }
        
        // الحفاظ على الشفافية
        if ($this->type == IMAGETYPE_PNG || $this->type == IMAGETYPE_GIF) {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
        }
    }
    
    /**
     * تدوير الصورة
     * 
     * @param int $angle زاوية الدوران
     */
    public function rotate($angle)
    {
        $this->image = imagerotate($this->image, -$angle, 0);
    }
    
    /**
     * قص الصورة
     * 
     * @param int $x إحداثية س
     * @param int $y إحداثية ص
     * @param int $width العرض
     * @param int $height الارتفاع
     */
    public function crop($x, $y, $width, $height)
    {
        $new = imagecreatetruecolor($width, $height);
        
        // الحفاظ على الشفافية
        if ($this->type == IMAGETYPE_PNG || $this->type == IMAGETYPE_GIF) {
            imagealphablending($new, false);
            imagesavealpha($new, true);
            $transparent = imagecolorallocatealpha($new, 255, 255, 255, 127);
            imagefilledrectangle($new, 0, 0, $width, $height, $transparent);
        }
        
        imagecopy($new, $this->image, 0, 0, $x, $y, $width, $height);
        $this->image = $new;
    }
    
    /**
     * تغيير حجم الصورة
     * 
     * @param int $width العرض الجديد
     * @param int $height الارتفاع الجديد
     */
    public function resize($width, $height)
    {
        $new = imagecreatetruecolor($width, $height);
        
        // الحفاظ على الشفافية
        if ($this->type == IMAGETYPE_PNG || $this->type == IMAGETYPE_GIF) {
            imagealphablending($new, false);
            imagesavealpha($new, true);
            $transparent = imagecolorallocatealpha($new, 255, 255, 255, 127);
            imagefilledrectangle($new, 0, 0, $width, $height, $transparent);
        }
        
        imagecopyresampled($new, $this->image, 0, 0, 0, 0, $width, $height, imagesx($this->image), imagesy($this->image));
        $this->image = $new;
    }
    
    /**
     * حفظ الصورة
     */
    public function save()
    {
        switch ($this->type) {
            case IMAGETYPE_JPEG:
                imagejpeg($this->image, $this->path, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->image, $this->path, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->image, $this->path);
                break;
        }
    }
    
    /**
     * تحرير المصادر عند الانتهاء
     */
    public function __destruct()
    {
        if (is_resource($this->image)) {
            imagedestroy($this->image);
        }
    }
}