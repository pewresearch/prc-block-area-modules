<?php
/**
 * Topic lede selection helpers.
 *
 * Pure functions for term matching, headline filtering, candidate ranking,
 * and pick validation. No database or network access.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides which term and which posts the curator uses.
 */
class Topic_Lede_Selection {

	/**
	 * Most keywords sent to the news sources for one term.
	 *
	 * @var int
	 */
	public const MAX_KEYWORDS = 4;

	/**
	 * Match user input against candidate terms.
	 *
	 * An exact ID, slug, or name wins. Otherwise one partial name or slug match
	 * wins, then one top-level partial match (topic ledes live on parent
	 * topics). Anything else is ambiguous; candidates list top-level terms first.
	 *
	 * @param string                                                             $input Term ID, slug, name, or free text.
	 * @param array<int, array{term_id:int,slug:string,name:string,parent?:int}> $terms Candidate terms.
	 * @return array{match: ?array, candidates: array}
	 */
	public static function match_term( string $input, array $terms ): array {
		$needle = self::normalize( $input );
		if ( '' === $needle ) {
			return array(
				'match'      => null,
				'candidates' => array(),
			);
		}

		foreach ( $terms as $term ) {
			if ( ctype_digit( $needle ) && (int) $needle === (int) $term['term_id'] ) {
				return array(
					'match'      => $term,
					'candidates' => array(),
				);
			}
			if ( self::normalize( $term['slug'] ) === $needle || self::normalize( $term['name'] ) === $needle ) {
				return array(
					'match'      => $term,
					'candidates' => array(),
				);
			}
		}

		$partial = array_values(
			array_filter(
				$terms,
				static function ( array $term ) use ( $needle ): bool {
					return str_contains( self::normalize( $term['name'] ), $needle )
						|| str_contains( self::normalize( $term['slug'] ), $needle );
				}
			)
		);

		$top_level = array_values(
			array_filter(
				$partial,
				static function ( array $term ): bool {
					return 0 === (int) ( $term['parent'] ?? 0 );
				}
			)
		);

		foreach ( array( $partial, $top_level ) as $matches ) {
			if ( 1 === count( $matches ) ) {
				return array(
					'match'      => $matches[0],
					'candidates' => array(),
				);
			}
		}

		usort(
			$partial,
			static function ( array $a, array $b ): int {
				return ( 0 === (int) ( $a['parent'] ?? 0 ) ? 0 : 1 ) <=> ( 0 === (int) ( $b['parent'] ?? 0 ) ? 0 : 1 );
			}
		);

		return array(
			'match'      => null,
			'candidates' => $partial,
		);
	}

