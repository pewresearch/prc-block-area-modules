<?php
/**
 * Topic lede selection model call.
 *
 * Ranks candidate posts against the news with the WP AI client.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

use WP_Error;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asks the model which posts fit the current news.
 */
class Topic_Lede_Model {

	/**
	 * Model call that ranks candidates against the news.
	 *
	 * @param WP_Term $term      Term.
	 * @param array   $headlines Headlines.
	 * @param array   $pool      Ranked candidates.
	 * @param int     $count     Stories needed.
	 * @return array{picks: array, relevant_headlines: ?int[]}|WP_Error
	 */
	public static function ask_model( WP_Term $term, array $headlines, array $pool, int $count ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'ai_client_unavailable', 'WP AI client is not available.' );
		}

		$news = array();
		foreach ( $headlines as $index => $headline ) {
			$news[] = array(
				'index'       => (int) $index,
				'title'       => (string) ( $headline['title'] ?? '' ),
				'description' => wp_trim_words( (string) ( $headline['description'] ?? '' ), 40 ),
				'source'      => (string) ( $headline['source'] ?? '' ),
			);
		}

		$posts = array();
		foreach ( $pool as $candidate ) {
			$posts[] = array(
				'post_id'   => (int) $candidate['id'],
				'title'     => $candidate['title'],
				'date'      => $candidate['date'],
				'excerpt'   => $candidate['excerpt'],
				'headlines' => $candidate['headlines'],
			);
		}

		$prompt = wp_sprintf(
			"TOPIC: %s\nSTORIES NEEDED: %d\n\nNEWS HEADLINES:\n%s\n\nPRC POSTS:\n%s",
			html_entity_decode( $term->name, ENT_QUOTES ),
			$count,
			wp_json_encode( $news ),
			wp_json_encode( $posts )
		);

		$builder = wp_ai_client_prompt( $prompt );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		$preferred = function_exists( 'WordPress\AI\get_preferred_models_for_text_generation' )
			? \WordPress\AI\get_preferred_models_for_text_generation()
			: array();

		$builder = $builder->using_system_instruction( self::system_instruction() );
		if ( array() !== $preferred ) {
			$builder = $builder->using_model_preference( ...$preferred );
		}

		$raw = $builder->as_json_response( self::picks_schema() )->generate_text();
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) || ! is_array( $decoded['picks'] ?? null ) ) {
			return new WP_Error( 'prc_topic_lede_invalid_model_output', 'The model returned no picks.' );
		}

		return array(
			'picks'              => $decoded['picks'],
			'relevant_headlines' => is_array( $decoded['relevant_headlines'] ?? null ) ? array_map( 'intval', $decoded['relevant_headlines'] ) : null,
		);
	}

	/**
	 * System instruction for the selection call.
	 *
	 * @return string
	 */
	private static function system_instruction(): string {
		return 'You curate the lede of a Pew Research Center topic page. Readers arrive because a topic is in the news. '
			. 'Choose PRC posts that give data and context for what the news is about right now. '
			. 'Rules: choose only post_id values from PRC POSTS. Choose exactly STORIES NEEDED distinct posts when enough posts exist. '
			. 'The first pick is the lead story: the post that best explains the biggest current headline. '
			. 'Prefer posts listed under a headline index, then the most recent posts when relevance is equal. '
			. 'Avoid two posts on the same narrow finding. '
			. 'For each pick, set headline_index to the news headline it supports, or -1 when none applies, and write a reason of one short sentence for an editor. '
			. 'Set relevant_headlines to the index of every headline that is about the TOPIC as PRC covers it. Leave out headlines that only share a word with it (for example animal migration for an immigration topic). '
			. 'A pick can cite only a relevant headline.';
	}

	/**
	 * JSON schema for the selection call.
	 *
	 * @return array
	 */
	private static function picks_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'relevant_headlines', 'picks' ),
			'properties'           => array(
				'relevant_headlines' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'picks'              => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'post_id', 'headline_index', 'reason' ),
						'properties'           => array(
							'post_id'        => array( 'type' => 'integer' ),
							'headline_index' => array( 'type' => 'integer' ),
							'reason'         => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
	}
}
