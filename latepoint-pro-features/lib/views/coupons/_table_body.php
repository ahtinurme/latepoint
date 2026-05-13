<?php
/** @var $coupons OsCouponModel[] */
?>
<?php
if($coupons){
  foreach ($coupons as $coupon){ ?>
    <tr class="os-clickable-row" <?php echo OsCouponsHelper::quick_coupon_btn_html($coupon->id); ?>>
      <td class="text-center os-column-faded text-right has-floating-button">
	      <?php echo $coupon->id; ?>
	      <div class="os-floating-button"><i class="latepoint-icon latepoint-icon-edit-3"></i></div>
      </td>
      <td><?php echo esc_html($coupon->name ?: $coupon->code); ?></td>
      <td><span class="in-table-coupon-code"><?php echo esc_html($coupon->code); ?></span></td>
      <td><?php echo $coupon->readable_discount(); ?></td>
      <td><span class="os-column-status os-column-status-<?php echo $coupon->status; ?>"><?php echo $coupon->get_nice_status(); ?></span></td>
      <td><?php echo $coupon->formatted_created_date(); ?></td>
    </tr>
    <?php
  };
}?>