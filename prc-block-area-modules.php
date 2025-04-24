<?php
/**
 * PRC Block Area Modules
 *
 * @package           PRC_Block_Area_Modules
 * @author            Seth Rubenstein
 * @copyright         2024 Pew Research Center
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       PRC Block Area Modules
 * Plugin URI:        https://github.com/pewresearch/prc-block-area-modules
 * Description:       Block Area Modules provides an editorially curated method for adding content to areas of a block theme.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 * Author URI:        https://pewresearch.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       prc-block-area-modules
 * Requires Plugins:  prc-platform-core
 */

namespace PRC\Platform\Block_Area_Modules;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PRC_BLOCK_AREA_MODULES_FILE', __FILE__ );
define( 'PRC_BLOCK_AREA_MODULES_DIR', __DIR__ );
define( 'PRC_BLOCK_AREA_MODULES_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-prc-x-x-x-activator.php
 */
function activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-activator.php';
	Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-prc-x-x-x-deactivator.php
 */
function deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-deactivator.php';
	Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, '\PRC\Platform\Block_Area_Modules\activate' );
register_deactivation_hook( __FILE__, '\PRC\Platform\Block_Area_Modules\deactivate' );

/**
 * Helper utilities
 */
require plugin_dir_path( __FILE__ ) . 'includes/utils.php';

/**
 * The core plugin class that is used to define the hooks that initialize the various components.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_prc_block_area_modules() {
	$plugin = new Plugin();
	$plugin->run();
}
run_prc_block_area_modules();
