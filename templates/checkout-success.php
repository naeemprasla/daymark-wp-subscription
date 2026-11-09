<?php
defined( 'ABSPATH' ) || exit;
?><main class="daymark-checkout-success" style="<?php echo esc_attr( $design_style ); ?>--daymark-success-accent:<?php echo esc_attr( $brand_color ); ?>" aria-labelledby="daymark-checkout-success-title">
	<section class="daymark-checkout-success__card">
		<div class="daymark-checkout-success__mark" aria-hidden="true"><span>✓</span></div>
		<p class="daymark-checkout-success__eyebrow"><?php echo esc_html__( 'Subscription checkout', 'daymark-subscriptions' ); ?></p>
		<h1 id="daymark-checkout-success-title"><?php echo esc_html( $review_request ? __( 'Request submitted for review', 'daymark-subscriptions' ) : __( 'Thank you — you’re all set', 'daymark-subscriptions' ) ); ?></h1>
		<p class="daymark-checkout-success__message"><?php echo esc_html( $review_request ? __( 'Your subscription request is pending admin approval. No payment has been taken.', 'daymark-subscriptions' ) : __( 'Your checkout was completed successfully. Your subscription access is being prepared and will appear in your account shortly.', 'daymark-subscriptions' ) ); ?></p>
		<?php if ( $review_request ) : ?><p class="daymark-checkout-success__context"><?php echo esc_html__( 'After approval, we’ll email you a secure payment link and your login link.', 'daymark-subscriptions' ); ?></p><?php endif; ?>
		<?php if ( $development ) : ?><p class="daymark-checkout-success__context"><?php echo esc_html__( 'Development mode is active. This was a local test checkout and no payment provider was charged.', 'daymark-subscriptions' ); ?></p><?php endif; ?>
		<div class="daymark-checkout-success__actions"><a class="daymark-checkout-success__action" href="<?php echo esc_url( wp_login_url( $home_url ) ); ?>"><?php echo esc_html__( 'Log in to your account', 'daymark-subscriptions' ); ?></a><a class="daymark-checkout-success__link" href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html__( 'Return to website', 'daymark-subscriptions' ); ?></a></div>
	</section>
</main>
