<?php

return [
    'subscription_plans' => [
        ['code' => 'semester', 'name_ar' => 'اشتراك فصل دراسي', 'name_en' => 'Semester subscription', 'billing_period' => 'semester', 'price' => 750, 'currency' => 'EGP', 'is_active' => true, 'display_order' => 1],
        ['code' => 'full-year', 'name_ar' => 'اشتراك العام الكامل', 'name_en' => 'Full-year subscription', 'billing_period' => 'year', 'price' => 1300, 'currency' => 'EGP', 'is_active' => true, 'display_order' => 2],
    ],

    'notification_templates' => [
        ['key' => 'payment_approved', 'name' => 'Payment approved', 'subject_ar' => 'تم تأكيد الدفع', 'subject_en' => 'Payment approved', 'body_ar' => 'مرحباً {{name}}، تم تأكيد دفع طلبك {{order}} وأصبحت الدورة متاحة.', 'body_en' => 'Hello {{name}}, payment for order {{order}} was approved and your course is now available.', 'is_active' => true],
        ['key' => 'payment_rejected', 'name' => 'Payment rejected', 'subject_ar' => 'تعذر قبول إثبات الدفع', 'subject_en' => 'Payment evidence rejected', 'body_ar' => 'مرحباً {{name}}، تعذر قبول إثبات الطلب {{order}}. السبب: {{reason}}', 'body_en' => 'Hello {{name}}, evidence for order {{order}} was rejected. Reason: {{reason}}', 'is_active' => true],
        ['key' => 'payment_needs_information', 'name' => 'Payment information requested', 'subject_ar' => 'مطلوب استكمال بيانات الدفع', 'subject_en' => 'More payment information required', 'body_ar' => 'مرحباً {{name}}، نحتاج بيانات إضافية للطلب {{order}}: {{reason}}', 'body_en' => 'Hello {{name}}, more information is required for order {{order}}: {{reason}}', 'is_active' => true],
        ['key' => 'payment_expired', 'name' => 'Payment expired', 'subject_ar' => 'انتهت مهلة طلب الدفع', 'subject_en' => 'Payment order expired', 'body_ar' => 'مرحباً {{name}}، انتهت مهلة الطلب {{order}}. يمكنك إنشاء طلب جديد.', 'body_en' => 'Hello {{name}}, order {{order}} expired. You can create a new order.', 'is_active' => true],
        ['key' => 'finance_alert', 'name' => 'Finance alert', 'subject_ar' => 'تنبيه مالي يحتاج المراجعة', 'subject_en' => 'Financial alert requires review', 'body_ar' => 'مرحباً {{name}}، {{message}}', 'body_en' => 'Hello {{name}}, {{message}}', 'is_active' => true],
        ['key' => 'finance_daily_summary', 'name' => 'Daily finance summary', 'subject_ar' => 'الملخص المالي اليومي', 'subject_en' => 'Daily finance summary', 'body_ar' => 'مرحباً {{name}}، {{message}}', 'body_en' => 'Hello {{name}}, {{message}}', 'is_active' => true],
    ],

    'academic_grades' => [
        ['code' => 'grade-1', 'name_ar' => 'الأول الثانوي', 'name_en' => 'First Secondary', 'display_order' => 1, 'is_active' => true],
        ['code' => 'grade-2', 'name_ar' => 'الثاني الثانوي', 'name_en' => 'Second Secondary', 'display_order' => 2, 'is_active' => true],
        ['code' => 'grade-3', 'name_ar' => 'الثالث الثانوي', 'name_en' => 'Third Secondary', 'display_order' => 3, 'is_active' => true],
    ],

    'academic_terms' => [
        ['code' => 'semester-1', 'name_ar' => 'الفصل الدراسي الأول', 'name_en' => 'Semester 1', 'display_order' => 1, 'is_active' => true],
        ['code' => 'semester-2', 'name_ar' => 'الفصل الدراسي الثاني', 'name_en' => 'Semester 2', 'display_order' => 2, 'is_active' => true],
    ],

    'subjects' => [
        ['code' => 'programming', 'name_ar' => 'البرمجة', 'name_en' => 'Programming', 'is_active' => true],
    ],
];
