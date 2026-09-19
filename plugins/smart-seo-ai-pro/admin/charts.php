<?php
/**
 * Charts & Visualization Helper Class
 *
 * @package Smart_SEO_AI_Suite_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Smart_SEO_AI_Charts {

	/**
	 * Render an SVG Donut Gauge Chart
	 *
	 * @param int $score 0-100
	 * @param string $label
	 * @param int $size
	 * @return string SVG HTML
	 */
	public static function render_donut_gauge( $score, $label = '', $size = 140 ) {
		$score = max( 0, min( 100, intval( $score ) ) );
		$radius = 50;
		$circumference = 2 * M_PI * $radius;
		$offset = $circumference - ( ( $score / 100 ) * $circumference );

		$color = '#10b981'; // Green
		if ( $score < 50 ) {
			$color = '#ef4444'; // Red
		} elseif ( $score < 80 ) {
			$color = '#f59e0b'; // Amber
		}

		ob_start();
		?>
		<div class="smart-donut-chart" style="width: <?php echo esc_attr( $size ); ?>px; height: <?php echo esc_attr( $size ); ?>px;">
			<svg width="<?php echo esc_attr( $size ); ?>" height="<?php echo esc_attr( $size ); ?>" viewBox="0 0 120 120">
				<circle cx="60" cy="60" r="<?php echo esc_attr( $radius ); ?>" fill="none" stroke="#e2e8f0" stroke-width="10" />
				<circle cx="60" cy="60" r="<?php echo esc_attr( $radius ); ?>" fill="none" stroke="<?php echo esc_attr( $color ); ?>" stroke-width="10"
					stroke-dasharray="<?php echo esc_attr( $circumference ); ?>"
					stroke-dashoffset="<?php echo esc_attr( $offset ); ?>"
					stroke-linecap="round"
					transform="rotate(-90 60 60)" />
				<text x="60" y="58" text-anchor="middle" font-size="24" font-weight="bold" fill="#1e293b"><?php echo esc_html( $score ); ?></text>
				<text x="60" y="75" text-anchor="middle" font-size="11" fill="#64748b"><?php echo esc_html( $label ?: '/100' ); ?></text>
			</svg>
		</div>
		<?php
		return ob_get_clean();
	}
}
