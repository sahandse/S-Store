<?php
/**
 * Plugin Name: دکمه پشتیبانی
 * Plugin URI:  https://github.com/sahandse/support-widget
 * Description: دکمه شناور پشتیبانی با انیمیشن، فونت وزیر، بارگذاری لوگو، ساعت کاری، آمار کلیک
 * Version: 4.1.1
 * Author:      سهند رضوان
 * License:     GPL v2 or later
 * Text Domain: support-button
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SUPPORT_BTN_VERSION', '4.1.0' );
define( 'SUPPORT_BTN_URL',     plugin_dir_url( __FILE__ ) );

/* ════════════════════════════════════════════════════════════════
   داده‌های پایه
════════════════════════════════════════════════════════════════ */

function support_btn_presets() {
	return [
		'telegram'  => [ 'label' => 'تلگرام',     'color' => '#229ED9' ],
		'whatsapp'  => [ 'label' => 'واتس‌اپ',    'color' => '#25D366' ],
		'bale'      => [ 'label' => 'بله',         'color' => '#2196F3' ],
		'rubika'    => [ 'label' => 'روبیکا',      'color' => '#F47B20' ],
		'eitaa'     => [ 'label' => 'ایتا',        'color' => '#259B87' ],
		'instagram' => [ 'label' => 'اینستاگرام',  'color' => '#E1306C' ],
		'linkedin'  => [ 'label' => 'لینکدین',     'color' => '#0077B5' ],
		'twitter'   => [ 'label' => 'توییتر / X',  'color' => '#14171A' ],
		'custom'    => [ 'label' => 'سایر...',     'color' => '#607D8B' ],
	];
}

function support_btn_get_channels() {
	$v = get_option( 'support_btn_channels', [] );
	return is_array( $v ) ? $v : [];
}

function support_btn_get_settings() {
	return wp_parse_args( get_option( 'support_btn_settings', [] ), [
		'position'       => 'right',
		'exclude_ids'    => '',
		'schedule_on'    => '0',
		'schedule_start' => '09:00',
		'schedule_end'   => '18:00',
		'schedule_days'  => [ '6', '0', '1', '2', '3' ],
		'offline_msg'    => 'در حال حاضر آفلاین هستیم',
		'bubble_on'      => '0',
		'bubble_text'    => 'سوالی دارید؟ اینجاییم!',
		'bubble_delay'   => '3',
		'btn_logo_id'    => '',
		'animation'      => 'pulse',
	] );
}

function support_btn_get_stats() {
	return (array) get_option( 'support_btn_stats', [] );
}

function support_btn_has_any_link() {
	foreach ( support_btn_get_channels() as $ch ) {
		if ( ! empty( $ch['url'] ) ) return true;
	}
	return false;
}

function support_btn_should_show() {
	$s = support_btn_get_settings();
	if ( empty( $s['exclude_ids'] ) ) return true;
	$ids = array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $s['exclude_ids'] ) ) );
	return ! in_array( (int) get_the_ID(), $ids, true );
}

