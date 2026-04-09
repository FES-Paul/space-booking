<?php
/** @var array $booking */
/** @var array $extras  */
$site_name  = get_bloginfo( 'name' );
$space_name = get_the_title( (int) $booking['space_id'] );
$admin_url  = admin_url( 'admin.php?page=space-booking' );
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>New Booking Notification</title>
<style>
  body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px}
  .wrap{max-width:600px;margin:auto;background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  table{width:100%;border-collapse:collapse}
  th{text-align:left;color:#555;padding:8px 0;width:40%;font-weight:600}
  td{padding:8px 0;color:#222;border-bottom:1px solid #eee}
  .btn{display:inline-block;background:#0073aa;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:700;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <h2>🔔 New Booking – <?php echo esc_html( $space_name ); ?></h2>
  <table>
    <tr><th>Customer</th><td><?php echo esc_html( $booking['customer_name'] ); ?></td></tr>
    <tr><th>Email</th><td><?php echo esc_html( $booking['customer_email'] ); ?></td></tr>
    <tr><th>Phone</th><td><?php echo esc_html( $booking['customer_phone'] ?: '—' ); ?></td></tr>
    <tr><th>Space</th><td><?php echo esc_html( $space_name ); ?></td></tr>
    <tr><th>Date</th><td><?php echo esc_html( $booking['booking_date'] ); ?></td></tr>
    <tr><th>Time</th><td><?php echo esc_html( substr( $booking['start_time'], 0, 5 ) . ' – ' . substr( $booking['end_time'], 0, 5 ) ); ?></td></tr>
    <?php if ( ! empty( $extras ) ) : ?>
    <tr><th>Extras</th><td><?php echo esc_html( implode( ', ', array_column( $extras, 'extra_name' ) ) ); ?></td></tr>
    <?php endif; ?>
    <tr><th>Total</th><td><strong>$<?php echo esc_html( number_format( (float) $booking['total_price'], 2 ) ); ?></strong></td></tr>
    <tr><th>Status</th><td><?php echo esc_html( ucfirst( $booking['status'] ) ); ?></td></tr>
  </table>
  <a class="btn" href="<?php echo esc_url( $admin_url ); ?>">View in Admin →</a>
</div>
</body>
</html>
