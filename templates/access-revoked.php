<?php
defined( 'ABSPATH' ) || exit;
?><main class="daymark-access-revoked" style="<?php echo esc_attr( $design_style ); ?>--daymark-revoked-accent:<?php echo esc_attr( $brand_color ); ?>" aria-labelledby="daymark-access-revoked-title">
	<section class="daymark-access-revoked__card">
		<div class="daymark-access-revoked__mark" aria-hidden="true"><span>!</span></div>
		<p class="daymark-access-revoked__eyebrow"><?php echo esc_html__( 'Subscription access', 'daymark-subscriptions' ); ?></p>
		<h1 id="daymark-access-revoked-title"><?php echo esc_html__( 'Access revoked', 'daymark-subscriptions' ); ?></h1>
		<p class="daymark-access-revoked__message"><?php echo esc_html__( 'Your access to this content is no longer active. The plan or subscription status that granted access may have changed.', 'daymark-subscriptions' ); ?></p>
		<?php if ( $page_title ) : ?><p class="daymark-access-revoked__context"><?php echo esc_html( $page_title ); ?></p><?php endif; ?>
		<a class="daymark-access-revoked__action" href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html__( 'Return to website', 'daymark-subscriptions' ); ?></a>
	</section>
</main>
