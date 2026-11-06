<?php
/**
 * Topic lede module writer.
 *
 * Creates the new block module from the current layout and the picks.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

use WP_Error;
use WP_Term;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes curated picks into a new topic lede block module.
 */
class Topic_Lede_Writer {

	/**
	 * Block area the curator writes to.
	 *
	 * @var string
	 */
	public const BLOCK_AREA = 'topic-lede';

	/**
	 * Audit meta key on modules the curator creates.
	 *
	 * @var string
	 */
	public const AUDIT_META = '_prc_auto_curated';

	/**
	 * Create the new topic lede module.
	 *
	 * The current module's layout is cloned and only its stories change. The
	 * old module stays published, so trashing the new one rolls back.
	 *
	 * @param WP_Term $term      Term.
	 * @param array   $picks     Hydrated picks.
	 * @param string  $mode      draft or publish.
	 * @param WP_User $actor     Editor credited with the module.
	 * @param array   $headlines Headline summaries for the audit meta.
	 * @return array|WP_Error Module summary.
	 */
	public static function write_module( WP_Term $term, array $picks, string $mode, WP_User $actor, array $headlines ) {
		if ( array() === $picks ) {
			return new WP_Error( 'prc_topic_lede_no_picks', __( 'There are no stories to place.', 'prc-block-area-modules' ), array( 'status' => 400 ) );
		}

		$current_id = self::current_module_id( $term );
		$blocks     = array();
		if ( $current_id ) {
			$blocks = parse_blocks( (string) get_post_field( 'post_content', $current_id ) );
		}
		if ( Topic_Lede_Layout::count_story_items( $blocks ) < 1 ) {
			$blocks = parse_blocks( Topic_Lede_Layout::default_markup( count( $picks ) ) );
		}

		$swapped = Topic_Lede_Layout::swap_story_items(
			$blocks,
			array_map(
				static function ( array $pick ): array {
					return array(
						'post_id'   => (int) $pick['post_id'],
						'post_type' => (string) $pick['post_type'],
					);
				},
				$picks
			)
		);

		$term_name = html_entity_decode( $term->name, ENT_QUOTES );
		$module_id = wp_insert_post(
			array(
				'post_type'    => Content_Type::$post_type,
				'post_status'  => 'draft',
				'post_author'  => $actor->ID,
				'post_title'   => sprintf( 'Topic Lede: %s (auto, %s)', $term_name, wp_date( 'M j, Y' ) ),
				'post_content' => wp_slash( serialize_blocks( $swapped['blocks'] ) ),
			),
			true
		);
		if ( is_wp_error( $module_id ) ) {
			return $module_id;
		}

		wp_set_object_terms( $module_id, self::BLOCK_AREA, Content_Type::$taxonomy );
		wp_set_object_terms( $module_id, array( (int) $term->term_id ), $term->taxonomy );

		update_post_meta(
			$module_id,
			self::AUDIT_META,
			array(
				'requested_by'      => (int) $actor->ID,
				'requested_at'      => gmdate( 'c' ),
				'replaces_module'   => $current_id ? (int) $current_id : 0,
				'headlines'         => $headlines,
				'picks'             => array_map(
					static function ( array $pick ): array {
						return array(
							'post_id' => (int) $pick['post_id'],
							'reason'  => (string) $pick['reason'],
						);
					},
					$picks
				),
				'stories_in_layout' => (int) $swapped['replaced'],
			)
		);

		if ( 'publish' === $mode ) {
			$updated = wp_update_post(
				array(
					'ID'          => $module_id,
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		return array(
			'id'                => (int) $module_id,
			'status'            => get_post_status( $module_id ),
			'edit_url'          => admin_url( 'post.php?post=' . (int) $module_id . '&action=edit' ),
			'archive_url'       => (string) get_term_link( $term ),
			'replaces_module'   => $current_id ? (int) $current_id : 0,
			'stories_in_layout' => (int) $swapped['replaced'],
		);
	}

	/**
	 * Current published topic lede module for a term.
	 *
	 * @param WP_Term $term Term.
	 * @return int Module ID, or 0.
	 */
	private static function current_module_id( WP_Term $term ): int {
		$args = Block_Area_Context_Provider::get_query_args( $term->taxonomy, $term->slug, self::BLOCK_AREA );
		if ( ! is_array( $args ) ) {
			return 0;
		}
		$args['post_status']   = 'publish';
		$args['orderby']       = 'date';
		$args['order']         = 'DESC';
		$args['no_found_rows'] = true;

		$query = new \WP_Query( $args );
		return $query->posts ? (int) $query->posts[0] : 0;
	}
}
