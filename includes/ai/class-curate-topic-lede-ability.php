<?php
/**
 * Curate Topic Lede ability.
 *
 * Finds what is in the news on a topic term, picks the PRC posts that best
 * match it, and writes them into the term's `topic-lede` block module.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

use PRC\Platform\Slack\Nexus\Machine_Principal;
use PRC\Platform\Slack\Nexus\Turn_Actor;
use WP_Error;
use WP_Term;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers prc-block-area-modules/curate-topic-lede.
 */
class Curate_Topic_Lede_Ability {

	/**
	 * Ability slug.
	 *
	 * @var string
	 */
	public const ABILITY = 'prc-block-area-modules/curate-topic-lede';

	/**
	 * Plugin file used to validate activation on the target site.
	 *
	 * @var string
	 */
	private const PLUGIN_FILE = 'prc-block-area-modules/prc-block-area-modules.php';

	/**
	 * Post types that can fill a topic lede.
	 *
	 * @var string[]
	 */
	private const POST_TYPES = array( 'post', 'short-read', 'fact-sheet' );

	/**
	 * Trending-news abilities this ability calls.
	 *
	 * @var array<string, string>
	 */
	private const SOURCES = array(
		'gnews' => 'prc-nexus-trending-news/fetch-headlines',
		'nyt'   => 'prc-nexus-trending-news/fetch-nyt-top-stories',
		'rss'   => 'prc-nexus-trending-news/fetch-rss-feeds',
	);

	private const MERGE_ABILITY  = 'prc-nexus-trending-news/merge-headlines';
	private const ENRICH_ABILITY = 'prc-nexus-trending-news/enrich-with-related-posts';

	/**
	 * Most headlines sent to related-post search and the model.
	 *
	 * @var int
	 */
	private const MAX_HEADLINES = 8;

