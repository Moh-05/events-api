<?php

// Custom API messages (Arabic). Same keys as lang/en/messages.php.
// Controllers reference these via __('messages.<key>').

return [

    // Auth
    'otp_sent'            => 'تم إرسال رمز التحقق',
    'invalid_otp'         => 'رمز التحقق غير صالح',
    'invalid_credentials' => 'بيانات الدخول غير صحيحة',
    'expired'             => 'منتهي الصلاحية',
    'logged_out'          => 'تم تسجيل الخروج',
    'account_suspended'   => 'تم تعليق حسابك، من فضلك تواصل مع الدعم',

    // Profile
    'profile_image_removed' => 'تمت إزالة صورة الملف الشخصي',
    'cover_image_removed'   => 'تمت إزالة صورة الغلاف',

    // Products
    'product_not_found' => 'المنتج غير موجود',

    // Bookings
    'booking_not_found'        => 'الحجز غير موجود',
    'booking_cancelled'        => 'تم إلغاء الحجز',
    'booking_cancelled_refund' => 'تم إلغاء الحجز - يستعيد العميل كامل المبلغ',
    'vendor_unavailable'       => 'هذا المزوّد غير متاح حالياً',
    // Developer-facing guard — a real user can never trigger it (the app only
    // ever sends one vendor's items). Kept in English on purpose.
    'items_same_vendor'        => 'All items must belong to the same vendor',
    'date_already_booked'      => 'هذا التاريخ محجوز مسبقاً',
    'date_not_available'       => 'هذا التاريخ غير متاح',
    'out_of_stock_approve'     => 'هذا المنتج نفدت كميته — لا يمكن قبول الطلب',
    'complete_before_date'     => 'لا يمكنك تعليم الحجز كمكتمل إلا في يوم المناسبة/التسليم أو بعده',

    // Availability
    'date_has_booking' => 'يوجد حجز على هذا التاريخ',
    'date_not_blocked' => 'هذا التاريخ غير محجوب',
    'date_unblocked'   => 'تم إلغاء حجب التاريخ',

    // Payments
    'payment_verified'     => 'تم تأكيد الدفع بنجاح',
    'booking_already_paid' => 'تم دفع هذا الحجز مسبقاً',
    'transaction_used'     => 'تم استخدام هذه العملية مسبقاً',

    // Reviews
    'review_deleted'   => 'تم حذف التقييم',
    'already_reviewed' => 'لقد قمت بتقييم هذا الحجز مسبقاً',

    // Dynamic messages (with placeholders)
    'product_not_available'    => '«:name» غير متاح',
    'only_n_in_stock'          => 'يتوفر :count فقط من «:name» في المخزون',
    'cancelled_partial_refund' => 'تم إلغاء الحجز — يستعيد العميل :percent% من المبلغ',
    'cannot_review_status'     => 'لا يمكنك تقييم حجز حالته «:status». يجب أن يكتمل الحجز أولاً',

    // Notification titles/bodies (sent to user or vendor)
    'notif_payment_received_title' => 'تم استلام الدفع',
    'notif_payment_received_body'  => 'تم تأكيد دفعتك. حجزك الآن بانتظار موافقة المزوّد',
    'notif_new_booking_title'      => 'حجز جديد',
    'notif_new_booking_body'       => 'لديك حجز جديد مدفوع من :name. يرجى القبول أو الرفض',
    'notif_approved_title'         => 'تم قبول الحجز',
    'notif_approved_body'          => 'قام :name بقبول حجزك',
    'notif_completed_title'        => 'اكتملت الخدمة',
    'notif_completed_body'         => 'اكتمل حجزك مع :name. لا تنسَ ترك تقييم!',
    'notif_declined_title'         => 'تم رفض الحجز',
    'notif_declined_body'          => 'قام :name برفض حجزك. سيتم إعادة مبلغك',
    'notif_review_title'           => 'تقييم جديد',
    'notif_review_body'            => 'قام :name بتقييمك بـ :rating نجوم',
    'notif_cancelled_title'        => 'تم إلغاء الحجز',
    'notif_cancelled_body'         => 'قام :name بإلغاء حجزه',

    // Notifications
    'notification_not_found' => 'الإشعار غير موجود',
    'all_marked_read'        => 'تم تعليم كل الإشعارات كمقروءة',

    // Products / Portfolio
    'product_deleted'        => 'تم حذف المنتج',
    'portfolio_item_deleted' => 'تم حذف عنصر المعرض',

    // Offers / discounts
    'offer_already_active' => 'يوجد عرض فعّال على هذا العنصر بالفعل',
    'offer_cooldown'       => 'يمكنك إضافة عرض جديد على هذا العنصر بعد أسبوع من انتهاء العرض السابق',
    'no_active_offer'      => 'لا يوجد عرض فعّال على هذا العنصر',
    'offer_removed'        => 'تم إلغاء العرض',

    // Wallet
    'no_balance'              => 'لا يوجد رصيد متاح للسحب حالياً',
    'withdrawal_successful'   => 'تم السحب بنجاح',
    'shamcash_account_saved'   => 'تم حفظ حساب ShamCash',
    'shamcash_account_missing' => 'الرجاء إضافة حساب ShamCash قبل السحب حتى نعرف وين نحوّل المبلغ',

    // Admin messages — kept in English for now (React admin dashboard / admins
    // work in English). Translate later if the admin app needs Arabic.
    'vendor_approved'            => 'Vendor approved',
    'vendor_kyc_rejected'        => 'Vendor KYC rejected',
    'vendor_reinstated'          => 'Vendor reinstated.',
    'vendor_already_active'      => 'Vendor is already active',
    'vendor_already_banned'      => 'Vendor is already banned',
    'vendor_already_banned_wind' => 'Vendor is already banned or winding down',
    'vendor_banned_immediately'  => 'Vendor had no committed bookings — banned immediately.',
    'admin_deleted'              => 'Admin deleted',
    'cannot_delete_self'         => 'You cannot delete your own account',
    'refund_already_paid'        => 'Refund already marked paid',
    'refund_marked_paid'         => 'Refund marked as paid',
    'withdrawal_already_paid'    => 'Withdrawal already marked paid',
    'withdrawal_marked_paid'     => 'Withdrawal marked as paid',

];
