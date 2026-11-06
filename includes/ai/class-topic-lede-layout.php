<?php
/**
 * Topic lede block layout helpers.
 *
 * Pure array transforms on parsed blocks. No database access, so the
 * curator ability and the PHPUnit suite share the same code.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Swaps the stories in a topic lede module and builds the default layout.
 */
class Topic_Lede_Layout {

	/**
	 * Story item block name.
	 *
	 * @var string
	 */
	public const STORY_ITEM = 'prc-block/story-item';

	/**
	 * Attributes that describe one specific story. They are removed on swap and
	 * refilled from the pick's story data, because the story item editor shows
	 * saved attributes and does not load them from postId. Layout attributes
	 * (image slot and size, header size, toggles) stay as the editor set them.
	 *
	 * @var string[]
	 */
	private const STORY_ATTRIBUTES = array( 'title', 'excerpt', 'url', 'label', 'date', 'image', 'isChartArt', 'enableExtra' );

	/**
	 * Count the story items in a block tree.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return int
	 */
	public static function count_story_items( array $blocks ): int {
		$count = 0;
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( self::STORY_ITEM === ( $block['blockName'] ?? null ) ) {
				++$count;
			}
			$count += self::count_story_items( (array) ( $block['innerBlocks'] ?? array() ) );
		}
		return $count;
	}

	/**
	 * Put the picked posts into the story items, in document order.
	 *
	 * Story items after the last pick keep their current story.
	 *
	 * @param array                                           $blocks Parsed blocks.
	 * @param array<int, array{post_id:int,post_type:string}> $picks  Posts, lead first.
	 * @return array{blocks: array, replaced: int}
	 */
	public static function swap_story_items( array $blocks, array $picks ): array {
		$picks    = array_values( $picks );
		$position = 0;
		$blocks   = self::swap_in_tree( $blocks, $picks, $position );

		return array(
			'blocks'   => $blocks,
			'replaced' => min( $position, count( $picks ) ),
		);
	}

	/**
	 * Recursive worker for swap_story_items().
	 *
	 * @param array $blocks   Parsed blocks.
	 * @param array $picks    Posts, lead first.
	 * @param int   $position Next pick index, shared across the tree.
	 * @return array
	 */
	private static function swap_in_tree( array $blocks, array $picks, int &$position ): array {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( self::STORY_ITEM === ( $block['blockName'] ?? null ) ) {
				if ( isset( $picks[ $position ] ) ) {
					$blocks[ $index ] = self::with_post( $block, $picks[ $position ] );
				}
				++$position;
				continue;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::swap_in_tree( (array) $block['innerBlocks'], $picks, $position );
				$blocks[ $index ]     = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Point one story item at a new post.
	 *
	 * @param array $block Story item block.
	 * @param array $pick  New post: post_id, post_type, and optional story
	 *                     (title, excerpt, url, label, date, art by image size).
	 * @return array
	 */
	private static function with_post( array $block, array $pick ): array {
		$attrs = (array) ( $block['attrs'] ?? array() );
		foreach ( self::STORY_ATTRIBUTES as $key ) {
			unset( $attrs[ $key ] );
		}
		$attrs['postId']   = (int) $pick['post_id'];
		$attrs['postType'] = (string) ( $pick['post_type'] ?? 'post' );

		$story = (array) ( $pick['story'] ?? array() );
		foreach ( array( 'title', 'url', 'label', 'date' ) as $key ) {
			if ( '' !== (string) ( $story[ $key ] ?? '' ) ) {
				$attrs[ $key ] = (string) $story[ $key ];
			}
		}

		$art = $story['art'][ $attrs['imageSize'] ?? 'A1' ] ?? null;
		if ( is_array( $art ) && '' !== (string) ( $art['rawUrl'] ?? '' ) ) {
			$attrs['image']      = (string) $art['rawUrl'];
			$attrs['isChartArt'] = (bool) ( $art['chartArt'] ?? false );
		}

		$block['attrs'] = $attrs;
		// The saved description and extra markup belong to the old story.
		$block['innerBlocks']  = array();
		$block['innerHTML']    = '';
		$block['innerContent'] = array();

		$excerpt = trim( (string) ( $story['excerpt'] ?? '' ) );
		if ( '' !== $excerpt ) {
			// The excerpt attribute is sourced from this markup, not from attrs.
			$block['innerHTML']    = '<div class="description">' . htmlspecialchars( $excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false ) . '</div>';
			$block['innerContent'] = array( $block['innerHTML'] );
		}

		return $block;
	}

	/**
	 * Serialized default layout for a term that has no topic lede module yet.
	 *
	 * It matches the production layout: one lead story on the left and the
	 * other stories stacked on the right. Story items start with postId 0 and
	 * are filled by swap_story_items().
	 *
	 * @param int $story_count Stories in the layout, lead included.
	 * @return string Block markup.
	 */
	public static function default_markup( int $story_count ): string {
		$story_count = max( 1, $story_count );

		$lead = '<!-- wp:prc-block/story-item {"postId":0,"imageSlot":"top","imageSize":"A1","headerSize":1} /-->';
		if ( 1 === $story_count ) {
			return $lead;
		}

		$secondary = str_repeat(
			"\n" . '<!-- wp:prc-block/story-item {"postId":0,"imageSlot":"right","imageSize":"A3","headerSize":2,"enableAltHeaderWeight":true,"enableExcerpt":false} /-->',
			$story_count - 1
		);

		return '<!-- wp:columns -->' . "\n"
			. '<div class="wp-block-columns"><!-- wp:column {"width":"60%"} -->' . "\n"
			. '<div class="wp-block-column" style="flex-basis:60%">' . $lead . '</div>' . "\n"
			. '<!-- /wp:column -->' . "\n\n"
			. '<!-- wp:column {"width":"40%"} -->' . "\n"
			. '<div class="wp-block-column" style="flex-basis:40%">' . $secondary . "\n" . '</div>' . "\n"
			. '<!-- /wp:column --></div>' . "\n"
			. '<!-- /wp:columns -->';
	}
}
