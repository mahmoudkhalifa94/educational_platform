<?php
/**
 * app/models/Album.php
 * نموذج الألبوم
 * يتعامل مع بيانات ألبومات الصور في النظام
 */
class Album extends Model
{
    // اسم الجدول في قاعدة البيانات
    protected $table = 'albums';
    
    // المفتاح الأساسي
    protected $primaryKey = 'id';
    
    // الحقول المسموح تعديلها
    protected $fillable = [
        'school_id', 'class_id', 'name', 'description', 
        'cover_image', 'created_by'
    ];
    
    // الحقول المحمية
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    /**
     * إنشاء ألبوم جديد
     * 
     * @param array $data بيانات الألبوم
     * @return int|false معرّف الألبوم الجديد أو false في حالة الفشل
     */
    public function createAlbum($data)
    {
        return $this->create($data);
    }
    
    /**
     * تحديث بيانات الألبوم
     * 
     * @param int $id معرّف الألبوم
     * @param array $data البيانات المراد تحديثها
     * @return bool نجاح العملية أم لا
     */
    public function updateAlbum($id, $data)
    {
        return $this->update($id, $data);
    }
    
    /**
     * الحصول على ألبوم مع التفاصيل
     * 
     * @param int $albumId معرّف الألبوم
     * @return array|false بيانات الألبوم أو false إذا لم يتم العثور عليه
     */
    public function getAlbumWithDetails($albumId)
    {
        $query = "SELECT a.*, 
                 s.name as school_name,
                 c.name as class_name, c.grade_level,
                 u.first_name as creator_first_name, u.last_name as creator_last_name,
                 i.file_path as cover_image_path, i.thumbnail_path as cover_thumbnail_path,
                 (SELECT COUNT(*) FROM images WHERE album_id = a.id) as images_count
                 FROM {$this->table} a 
                 JOIN schools s ON a.school_id = s.id 
                 LEFT JOIN classes c ON a.class_id = c.id 
                 JOIN users u ON a.created_by = u.id 
                 LEFT JOIN images i ON a.cover_image = i.id 
                 WHERE a.id = ? 
                 LIMIT 1";
        
        return $this->db->fetchOne($query, [$albumId]);
    }
    
    /**
     * الحصول على الألبومات حسب المدرسة
     * 
     * @param int $schoolId معرّف المدرسة
     * @return array قائمة الألبومات
     */
    public function getAlbumsBySchool($schoolId)
    {
        $query = "SELECT a.*, 
                 c.name as class_name,
                 u.first_name as creator_first_name, u.last_name as creator_last_name,
                 i.thumbnail_path as cover_thumbnail_path,
                 (SELECT COUNT(*) FROM images WHERE album_id = a.id) as images_count
                 FROM {$this->table} a 
                 LEFT JOIN classes c ON a.class_id = c.id 
                 JOIN users u ON a.created_by = u.id 
                 LEFT JOIN images i ON a.cover_image = i.id 
                 WHERE a.school_id = ? 
                 ORDER BY a.created_at DESC";
        
        return $this->db->fetchAll($query, [$schoolId]);
    }
    
    /**
     * الحصول على الألبومات حسب الصف
     * 
     * @param int $classId معرّف الصف
     * @return array قائمة الألبومات
     */
    public function getAlbumsByClass($classId)
    {
        $query = "SELECT a.*, 
                 u.first_name as creator_first_name, u.last_name as creator_last_name,
                 i.thumbnail_path as cover_thumbnail_path,
                 (SELECT COUNT(*) FROM images WHERE album_id = a.id) as images_count
                 FROM {$this->table} a 
                 JOIN users u ON a.created_by = u.id 
                 LEFT JOIN images i ON a.cover_image = i.id 
                 WHERE a.class_id = ? 
                 ORDER BY a.created_at DESC";
        
        return $this->db->fetchAll($query, [$classId]);
    }
    
    /**
     * الحصول على الألبومات حسب المستخدم
     * 
     * @param int $userId معرّف المستخدم
     * @return array قائمة الألبومات
     */
    public function getAlbumsByUser($userId)
    {
        $query = "SELECT a.*, 
                 s.name as school_name,
                 c.name as class_name,
                 i.thumbnail_path as cover_thumbnail_path,
                 (SELECT COUNT(*) FROM images WHERE album_id = a.id) as images_count
                 FROM {$this->table} a 
                 JOIN schools s ON a.school_id = s.id 
                 LEFT JOIN classes c ON a.class_id = c.id 
                 LEFT JOIN images i ON a.cover_image = i.id 
                 WHERE a.created_by = ? 
                 ORDER BY a.created_at DESC";
        
        return $this->db->fetchAll($query, [$userId]);
    }
    
