<?php
defined( 'ABSPATH' ) || exit;

$author = get_queried_object();
if ( ! $author instanceof WP_User ) {
	get_header();
	echo '<p>' . esc_html__( 'Profile not found.', 'daymark-subscriptions' ) . '</p>';
	get_footer();
	return;
}

$color = Daymark_Subscriptions_Admin::brand_color();
wp_add_inline_style( 'daymark-subscriptions-frontend', '.daymark-author-profile{' . Daymark_Subscriptions_Admin::frontend_css_vars( $color ) . '}' );

$visibility = get_user_meta( $author->ID, 'daymark_profile_visibility', true );
if ( 'private' === $visibility && get_current_user_id() !== (int) $author->ID ) {
	get_header();
	?><section class="daymark-author-profile" aria-labelledby="daymark-author-profile-private-title"><div class="daymark-author-profile-shell"><div class="daymark-author-profile-private"><span class="daymark-form-eyebrow"><?php echo esc_html__( 'Member profile', 'daymark-subscriptions' ); ?></span><h1 id="daymark-author-profile-private-title"><?php echo esc_html__( 'This profile is private', 'daymark-subscriptions' ); ?></h1><p><?php echo esc_html__( 'This member has chosen not to show their profile publicly.', 'daymark-subscriptions' ); ?></p></div></div></section><?php
	get_footer();
	return;
}

$picture_url = esc_url( get_user_meta( $author->ID, 'daymark_profile_picture_url', true ) );
$member_since = $author->user_registered ? wp_date( get_option( 'date_format' ), strtotime( $author->user_registered ) ) : '';
get_header();
?>
<section class="daymark-author-profile" aria-labelledby="daymark-author-profile-title">
	<div class="daymark-author-profile-shell">
		<header class="daymark-author-profile-header">
			<div class="daymark-author-profile-avatar"><?php echo $picture_url ? '<img src="' . $picture_url . '" alt="' . esc_attr( $author->display_name ) . '">' : get_avatar( $author->ID, 144, '', $author->display_name, array( 'class' => array( 'daymark-author-profile-avatar-image' ) ) ); ?></div>
			<div class="daymark-author-profile-identity">
				<span class="daymark-form-eyebrow"><?php echo esc_html__( 'Member profile', 'daymark-subscriptions' ); ?></span>
				<h1 id="daymark-author-profile-title"><?php echo esc_html( $author->display_name ); ?></h1>
				<p class="daymark-author-profile-handle">@<?php echo esc_html( $author->user_nicename ); ?></p>
			</div>
		</header>
		<div class="daymark-author-profile-content">
			<section class="daymark-author-profile-about" aria-labelledby="daymark-author-profile-about-title">
				<span class="daymark-author-profile-section-label"><?php echo esc_html__( 'About', 'daymark-subscriptions' ); ?></span>
				<h2 id="daymark-author-profile-about-title"><?php echo esc_html__( 'A little about this member', 'daymark-subscriptions' ); ?></h2>
				<?php if ( trim( $author->description ) ) : ?><div class="daymark-author-profile-description"><?php echo wp_kses_post( wpautop( $author->description ) ); ?></div><?php else : ?><p class="daymark-author-profile-empty"><?php echo esc_html__( 'This member has not added a profile description yet.', 'daymark-subscriptions' ); ?></p><?php endif; ?>
			</section>
			<aside class="daymark-author-profile-aside" aria-label="<?php echo esc_attr__( 'Profile details', 'daymark-subscriptions' ); ?>">
				<div><span><?php echo esc_html__( 'Member since', 'daymark-subscriptions' ); ?></span><strong><?php echo esc_html( $member_since ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Profile', 'daymark-subscriptions' ); ?></span><strong><?php echo esc_html__( 'Public member', 'daymark-subscriptions' ); ?></strong></div>
			</aside>
		</div>
	</div>
</section>
<?php get_footer();
