<?php
/** @var array  $booking */
/** @var array  $extras  */
$site_name  = get_bloginfo( 'name' );
$space_name = get_the_title( (int) $booking['space_id'] );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php esc_html_e( 'Booking Confirmed', 'space-booking' ); ?></title>
<style>
  body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px}
  .email-wrap{max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .email-header{background:#2d6a4f;color:#fff;padding:32px;text-align:center}
  .email-header h1{margin:0;font-size:24px}
  .email-body{padding:32px}
  .email-table{width:100%;border-collapse:collapse;margin:16px 0}
  .email-table th{text-align:left;color:#555;font-weight:600;padding:8px 0;width:40%}
  .email-table td{padding:8px 0;color:#222;border-bottom:1px solid #eee}
  .total-row td,.total-row th{font-size:18px;font-weight:700;color:#2d6a4f;border:none}
  .email-footer{background:#f9f9f9;padding:16px 32px;font-size:12px;color:#888;text-align:center}
  .btn{display:inline-block;background:#2d6a4f;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:700;margin-top:16px}
</style>
</head>
<body>
<div class="email-wrap">
  <div class="email-header">
    <h1>✅ <?php esc_html_e( 'Booking Confirmed!', 'space-booking' ); ?></h1>
    <p><?php echo esc_html( $site_name ); ?></p>
  </div>

  <div class="email-body">
    <p><?php printf( esc_html__( 'Hi %s,', 'space-booking' ), esc_html( $booking['customer_name'] ) ); ?></p>
    <p><?php esc_html_e( 'Your booking is confirmed and payment received. Here are your details:', 'space-booking' ); ?></p>

    <table class="email-table">
      <tr><th><?php esc_html_e( 'Space', 'space-booking' ); ?></th><td><?php echo esc_html( $space_name ); ?></td></tr>
      <tr><th><?php esc_html_e( 'Date', 'space-booking' ); ?></th><td><?php echo esc_html( $booking['booking_date'] ); ?></td></tr>
      <tr><th><?php esc_html_e( 'Time', 'space-booking' ); ?></th>
          <td><?php echo esc_html( substr( $booking['start_time'], 0, 5 ) . ' – ' . substr( $booking['end_time'], 0, 5 ) ); ?></td></tr>
      <tr><th><?php esc_html_e( 'Duration', 'space-booking' ); ?></th><td><?php echo esc_html( $booking['duration_hours'] ); ?>h</td></tr>
      <?php if ( ! empty( $extras ) ) : ?>
      <tr><th><?php esc_html_e( 'Extras', 'space-booking' ); ?></th>
          <td><?php echo esc_html( implode( ', ', array_column( $extras, 'extra_name' ) ) ); ?></td></tr>
      <?php endif; ?>
      <?php if ( $booking['notes'] ) : ?>
      <tr><th><?php esc_html_e( 'Notes', 'space-booking' ); ?></th><td><?php echo esc_html( $booking['notes'] ); ?></td></tr>
      <?php endif; ?>
      <tr class="total-row">
        <th><?php esc_html_e( 'Total Paid', 'space-booking' ); ?></th>
        <td>$<?php echo esc_html( number_format( (float) $booking['total_price'], 2 ) ); ?></td>
      </tr>
    </table>

    <p><?php esc_html_e( 'Need to view or change your booking?', 'space-booking' ); ?></p>
    <a class="btn" href="<?php echo esc_url( home_url( '/booking-lookup/' ) ); ?>">
      <?php esc_html_e( 'Manage My Booking', 'space-booking' ); ?>
    </a>
  </div>

  <div class="email-footer">
    <p><?php printf( esc_html__( '© %s %s. All rights reserved.', 'space-booking' ), date( 'Y' ), esc_html( $site_name ) ); ?></p>
  </div>
</div>
</body>
</html>