/* ════════════════════════════════════════════════════════════════
   پنل مدیریت
════════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'support_btn_admin_menu' );
function support_btn_admin_menu() {
	if ( function_exists( 's_store_register_submenu' ) ) {
		s_store_register_submenu( 'support-button', 'دکمه پشتیبانی', 'support_btn_settings_page', 'manage_options', 'دکمه پشتیبانی' );
		return;
	}
	add_options_page( 'دکمه پشتیبانی', 'دکمه پشتیبانی', 'manage_options', 'support-button', 'support_btn_settings_page' );
}

add_action( 'admin_enqueue_scripts', 'support_btn_admin_enqueue' );
function support_btn_admin_enqueue( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'support-button' !== $page && 'settings_page_support-button' !== $hook ) return;
	wp_enqueue_media();
	wp_enqueue_style(  'support-btn-admin', SUPPORT_BTN_URL . 'assets/admin/admin.css', [], SUPPORT_BTN_VERSION );
	wp_enqueue_script( 'support-btn-admin', SUPPORT_BTN_URL . 'assets/admin/admin.js', [ 'jquery' ], SUPPORT_BTN_VERSION, true );

	$channels = support_btn_get_channels();
	$settings = support_btn_get_settings();
	wp_localize_script( 'support-btn-admin', 'spbAdmin', [
		'nextIdx'   => (int) count( $channels ),
		'activeTab' => sanitize_key( $_GET['spb-tab'] ?? 'channels' ),
		'logoUrl'   => $settings['btn_logo_id']
			? wp_get_attachment_image_url( (int) $settings['btn_logo_id'], 'thumbnail' )
			: '',
	] );
}

add_action( 'admin_init', 'support_btn_register_settings' );
function support_btn_register_settings() {
	register_setting( 'support_btn_options', 'support_btn_channels', [ 'sanitize_callback' => 'support_btn_sanitize_channels' ] );
	register_setting( 'support_btn_options', 'support_btn_settings', [ 'sanitize_callback' => 'support_btn_sanitize_settings' ] );
}

function support_btn_sanitize_channels( $input ) {
	if ( ! is_array( $input ) ) return [];
	$presets = support_btn_presets();
	$clean   = [];
	foreach ( $input as $row ) {
		if ( empty( $row['url'] ) ) continue;
		$net     = array_key_exists( $row['network'] ?? '', $presets ) ? $row['network'] : 'custom';
		$clean[] = [
			'network'     => $net,
			'custom_name' => sanitize_text_field( $row['custom_name'] ?? '' ),
			'operator'    => sanitize_text_field( $row['operator'] ?? '' ),
			'url'         => esc_url_raw( $row['url'] ),
			'color'       => sanitize_hex_color( $row['color'] ?? '' ) ?: $presets[ $net ]['color'],
		];
	}
	return $clean;
}

function support_btn_sanitize_settings( $input ) {
	if ( ! is_array( $input ) ) return [];
	$valid_anims = [ 'none', 'pulse', 'bounce', 'attention' ];
	return [
		'position'       => in_array( $input['position'] ?? '', [ 'right', 'left' ], true ) ? $input['position'] : 'right',
		'exclude_ids'    => sanitize_text_field( $input['exclude_ids'] ?? '' ),
		'schedule_on'    => empty( $input['schedule_on'] )   ? '0' : '1',
		'schedule_start' => preg_match( '/^\d{2}:\d{2}$/', $input['schedule_start'] ?? '' ) ? $input['schedule_start'] : '09:00',
		'schedule_end'   => preg_match( '/^\d{2}:\d{2}$/', $input['schedule_end']   ?? '' ) ? $input['schedule_end']   : '18:00',
		'schedule_days'  => array_values( array_filter(
			array_map( 'intval', (array) ( $input['schedule_days'] ?? [] ) ),
			function ( $d ) { return $d >= 0 && $d <= 6; }
		) ),
		'offline_msg'    => sanitize_text_field( $input['offline_msg'] ?? '' ),
		'bubble_on'      => empty( $input['bubble_on'] ) ? '0' : '1',
		'bubble_text'    => sanitize_text_field( $input['bubble_text'] ?? '' ),
		'bubble_delay'   => max( 0, min( 60, (int) ( $input['bubble_delay'] ?? 3 ) ) ),
		'btn_logo_id'    => (int) ( $input['btn_logo_id'] ?? 0 ) ?: '',
		'animation'      => in_array( $input['animation'] ?? '', $valid_anims, true ) ? $input['animation'] : 'pulse',
	];
}

add_action( 'admin_post_support_btn_reset_stats', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );
	check_admin_referer( 'support_btn_reset_stats' );
	delete_option( 'support_btn_stats' );
	$base_url = function_exists( 's_store_register_submenu' ) ? admin_url( 'admin.php' ) : admin_url( 'options-general.php' );
	wp_safe_redirect( add_query_arg( [ 'page' => 'support-button', 'spb-tab' => 'stats', 'reset' => '1' ], $base_url ) );
	exit;
} );

/* ════════════════════════════════════════════════════════════════
   صفحه تنظیمات
════════════════════════════════════════════════════════════════ */