	/**
	 * News keywords for a term: the name's parts, then extra phrases.
	 *
	 * "Immigration & Migration" gives "Immigration" and "Migration".
	 *
	 * @param string   $name  Term name.
	 * @param string[] $extra Extra phrases (for example from AI search terms).
	 * @return string[]
	 */
	public static function keywords( string $name, array $extra = array() ): array {
		$name  = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$parts = preg_split( '/\s*(?:&|,|\/|\band\b)\s*/i', $name );
		$parts = is_array( $parts ) ? $parts : array( $name );

		$keywords = array();
		$seen     = array();
		foreach ( array_merge( $parts, $extra ) as $keyword ) {
			$keyword = trim( (string) $keyword );
			$key     = strtolower( $keyword );
			if ( strlen( $keyword ) < 3 || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$keywords[]   = $keyword;
			if ( count( $keywords ) >= self::MAX_KEYWORDS ) {
				break;
			}
		}

		return $keywords;
	}

	/**
	 * Keep articles whose title or description has any keyword.
	 *
	 * @param array    $articles Articles with title and description.
	 * @param string[] $keywords Keywords.
	 * @return array
	 */
	public static function filter_articles( array $articles, array $keywords ): array {
		if ( array() === $keywords ) {
			return array();
		}

		return array_values(
			array_filter(
				$articles,
				static function ( $article ) use ( $keywords ): bool {
					if ( ! is_array( $article ) ) {
						return false;
					}
					$text = (string) ( $article['title'] ?? '' ) . ' ' . (string) ( $article['description'] ?? '' );
					foreach ( $keywords as $keyword ) {
						if ( false !== stripos( $text, $keyword ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}

	/**
	 * Order candidates: posts tied to more headlines first, then newest.
	 *
	 * @param array<int, array{id:int,date:string,headlines?:int[]}> $pool Candidates.
	 * @return array<int, array>
	 */
	public static function rank_pool( array $pool ): array {
		$pool = array_values( $pool );
		usort(
			$pool,
			static function ( array $a, array $b ): int {
				$by_matches = count( (array) ( $b['headlines'] ?? array() ) ) <=> count( (array) ( $a['headlines'] ?? array() ) );
				if ( 0 !== $by_matches ) {
					return $by_matches;
				}
				return strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ) );
			}
		);
		return $pool;
	}

	/**
	 * Keep valid model picks and fill open slots from the ranked pool.
	 *
	 * A pick is valid when its post ID is in the pool and not already picked.
	 * Filled slots get `filled: true` so the preview can say so.
	 *
	 * @param array             $raw_picks Model output: post_id, headline_index, reason.
	 * @param array<int, array> $pool      Ranked candidates keyed in rank order.
	 * @param int               $count     Stories needed.
	 * @return array<int, array{post_id:int,headline_index:int,reason:string,filled:bool}>
	 */
	public static function validate_picks( array $raw_picks, array $pool, int $count ): array {
		$pool_ids = array();
		foreach ( $pool as $candidate ) {
			$pool_ids[ (int) $candidate['id'] ] = true;
		}

		$picks = array();
		$used  = array();
		foreach ( $raw_picks as $raw ) {
			if ( count( $picks ) >= $count ) {
				break;
			}
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$post_id = (int) ( $raw['post_id'] ?? 0 );
			if ( ! isset( $pool_ids[ $post_id ] ) || isset( $used[ $post_id ] ) ) {
				continue;
			}
			$used[ $post_id ] = true;
			$picks[]          = array(
				'post_id'        => $post_id,
				'headline_index' => isset( $raw['headline_index'] ) ? (int) $raw['headline_index'] : -1,
				'reason'         => trim( (string) ( $raw['reason'] ?? '' ) ),
				'filled'         => false,
			);
		}

		foreach ( $pool as $candidate ) {
			if ( count( $picks ) >= $count ) {
				break;
			}
			$post_id = (int) $candidate['id'];
			if ( isset( $used[ $post_id ] ) ) {
				continue;
			}
			$used[ $post_id ] = true;
			$headlines        = (array) ( $candidate['headlines'] ?? array() );
			$picks[]          = array(
				'post_id'        => $post_id,
				'headline_index' => array() !== $headlines ? (int) $headlines[0] : -1,
				'reason'         => array() !== $headlines ? 'Related to a current headline.' : 'Newest research in this topic.',
				'filled'         => true,
			);
		}

		return $picks;
	}

	/**
	 * Keep only the headlines the model marked as on topic.
	 *
	 * Pick headline indexes are renumbered to the kept list. A pick that cites
	 * a dropped headline gets -1.
	 *
	 * @param array $headlines Headlines in model index order.
	 * @param int[] $relevant  Indexes of on-topic headlines.
	 * @param array $picks     Validated picks.
	 * @return array{headlines: array, picks: array}
	 */
	public static function keep_relevant_headlines( array $headlines, array $relevant, array $picks ): array {
		$headlines = array_values( $headlines );
		$kept      = array();
		$new_index = array();
		foreach ( array_values( array_unique( array_map( 'intval', $relevant ) ) ) as $old ) {
			if ( isset( $headlines[ $old ] ) ) {
				$new_index[ $old ] = count( $kept );
				$kept[]            = $headlines[ $old ];
			}
		}

		foreach ( $picks as $i => $pick ) {
			$old                           = (int) ( $pick['headline_index'] ?? -1 );
			$picks[ $i ]['headline_index'] = $new_index[ $old ] ?? -1;
		}

		return array(
			'headlines' => $kept,
			'picks'     => $picks,
		);
	}

	/**
	 * Lowercase, entity-decoded, trimmed text for comparison.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normalize( string $text ): string {
		return strtolower( trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}
}
