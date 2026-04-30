<?php
/** @var array $booking */
/** @var array $extras */
/** @var string $price_breakdown */
/** @var string $customer_name */
/** @var string $space_name */
/** @var string $access_instructions */
/** @var string $total_price */
/** @var int $order_id */
/** @var string $site_name */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Booking Confirmed</title>
    <style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        background: #f8f9fa;
        margin: 0;
        padding: 20px;
        line-height: 1.6
    }

    .email-wrap {
        max-width: 600px;
        margin: auto;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .1)
    }

    .email-header {
        background: linear-gradient(135deg, #2d6a4f, #4a9c7a);
        color: #fff;
        padding: 40px 32px;
        text-align: center
    }

    .email-header img {
        max-width: 180px;
        height: auto;
        margin-bottom: 12px
    }

    .email-header h1 {
        margin: 0 0 8px;
        font-size: 28px;
        font-weight: 300
    }

    .email-body {
        padding: 40px 32px
    }

    .greeting {
        margin-bottom: 24px;
        font-size: 18px
    }

    .email-table {
        width: 100%;
        border-collapse: collapse;
        margin: 24px 0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .06)
    }

    .email-table th {
        text-align: left;
        color: #555;
        font-weight: 600;
        padding: 12px 16px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef
    }

    .email-table td {
        padding: 12px 16px;
        color: #333;
        border-bottom: 1px solid #f1f3f4
    }

    .total-row td,
    .total-row th {
        font-size: 20px;
        font-weight: 700;
        color: #2d6a4f;
        background: #f0f8f0;
        border: none;
        padding: 16px
    }

    .section {
        margin: 32px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #2d6a4f
    }

    .section h3 {
        margin: 0 0 16px;
        font-size: 18px;
        color: #2d6a4f
    }

    .btn {
        display: inline-block;
        background: #2d6a4f;
        color: #fff;
        padding: 14px 28px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin: 16px 0;
        font-size: 16px;
        box-shadow: 0 2px 8px rgba(45, 106, 79, .3)
    }

    .email-footer {
        background: #f1f3f4;
        padding: 24px 32px;
        font-size: 14px;
        color: #6c757d;
        text-align: center;
        border-top: 1px solid #dee2e6
    }

    .venue-info {
        background: #e8f5e8;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0
    }
    </style>
</head>

<body>
    <div class="email-wrap">
        <div class="email-header">
            <img src="https://kukoolala.com/logo.png" alt="Kukoolala" style="display:block;margin:0 auto">
            <h1>✅ Booking Confirmed!</h1>
            <p style="margin:0;opacity:.95"><?php echo esc_html($site_name); ?></p>
        </div>

        <div class="email-body">
            <p class="greeting">Hi <?php echo esc_html($customer_name); ?>,</p>

            <p>Your booking has been confirmed! Here are the details:</p>

            <table class="email-table">
                <?php echo $booking_details; ?>
                <?php if (!empty($extras)): ?>
                <tr>
                    <th><?php _e('Extras', 'space-booking'); ?></th>
                    <td><?php echo esc_html(implode(', ', array_column($extras, 'extra_name'))); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <th><?php _e('Total', 'space-booking'); ?></th>
                    <td><?php echo $total_price; ?></td>
                </tr>
            </table>

            <div class="section">
                <h3>💳 Payment Details</h3>
                <?php echo $price_breakdown; ?>
                <p><small>Order #<?php echo $order_id; ?></small></p>
            </div>

            <div class="section venue-info">
                <h3>📋 Access Instructions</h3>
                <?php echo $access_instructions; ?>
            </div>

            <p style="margin:32px 0;font-size:16px">Need to manage your booking?</p>
            <a class="btn" href="<?php echo esc_url(home_url('/booking-lookup/')); ?>">
                Manage My Bookings
            </a>
        </div>

        <div class="email-footer">
            <p><?php printf(__('© %s %s. All rights reserved.', 'space-booking'), date('Y'), esc_html($site_name)); ?>
            </p>
            <p><?php _e('Venue Address: 123 Kukoolala St, Your City | Contact: info@kukoolala.com', 'space-booking'); ?>
            </p>
        </div>
    </div>
</body>

</html>