function support_btn_settings_page() {
	$channels    = support_btn_get_channels();
	$settings    = support_btn_get_settings();
	$stats       = support_btn_get_stats();
	$presets     = support_btn_presets();
	$days_fa     = [ 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ];
	$active_tab  = sanitize_key( $_GET['spb-tab'] ?? 'channels' );
	$logo_url    = $settings['btn_logo_id']
		? wp_get_attachment_image_url( (int) $settings['btn_logo_id'], 'thumbnail' )
		: '';
	$total_clicks = $stats ? array_sum( array_column( $stats, 'count' ) ) : 0;
	$anims = [
		'none'      => 'بدون',
		'pulse'     => 'نبض',
		'bounce'    => 'جهش',
		'attention' => 'لرزش',
	];
	?>
	<div class="wrap spb-wrap" dir="rtl">

		<div class="spb-header">
			<h1>دکمه پشتیبانی شناور</h1>
			<span class="spb-ver">v<?php echo SUPPORT_BTN_VERSION; ?></span>
		</div>

		<nav class="nav-tab-wrapper spb-nav" style="margin-bottom:0">
			<a href="#channels" class="nav-tab">
				کانال‌ها
				<span class="spb-badge"><?php echo count( $channels ); ?></span>
			</a>
			<a href="#settings" class="nav-tab">تنظیمات</a>
			<a href="#stats"    class="nav-tab">
				آمار کلیک
				<?php if ( $total_clicks ) : ?>
				<span class="spb-badge spb-badge-blue"><?php echo $total_clicks; ?></span>
				<?php endif; ?>
			</a>
		</nav>

		<div class="spb-panel">

		<form method="post" action="options.php" id="spb-main-form">
		<?php settings_fields( 'support_btn_options' ); ?>

		<!-- ▸ Tab: کانال‌ها ──────────────────────────────────────── -->
		<div class="spb-tab" id="spb-tab-channels" style="display:none">
			<p class="spb-hint">هر ردیف یک دکمه می‌سازد. برای مرتب‌سازی بکشید.</p>

			<table class="wp-list-table widefat fixed striped spb-ch-table" id="spb-table">
				<thead>
					<tr>
						<th class="col-hnd"></th>
						<th class="col-net" style="text-align:right">شبکه</th>
						<th style="text-align:right">اسم اپراتور</th>
						<th style="text-align:right">لینک</th>
						<th class="col-clr" style="text-align:center">رنگ</th>
						<th class="col-del"></th>
					</tr>
				</thead>
				<tbody id="spb-rows">
				<?php foreach ( $channels as $i => $ch ) :
					$is_custom = ( $ch['network'] === 'custom' ); ?>
					<tr class="spb-row" draggable="true">
						<td class="col-hnd spb-handle">⠿</td>
						<td class="col-net spb-td">
							<select name="support_btn_channels[<?php echo $i; ?>][network]" class="spb-net-sel">
								<?php foreach ( $presets as $key => $p ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" data-color="<?php echo esc_attr( $p['color'] ); ?>" <?php selected( $ch['network'], $key ); ?>><?php echo esc_html( $p['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="support_btn_channels[<?php echo $i; ?>][custom_name]" class="spb-custom-name" placeholder="نام شبکه..." value="<?php echo esc_attr( $ch['custom_name'] ?? '' ); ?>" <?php echo $is_custom ? '' : 'style="display:none"'; ?> />
						</td>
						<td class="spb-td">
							<input type="text" name="support_btn_channels[<?php echo $i; ?>][operator]" value="<?php echo esc_attr( $ch['operator'] ?? '' ); ?>" placeholder="پشتیبانی فروش" />
						</td>
						<td class="spb-td">
							<input type="url" name="support_btn_channels[<?php echo $i; ?>][url]" value="<?php echo esc_url( $ch['url'] ); ?>" placeholder="https://..." />
						</td>
						<td class="col-clr spb-td" style="text-align:center">
							<input type="color" name="support_btn_channels[<?php echo $i; ?>][color]" value="<?php echo esc_attr( $ch['color'] ); ?>" class="spb-color-inp" />
						</td>
						<td class="col-del" style="text-align:center">
							<button type="button" class="button spb-remove" title="حذف">✕</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="spb-row-footer">
				<button type="button" id="spb-add" class="button button-secondary">+ افزودن کانال</button>
			</div>

			<template id="spb-row-tpl">
				<tr class="spb-row" draggable="true">
					<td class="col-hnd spb-handle">⠿</td>
					<td class="col-net spb-td">
						<select class="spb-net-sel">
							<?php foreach ( $presets as $key => $p ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" data-color="<?php echo esc_attr( $p['color'] ); ?>"><?php echo esc_html( $p['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" class="spb-custom-name" placeholder="نام شبکه..." style="display:none" />
					</td>
					<td class="spb-td"><input type="text"  placeholder="پشتیبانی فروش" /></td>
					<td class="spb-td"><input type="url"   placeholder="https://..." /></td>
					<td class="col-clr spb-td" style="text-align:center">
						<input type="color" value="#0088CC" class="spb-color-inp" />
					</td>
					<td class="col-del" style="text-align:center">
						<button type="button" class="button spb-remove" title="حذف">✕</button>
					</td>
				</tr>
			</template>
		</div>

		<!-- ▸ Tab: تنظیمات ──────────────────────────────────────── -->
		<div class="spb-tab" id="spb-tab-settings" style="display:none">

			<!-- لوگو -->
			<div class="spb-section">
				<h3 class="spb-sec-title">لوگوی دکمه اصلی</h3>
				<div class="spb-logo-area">
					<div id="spb-logo-preview" class="spb-logo-preview">
						<?php if ( $logo_url ) : ?><img src="<?php echo esc_url( $logo_url ); ?>" alt="" /><?php endif; ?>
					</div>
					<input type="hidden" name="support_btn_settings[btn_logo_id]" id="spb-logo-id" value="<?php echo esc_attr( $settings['btn_logo_id'] ); ?>" />
					<div class="spb-logo-btns">
						<button type="button" id="spb-logo-upload" class="button">انتخاب تصویر</button>
						<button type="button" id="spb-logo-remove" class="button button-link-delete" <?php echo $logo_url ? '' : 'style="display:none"'; ?>>حذف</button>
					</div>
					<p class="description">تصویر مربع PNG با پس‌زمینه شفاف (۶۰×۶۰ px توصیه می‌شود)</p>
				</div>
			</div>

			<!-- انیمیشن -->
			<div class="spb-section">
				<h3 class="spb-sec-title">انیمیشن دکمه</h3>
				<div class="spb-anim-row">
					<?php foreach ( $anims as $key => $label ) : ?>
					<label class="spb-anim-opt <?php echo $settings['animation'] === $key ? 'is-active' : ''; ?>">
						<input type="radio" name="support_btn_settings[animation]" value="<?php echo $key; ?>" <?php checked( $settings['animation'], $key ); ?> />
						<span class="spb-anim-demo spb-anim-<?php echo $key; ?>"></span>
						<span><?php echo $label; ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- موقعیت -->
			<div class="spb-section">
				<h3 class="spb-sec-title">موقعیت دکمه</h3>
				<div class="spb-pos-row">
					<label class="spb-pos-opt <?php echo $settings['position'] === 'right' ? 'is-active' : ''; ?>">
						<input type="radio" name="support_btn_settings[position]" value="right" <?php checked( $settings['position'], 'right' ); ?> />
						<span>↙ پایین-راست</span>
					</label>
					<label class="spb-pos-opt <?php echo $settings['position'] === 'left' ? 'is-active' : ''; ?>">
						<input type="radio" name="support_btn_settings[position]" value="left" <?php checked( $settings['position'], 'left' ); ?> />
						<span>↘ پایین-چپ</span>
					</label>
				</div>
			</div>

			<!-- مخفی در صفحات -->
			<div class="spb-section">
				<h3 class="spb-sec-title">مخفی در صفحات خاص</h3>
				<input type="text" name="support_btn_settings[exclude_ids]" value="<?php echo esc_attr( $settings['exclude_ids'] ); ?>" placeholder="مثلاً: 5, 12, 34" class="regular-text" />
				<p class="description">شناسه (ID) صفحاتی که دکمه نمایش داده نمی‌شود، با ویرگول جدا کنید.</p>
			</div>

			<!-- ساعت کاری -->
			<div class="spb-section">
				<h3 class="spb-sec-title">ساعت کاری</h3>
				<label class="spb-toggle-row">
					<input type="checkbox" name="support_btn_settings[schedule_on]" value="1" <?php checked( $settings['schedule_on'], '1' ); ?> id="spb-sch-on" />
					<span class="spb-sw"></span>
					<span>فعال‌سازی ساعت کاری</span>
				</label>
				<div id="spb-sch-box" class="spb-sub" <?php echo $settings['schedule_on'] === '1' ? '' : 'style="display:none"'; ?>>
					<div class="spb-time-row">
						<label>از: <input type="time" name="support_btn_settings[schedule_start]" value="<?php echo esc_attr( $settings['schedule_start'] ); ?>" /></label>
						<label>تا: <input type="time" name="support_btn_settings[schedule_end]"   value="<?php echo esc_attr( $settings['schedule_end'] ); ?>" /></label>
					</div>
					<div class="spb-days">
						<?php foreach ( $days_fa as $num => $name ) : ?>
						<label class="spb-day">
							<input type="checkbox" name="support_btn_settings[schedule_days][]" value="<?php echo $num; ?>"
								<?php checked( in_array( (string) $num, array_map( 'strval', (array) $settings['schedule_days'] ), true ) ); ?> />
							<?php echo $name; ?>
						</label>
						<?php endforeach; ?>
					</div>
					<div class="spb-field">
						<label>پیام آفلاین</label>
						<input type="text" name="support_btn_settings[offline_msg]" value="<?php echo esc_attr( $settings['offline_msg'] ); ?>" placeholder="در حال حاضر آفلاین هستیم" class="regular-text" />
						<p class="description">خالی = پنهان شدن کامل دکمه</p>
					</div>
				</div>
			</div>

			<!-- پیام خوش‌آمد -->
			<div class="spb-section">
				<h3 class="spb-sec-title">پیام خوش‌آمد</h3>
				<label class="spb-toggle-row">
					<input type="checkbox" name="support_btn_settings[bubble_on]" value="1" <?php checked( $settings['bubble_on'], '1' ); ?> id="spb-bub-on" />
					<span class="spb-sw"></span>
					<span>نمایش حباب پیام</span>
				</label>
				<div id="spb-bub-box" class="spb-sub" <?php echo $settings['bubble_on'] === '1' ? '' : 'style="display:none"'; ?>>
					<div class="spb-field">
						<label>متن پیام</label>
						<input type="text" name="support_btn_settings[bubble_text]" value="<?php echo esc_attr( $settings['bubble_text'] ); ?>" placeholder="سوالی دارید؟ اینجاییم!" class="regular-text" />
					</div>
					<div class="spb-field">
						<label>تأخیر نمایش</label>
						<input type="number" name="support_btn_settings[bubble_delay]" value="<?php echo esc_attr( $settings['bubble_delay'] ); ?>" min="0" max="60" style="width:72px" />
						<span class="description" style="display:inline"> ثانیه</span>
					</div>
				</div>
			</div>

		</div>

		<?php submit_button( 'ذخیره تنظیمات', 'primary spb-save' ); ?>
		</form>

		<!-- ▸ Tab: آمار ─────────────────────────────────────────── -->
		<div class="spb-tab" id="spb-tab-stats" style="display:none">
			<?php if ( isset( $_GET['reset'] ) ) : ?>
			<div class="notice notice-success inline" style="margin-top:0"><p>آمار پاک شد.</p></div>
			<?php endif; ?>
			<?php
			uasort( $stats, function ( $a, $b ) { return $b['count'] - $a['count']; } );
			if ( empty( $stats ) ) : ?>
			<p class="spb-hint">هنوز هیچ کلیکی ثبت نشده است.</p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped spb-stats-tbl">
				<thead>
					<tr>
						<th style="text-align:right">کانال</th>
						<th style="width:110px;text-align:center">کلیک</th>
						<th style="width:155px;text-align:center">آخرین کلیک</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stats as $row ) : ?>
					<tr>
						<td style="text-align:right">
							<?php echo esc_html( $row['label'] ); ?>
							<br /><small class="spb-url-sm"><?php echo esc_html( $row['url'] ); ?></small>
						</td>
						<td style="text-align:center"><strong class="spb-cnt"><?php echo (int) $row['count']; ?></strong></td>
						<td style="text-align:center;color:#777"><?php echo $row['last'] ? esc_html( wp_date( 'Y/m/d H:i', $row['last'] ) ) : '—'; ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th style="text-align:right">مجموع</th>
						<th style="text-align:center"><strong class="spb-cnt"><?php echo array_sum( array_column( $stats, 'count' ) ); ?></strong></th>
						<th></th>
					</tr>
				</tfoot>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spb-reset-form" onsubmit="return confirm('آمار پاک شود؟')">
				<input type="hidden" name="action" value="support_btn_reset_stats" />
				<?php wp_nonce_field( 'support_btn_reset_stats' ); ?>
				<?php submit_button( 'پاک کردن آمار', 'delete small', '', false ); ?>
			</form>
			<?php endif; ?>
		</div>

		</div><!-- /spb-panel -->
	</div>
	<?php
}

/* ════════════════════════════════════════════════════════════════
   آمار کلیک — AJAX
════════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_support_btn_track',        'support_btn_track_click' );
add_action( 'wp_ajax_nopriv_support_btn_track', 'support_btn_track_click' );
function support_btn_track_click() {
	check_ajax_referer( 'support_btn_track', 'nonce' );
	$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
	if ( ! $url ) wp_die( '', '', [ 'response' => 204 ] );
	$key   = md5( $url );
	$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? $url ) );
	$stats = get_option( 'support_btn_stats', [] );
	if ( ! isset( $stats[ $key ] ) ) {
		$stats[ $key ] = [ 'label' => $label, 'url' => $url, 'count' => 0, 'last' => 0 ];
	}
	$stats[ $key ]['count']++;
	$stats[ $key ]['last']  = time();
	$stats[ $key ]['label'] = $label;
	update_option( 'support_btn_stats', $stats, false );
	wp_die( '', '', [ 'response' => 204 ] );
}

/* ════════════════════════════════════════════════════════════════
   فرانت‌اند
════════════════════════════════════════════════════════════════ */

add_action( 'wp_enqueue_scripts', 'support_btn_enqueue' );
function support_btn_enqueue() {
	if ( ! support_btn_has_any_link() ) return;
	$s = support_btn_get_settings();
	wp_enqueue_style(  'support-button', SUPPORT_BTN_URL . 'assets/css/support-button.css', [], SUPPORT_BTN_VERSION );
	wp_enqueue_script( 'support-button', SUPPORT_BTN_URL . 'assets/js/support-button.js',  [], SUPPORT_BTN_VERSION, true );
	wp_localize_script( 'support-button', 'spbConfig', [
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'support_btn_track' ),
		'tzOffset' => (float) get_option( 'gmt_offset' ) * 60,
		'schedule' => $s['schedule_on'] === '1' ? [
			'start'      => $s['schedule_start'],
			'end'        => $s['schedule_end'],
			'days'       => array_values( array_map( 'intval', (array) $s['schedule_days'] ) ),
			'offlineMsg' => $s['offline_msg'],
		] : null,
		'bubble' => $s['bubble_on'] === '1' && $s['bubble_text'] ? [
			'text'  => $s['bubble_text'],
			'delay' => (int) $s['bubble_delay'],
		] : null,
	] );
}

