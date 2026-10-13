<?php

return [

    /*
    | Manual billing (invoice + bank transfer, marked paid by a platform admin).
    | All periods are in days.
    */

    // Length of a trial a platform admin starts for a company.
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    // Days a new invoice has before it is due.
    'invoice_due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 14),

    // Renewal invoices are issued this many days before a period ends.
    'renewal_notice_days' => (int) env('BILLING_RENEWAL_NOTICE_DAYS', 7),

    // After a period ends unpaid the company keeps its plan this long
    // (status "past due"), then moves to the default (free) plan. Data is
    // never deleted by billing.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    // Shown on invoices and the pricing page. Replace with your real details.
    'seller' => [
        'name' => env('BILLING_SELLER_NAME', env('APP_NAME', 'ImportFlow')),
        'address' => env('BILLING_SELLER_ADDRESS', ''),
        'email' => env('BILLING_SELLER_EMAIL', env('MAIL_FROM_ADDRESS')),
        'payment_instructions' => env('BILLING_PAYMENT_INSTRUCTIONS', 'Bank transfer details will be sent with the invoice. Please quote the invoice number as the payment reference.'),
    ],

];