    /**
     * البحث عن ألبومات
     * 
     * @param string $searchTerm مصطلح البحث
     * @param int|null $schoolId معرّف المدرسة (اختياري)
     * @param int|null $classId معرّف الصف (اختياري)
     * @return array نتائج البحث
     */
    public function searchAlbums($searchTerm, $schoolId = null, $classId = null)
    {
        $query = "SELECT a.*, 
                 s.name as school_name,
                 c.name as class_name,
                 u.first_name as creator_first_name, u.last_name as creator_last_name,
                 i.thumbnail_path as cover_thumbnail_path,
                 (SELECT COUNT(*) FROM images WHERE album_id = a.id) as images_count
                 FROM {$this->table} a 
                 JOIN schools s ON a.school_id = s.id 
                 LEFT JOIN classes c ON a.class_id = c.id 
                 JOIN users u ON a.created_by = u.id 
                 LEFT JOIN images i ON a.cover_image = i.id";
        
        $conditions = [];
        $params = [];
        
        // إضافة شرط البحث
        $conditions[] = "(a.name LIKE ? OR a.description LIKE ?)";
        $searchParam = "%{$searchTerm}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        
        // إضافة شرط المدرسة
        if ($schoolId !== null) {
            $conditions[] = "a.school_id = ?";
            $params[] = $schoolId;
        }
        
        // إضافة شرط الصف
        if ($classId !== null) {
            $conditions[] = "a.class_id = ?";
            $params[] = $classId;
        }
        
        // إضافة الشروط إلى الاستعلام
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY a.created_at DESC LIMIT 100";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * تعيين صورة غلاف للألبوم
     * 
     * @param int $albumId معرّف الألبوم
     * @param int $imageId معرّف الصورة
     * @return bool نجاح العملية أم لا
     */
    public function setCoverImage($albumId, $imageId)
    {
        return $this->update($albumId, ['cover_image' => $imageId]);
    }
    
    /**
     * الحصول على الصور في ألبوم معين
     * 
     * @param int $albumId معرّف الألبوم
     * @return array قائمة الصور
     */
    public function getAlbumImages($albumId)
    {
        $query = "SELECT i.*, 
                 u.first_name as uploader_first_name, u.last_name as uploader_last_name
                 FROM images i 
                 JOIN users u ON i.uploaded_by = u.id 
                 WHERE i.album_id = ? 
                 ORDER BY i.created_at DESC";
        
        return $this->db->fetchAll($query, [$albumId]);
    }
    
    /**
     * نقل صورة إلى ألبوم
     * 
     * @param int $imageId معرّف الصورة
     * @param int $albumId معرّف الألبوم
     * @return bool نجاح العملية أم لا
     */
    public function moveImageToAlbum($imageId, $albumId)
    {
        // التحقق من وجود الألبوم
        $album = $this->find($albumId);
        
        if (!$album) {
            return false;
        }
        
        // تحديث الصورة
        $imageUpdated = $this->db->update(
            'images',
            [
                'album_id' => $albumId,
                'class_id' => $album['class_id'] // تحديث الصف أيضًا
            ],
            'id = ?',
            [$imageId]
        );
        
        return $imageUpdated;
    }
    
    /**
     * نقل عدة صور إلى ألبوم
     * 
     * @param array $imageIds مصفوفة معرّفات الصور
     * @param int $albumId معرّف الألبوم
     * @return array نتائج العملية [نجاح => عدد العمليات الناجحة، فشل => عدد العمليات الفاشلة]
     */
    public function moveImagesToAlbum($imageIds, $albumId)
    {
        $results = [
            'success' => 0,
            'fail' => 0
        ];
        
        // التحقق من وجود الألبوم
        $album = $this->find($albumId);
        
        if (!$album) {
            $results['fail'] = count($imageIds);
            return $results;
        }
        
        foreach ($imageIds as $imageId) {
            $success = $this->moveImageToAlbum($imageId, $albumId);
            
            if ($success) {
                $results['success']++;
            } else {
                $results['fail']++;
            }
        }
        
        return $results;
    }
    
    /**
     * حذف ألبوم
     * 
     * @param int $albumId معرّف الألبوم
     * @param bool $deleteImages حذف الصور في الألبوم
     * @return bool نجاح العملية أم لا
     */
    public function deleteAlbum($albumId, $deleteImages = false)
    {
        // بدء معاملة قاعدة البيانات
        $this->db->beginTransaction();
        
        try {
            if ($deleteImages) {
                // حذف الصور
                $images = $this->getAlbumImages($albumId);
                
                $imageModel = new Image();
                
                foreach ($images as $image) {
                    $imageModel->deleteImage($image['id']);
                }
            } else {
                // إعادة تعيين album_id للصور
                $this->db->update(
                    'images',
                    ['album_id' => null],
                    'album_id = ?',
                    [$albumId]
                );
            }
            
            // حذف الألبوم
            $albumDeleted = $this->delete($albumId);
            
            if ($albumDeleted) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error deleting album: " . $e->getMessage());
            return false;
        }
    }
}