add_action( 'wp_footer', 'support_btn_output' );
function support_btn_output() {
	if ( ! support_btn_has_any_link() || ! support_btn_should_show() ) return;
	$channels = support_btn_get_channels();
	$settings = support_btn_get_settings();
	$presets  = support_btn_presets();
	$has = false;
	foreach ( $channels as $ch ) { if ( ! empty( $ch['url'] ) ) { $has = true; break; } }
	if ( ! $has ) return;

	$logo_url = $settings['btn_logo_id']
		? wp_get_attachment_image_url( (int) $settings['btn_logo_id'], [ 60, 60 ] )
		: '';
	$anim_cls = $settings['animation'] !== 'none' ? 'spb-anim-' . $settings['animation'] : '';
	?>
	<div id="spb-widget" class="spb-pos-<?php echo esc_attr( $settings['position'] ); ?>" role="complementary" aria-label="پشتیبانی">

		<?php if ( $settings['bubble_on'] === '1' && $settings['bubble_text'] ) : ?>
		<div id="spb-bubble" class="spb-bubble" role="status" aria-live="polite">
			<button class="spb-bubble-x" aria-label="بستن">✕</button>
			<?php echo esc_html( $settings['bubble_text'] ); ?>
		</div>
		<?php endif; ?>

		<div id="spb-menu" aria-hidden="true">
			<?php foreach ( $channels as $ch ) :
				if ( empty( $ch['url'] ) ) continue;
				$net      = $ch['network'] ?? 'custom';
				$net_name = ( $net === 'custom' && ! empty( $ch['custom_name'] ) )
					? $ch['custom_name']
					: ( $presets[ $net ]['label'] ?? $net );
				$operator = trim( $ch['operator'] ?? '' );
				$color    = $ch['color'] ?? ( $presets[ $net ]['color'] ?? '#607D8B' );
				$title    = $operator ? "$operator — $net_name" : $net_name;
			?>
			<a href="<?php echo esc_url( $ch['url'] ); ?>"
			   target="_blank" rel="noopener noreferrer"
			   class="spb-item"
			   style="--c:<?php echo esc_attr( $color ); ?>;background:<?php echo esc_attr( $color ); ?>"
			   data-label="<?php echo esc_attr( $title ); ?>"
			   title="<?php echo esc_attr( $title ); ?>">
				<span class="spb-icon" aria-hidden="true"><?php echo support_btn_get_icon( $net ); ?></span>
				<span class="spb-label-wrap">
					<span class="spb-op"><?php echo esc_html( $operator ?: $net_name ); ?></span>
					<?php if ( $operator ) : ?><span class="spb-nw"><?php echo esc_html( $net_name ); ?></span><?php endif; ?>
				</span>
			</a>
			<?php endforeach; ?>
		</div>

		<?php if ( $settings['schedule_on'] === '1' && $settings['offline_msg'] ) : ?>
		<div id="spb-offline" class="spb-offline" aria-hidden="true">
			<?php echo esc_html( $settings['offline_msg'] ); ?>
		</div>
		<?php endif; ?>

		<button id="spb-toggle" class="<?php echo esc_attr( $anim_cls ); ?>" aria-label="باز کردن منوی پشتیبانی" aria-expanded="false">
			<span class="spb-ico-open" aria-hidden="true">
				<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="spb-logo" />
				<?php else : echo support_btn_svg_chat(); endif; ?>
			</span>
			<span class="spb-ico-close" aria-hidden="true">&#x2715;</span>
		</button>
	</div>
	<?php
}

