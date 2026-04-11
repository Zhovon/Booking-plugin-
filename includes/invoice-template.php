<?php
defined( 'ABSPATH' ) || exit;

function zb_render_invoice( $booking ) {
    $currency  = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';
    $site_name = get_bloginfo('name');
    ?>
    <!DOCTYPE html>
    <html lang="da">
    <head>
        <meta charset="UTF-8">
        <title>Faktura #<?php echo $booking->id; ?> - <?php echo $site_name; ?></title>
        <style>
            body { font-family: -apple-system, system-ui, sans-serif; padding: 40px; color: #333; line-height: 1.6; max-width: 800px; margin: 0 auto; background: #fff; }
            .header { display: flex; justify-content: space-between; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px; margin-bottom: 40px; }
            .logo { font-size: 24px; font-weight: 800; color: #4a7c59; }
            .inv-info { text-align: right; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
            .label { font-weight: 600; font-size: 13px; text-transform: uppercase; color: #999; margin-bottom: 4px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
            table th { text-align: left; padding: 12px; background: #f9fafb; font-size: 14px; }
            table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
            .total-row { text-align: right; font-size: 18px; font-weight: 700; margin-top: 20px; }
            .footer { margin-top: 80px; font-size: 12px; color: #999; text-align: center; border-top: 1px solid #f0f0f0; padding-top: 20px; }
            @media print { .no-print { display: none; } body { padding: 0; } }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align:right; margin-bottom:20px;">
            <button onclick="window.print()" style="padding:8px 16px; background:#4a7c59; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:600;">Print Faktura</button>
        </div>
        <div class="header">
            <div class="logo"><?php echo esc_html($site_name); ?></div>
            <div class="inv-info">
                <div class="label">Faktura #</div>
                <div style="font-size:18px; font-weight:700;"><?php echo absint($booking->id); ?></div>
                <div class="label" style="margin-top:10px;">Dato</div>
                <div><?php echo date('d.m.Y'); ?></div>
            </div>
        </div>
        <div class="grid">
            <div>
                <div class="label">Fra</div>
                <strong><?php echo esc_html($site_name); ?></strong><br>
                <?php echo nl2br(get_option('owner_address', 'CVR: 12345678')); ?>
            </div>
            <div>
                <div class="label">Til</div>
                <strong><?php echo esc_html($booking->company_name); ?></strong><br>
                <?php echo esc_html($booking->contact_person); ?><br>
                <?php echo nl2br(esc_html($booking->address)); ?>
            </div>
        </div>
        <table>
            <thead><tr><th>Beskrivelse</th><th style="text-align:right;">Pris ekskl. moms</th></tr></thead>
            <tbody>
                <tr>
                    <td>Foto/Video pakke: <?php echo esc_html($booking->services); ?><br><small><?php echo esc_html($booking->booking_date); ?></small></td>
                    <td style="text-align:right;"><?php echo esc_html($booking->price); ?> <?php echo esc_html($currency); ?></td>
                </tr>
                <?php if ($booking->coupon_price) : ?>
                <tr>
                    <td>Anvendt rabat</td>
                    <td style="text-align:right; color:#15803d;">-<?php echo number_format($booking->price - $booking->coupon_price, 2); ?> <?php echo esc_html($currency); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="total-row">
            <div class="label">Total pr. ydelse</div>
            <?php echo $booking->coupon_price ?: $booking->price; ?> <?php echo esc_html($currency); ?>
        </div>
        <div class="footer">
            <p>Tak fordi du valgte <?php echo esc_html($site_name); ?>. Betaling sker efter aftalt fotografering.</p>
        </div>
    </body>
    </html>
    <?php
}
