<?php

return [
    'navigation' => 'الموردون',
    'title' => 'الموردون',
    'subtitle' => 'بيانات الاتصال الأساسية لموردي المنتجات.',
    'search_label' => 'بحث',
    'search_placeholder' => 'الاسم أو جهة الاتصال أو الهاتف',

    'fields' => [
        'name' => 'اسم المورد',
        'contact_name' => 'جهة الاتصال',
        'phone' => 'الهاتف',
        'email' => 'البريد الإلكتروني',
        'tax_number' => 'الرقم الضريبي',
        'address' => 'العنوان',
        'notes' => 'ملاحظات',
        'status' => 'الحالة',
    ],

    'statuses' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
    ],

    'actions' => [
        'create' => 'إضافة مورد',
        'edit' => 'تعديل',
        'cancel' => 'إلغاء',
        'save' => 'حفظ',
        'saving' => 'جارٍ الحفظ…',
    ],

    'empty' => [
        'title' => 'لا يوجد موردون بعد',
        'body' => 'أضف أول مورد لحفظ بيانات الاتصال به.',
        'no_matches_title' => 'لا توجد نتائج مطابقة',
        'no_matches_body' => 'جرّب كلمة بحث أخرى.',
    ],

    'form' => [
        'create_title' => 'إضافة مورد',
        'edit_title' => 'تعديل بيانات المورد',
        'active' => 'مورد نشط',
    ],

    'messages' => [
        'created' => 'تمت إضافة المورد.',
        'updated' => 'تم تحديث بيانات المورد.',
    ],
];
