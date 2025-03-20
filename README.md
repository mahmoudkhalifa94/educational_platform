# المنصة التعليمية المتكاملة (Integrated Educational Platform)

## نظرة عامة (Overview)

المنصة التعليمية المتكاملة هي نظام إدارة تعليمي شامل مصمم للمدارس والمؤسسات التعليمية. يوفر النظام حلاً متكاملاً لإدارة العملية التعليمية بالكامل، بما في ذلك إدارة المدارس والصفوف والطلاب والمعلمين والمواد الدراسية والمهام والدرجات والتواصل بين أطراف العملية التعليمية.

## الخصائص الرئيسية (Key Features)

- **تعدد الأدوار (Multiple Roles)**: يدعم النظام عدة أدوار: مدير النظام، مدير المدرسة، المعلم، الطالب، ولي الأمر.
- **إدارة المدارس (School Management)**: إنشاء وإدارة المدارس والاشتراكات.
- **إدارة المستخدمين (User Management)**: إدارة شاملة لجميع المستخدمين والصلاحيات.
- **إدارة الصفوف (Class Management)**: إنشاء وإدارة الصفوف والجداول الدراسية.
- **إدارة المواد (Subject Management)**: إدارة المواد الدراسية وتخصيصها للصفوف والمعلمين.
- **نظام المهام والتقييم (Assignment System)**: إنشاء وتسليم وتصحيح المهام الدراسية.
- **نظام التواصل (Communication System)**: غرف دردشة وإشعارات للتواصل بين المستخدمين.
- **معرض الصور (Image Gallery)**: مشاركة الصور والألبومات بين الصفوف.
- **التقارير والإحصائيات (Reports & Statistics)**: تقارير شاملة عن الأداء والنشاطات.

## المتطلبات (Requirements)

- PHP 7.4 أو أعلى
- MySQL 5.7 أو أعلى
- خادم ويب (Apache/Nginx)
- تفعيل mod_rewrite
- تفعيل خصائص PHP: PDO, GD Library, JSON, Fileinfo

## التثبيت (Installation)

1. قم بنسخ مستودع المشروع:
   ```
   git clone https://github.com/yourusername/educational_platform.git
   ```

2. انتقل إلى مجلد المشروع:
   ```
   cd educational_platform
   ```

3. قم بتثبيت التبعيات باستخدام Composer:
   ```
   composer install
   ```

4. أنشئ قاعدة بيانات جديدة في MySQL.

5. قم بتعديل ملف التكوين `config/database.php` بإعدادات قاعدة البيانات الخاصة بك.

6. قم بتشغيل سكربت إعداد قاعدة البيانات:
   ```
   php setup/install.php
   ```

7. قم بضبط إعدادات الخادم لتوجيه جميع الطلبات إلى `public/index.php`.

### إعدادات Apache (Apache Configuration)

أضف الملف `.htaccess` التالي في المجلد الجذر:

```apache
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
```

وأضف الملف `.htaccess` التالي في مجلد `public`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?$1 [L,QSA]
```

### إعدادات Nginx (Nginx Configuration)

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /path/to/educational_platform/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## الاستخدام (Usage)

بعد التثبيت، يمكنك الوصول إلى النظام من خلال المتصفح. استخدم بيانات الدخول الافتراضية للمدير الرئيسي:

- البريد الإلكتروني: admin@system.com
- كلمة المرور: admin123

قم بتغيير كلمة المرور فور تسجيل الدخول الأول.

## بنية المشروع (Project Structure)

```
educational_platform/
├── app/
│   ├── controllers/    # متحكمات النظام
│   ├── models/         # نماذج البيانات
│   ├── views/          # قوالب العرض
│   └── helpers/        # دوال مساعدة
├── config/             # ملفات التكوين
├── core/               # النواة الأساسية للإطار
├── public/             # الملفات العامة
│   ├── assets/         # الموارد (CSS, JS, صور)
│   └── index.php       # نقطة الدخول
├── logs/               # سجلات النظام
└── setup/              # سكربتات الإعداد
```

## الأدوار والصلاحيات (Roles & Permissions)

### مدير النظام (Super Admin)
- إدارة كاملة للنظام
- إنشاء وإدارة المدارس
- إدارة الاشتراكات والفواتير

### مدير المدرسة (School Admin)
- إدارة بيانات المدرسة
- إدارة المعلمين والطلاب وأولياء الأمور
- إدارة الصفوف والمواد

### المعلم (Teacher)
- إدارة المهام والدرجات
- التواصل مع الطلاب وأولياء الأمور
- رفع الصور والملفات

### الطالب (Student)
- عرض وتسليم المهام
- الاطلاع على الدرجات
- التواصل مع المعلمين

### ولي الأمر (Parent)
- متابعة أداء الأبناء
- الاطلاع على الدرجات والمهام
- التواصل مع المعلمين

## المساهمة (Contributing)

نرحب بمساهماتكم في تطوير المنصة. يرجى اتباع الخطوات التالية:

1. قم بعمل Fork للمشروع
2. أنشئ فرع (Branch) للميزة الجديدة (`git checkout -b feature/amazing-feature`)
3. قم بتثبيت التغييرات (`git commit -m 'Add some amazing feature'`)
4. ارفع التغييرات إلى الفرع (`git push origin feature/amazing-feature`)
5. افتح طلب دمج (Pull Request)

## الترخيص (License)

هذا المشروع مرخص تحت رخصة MIT - انظر ملف [LICENSE](LICENSE) للتفاصيل.

## الاتصال (Contact)

لأية استفسارات، يرجى التواصل عبر البريد الإلكتروني: support@example.com