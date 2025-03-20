<?php
/**
 * core/Helper.php
 * فئة المساعد
 * توفر وظائف مساعدة متنوعة للتطبيق
 */
class Helper
{
    /**
     * تطهير النص من HTML
     * 
     * @param string $text النص المراد تطهيره
     * @return string النص المطهر
     */
    public static function escape($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * تنسيق التاريخ
     * 
     * @param string $date التاريخ
     * @param string $format صيغة التنسيق
     * @return string التاريخ المنسق
     */
    public static function formatDate($date, $format = 'Y-m-d')
    {
        if (empty($date)) {
            return '';
        }
        
        $dateObj = is_string($date) ? new DateTime($date) : $date;
        return $dateObj->format($format);
    }
    
    /**
     * تنسيق الرقم
     * 
     * @param float $number الرقم
     * @param int $decimals عدد الأرقام العشرية
     * @return string الرقم المنسق
     */
    public static function formatNumber($number, $decimals = 2)
    {
        return number_format($number, $decimals, '.', ',');
    }
    
    /**
     * تنسيق العملة
     * 
     * @param float $amount المبلغ
     * @param string $currency رمز العملة
     * @param int $decimals عدد الأرقام العشرية
     * @return string المبلغ المنسق
     */
    public static function formatCurrency($amount, $currency = '$', $decimals = 2)
    {
        return $currency . ' ' . number_format($amount, $decimals, '.', ',');
    }
    
    /**
     * اختصار النص
     * 
     * @param string $text النص
     * @param int $length الطول الأقصى
     * @param string $append النص المضاف في نهاية النص المختصر
     * @return string النص المختصر
     */
    public static function truncate($text, $length = 100, $append = '...')
    {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        
        $text = mb_substr($text, 0, $length, 'UTF-8');
        $lastSpace = mb_strrpos($text, ' ', 0, 'UTF-8');
        
        if ($lastSpace !== false) {
            $text = mb_substr($text, 0, $lastSpace, 'UTF-8');
        }
        
        return $text . $append;
    }
    
    /**
     * إنشاء رابط
     * 
     * @param string $path المسار
     * @param array $params المعلمات
     * @return string الرابط النهائي
     */
    public static function url($path, $params = [])
    {
        $url = $path;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * التحقق من نشاط الرابط الحالي
     * 
     * @param string $path المسار
     * @param string $activeClass الصنف النشط
     * @return string الصنف إذا كان المسار نشطًا، وإلا فارغ
     */
    public static function isActiveUrl($path, $activeClass = 'active')
    {
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        if ($currentPath === $path) {
            return $activeClass;
        }
        
        if ($path !== '/' && strpos($currentPath, $path) === 0) {
            return $activeClass;
        }
        
        return '';
    }
    
    /**
     * إنشاء معرف فريد
     * 
     * @param int $length طول المعرف
     * @return string المعرف
     */
    public static function uniqueId($length = 16)
    {
        if (function_exists('random_bytes')) {
            $bytes = random_bytes(ceil($length / 2));
            return substr(bin2hex($bytes), 0, $length);
        }
        
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(ceil($length / 2));
            return substr(bin2hex($bytes), 0, $length);
        }
        
        // طريقة احتياطية
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        return $result;
    }
    
    /**
     * إنشاء كلمة مرور عشوائية
     * 
     * @param int $length طول كلمة المرور
     * @param bool $includeSpecialChars تضمين أحرف خاصة
     * @return string كلمة المرور
     */
    public static function generatePassword($length = 10, $includeSpecialChars = true)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        
        if ($includeSpecialChars) {
            $chars .= '!@#$%^&*()-_=+[]{}|;:,.<>?';
        }
        
        $password = '';
        $charsLength = strlen($chars);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $password;
    }
    
