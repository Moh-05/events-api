<?php

// Custom API messages (English). The Arabic versions live in lang/ar/messages.php
// with the SAME keys. Controllers reference these via __('messages.<key>').

return [

    // Auth
    'otp_sent'            => 'OTP sent',
    'invalid_otp'         => 'Invalid OTP',
    'invalid_credentials' => 'Invalid credentials',
    'expired'             => 'Expired',
    'logged_out'          => 'Logged out',
    'account_suspended'   => 'Your account has been suspended. Please contact support.',

    // Profile
    'profile_image_removed' => 'Profile image removed',
    'cover_image_removed'   => 'Cover image removed',

    // Products
    'product_not_found' => 'Product not found',

    // Bookings
    'booking_not_found'          => 'Booking not found',
    'booking_cancelled'          => 'Booking cancelled',
    'booking_cancelled_refund'   => 'Booking cancelled — full refund due to the user',
    'vendor_unavailable'         => 'This vendor is currently unavailable',
    'items_same_vendor'          => 'All items must belong to the same vendor',
    'date_already_booked'        => 'This date is already booked',
    'date_not_available'         => 'This date is not available',
    'out_of_stock_approve'       => 'This product is out of stock — cannot approve',
    'complete_before_date'       => 'You can only mark this completed on or after the event/delivery date',

    // Availability
    'date_has_booking' => 'This date already has a booking',
    'date_not_blocked' => 'This date is not blocked',
    'date_unblocked'   => 'Date unblocked',

    // Payments
    'payment_verified'      => 'Payment verified successfully',
    'booking_already_paid'  => 'This booking is already paid',
    'transaction_used'      => 'This transaction has already been used',

    // Reviews
    'review_deleted'       => 'Review deleted',
    'already_reviewed'     => 'You have already reviewed this booking',

    // Dynamic messages (with placeholders)
    'product_not_available'    => "':name' is not available",
    'only_n_in_stock'          => "Only :count of ':name' left in stock",
    'cancelled_partial_refund' => 'Booking cancelled — :percent% refund due to the user',
    'cannot_review_status'     => "You can't review a booking with status ':status'. It must be completed first (approved is allowed for testing).",

    // Notification titles/bodies (sent to user or vendor)
    'notif_payment_received_title' => 'Payment Received',
    'notif_payment_received_body'  => 'Your payment was confirmed. Your booking is now waiting for the vendor to accept it.',
    'notif_new_booking_title'      => 'New Booking',
    'notif_new_booking_body'       => 'You have a new paid booking from :name. Please accept or decline it.',
    'notif_approved_title'         => 'Booking Accepted',
    'notif_approved_body'          => ':name accepted your booking.',
    'notif_completed_title'        => 'Service Completed',
    'notif_completed_body'         => "Your booking with :name is complete. Don't forget to leave a review!",
    'notif_declined_title'         => 'Booking Declined',
    'notif_declined_body'          => ':name declined your booking. Your payment will be refunded.',
    'notif_review_title'           => 'New Review',
    'notif_review_body'            => ':name gave you a :rating-star review.',
    'notif_event_reminder_title'   => 'Upcoming Event',
    'notif_event_reminder_body'    => 'Your event with :name is in :days days. Get ready!',
    'notif_low_stock_title'        => 'Low Stock',
    'notif_low_stock_body'         => 'Only :count left of ":name". Restock before it sells out.',
    'notif_order_received_title'   => 'Order Received',
    'notif_order_received_body'    => ':name confirmed they received their order. Your payout is now available to withdraw.',
    'notif_cancelled_title'        => 'Booking Cancelled',
    'notif_cancelled_body'         => ':name cancelled their booking.',

    // Notifications
    'notification_not_found'  => 'Notification not found',
    'all_marked_read'         => 'All notifications marked as read',

    // Products / Portfolio
    'product_deleted'         => 'Product deleted',
    'portfolio_item_deleted'  => 'Portfolio item deleted',

    // Offers / discounts
    'offer_already_active' => 'This item already has an active offer',
    'offer_cooldown'       => 'You can add a new offer on this item only one week after the previous one ended',
    'no_active_offer'      => 'This item has no active offer',
    'offer_removed'        => 'Offer removed',

    // Wallet
    'no_balance'                 => 'No balance available to withdraw yet',
    'withdrawal_successful'      => 'Withdrawal successful (real payout pending ShamCash payout API)',
    'shamcash_account_saved'     => 'ShamCash account saved',
    'shamcash_account_missing'   => 'Set your ShamCash account before withdrawing, so we know where to send your payout',

    // Admin — vendors
    'vendor_approved'              => 'Vendor approved',
    'vendor_kyc_rejected'          => 'Vendor KYC rejected',
    'vendor_reinstated'            => 'Vendor reinstated.',
    'vendor_already_active'        => 'Vendor is already active',
    'vendor_already_banned'        => 'Vendor is already banned',
    'vendor_already_banned_wind'   => 'Vendor is already banned or winding down',
    'vendor_banned_immediately'    => 'Vendor had no committed bookings — banned immediately.',

    // Admin — money / accounts
    'admin_deleted'            => 'Admin deleted',
    'cannot_delete_self'       => 'You cannot delete your own account',
    'refund_already_paid'      => 'Refund already marked paid',
    'refund_marked_paid'       => 'Refund marked as paid',
    'withdrawal_already_paid'  => 'Withdrawal already marked paid',
    'withdrawal_marked_paid'   => 'Withdrawal marked as paid',

];
