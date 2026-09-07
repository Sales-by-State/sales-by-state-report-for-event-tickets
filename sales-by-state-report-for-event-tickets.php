<?php
/**
 * Plugin Name:          Sales by State Report for Event Tickets
 * Plugin URI:           https://salesbystate.com/
 * Description:          See a yearly breakdown of Event Tickets sales by state / county / province for a given country, filterable by order status.
 * Version:              1.0.0
 * Author:               Rodolfo Melogli
 * Author URI:           https://www.businessbloomer.com/
 * Developer:            Rodolfo Melogli
 * Developer URI:        https://www.businessbloomer.com/
 * Text Domain:          sales-by-state-report-for-event-tickets
 * Domain Path:          /languages
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     event-tickets
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SalesByStateReportForEventTickets
 * @copyright 2026 Rodolfo Melogli
 */

defined( 'ABSPATH' ) || exit;

define( 'SBSET_VERSION', '1.0.0' );
define( 'SBSET_FILE', __FILE__ );
define( 'SBSET_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBSET_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'SBSET\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SBSET_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'Tribe__Tickets__Main' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Sales by State Report for Event Tickets requires Event Tickets to be installed and active.', 'sales-by-state-report-for-event-tickets' )
					);
				}
			);

			return;
		}

		SBSET\Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	SBSET_FILE,
	function () {
		require_once SBSET_DIR . 'src/Install/Schema.php';
		SBSET\Install\Schema::install();
	}
);

register_deactivation_hook(
	SBSET_FILE,
	function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sbset_backfill_batch', array(), 'sales-by-state-report-for-event-tickets' );
		}
	}
);
