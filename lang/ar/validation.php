<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون حقل :attribute نصاً.',
    'email' => 'يجب أن يكون حقل :attribute عنوان بريد إلكتروني صحيحاً.',
    'max' => [
        'string' => 'يجب ألا يتجاوز حقل :attribute :max حرف.',
    ],
    'min' => [
        'string' => 'يجب أن يكون حقل :attribute على الأقل :min أحرف.',
    ],
    'regex' => 'تنسيق حقل :attribute غير صحيح.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'confirmed' => 'تأكيد حقل :attribute غير متطابق.',
    'in' => 'القيمة المحددة لحقل :attribute غير صحيحة.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'username' => 'اسم المستخدم',
        'email' => 'عنوان البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'country_code' => 'رمز الدولة',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'profile_photo' => 'صورة الملف الشخصي',
        'packageName' => 'الاسم',
        'packageCategoryId' => 'الفئة',
        'packageDescription' => 'الوصف',
        'packageOrder' => 'الترتيب',
        'packageIcon' => 'الأيقونة',
        'packageImageFile' => 'الصورة',
        'packageIsActive' => 'الحالة',
        'requirementKey' => 'المفتاح',
        'requirementLabel' => 'التسمية',
        'requirementType' => 'النوع',
        'requirementIsRequired' => 'مطلوب',
        'requirementValidationRules' => 'قواعد التحقق',
        'requirementOrder' => 'الترتيب',
        'newName' => 'الاسم',
        'newParentId' => 'الفئة الأب',
        'newOrder' => 'الترتيب',
        'newIcon' => 'الأيقونة',
        'newImageFile' => 'الصورة',
        'newIsActive' => 'الحالة',
        'productPackageId' => 'الباقة',
        'productSerial' => 'الرقم التسلسلي',
        'productName' => 'الاسم',
        'productRetailPrice' => 'سعر البيع',
        'productWholesalePrice' => 'سعر الجملة',
        'productOrder' => 'الترتيب',
        'productIsActive' => 'الحالة',
        'deliveredPayloadInput' => 'بيانات التسليم',
        'failureReason' => 'سبب الفشل',
    ],

];