    /**
     * تحويل حجم الملف إلى صيغة مقروءة
     * 
     * @param int $bytes حجم الملف بالبايت
     * @param int $precision عدد الأرقام العشرية
     * @return string الحجم المنسق
     */
    public static function formatFileSize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * التحقق من صحة البريد الإلكتروني
     * 
     * @param string $email البريد الإلكتروني
     * @return bool هل البريد صحيح
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * تحويل النص إلى سلاج (Slug)
     * 
     * @param string $text النص
     * @return string السلاج
     */
    public static function slugify($text)
    {
        // تحويل الأحرف العربية إلى لاتينية (Transliteration)
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        
        // تحويل إلى أحرف صغيرة
        $text = strtolower($text);
        
        // إزالة الأحرف الخاصة
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
        
        // استبدال الشرطات المتعددة بشرطة واحدة
        $text = preg_replace('/-+/', '-', $text);
        
        // إزالة الشرطات من البداية والنهاية
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * تحويل سلسلة HTML إلى نص عادي
     * 
     * @param string $html سلسلة HTML
     * @return string النص العادي
     */
    public static function stripHtml($html)
    {
        return strip_tags($html);
    }
    
    /**
     * استخراج امتداد الملف
     * 
     * @param string $filename اسم الملف
     * @return string الامتداد
     */
    public static function getFileExtension($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * التحقق من نوع MIME للملف
     * 
     * @param string $filePath مسار الملف
     * @param array $allowedTypes الأنواع المسموح بها
     * @return bool هل النوع مسموح به
     */
    public static function isAllowedFileType($filePath, $allowedTypes)
    {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $fileType = mime_content_type($filePath);
        return in_array($fileType, $allowedTypes);
    }
    
    /**
     * إنشاء مصغر للصورة
     * 
     * @param string $sourcePath مسار الصورة الأصلية
     * @param string $targetPath مسار الصورة المصغرة
     * @param int $width العرض
     * @param int $height الارتفاع
     * @param bool $crop اقتصاص الصورة
     * @return bool نجاح العملية
     */
    public static function createThumbnail($sourcePath, $targetPath, $width, $height, $crop = false)
    {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        list($sourceWidth, $sourceHeight, $sourceType) = getimagesize($sourcePath);
        
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if ($crop) {
            // حساب نسبة العرض إلى الارتفاع
            $sourceRatio = $sourceWidth / $sourceHeight;
            $targetRatio = $width / $height;
            
            if ($sourceRatio > $targetRatio) {
                // الصورة أعرض، اقتصاص من الجوانب
                $newWidth = $sourceHeight * $targetRatio;
                $newHeight = $sourceHeight;
                $sourceX = ($sourceWidth - $newWidth) / 2;
                $sourceY = 0;
            } else {
                // الصورة أطول، اقتصاص من الأعلى والأسفل
                $newWidth = $sourceWidth;
                $newHeight = $sourceWidth / $targetRatio;
                $sourceX = 0;
                $sourceY = ($sourceHeight - $newHeight) / 2;
            }
            
            $targetImage = imagecreatetruecolor($width, $height);
            
            // الحفاظ على الشفافية للصور PNG
            if ($sourceType === IMAGETYPE_PNG) {
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                imagefilledrectangle($targetImage, 0, 0, $width, $height, $transparent);
            }
            
            imagecopyresampled(
                $targetImage, $sourceImage,
                0, 0, $sourceX, $sourceY,
                $width, $height, $newWidth, $newHeight
            );
        } else {
            // حساب الأبعاد الجديدة مع الحفاظ على النسبة
            if ($sourceWidth / $sourceHeight > $width / $height) {
                $newWidth = $width;
                $newHeight = $sourceHeight * ($width / $sourceWidth);
            } else {
                $newWidth = $sourceWidth * ($height / $sourceHeight);
                $newHeight = $height;
            }
            
            $targetImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // الحفاظ على الشفافية للصور PNG
            if ($sourceType === IMAGETYPE_PNG) {
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            imagecopyresampled(
                $targetImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight, $sourceWidth, $sourceHeight
            );
        }
        
        // حفظ الصورة المصغرة
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                return imagejpeg($targetImage, $targetPath, 90);
            case IMAGETYPE_PNG:
                return imagepng($targetImage, $targetPath, 9);
            case IMAGETYPE_GIF:
                return imagegif($targetImage, $targetPath);
        }
        
        return false;
    }
    
    /**
     * إرسال بريد إلكتروني
     * 
     * @param string $to البريد المستلم
     * @param string $subject الموضوع
     * @param string $body المحتوى
     * @param array $headers ترويسات إضافية
     * @return bool نجاح العملية
     */
    public static function sendEmail($to, $subject, $body, $headers = [])
    {
        // تكوين الترويسات
        $defaultHeaders = [
            'MIME-Version' => '1.0',
            'Content-type' => 'text/html; charset=utf-8',
            'From' => 'noreply@example.com',
            'X-Mailer' => 'PHP/' . phpversion()
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        // تحويل الترويسات إلى سلسلة نصية
        $headerString = '';
        
        foreach ($headers as $name => $value) {
            $headerString .= $name . ': ' . $value . "\r\n";
        }
        
        // إرسال البريد
        return mail($to, $subject, $body, $headerString);
    }
    
    /**
     * تحويل تاريخ إلى وقت نسبي
     * 
     * @param string $date التاريخ
     * @return string الوقت النسبي
     */
    public static function timeAgo($date)
    {
        $timestamp = is_string($date) ? strtotime($date) : $date;
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return 'منذ لحظات';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return 'منذ ' . $minutes . ' دقيقة' . ($minutes > 2 ? '' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return 'منذ ' . $hours . ' ساعة' . ($hours > 2 ? 'ات' : '');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return 'منذ ' . $days . ' يوم' . ($days > 2 ? '' : '');
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return 'منذ ' . $weeks . ' أسبوع' . ($weeks > 2 ? '' : '');
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return 'منذ ' . $months . ' شهر' . ($months > 2 ? '' : '');
        } else {
            $years = floor($diff / 31536000);
            return 'منذ ' . $years . ' سنة' . ($years > 2 ? '' : '');
        }
    }
    
    /**
     * تحويل سلسلة نصية إلى تعبير نمطي آمن
     * 
     * @param string $string السلسلة النصية
     * @return string التعبير النمطي
     */
    public static function escapeRegex($string)
    {
        return preg_quote($string, '/');
    }
    
    /**
     * تشفير بيانات JSON
     * 
     * @param mixed $data البيانات
     * @param int $options خيارات التشفير
     * @return string|false سلسلة JSON أو false في حالة الفشل
     */
    public static function jsonEncode($data, $options = 0)
    {
        $result = json_encode($data, $options);
        
        if ($result === false && json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON encoding error: ' . json_last_error_msg());
            return false;
        }
        
        return $result;
    }
    
    /**
     * فك تشفير بيانات JSON
     * 
     * @param string $json سلسلة JSON
     * @param bool $assoc تحويل الكائنات إلى مصفوفات ترابطية
     * @return mixed البيانات المستخرجة أو null في حالة الفشل
     */
    public static function jsonDecode($json, $assoc = true)
    {
        $result = json_decode($json, $assoc);
        
        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decoding error: ' . json_last_error_msg());
            return null;
        }
        
        return $result;
    }
}