	/**
	 * Newest topic posts added to the candidate pool.
	 *
	 * @var int
	 */
	private const NEWEST_IN_TERM = 12;

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'wp_abilities_api_init', $this, 'register_ability' );
	}

	/**
	 * Register the ability.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function register_ability(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::ABILITY,
			array(
				'label'               => __( 'Curate topic lede from the news', 'prc-block-area-modules' ),
				'description'         => __( 'Finds what is in the news on a topic, picks the PRC posts that fit it best, and puts them in the topic lede block module. Preview by default; draft or publish writes a new module.', 'prc-block-area-modules' ),
				'category'            => Ability_Categories::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'term' ),
					'additionalProperties' => false,
					'properties'           => array(
						'term'          => array(
							'type'        => 'string',
							'description' => 'Topic term: ID, slug, or name, for example "immigration-migration" or "Immigration".',
							'minLength'   => 1,
						),
						'taxonomy'      => array(
							'type'        => 'string',
							'description' => 'Taxonomy of the term.',
							'enum'        => array( 'category' ),
							'default'     => 'category',
						),
						'story_count'   => array(
							'type'        => 'integer',
							'description' => 'Stories to place, lead story first.',
							'default'     => 4,
							'minimum'     => 1,
							'maximum'     => 8,
						),
						'mode'          => array(
							'type'        => 'string',
							'description' => 'preview returns picks only. draft saves a draft module. publish makes the new module live.',
							'enum'        => array( 'preview', 'draft', 'publish' ),
							'default'     => 'preview',
						),
						'post_ids'      => array(
							'type'        => 'array',
							'description' => 'Post IDs to place, lead first. Pass the picks from the preview so the write matches it. Skips the news search.',
							'items'       => array( 'type' => 'integer' ),
						),
						'picks'         => array(
							'type'        => 'array',
							'description' => 'The preview picks, passed with post_ids so the write keeps each reason and headline tie.',
							'items'       => array( 'type' => 'object' ),
						),
						'headlines'     => array(
							'type'        => 'array',
							'description' => 'The preview headlines, passed with post_ids so the write records the news context.',
							'items'       => array( 'type' => 'object' ),
						),
						'news_days'     => array(
							'type'        => 'integer',
							'description' => 'Days of news to read.',
							'default'     => 2,
							'minimum'     => 1,
							'maximum'     => 7,
						),
						'lookback_days' => array(
							'type'        => 'integer',
							'description' => 'Oldest PRC post to consider, in days.',
							'default'     => 365,
							'minimum'     => 7,
							'maximum'     => 1825,
						),
						'site_id'       => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'status'     => array(
							'type'        => 'string',
							'description' => 'preview, draft, published, or needs_clarification.',
						),
						'message'    => array(
							'type'        => 'string',
							'description' => 'Slack-ready markdown summary.',
						),
						'term'       => array( 'type' => 'object' ),
						'candidates' => array(
							'type'        => 'array',
							'description' => 'Terms that match the input when status is needs_clarification.',
							'items'       => array( 'type' => 'object' ),
						),
						'headlines'  => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'picks'      => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'post_ids'   => array(
							'type'        => 'array',
							'description' => 'Picked post IDs, lead first. Pass these back to write the same picks.',
							'items'       => array( 'type' => 'integer' ),
						),
						'fallback'   => array(
							'type'        => 'boolean',
							'description' => 'True when no news matched and the picks are the newest topic posts.',
						),
						'module'     => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_use' ),
				'meta'                => array(
					'annotations'  => array(
						'instructions' => 'Run mode=preview first and show the picks. Write with mode=draft (or mode=publish only when the editor asks to go live) and pass the preview post_ids, picks, and headlines. If status is needs_clarification, ask which candidate term the editor means.',
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => false,
					),
					'show_in_rest' => false,
					'mcp'          => array(
						'public' => false,
					),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @param array|null $input Ability input.
	 * @return bool|WP_Error
	 */
	public function can_use( $input = null ) {
		return \PRC\Platform\AI\Utils\with_site(
			\PRC\Platform\AI\Utils\resolve_site_id( is_array( $input ) ? $input : null ),
			self::PLUGIN_FILE,
			static function () {
				return current_user_can( 'edit_posts' );
			}
		);
	}

	/**
	 * Execute the ability on the target site.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input = is_array( $input ) ? $input : array();
		return \PRC\Platform\AI\Utils\with_site(
			\PRC\Platform\AI\Utils\resolve_site_id( $input ),
			self::PLUGIN_FILE,
			function () use ( $input ) {
				return $this->curate( self::normalize_input( $input ) );
			}
		);
	}

	/**
	 * Apply defaults and bounds to raw input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function normalize_input( array $input ): array {
		$mode = in_array( $input['mode'] ?? '', array( 'preview', 'draft', 'publish' ), true ) ? $input['mode'] : 'preview';

		return array(
			'term'          => trim( (string) ( $input['term'] ?? '' ) ),
			'taxonomy'      => 'category',
			'story_count'   => max( 1, min( 8, (int) ( $input['story_count'] ?? 4 ) ) ),
			'mode'          => $mode,
			'post_ids'      => array_values( array_unique( array_filter( array_map( 'intval', (array) ( $input['post_ids'] ?? array() ) ) ) ) ),
			'picks'         => array_values( array_filter( (array) ( $input['picks'] ?? array() ), 'is_array' ) ),
			'headlines'     => array_values( array_filter( (array) ( $input['headlines'] ?? array() ), 'is_array' ) ),
			'news_days'     => max( 1, min( 7, (int) ( $input['news_days'] ?? 2 ) ) ),
			'lookback_days' => max( 7, min( 1825, (int) ( $input['lookback_days'] ?? 365 ) ) ),
		);
	}

	/**
	 * Run the curation.
	 *
	 * @param array $input Normalized input.
	 * @return array|WP_Error
	 */
	private function curate( array $input ) {
		$actor = null;
		if ( 'preview' !== $input['mode'] ) {
			$actor = self::resolve_actor( $input['mode'] );
			if ( is_wp_error( $actor ) ) {
				return $actor;
			}
		}

		$term = self::resolve_term( $input['term'], $input['taxonomy'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		if ( is_array( $term ) ) {
			return array(
				'status'     => 'needs_clarification',
				'candidates' => $term,
				'message'    => Topic_Lede_Message::clarification_markdown( $input['term'], $term ),
			);
		}

		$headlines = array();
		$fallback  = false;
		$notes     = array();

		if ( array() !== $input['post_ids'] ) {
			$picks = self::picks_from_ids( $input['post_ids'], $input['story_count'], $input['picks'] );
			if ( is_wp_error( $picks ) ) {
				return $picks;
			}
			$headlines = $input['headlines'];
		} else {
			if ( ! wp_get_ability( self::SOURCES['gnews'] ) ) {
				return new WP_Error(
					'prc_topic_lede_trending_news_inactive',
					__( 'Trending news is not active on this site, so the curator cannot read the news.', 'prc-block-area-modules' ),
					array( 'status' => 503 )
				);
			}

			$keywords  = self::term_keywords( $term );
			$headlines = self::fetch_headlines( $keywords, $input['news_days'], $notes );
			$pool      = self::build_pool( $term, $headlines, $input['lookback_days'], $notes );
			if ( array() === $pool ) {
				return new WP_Error(
					'prc_topic_lede_no_posts',
					sprintf(
						/* translators: %s: term name */
						__( 'No published posts found in %s for the lookback window.', 'prc-block-area-modules' ),
						html_entity_decode( $term->name, ENT_QUOTES )
					),
					array( 'status' => 404 )
				);
			}

			$fallback = array() === $headlines;
			$picks    = self::select( $term, $headlines, $pool, $input['story_count'], $fallback, $notes );
			$picks    = self::hydrate_picks( $picks, $pool );
			if ( ! $fallback && array() === $headlines ) {
				$fallback = true;
				$notes[]  = 'None of the headlines were about this topic, so the picks favor the newest posts.';
			}
		}

		$result = array(
			'status'    => 'preview',
			'term'      => self::term_summary( $term ),
			'headlines' => array_map( array( self::class, 'headline_summary' ), $headlines ),
			'picks'     => $picks,
			'post_ids'  => array_column( $picks, 'post_id' ),
			'fallback'  => $fallback,
		);

		if ( $actor instanceof WP_User ) {
			$module = Topic_Lede_Writer::write_module( $term, $picks, $input['mode'], $actor, $result['headlines'] );
			if ( is_wp_error( $module ) ) {
				return $module;
			}
			$result['status'] = 'publish' === $input['mode'] ? 'published' : 'draft';
			$result['module'] = $module;
		}

		$result['message'] = Topic_Lede_Message::result_markdown( $result, $notes );

		return $result;
	}

	/**
	 * Resolve the editor who asked for a write.
	 *
	 * Slack turns run as the bot and carry the mapped editor in Turn_Actor.
	 * The write is credited to, and gated on, that editor. The bot alone can
	 * never write.
	 *
	 * @param string $mode draft or publish.
	 * @return WP_User|WP_Error
	 */
	public static function resolve_actor( string $mode ) {
		$user = null;
		if ( class_exists( Turn_Actor::class ) ) {
			$user = Turn_Actor::user();
		}
		if ( ! $user instanceof WP_User || $user->ID <= 0 ) {
			$user = wp_get_current_user();
		}

		$is_bot = class_exists( Machine_Principal::class ) && Machine_Principal::is_bot_user( (int) $user->ID );
		if ( $user->ID <= 0 || $is_bot ) {
			return new WP_Error(
				'prc_topic_lede_actor_missing',
				__( 'Saving a topic lede needs a linked WordPress editor. Connect Slack on your WordPress profile and try again.', 'prc-block-area-modules' ),
				array( 'status' => 403 )
			);
		}

		$capability = 'publish' === $mode ? 'publish_posts' : 'edit_posts';
		if ( ! user_can( $user, $capability ) ) {
			return new WP_Error(
				'prc_topic_lede_forbidden',
				'publish' === $mode
					? __( 'You cannot publish block modules. Save a draft instead.', 'prc-block-area-modules' )
					: __( 'You cannot create block modules.', 'prc-block-area-modules' ),
				array( 'status' => 403 )
			);
		}

		return $user;
	}

	/**
	 * Find the term the editor means.
	 *
	 * @param string $input    Term ID, slug, name, or free text.
	 * @param string $taxonomy Taxonomy.
	 * @return WP_Term|array|WP_Error Term, candidate list when ambiguous, or error.
	 */
	private static function resolve_term( string $input, string $taxonomy ) {
		$exact = ctype_digit( $input ) ? get_term( (int) $input, $taxonomy ) : get_term_by( 'slug', sanitize_title( $input ), $taxonomy );
		if ( ! $exact instanceof WP_Term ) {
			$exact = get_term_by( 'name', $input, $taxonomy );
		}
		if ( $exact instanceof WP_Term ) {
			return $exact;
		}

		$found = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'search'     => $input,
				'number'     => 25,
			)
		);
		$found = is_array( $found ) ? $found : array();

		$rows = array_map(
			static function ( WP_Term $term ): array {
				return array(
					'term_id' => (int) $term->term_id,
					'slug'    => $term->slug,
					'name'    => html_entity_decode( $term->name, ENT_QUOTES ),
					'parent'  => (int) $term->parent,
				);
			},
			$found
		);

		$matched = Topic_Lede_Selection::match_term( $input, $rows );
		if ( null !== $matched['match'] ) {
			$term = get_term( (int) $matched['match']['term_id'], $taxonomy );
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}

		if ( array() !== $matched['candidates'] ) {
			return $matched['candidates'];
		}

		return new WP_Error(
			'prc_topic_lede_term_not_found',
			sprintf(
				/* translators: %s: user input */
				__( 'No topic matches "%s".', 'prc-block-area-modules' ),
				$input
			),
			array( 'status' => 404 )
		);
	}

	/**
	 * News keywords for the term: name parts, then AI search phrases.
	 *
	 * @param WP_Term $term Term.
	 * @return string[]
	 */
	private static function term_keywords( WP_Term $term ): array {
		$extra = array();
		if ( function_exists( 'PRC\Platform\AI\Utils\extract_search_terms' ) ) {
			$result = \PRC\Platform\AI\Utils\extract_search_terms(
				array(
					array(
						'id'      => 'term',
						'text'    => html_entity_decode( $term->name, ENT_QUOTES ),
						'context' => wp_strip_all_tags( (string) $term->description ),
					),
				),
				3
			);
			if ( ! is_wp_error( $result ) ) {
				$extra = (array) ( $result['items'][0]['terms'] ?? array() );
			}
		}

		return Topic_Lede_Selection::keywords( $term->name, $extra );
	}

	/**
	 * Read current headlines that mention the term.
	 *
	 * GNews is searched once per keyword. NYT and RSS return general top
	 * stories, so they are filtered on the keywords here.
	 *
	 * @param string[] $keywords Keywords.
	 * @param int      $days     Days of news.
	 * @param string[] $notes    Notes for the preview, by reference.
	 * @return array Merged headlines, best first.
	 */
	private static function fetch_headlines( array $keywords, int $days, array &$notes ): array {
		$range = array(
			'from' => gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) ),
			'to'   => gmdate( 'Y-m-d' ),
		);

		$branches = array();

		$articles = array();
		$status   = array();
		foreach ( $keywords as $keyword ) {
			$output = self::run_ability(
				self::SOURCES['gnews'],
				$range + array(
					'endpoint' => 'search',
					'query'    => $keyword,
					'total'    => 10,
				)
			);
			if ( ! is_array( $output ) ) {
				continue;
			}
			$articles = array_merge( $articles, (array) ( $output['articles'] ?? array() ) );
			$status   = array_intersect_key( $output, array_flip( array( 'skipped', 'error' ) ) ) + $status;
		}
		// Merge treats a branch with an error as failed, so report one only when no keyword returned articles.
		$branches['gnews'] = array(
			'articles' => $articles,
			'feed'     => 'gnews',
		) + ( array() === $articles ? $status : array() );

		foreach ( array( 'nyt', 'rss' ) as $key ) {
			$output = self::run_ability( self::SOURCES[ $key ], $range + array( 'total' => 60 ) );
			if ( ! is_array( $output ) ) {
				continue;
			}
			$output['articles'] = Topic_Lede_Selection::filter_articles( (array) ( $output['articles'] ?? array() ), $keywords );
			$branches[ $key ]   = $output;
		}

		$merged = self::run_ability(
			self::MERGE_ABILITY,
			array(
				'branches' => $branches,
				'limit'    => self::MAX_HEADLINES,
			)
		);

		$notes[] = 'News searched for: ' . implode( ', ', $keywords ) . '.';

		if ( ! is_array( $merged ) || empty( $merged['articles'] ) ) {
			$notes[] = sprintf(
				'No current headlines matched %s, so the picks are the newest posts in the topic.',
				implode( ', ', $keywords )
			);
			return array();
		}

		return array_values( (array) $merged['articles'] );
	}

	/**
	 * Build the candidate posts: search hits for the headlines plus the newest
	 * topic posts. Only posts in the term (or a child term) are kept.
	 *
	 * @param WP_Term  $term          Term.
	 * @param array    $headlines     Headlines.
	 * @param int      $lookback_days Oldest post, in days.
	 * @param string[] $notes         Notes for the preview, by reference.
	 * @return array<int, array> Ranked candidates.
	 */
	private static function build_pool( WP_Term $term, array $headlines, int $lookback_days, array &$notes ): array {
		$term_ids   = array_merge( array( (int) $term->term_id ), array_map( 'intval', (array) get_term_children( $term->term_id, $term->taxonomy ) ) );
		$after      = gmdate( 'Y-m-d', strtotime( '-' . $lookback_days . ' days' ) );
		$candidates = array();

		if ( array() !== $headlines ) {
			$stories = array();
			foreach ( $headlines as $headline ) {
				$stories[] = array(
					'title'       => (string) ( $headline['title'] ?? '' ),
					'description' => (string) ( $headline['description'] ?? '' ),
					'categories'  => array(
						array(
							'term_id' => (int) $term->term_id,
							'name'    => html_entity_decode( $term->name, ENT_QUOTES ),
						),
					),
				);
			}

			$enriched = self::run_ability(
				self::ENRICH_ABILITY,
				array(
					'stories' => $stories,
					'limit'   => 6,
				)
			);
			if ( is_array( $enriched ) && ! empty( $enriched['search_terms_error'] ) ) {
				$notes[] = 'Search phrase extraction failed; related posts come from the topic only.';
			}

			foreach ( (array) ( $enriched['stories'] ?? array() ) as $index => $story ) {
				foreach ( (array) ( $story['related_posts'] ?? array() ) as $post ) {
					$post_id = (int) ( $post['id'] ?? 0 );
					if ( $post_id <= 0 ) {
						continue;
					}
					if ( ! isset( $candidates[ $post_id ] ) ) {
						$candidates[ $post_id ] = self::candidate( $post_id );
					}
					if ( null !== $candidates[ $post_id ] ) {
						$candidates[ $post_id ]['headlines'][] = (int) $index;
					}
				}
			}
		}

		$query = new \WP_Query(
			array(
				'post_type'           => self::POST_TYPES,
				'post_status'         => 'publish',
				'post_parent'         => 0,
				'posts_per_page'      => self::NEWEST_IN_TERM,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'fields'              => 'ids',
				'date_query'          => array(
					array(
						'after'     => $after,
						'inclusive' => true,
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'           => array(
					array(
						'taxonomy' => $term->taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_ids,
					),
				),
			)
		);
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! isset( $candidates[ $post_id ] ) ) {
				$candidates[ $post_id ] = self::candidate( $post_id );
			}
		}

		$pool = array();
		foreach ( $candidates as $post_id => $candidate ) {
			if ( null === $candidate || $candidate['date'] < $after || ! has_term( $term_ids, $term->taxonomy, $post_id ) ) {
				continue;
			}
			$candidate['headlines'] = array_values( array_unique( (array) ( $candidate['headlines'] ?? array() ) ) );
			$pool[]                 = $candidate;
		}

		return Topic_Lede_Selection::rank_pool( $pool );
	}

	/**
	 * Candidate row for one published post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private static function candidate( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return null;
		}

		return array(
			'id'        => $post_id,
			'post_type' => $post->post_type,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
			'date'      => get_the_date( 'Y-m-d', $post ),
			'url'       => get_permalink( $post ),
			'excerpt'   => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 45 ),
			'headlines' => array(),
		);
	}

	/**
	 * Ask the model to choose the stories, then validate its answer.
	 *
	 * When the model marks which headlines are about the topic, the others
	 * are removed from $headlines.
	 *
	 * @param WP_Term  $term      Term.
	 * @param array    $headlines Headlines, by reference.
	 * @param array    $pool      Ranked candidates.
	 * @param int      $count     Stories needed.
	 * @param bool     $fallback  Whether no news matched, by reference.
	 * @param string[] $notes     Notes for the preview, by reference.
	 * @return array Validated picks.
	 */
	private static function select( WP_Term $term, array &$headlines, array $pool, int $count, bool &$fallback, array &$notes ): array {
		/**
		 * Short-circuit the model call with raw picks.
		 *
		 * Return an array of `{ post_id, headline_index, reason }` to skip the
		 * model. Picks still pass validation against the pool.
		 *
		 * @param array|null $picks     Raw picks, or null to ask the model.
		 * @param WP_Term    $term      Term.
		 * @param array      $headlines Headlines.
		 * @param array      $pool      Ranked candidates.
		 * @param int        $count     Stories needed.
		 */
		$raw = apply_filters( 'prc_block_area_modules_topic_lede_picks', null, $term, $headlines, $pool, $count );

		if ( null === $raw && $fallback ) {
			$raw = array();
		}

		$relevant = null;
		if ( null === $raw ) {
			$answer = Topic_Lede_Model::ask_model( $term, $headlines, $pool, $count );
			if ( is_wp_error( $answer ) ) {
				$notes[]  = 'The model call failed (' . $answer->get_error_message() . '), so the picks follow headline matches and recency.';
				$fallback = true;
				$raw      = array();
			} else {
				$raw      = $answer['picks'];
				$relevant = $answer['relevant_headlines'];
			}
		}

		$picks = Topic_Lede_Selection::validate_picks( (array) $raw, $pool, $count );
		if ( null === $relevant ) {
			return $picks;
		}

		$kept      = Topic_Lede_Selection::keep_relevant_headlines( $headlines, $relevant, $picks );
		$headlines = $kept['headlines'];
		if ( array() === $headlines ) {
			$newest = array_map(
				static function ( array $candidate ): array {
					$candidate['headlines'] = array();
					return $candidate;
				},
				$pool
			);
			return Topic_Lede_Selection::validate_picks( array(), Topic_Lede_Selection::rank_pool( $newest ), $count );
		}
		return $kept['picks'];
	}

	/**
	 * Picks from explicit post IDs (the write after a preview).
	 *
	 * @param int[] $post_ids Post IDs, lead first.
	 * @param int   $count    Most stories to place.
	 * @param array $preview  Preview picks carrying reason and headline_index.
	 * @return array|WP_Error
	 */
	private static function picks_from_ids( array $post_ids, int $count, array $preview = array() ) {
		$by_id = array();
		foreach ( $preview as $pick ) {
			$by_id[ (int) ( $pick['post_id'] ?? 0 ) ] = $pick;
		}

		$picks = array();
		foreach ( array_slice( $post_ids, 0, $count ) as $post_id ) {
			$candidate = self::candidate( (int) $post_id );
			if ( null === $candidate ) {
				return new WP_Error(
					'prc_topic_lede_invalid_post',
					sprintf(
						/* translators: %d: post ID */
						__( 'Post %d is not a published post, short read, or fact sheet.', 'prc-block-area-modules' ),
						(int) $post_id
					),
					array( 'status' => 400 )
				);
			}
			$picks[] = array(
				'post_id'        => (int) $post_id,
				'post_type'      => $candidate['post_type'],
				'title'          => $candidate['title'],
				'url'            => $candidate['url'],
				'date'           => $candidate['date'],
				'headline_index' => (int) ( $by_id[ (int) $post_id ]['headline_index'] ?? -1 ),
				'reason'         => trim( (string) ( $by_id[ (int) $post_id ]['reason'] ?? '' ) ),
				'filled'         => (bool) ( $by_id[ (int) $post_id ]['filled'] ?? false ),
			);
		}
		return $picks;
	}

	/**
	 * Add post fields to validated picks.
	 *
	 * @param array $picks Validated picks.
	 * @param array $pool  Candidates.
	 * @return array
	 */
	private static function hydrate_picks( array $picks, array $pool ): array {
		$by_id = array();
		foreach ( $pool as $candidate ) {
			$by_id[ (int) $candidate['id'] ] = $candidate;
		}

		$hydrated = array();
		foreach ( $picks as $pick ) {
			$candidate  = $by_id[ $pick['post_id'] ] ?? null;
			$hydrated[] = $pick + array(
				'post_type' => $candidate['post_type'] ?? 'post',
				'title'     => $candidate['title'] ?? '',
				'url'       => $candidate['url'] ?? '',
				'date'      => $candidate['date'] ?? '',
			);
		}
		return $hydrated;
	}

	/**
	 * Execute another ability and return its output.
	 *
	 * @param string $name  Ability slug.
	 * @param array  $input Input.
	 * @return mixed Output, or WP_Error.
	 */
	private static function run_ability( string $name, array $input ) {
		$ability = wp_get_ability( $name );
		if ( ! $ability ) {
			return new WP_Error( 'prc_topic_lede_missing_ability', $name . ' is not registered.' );
		}
		return $ability->execute( $input );
	}

	/**
	 * Term fields for the output.
	 *
	 * @param WP_Term $term Term.
	 * @return array
	 */
	private static function term_summary( WP_Term $term ): array {
		return array(
			'term_id'     => (int) $term->term_id,
			'slug'        => $term->slug,
			'name'        => html_entity_decode( $term->name, ENT_QUOTES ),
			'taxonomy'    => $term->taxonomy,
			'archive_url' => (string) get_term_link( $term ),
		);
	}

	/**
	 * Headline fields for the output.
	 *
	 * @param array $headline Merged headline.
	 * @return array
	 */
	private static function headline_summary( $headline ): array {
		$headline = (array) $headline;
		return array(
			'title'       => (string) ( $headline['title'] ?? '' ),
			'url'         => (string) ( $headline['url'] ?? '' ),
			'source'      => (string) ( $headline['source'] ?? '' ),
			'publishedAt' => (string) ( $headline['publishedAt'] ?? '' ),
		);
	}
}
