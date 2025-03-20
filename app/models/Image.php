<?php
/**
 * app/models/Image.php
 * نموذج الصورة
 * يتعامل مع بيانات معرض الصور في النظام
 */
class Image extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'images';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'class_id', 'album_id', 'title', 'description', 
        'file_path', 'thumbnail_path', 'uploaded_by', 'privacy_level'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء صورة جديدة
     * 
     * @param array $data بيانات الصورة
     * @return int|false معرّف الصورة الجديدة أو false في حالة الفشل
     */
    public function createImage($data)
    {
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات الصورة
     * 
     * @param int $id معرّف الصورة
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateImage($id, $data)
    {
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على صورة مع التفاصيل
     * 
     * @param int $imageId معرّف الصورة
     * @return array|false بيانات الصورة أو false إذا لم يتم العثور عليها
     */
    public function getImageWithDetails($imageId)
    {
        $query = "SELECT i.*, 
                 c.name as class_name, a.name as album_name,
                 u.first_name as uploader_first_name, u.last_name as uploader_last_name
                 FROM {$this->table} i 
                 LEFT JOIN classes c ON i.class_id = c.id 
                 LEFT JOIN albums a ON i.album_id = a.id 
                 JOIN users u ON i.uploaded_by = u.id 
                 WHERE i.id = ? 
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$imageId]);
    }
    
    /**
     * الحصول على الصور حسب الألبوم
     * 
     * @param int $albumId معرّف الألبوم
     * @return array قائمة الصور
     */
    public function getImagesByAlbum($albumId)
    {
        $query = "SELECT i.*, 
                 u.first_name as uploader_first_name, u.last_name as uploader_last_name
                 FROM {$this->table} i 
                 JOIN users u ON i.uploaded_by = u.id 
                 WHERE i.album_id = ? 
                 ORDER BY i.created_at DESC";
        
        return $this->db->fetchAll($query, [$albumId]);
    }
    
    /**
     * الحصول على الصور حسب الصف
     * 
     * @param int $classId معرّف الصف
     * @param int|null $albumId معرّف الألبوم (اختياري)
     * @return array قائمة الصور
     */
    public function getImagesByClass($classId, $albumId = null)
    {
        $query = "SELECT i.*, 
                 a.name as album_name,
                 u.first_name as uploader_first_name, u.last_name as uploader_last_name
                 FROM {$this->table} i 
                 LEFT JOIN albums a ON i.album_id = a.id 
                 JOIN users u ON i.uploaded_by = u.id 
                 WHERE i.class_id = ?";
        
        $params = [$classId];
        
        if ($albumId !== null) {
            $query .= " AND i.album_id = ?";
            $params[] = $albumId;
        }
        
        $query .= " ORDER BY i.created_at DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على الصور حسب المستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @return array قائمة الصور
     */
    public function getImagesByUser($userId)
    {
        $query = "SELECT i.*, 
                 c.name as class_name, a.name as album_name
                 FROM {$this->table} i 
                 LEFT JOIN classes c ON i.class_id = c.id 
                 LEFT JOIN albums a ON i.album_id = a.id 
                 WHERE i.uploaded_by = ? 
                 ORDER BY i.created_at DESC";
        
        return $this->db->fetchAll($query, [$userId]);
    }
    
    /**
     * البحث عن صور
     * 
     * @param string $searchTerm مصطلح البحث
     * @param int|null $schoolId معرّف المدرسة (اختياري)
     * @param int|null $classId معرّف الصف (اختياري)
     * @return array نتائج البحث
     */
    public function searchImages($searchTerm, $schoolId = null, $classId = null)
    {
        $query = "SELECT i.*, 
                 c.name as class_name, a.name as album_name,
                 u.first_name as uploader_first_name, u.last_name as uploader_last_name
                 FROM {$this->table} i 
                 LEFT JOIN classes c ON i.class_id = c.id 
                 LEFT JOIN albums a ON i.album_id = a.id 
                 JOIN users u ON i.uploaded_by = u.id";
        
        $conditions = [];
        $params = [];
        
        // إضافة شرط البحث
        $conditions[] = "(i.title LIKE ? OR i.description LIKE ?)";
        $searchParam = "%{$searchTerm}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        
        // إضافة شرط المدرسة
        if ($schoolId !== null) {
            $conditions[] = "c.school_id = ?";
            $params[] = $schoolId;
        }
        
        // إضافة شرط الصف
        if ($classId !== null) {
            $conditions[] = "i.class_id = ?";
            $params[] = $classId;
        }
        
        // إضافة الشروط إلى الاستعلام
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY i.created_at DESC LIMIT 100";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * الحصول على الصور المتاحة للمستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @param string $role دور المستخدم
     * @param int|null $classId معرّف الصف (للطلاب)
     * @param int|null $schoolId معرّف المدرسة
     * @return array قائمة الصور
     */
    public function getAccessibleImages($userId, $role, $classId = null, $schoolId = null)
    {
        $query = "SELECT i.*, 
                 c.name as class_name, a.name as album_name,
                 u.first_name as uploader_first_name, u.last_name as uploader_last_name
                 FROM {$this->table} i 
                 LEFT JOIN classes c ON i.class_id = c.id 
                 LEFT JOIN albums a ON i.album_id = a.id 
                 JOIN users u ON i.uploaded_by = u.id 
                 WHERE 1=1";
        
        $params = [];
        
        // تصفية حسب الدور ومستوى الخصوصية
        switch ($role) {
            case 'super_admin':
                // يمكن للمدير الرئيسي رؤية جميع الصور
                break;
                
            case 'school_admin':
                // يمكن لمدير المدرسة رؤية الصور العامة وصور مدرسته
                $query .= " AND (i.privacy_level = 'public' OR (c.school_id = ?))";
                $params[] = $schoolId;
                break;
                
            case 'teacher':
                // يمكن للمعلم رؤية الصور العامة وصور مدرسته وصوره الخاصة
                $query .= " AND (i.privacy_level IN ('public', 'school') OR (c.school_id = ?) OR i.uploaded_by = ?)";
                $params[] = $schoolId;
                $params[] = $userId;
                break;
                
            case 'student':
                // يمكن للطالب رؤية الصور العامة وصور مدرسته وصور صفه
                if ($classId) {
                    $query .= " AND (i.privacy_level IN ('public', 'school') OR (c.school_id = ?) OR (i.class_id = ? AND i.privacy_level = 'class'))";
                    $params[] = $schoolId;
                    $params[] = $classId;
                } else {
                    $query .= " AND (i.privacy_level IN ('public', 'school') OR (c.school_id = ?))";
                    $params[] = $schoolId;
                }
                break;
                
            case 'parent':
                // يمكن لولي الأمر رؤية الصور العامة وصور مدرسة أبنائه وصور صفوف أبنائه
                $query .= " AND (i.privacy_level IN ('public', 'school') OR (c.school_id = ?) OR (i.class_id IN (
                    SELECT s.class_id FROM students s WHERE s.parent_id = ?
                ) AND i.privacy_level = 'class'))";
                $params[] = $schoolId;
                $params[] = $userId;
                break;
                
            default:
                // للمستخدمين الآخرين، عرض الصور العامة فقط
                $query .= " AND i.privacy_level = 'public'";
                break;
        }
        
        $query .= " ORDER BY i.created_at DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * إنشاء نسخة مصغرة من الصورة
     * 
     * @param string $imagePath مسار الصورة الأصلية
     * @param int $width العرض المطلوب
     * @param int $height الارتفاع المطلوب
     * @return string|false مسار النسخة المصغرة أو false في حالة الفشل
     */
    public function createThumbnail($imagePath, $width = 200, $height = 200)
    {
        // التحقق من وجود الصورة
        if (!file_exists($imagePath)) {
            return false;
        }
        
        // الحصول على معلومات الصورة
        $imageInfo = pathinfo($imagePath);
        $extension = strtolower($imageInfo['extension']);
        
        // إنشاء مسار النسخة المصغرة
        $thumbnailPath = $imageInfo['dirname'] . '/' . $imageInfo['filename'] . '_thumb.' . $extension;
        
        // تحميل الصورة الأصلية
        $sourceImage = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $sourceImage = imagecreatefromjpeg($imagePath);
                break;
            case 'png':
                $sourceImage = imagecreatefrompng($imagePath);
                break;
            case 'gif':
                $sourceImage = imagecreatefromgif($imagePath);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // الحصول على أبعاد الصورة الأصلية
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // حساب نسبة العرض إلى الارتفاع
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $width / $height;
        
        if ($sourceRatio > $targetRatio) {
            // الصورة أعرض من النسبة المطلوبة
            $newWidth = $width;
            $newHeight = $width / $sourceRatio;
        } else {
            // الصورة أطول من النسبة المطلوبة
            $newHeight = $height;
            $newWidth = $height * $sourceRatio;
        }
        
        // إنشاء الصورة المصغرة
        $thumbnailImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // الحفاظ على الشفافية للصور PNG
        if ($extension === 'png') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbnailImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // نسخ وتغيير حجم الصورة
        imagecopyresampled(
            $thumbnailImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight, $sourceWidth, $sourceHeight
        );
        
        // حفظ الصورة المصغرة
        $success = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($thumbnailImage, $thumbnailPath, 85);
                break;
            case 'png':
                $success = imagepng($thumbnailImage, $thumbnailPath, 8);
                break;
            case 'gif':
                $success = imagegif($thumbnailImage, $thumbnailPath);
                break;
        }
        
        // تحرير الذاكرة
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
        
        return $success ? $thumbnailPath : false;
    }
    
    /**
     * حساب عدد الصور لكل صف
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array عدد الصور لكل صف
     */
    public function countImagesByClass($schoolId)
    {
        $query = "SELECT c.id, c.name, COUNT(i.id) as images_count
                 FROM classes c
                 LEFT JOIN {$this->table} i ON c.id = i.class_id
                 WHERE c.school_id = ?
                 GROUP BY c.id
                 ORDER BY c.grade_level, c.name";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * حذف صورة
     * 
     * @param int $imageId معرّف الصورة
     * @param bool $deleteFile حذف ملف الصورة من النظام
     * @return bool نجاح العملية أم لا
     */
    public function deleteImage($imageId, $deleteFile = true)
    {
        if ($deleteFile) {
            // الحصول على مسارات الملفات
            $image = $this->find($imageId);
            
            if ($image) {
                // حذف الملف الأصلي
                if (!empty($image['file_path']) && file_exists($image['file_path'])) {
                    unlink($image['file_path']);
                }
                
                // حذف النسخة المصغرة
                if (!empty($image['thumbnail_path']) && file_exists($image['thumbnail_path'])) {
                    unlink($image['thumbnail_path']);
                }
            }
        }
        
        // حذف السجل من قاعدة البيانات
        return $this->delete($imageId);
    }
}