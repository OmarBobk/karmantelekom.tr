<?php

return [
    'navigation' => 'المخزون',
    'title' => 'المخزون',
    'breadcrumbs' => 'مسار التنقل',

    'unit_statuses' => [
        'available' => 'متاح',
        'inspection' => 'بانتظار الفحص',
        'in_repair' => 'قيد الصيانة',
        'removed' => 'مُزال',
        'sold' => 'مُباع',
    ],

    'conditions' => [
        'new' => 'جديد',
        'a' => 'A',
        'b' => 'B',
        'c' => 'C',
        'for_parts' => 'قطع غيار',
    ],

    'movement_types' => [
        'opening_balance' => 'رصيد افتتاحي',
        'adjustment_increase' => 'زيادة',
        'adjustment_decrease' => 'نقص',
        'purchase_receipt' => 'استلام مشتريات',
        'inspection' => 'فحص',
        'repair_started' => 'بدء صيانة',
        'repair_completed' => 'إكمال صيانة',
        'repair_cancelled' => 'إلغاء صيانة',
        'device_cost_recorded' => 'تسجيل تكلفة',
        'device_cost_reversed' => 'تصحيح تكلفة',
        'identifier_corrected' => 'تصحيح معرّف',
        'sale' => 'بيع',
    ],

    'reasons' => [
        'opening_balance' => 'رصيد افتتاحي',
        'count_correction' => 'تصحيح جرد',
        'physical_count' => 'جرد فعلي',
        'found' => 'تم العثور عليه',
        'damaged' => 'تالف',
        'lost' => 'مفقود',
        'purchase' => 'استلام مشتريات',
        'inspection' => 'فحص',
        'repair' => 'صيانة',
        'device_cost' => 'تكلفة جهاز',
        'identity_correction' => 'تصحيح معرّف',
        'sale' => 'بيع',
    ],

    'identifier_types' => [
        'imei' => 'IMEI',
        'serial' => 'الرقم التسلسلي',
    ],

    'fields' => [
        'quantity' => 'الكمية',
        'note' => 'ملاحظة',
        'reason' => 'السبب',
        'counted_quantity' => 'العدد الفعلي',
        'product' => 'المنتج',
        'on_hand' => 'المتوفر',
        'condition' => 'الحالة',
        'imei' => 'IMEI',
        'secondary_imei' => 'IMEI الثاني (شريحتان)',
        'serial' => 'الرقم التسلسلي',
        'store' => 'الفرع',
        'removed_at' => 'تاريخ الإزالة',
    ],

    'errors' => [
        'invalid_imei' => 'يجب أن يتكون IMEI من 15 رقمًا برقم تحقق صحيح.',
        'quantity_range' => 'يجب أن تكون الكمية عددًا صحيحًا بين 1 و:max.',
        'requires_quantity_tracking' => 'هذا المنتج لا يُتتبع بالكمية.',
        'opening_balance_exists' => 'لا يمكن تسجيل رصيد افتتاحي بعد وجود سجل مخزون لهذا المنتج في هذا الفرع. استخدم الجرد الفعلي.',
        'count_range' => 'يجب أن يكون العدد الفعلي عددًا صحيحًا بين 0 و:max.',
        'count_reason_required' => 'أدخل سبب هذا الجرد.',
        'count_stale' => 'تغيّر المخزون بعد بدء هذا الجرد. أعد عدّ الرف، ثم ابدأ الجرد من جديد.',
        'reason_mismatch' => 'السبب المحدد لا يناسب نوع العملية.',
        'note_required' => 'الملاحظة مطلوبة عند إنقاص المخزون.',
        'insufficient_stock' => 'الكمية غير كافية. المتوفر حاليًا: :available.',
        'requires_unit_tracking' => 'هذا المنتج لا يُتتبع بالوحدات.',
        'condition_not_allowed' => 'الحالة المحددة غير مسموحة لهذا النوع من المنتجات.',
        'identifier_required' => 'أدخل IMEI أو رقمًا تسلسليًا واحدًا على الأقل.',
        'primary_imei_required' => 'أدخل IMEI الأول قبل IMEI الثاني.',
        'duplicate_imei_pair' => 'يجب أن يختلف IMEI الثاني عن الأول.',
        'imei_taken' => 'رقم IMEI هذا مسجل مسبقًا لجهاز آخر.',
        'serial_taken' => 'هذا الرقم التسلسلي مسجل مسبقًا لهذا المنتج.',
        'unit_not_available' => 'هذه الوحدة غير متاحة في المخزون.',
        'submission_conflict' => 'تم استخدام هذا النموذج لعملية مختلفة. راجع البيانات وأرسلها مرة أخرى.',
    ],

    'messages' => [
        'recorded' => 'تم تسجيل حركة المخزون.',
        'already_recorded' => 'هذه العملية مسجلة مسبقًا، ولم تُكرَّر.',
        'count_matches' => 'العدد يطابق الرصيد الحالي. لم يُسجَّل أي تغيير.',
    ],

    'actions' => [
        'open_store' => 'فتح مخزون الفرع',
    ],

    'stores' => [
        'subtitle' => 'اختر فرعًا لعرض مخزونه وتسجيل التعديلات.',
        'empty_title' => 'لا توجد فروع متاحة',
        'empty_body' => 'لا تملك صلاحية الوصول إلى أي فرع.',
        'quantity_on_hand' => 'الكمية المتوفرة',
        'available_units' => 'الوحدات المتاحة',
    ],

    'store' => [
        'subtitle' => 'المنتجات التي لها سجل مخزون في هذا الفرع.',
        'find_product_title' => 'إضافة مخزون لمنتج',
        'find_product_help' => 'ابحث بالاسم أو الموديل، أو اكتب رمز SKU أو الباركود كاملًا.',
        'find_product_placeholder' => 'اسم المنتج أو SKU أو الباركود',
        'no_catalog_matches' => 'لا يوجد تعريف منتج نشط مطابق. أضفه أولًا من كتالوج المنتجات.',
        'stocked_title' => 'مخزون الفرع',
        'filter_label' => 'تصفية حسب الاسم أو الموديل',
        'empty_title' => 'لا يوجد مخزون في هذا الفرع بعد',
        'empty_body' => 'ابحث عن منتج أعلاه وسجّل رصيده الافتتاحي.',
        'no_matches_title' => 'لا توجد منتجات مطابقة',
        'no_matches_body' => 'جرّب كلمة بحث أخرى.',
    ],

    'product' => [
        'available_units' => 'الوحدات المتاحة',
        'quantity_in_store' => 'الكمية في :store',
        'read_only' => 'يمكنك عرض المخزون فقط. لا تملك صلاحية تعديله.',
    ],

    'opening' => [
        'title' => 'الرصيد الافتتاحي',
        'help' => 'للإعداد الأولي فقط: يُسجَّل مرة واحدة قبل أي حركة أخرى في هذا الفرع. التغييرات اللاحقة تأتي من استلام المشتريات والمبيعات والجرد الفعلي.',
        'submit' => 'تسجيل الرصيد الافتتاحي',
    ],

    'count' => [
        'title' => 'الجرد الفعلي',
        'help' => 'ابدأ الجرد قبل عدّ الرف. أي بيع أو استلام أثناء الجرد يجعله قديمًا.',
        'in_progress_help' => 'أدخل العدد الكلي الذي وجدته على الرف، حتى لو كان صفرًا. يُسجَّل الفرق في السجل.',
        'start' => 'بدء الجرد',
        'restart' => 'إعادة بدء الجرد',
        'quantity_help' => 'العدد الفعلي الكامل، وليس الفرق.',
        'submit' => 'تسجيل الجرد',
        'cancel' => 'إلغاء',
    ],

    'receiving' => [
        'help' => 'يُستلم مخزون المورّد من خلال فاتورة الشراء الخاصة به.',
        'link' => 'فتح المشتريات',
    ],

    'register' => [
        'title' => 'تسجيل وحدة',
        'help' => 'سجّل جهازًا واحدًا متاحًا في مخزون هذا الفرع.',
        'identifier_help' => 'IMEI فريد على مستوى النظام كله، والرقم التسلسلي فريد لكل منتج، حتى بعد إزالة الوحدة.',
        'submit' => 'تسجيل الوحدة',
    ],

    'form' => [
        'optional' => 'اختياري',
        'required' => 'مطلوب',
        'choose' => 'اختر…',
        'saving' => 'جارٍ التسجيل…',
    ],

    'units' => [
        'title' => 'الوحدات',
        'filter' => 'تصفية الوحدات حسب الحالة',
        'filters' => [
            'available' => 'المتاحة',
            'in_service' => 'الفحص والصيانة',
            'removed' => 'المُزالة',
            'sold' => 'المُباعة',
            'all' => 'الكل',
        ],
        'empty' => 'لا توجد وحدات في هذا العرض.',
    ],

    'history' => [
        'title' => 'سجل الحركات',
        'subtitle' => 'سجل دائم لا يمكن تعديله أو حذفه.',
        'empty' => 'لا توجد حركات مخزون بعد.',
        'balance_after' => 'الرصيد بعدها',
        'previous_balance' => 'الرصيد السابق',
        'counted_total' => 'العدد الفعلي',
        'status_change' => 'الحالة',
    ],

    'remove' => [
        'title' => 'إزالة من المخزون',
        'help' => 'تبقى الوحدة ومعرّفاتها محفوظة في السجل ولا يمكن إعادة استخدام معرّفاتها.',
        'submit' => 'إزالة الوحدة',
        'confirm' => 'هل تريد إزالة هذه الوحدة من المخزون المتاح؟',
        'already_removed' => 'تمت إزالة هذه الوحدة من المخزون.',
    ],
];
