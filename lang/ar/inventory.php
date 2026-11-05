<?php

return [
    'navigation' => 'المخزون',
    'title' => 'المخزون',
    'breadcrumbs' => 'مسار التنقل',

    'unit_statuses' => [
        'available' => 'متاح',
        'removed' => 'مُزال',
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
    ],

    'reasons' => [
        'opening_balance' => 'رصيد افتتاحي',
        'count_correction' => 'تصحيح جرد',
        'found' => 'تم العثور عليه',
        'damaged' => 'تالف',
        'lost' => 'مفقود',
    ],

    'identifier_types' => [
        'imei' => 'IMEI',
        'serial' => 'الرقم التسلسلي',
    ],

    'fields' => [
        'quantity' => 'الكمية',
        'note' => 'ملاحظة',
        'reason' => 'السبب',
        'direction' => 'نوع التعديل',
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
        'opening_balance_exists' => 'لا يمكن تسجيل رصيد افتتاحي بعد وجود سجل مخزون لهذا المنتج في هذا الفرع. استخدم التعديل.',
        'invalid_adjustment_type' => 'نوع التعديل غير صالح.',
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
        'help' => 'يُسجَّل مرة واحدة فقط قبل أي حركة أخرى في هذا الفرع. التغييرات اللاحقة تتم عبر التعديل.',
        'submit' => 'تسجيل الرصيد الافتتاحي',
    ],

    'adjust' => [
        'title' => 'تعديل الكمية',
        'help' => 'لا يمكن أن تقل الكمية عن صفر. كل تعديل يُسجَّل في السجل ولا يمكن حذفه.',
        'submit' => 'تسجيل التعديل',
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
            'removed' => 'المُزالة',
            'all' => 'الكل',
        ],
        'empty' => 'لا توجد وحدات في هذا العرض.',
    ],

    'history' => [
        'title' => 'سجل الحركات',
        'subtitle' => 'سجل دائم لا يمكن تعديله أو حذفه.',
        'empty' => 'لا توجد حركات مخزون بعد.',
        'balance_after' => 'الرصيد بعدها',
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
