<?php
/**
 * core/Response.php
 * فئة الاستجابة
 * تدير استجابة HTTP
 */
class Response
{
    private $statusCode = 200;
    private $headers = [];
    private $content = '';
    private $contentType = 'text/html';
    private $charset = 'UTF-8';
    
    /**
     * تهيئة الاستجابة
     * 
     * @param string $content محتوى الاستجابة
     * @param int $statusCode رمز الحالة
     * @param array $headers الترويسات
     */
    public function __construct($content = '', $statusCode = 200, $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }
    
    /**
     * تعيين محتوى الاستجابة
     * 
     * @param string $content المحتوى
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }
    
    /**
     * الحصول على محتوى الاستجابة
     * 
     * @return string المحتوى
     */
    public function getContent()
    {
        return $this->content;
    }
    
    /**
     * تعيين رمز الحالة
     * 
     * @param int $statusCode رمز الحالة
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }
    
    /**
     * الحصول على رمز الحالة
     * 
     * @return int رمز الحالة
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
    
    /**
     * إضافة ترويسة
     * 
     * @param string $name اسم الترويسة
     * @param string $value قيمة الترويسة
     * @return $this
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * إضافة عدة ترويسات
     * 
     * @param array $headers الترويسات
     * @return $this
     */
    public function setHeaders($headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }
    
    /**
     * الحصول على الترويسات
     * 
     * @return array الترويسات
     */
    public function getHeaders()
    {
        return $this->headers;
    }
    
    /**
     * تعيين نوع المحتوى
     * 
     * @param string $contentType نوع المحتوى
     * @return $this
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }
    
    /**
     * تعيين ترميز الاستجابة
     * 
     * @param string $charset الترميز
     * @return $this
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
        return $this;
    }
    
    /**
     * إرسال الترويسات
     * 
     * @return $this
     */
    public function sendHeaders()
    {
        // تعيين رمز الحالة
        http_response_code($this->statusCode);
        
        // تعيين ترويسة نوع المحتوى والترميز
        header('Content-Type: ' . $this->contentType . '; charset=' . $this->charset);
        
        // تعيين الترويسات المخصصة
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        
        return $this;
    }
    
    /**
     * إرسال المحتوى
     * 
     * @return $this
     */
    public function sendContent()
    {
        echo $this->content;
        return $this;
    }
    
    /**
     * إرسال الاستجابة (الترويسات والمحتوى)
     * 
     * @return $this
     */
    public function send()
    {
        $this->sendHeaders();
        $this->sendContent();
        return $this;
    }
    
    /**
     * إنشاء استجابة JSON
     * 
     * @param mixed $data البيانات
     * @param int $statusCode رمز الحالة
     * @param array $headers الترويسات
     * @return Response كائن الاستجابة
     */
    public static function json($data, $statusCode = 200, $headers = [])
    {
        $content = json_encode($data);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error encoding JSON: ' . json_last_error_msg());
        }
        
        $response = new self($content, $statusCode, $headers);
        $response->setContentType('application/json');
        
        return $response;
    }
    
    /**
     * إنشاء استجابة نص عادي
     * 
     * @param string $text النص
     * @param int $statusCode رمز الحالة
     * @param array $headers الترويسات
     * @return Response كائن الاستجابة
     */
    public static function text($text, $statusCode = 200, $headers = [])
    {
        $response = new self($text, $statusCode, $headers);
        $response->setContentType('text/plain');
        
        return $response;
    }
    
    /**
     * إنشاء استجابة HTML
     * 
     * @param string $html محتوى HTML
     * @param int $statusCode رمز الحالة
     * @param array $headers الترويسات
     * @return Response كائن الاستجابة
     */
    public static function html($html, $statusCode = 200, $headers = [])
    {
        $response = new self($html, $statusCode, $headers);
        $response->setContentType('text/html');
        
        return $response;
    }
    
    /**
     * إنشاء استجابة XML
     * 
     * @param string $xml محتوى XML
     * @param int $statusCode رمز الحالة
     * @param array $headers الترويسات
     * @return Response كائن الاستجابة
     */
    public static function xml($xml, $statusCode = 200, $headers = [])
    {
        $response = new self($xml, $statusCode, $headers);
        $response->setContentType('application/xml');
        
        return $response;
    }
    
    /**
     * إنشاء استجابة تحويل
     * 
     * @param string $url العنوان
     * @param int $statusCode رمز الحالة (افتراضياً 302)
     * @return Response كائن الاستجابة
     */
    public static function redirect($url, $statusCode = 302)
    {
        $response = new self('', $statusCode);
        $response->setHeader('Location', $url);
        
        return $response;
    }
    
    /**
     * إنشاء استجابة تنزيل ملف
     * 
     * @param string $filePath مسار الملف
     * @param string $filename اسم الملف
     * @param string $contentType نوع المحتوى
     * @param array $headers الترويسات
     * @return Response كائن الاستجابة
     */
    public static function download($filePath, $filename = null, $contentType = null, $headers = [])
    {
        if (!file_exists($filePath)) {
            throw new Exception('File not found: ' . $filePath);
        }
        
        // استخراج اسم الملف إذا لم يتم تحديده
        if ($filename === null) {
            $filename = basename($filePath);
        }
        
        // استخراج نوع المحتوى إذا لم يتم تحديده
        if ($contentType === null) {
            $contentType = mime_content_type($filePath);
            
            if ($contentType === false) {
                // نوع محتوى افتراضي
                $contentType = 'application/octet-stream';
            }
        }
        
        // قراءة محتوى الملف
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            throw new Exception('Error reading file: ' . $filePath);
        }
        
        // إعداد ترويسات التنزيل
        $headers = array_merge($headers, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => filesize($filePath)
        ]);
        
        $response = new self($content, 200, $headers);
        $response->setContentType($contentType);
        
        return $response;
    }
    
    /**
     * إنشاء استجابة خطأ 404
     * 
     * @param string $message رسالة الخطأ
     * @return Response كائن الاستجابة
     */
    public static function notFound($message = 'Page not found')
    {
        return new self($message, 404);
    }
    
    /**
     * إنشاء استجابة خطأ 403
     * 
     * @param string $message رسالة الخطأ
     * @return Response كائن الاستجابة
     */
    public static function forbidden($message = 'Forbidden')
    {
        return new self($message, 403);
    }
    
    /**
     * إنشاء استجابة خطأ 500
     * 
     * @param string $message رسالة الخطأ
     * @return Response كائن الاستجابة
     */
    public static function serverError($message = 'Internal Server Error')
    {
        return new self($message, 500);
    }
    
    /**
     * إنشاء استجابة خطأ 400
     * 
     * @param string $message رسالة الخطأ
     * @return Response كائن الاستجابة
     */
    public static function badRequest($message = 'Bad Request')
    {
        return new self($message, 400);
    }
    
    /**
     * إنشاء استجابة خطأ 401
     * 
     * @param string $message رسالة الخطأ
     * @return Response كائن الاستجابة
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        return new self($message, 401);
    }
}