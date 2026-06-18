# تعديلات نظام الأدمن — حفلتي

**التاريخ:** 2026-06-09
**المنفّذ:** Amer
**الأساس:** خطة المراجعة المرفقة من محمد

هاد توثيق لكل التعديلات اللي صارت على نظام الأدمن بعد المراجعة. كل نقطة فيها: شو كانت المشكلة، وشو صار الحل.

---

## 1. تقسيم الصلاحيات بين الأدوار

### المشكلة
الـ middleware المسمّى

```
EnsureAdminRole
```

كان بيقبل دور واحد بس، ومستعمَل دايماً كـ

```
role:super_admin
```

. النتيجة: موظف الدعم

```
support
```

كان ممنوع من كل شي — حتى الـ

```
dashboard
```

ما بيقدر يفتحو.

### الحل
- صار الـ middleware يقبل قائمة أدوار:

```
role:super_admin,support
```

- انقسمت الراوتات لمجموعتين حسب الحساسية.

### مصفوفة الصلاحيات النهائية

| القدرة | super_admin | support |
|---|:---:|:---:|
| مشاهدة الـ dashboard والإحصائيات | ✅ | ✅ |
| مشاهدة القوائم (مستخدمين / فيندورز / حجوزات) | ✅ | ✅ |
| موافقة / رفض KYC | ✅ | ✅ |
| حظر مستخدم أو فيندور | ✅ | ❌ |
| المدفوعات والاسترجاع | ✅ | ❌ |
| سجل التدقيق وإدارة الأدمنز | ✅ | ❌ |

---

## 2. فصل "الرفض" عن "الحظر"

### المشكلة
الفعل

```
rejectVendor
```

كان بيعمل

```
is_active = false
```

(يعني حظر فعلي)، وهو متاح للـ

```
support
```

. يعني موظف الدعم كان بيقدر يحظر أي فيندور — وهي صلاحية لازم تكون ممنوعة عنه.

### الحل
- **الرفض** صار يغيّر

```
is_approved = false
```

فقط، ويخزّن سبب الرفض بعمود جديد

```
rejection_reason
```

(صار إجباري). متاح للدورين.

- **الحظر** صار فعل منفصل تماماً

```
toggleVendor
```

، متاح للـ

```
super_admin
```

فقط.

---

## 3. حظر المستخدمين

### المشكلة
ما كان في طريقة لحظر مستخدم. وجدول المستخدمين أصلاً ما فيه عمود للحالة.

### الحل
- ضفت عمود

```
is_active
```

على جدول المستخدمين.

- ضفت فعل

```
toggleUser
```

لحظر / فك حظر المستخدم — للـ

```
super_admin
```

فقط.

---

## 4. سجل التدقيق (Audit Log)

### المشكلة
ما كان في أي تسجيل لأفعال الأدمنز. وهي شغلة حرجة لمنصة فيها أموال.

### الحل
جدول جديد

```
admin_audit_logs
```

بيسجّل لكل فعل حسّاس:

- مين الأدمن اللي عملو
- شو الفعل (موافقة، رفض، حظر، إنشاء أدمن...)
- على مين (النوع والـ id)
- إمتى صار

التسجيل بيصير تلقائياً مع كل فعل حسّاس.

---

## 5. الترقيم (Pagination)

### المشكلة
كل القوائم كانت تستعمل

```
get()

```

— يعني تجيب كل البيانات دفعة وحدة. خطر استهلاك ذاكرة لما تكبر البيانات.

### الحل
كل القوائم صارت

```
paginate(20)
```

.

---

## 6. حماية تسجيل الدخول

### المشكلة
- ما في حماية ضد محاولات تخمين كلمة المرور (brute force).
- الإيميل ما كان يتطبّع، فممكن نفس الإيميل بأحرف مختلفة ما ينطابق.

### الحل
- ضفت

```
throttle:5,1
```

على الـ login — 5 محاولات بالدقيقة لكل IP.

- تطبيع الإيميل (تصغير الأحرف + إزالة الفراغات).

- تسجيل وقت آخر دخول بعمود

```
last_login_at
```

.

---

## 7. إدارة حسابات الأدمنز

### المشكلة
ما كان في طريقة لإنشاء أو حذف حسابات أدمن.

### الحل
كنترولر جديد

```
AdminManagementController
```

— للـ

```
super_admin
```

فقط:

- عرض كل الأدمنز
- إنشاء حساب جديد (عادةً

```
support
```

)
- حذف حساب (ما بيقدر يحذف حسابو هو، مشان ما يقفل نفسو برّا)

---

## 8. تنظيفات صغيرة

- شيل

```
fcm_token
```

من جدول الأدمنز (ما إلو استخدام).

- إصلاح الإحصائية

```
total_vendors
```

بالـ dashboard لتعدّ كل الفيندورز، مع إضافة

```
approved_vendors
```

و

```
pending_vendors
```

.

- إضافة

```
AdminSeeder
```

لإنشاء أول حساب super_admin.

- إصلاح seeder افتراضي قديم كان رح يفشل (كان بينشئ مستخدم بحقول مش موجودة).

---

## قائمة كل راوتات الأدمن

### عام (بدون تسجيل دخول)
```
POST   /api/admin/login        (throttle: 5/min)
```

### بعد تسجيل الدخول — كل الأدمنز
```
POST   /api/admin/logout
```

### عرض + KYC — super_admin و support
```
GET    /api/admin/dashboard
GET    /api/admin/vendors
GET    /api/admin/vendors/pending
GET    /api/admin/vendors/{id}
POST   /api/admin/vendors/{id}/approve
POST   /api/admin/vendors/{id}/reject
GET    /api/admin/users
GET    /api/admin/bookings
```

### أفعال حسّاسة — super_admin فقط
```
POST   /api/admin/vendors/{id}/toggle    (حظر / فك حظر فيندور)
POST   /api/admin/users/{id}/toggle      (حظر / فك حظر مستخدم)
GET    /api/admin/payments
GET    /api/admin/audit-logs
GET    /api/admin/admins
POST   /api/admin/admins                 (إنشاء حساب أدمن)
DELETE /api/admin/admins/{id}            (حذف حساب أدمن)
```

---

## بيانات أول أدمن (للاختبار)

```
Email:    admin@haflati.com
Password: 0000
Role:     super_admin
```

> يُفضّل تغيير كلمة المرور بعد أول دخول.

---

## الملفات المتأثرة

```
app/Http/Middleware/EnsureAdminRole.php       (تعديل)
app/Http/Controllers/AdminAuthController.php   (تعديل)
app/Http/Controllers/AdminController.php       (إعادة كتابة)
app/Http/Controllers/AdminManagementController.php (جديد)
app/Models/Admin.php                           (تعديل)
app/Models/User.php                            (تعديل)
app/Models/AdminAuditLog.php                   (جديد)
database/migrations/...create_users_table      (تعديل: is_active)
database/migrations/...create_vendors_table    (تعديل: rejection_reason)
database/migrations/...create_admins_table     (تعديل: last_login_at, -fcm_token)
database/migrations/...create_admin_audit_logs_table (جديد)
database/seeders/AdminSeeder.php               (جديد)
database/seeders/DatabaseSeeder.php            (تعديل)
routes/api.php                                 (تعديل)
```