/* ════════════════════════════════════════════════════════════════
   آیکون‌های SVG
════════════════════════════════════════════════════════════════ */

function support_btn_get_icon( $net ) {
	$map = [
		'telegram'  => 'support_btn_svg_telegram',
		'whatsapp'  => 'support_btn_svg_whatsapp',
		'bale'      => 'support_btn_svg_bale',
		'rubika'    => 'support_btn_svg_rubika',
		'eitaa'     => 'support_btn_svg_eitaa',
		'instagram' => 'support_btn_svg_instagram',
		'linkedin'  => 'support_btn_svg_linkedin',
		'twitter'   => 'support_btn_svg_twitter',
	];
	return isset( $map[ $net ] ) ? call_user_func( $map[ $net ] ) : support_btn_svg_link();
}

/* آیکون اصلی دکمه */
function support_btn_svg_chat() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2zm4 0h-2V9h2zm4 0h-2V9h2z"/></svg>';
}

/* تلگرام — لوگوی رسمی */
function support_btn_svg_telegram() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>';
}

/* واتس‌اپ — لوگوی رسمی */
function support_btn_svg_whatsapp() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>';
}

/* بله — آیکون چت با سه نقطه */
function support_btn_svg_bale() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M20 2H4C2.9 2 2 2.9 2 4v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/></svg>';
}

/* روبیکا — دو حباب گفتگو */
function support_btn_svg_rubika() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14l4-4h9c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 10H10.83L8 17.83V7h11v8z"/></svg>';
}

/* ایتا — آیکون اختصاصی با خطوط */
function support_btn_svg_eitaa() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-6 12h-4v-2h4v2zm2-4H8V8h8v2z"/></svg>';
}

/* اینستاگرام — لوگوی رسمی */
function support_btn_svg_instagram() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>';
}

/* لینکدین — لوگوی رسمی */
function support_btn_svg_linkedin() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
}

/* توییتر/X — لوگوی رسمی X */
function support_btn_svg_twitter() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
}

/* آیکون عمومی برای شبکه سفارشی */
function support_btn_svg_link() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M8 13h8v-2H8zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1S18.71 15.1 17 15.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5zM7 11c0-1.71 1.39-3.1 3.1-3.1H14V6h-3.9C7.24 6 5 8.24 5 11s2.24 5 5.1 5H14v-1.9h-3.9C8.39 14.1 7 12.71 7 11z"/></svg>';
}
