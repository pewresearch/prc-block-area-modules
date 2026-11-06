<?php
/**
 * Block Area Modules ability category registration.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Block Area Modules ability category.
 */
class Ability_Categories {

	/**
	 * Ability category slug used by prc-block-area-modules/* abilities.
	 */
	public const CATEGORY = 'block-area-modules';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'wp_abilities_api_categories_init', $this, 'register_categories' );
	}

	/**
	 * Register the category.
	 *
	 * @hook wp_abilities_api_categories_init
	 */
	public function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Block Area Modules', 'prc-block-area-modules' ),
				'description' => __( 'Abilities that curate block area modules such as topic ledes.', 'prc-block-area-modules' ),
			)
		);
	}
}
