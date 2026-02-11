<?php

return [
    'no_reason_given' => 'لم يُذكر سبب',

    'topup_requested_title' => 'طلب إيداع جديد',
    'topup_requested_message' => 'إيداع بمبلغ :amount :currency (طلب #:id) بانتظار المراجعة.',

    'topup_approved_title' => 'تم الموافقة على الإيداع',
    'topup_approved_message' => 'تمت الموافقة على إيداعك بمبلغ :amount :currency وإضافته إلى محفظتك.',

    'topup_rejected_title' => 'تم رفض الإيداع',
    'topup_rejected_message' => 'تم رفض إيداعك بمبلغ :amount :currency. السبب: :reason',

    'refund_requested_title' => 'طلب استرداد جديد',
    'refund_requested_message' => 'استرداد بمبلغ :amount (معاملة #:transaction_id) بانتظار الموافقة.',

    'refund_approved_title' => 'تمت الموافقة على الاسترداد',
    'refund_approved_message' => 'تمت الموافقة على استردادك بمبلغ :amount للطلب :order_number وإضافته إلى محفظتك.',

    'refund_rejected_title' => 'تم رفض الاسترداد',
    'refund_rejected_message' => 'تم رفض استردادك بمبلغ :amount للطلب :order_number.',

    'fulfillment_process_failed_title' => 'فشل تنفيذ الطلب',
    'fulfillment_process_failed_message' => 'فشل التنفيذ #:fulfillment_id (الطلب #:order_id). الخطأ: :error',

    'fulfillment_completed_title' => 'تم توصيل صنف الطلب',
    'fulfillment_completed_message' => 'تم توصيل صنف من طلبك (#:order_id).',

    'fulfillment_failed_title' => 'فشل توصيل صنف الطلب',
    'fulfillment_failed_message' => 'فشل التوصيل لصنف من الطلب #:order_id. السبب: :reason. يمكنك طلب الاسترداد.',

    'wallet_reconciled_title' => 'تم تصحيح رصيد المحفظة',
    'wallet_reconciled_message' => 'تم تسوية المحفظة #:wallet_id (المستخدم #:user_id). المخزون: :stored، المتوقع: :expected، الفرق: :diff.',

    'settlement_created_title' => 'تم إنشاء دفعة تسوية',
    'settlement_created_message' => 'تم إنشاء التسوية #:settlement_id: :amount من :count تنفيذ(ات).',

    'loyalty_tier_changed_title' => 'تم تحديث مستوى الولاء',
    'loyalty_tier_changed_message' => 'تغير مستوى ولائك من :previous_tier إلى :new_tier.',

    'user_blocked_title' => 'تم حظر الحساب',
    'user_blocked_message' => 'تم حظر حسابك. تواصل مع الدعم للمساعدة.',

    'user_unblocked_title' => 'تم إلغاء حظر الحساب',
    'user_unblocked_message' => 'تم إلغاء حظر حسابك. يمكنك تسجيل الدخول مرة أخرى.',

    'payment_failed_title' => 'فشل الدفع',
    'payment_failed_message' => 'تعذر إتمام الدفع للطلب :order_number. :reason',
    'payment_failed_message_no_order' => 'تعذر إتمام الدفع. :reason',
];
