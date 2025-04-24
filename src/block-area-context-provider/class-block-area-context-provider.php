<?php
/**
 * Block Area Context Provider class for managing block area context.
 *
 * @package PRC\Platform
 * @since   1.0.0
 */

namespace PRC\Platform\Block_Area_Modules;

use WP_Error;
use WP_Query;
use WP_Term;
use WP_Post;

/**
 * Provides a block and php filter to wrap query blocks and block area modules to collect all story item id's from the content therein and then inject them into the query block post_not_in arg so that they dont repeat. This is a special block really only intended for dev use.
 *
 * @package PRC\Platform\Block_Area_Modules
 * @since   1.0.0
 */
class Block_Area_Context_Provider {
	/**
	 * The collected story item ids.
	 *
	 * @var array
	 */
	public $collected_story_item_ids = array();

	/**
	 * The handle.
	 *
	 * @var string
	 */
	public static $handle = 'prc-platform-block-area-context-provider';

	/**
	 * The cache key.
	 *
	 * @var string
	 */
	public static $cache_key = 'prc_block_area_module_story_item_ids';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param object $loader The loader object.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param object $loader The loader object.
	 */
	public function init( $loader ) {
		// Handle block area context.
		$loader->add_filter(
			'render_block_context',
			$this,
			'construct_block_context',
			1,
			3
		);
		$loader->add_filter(
			'render_block_context',
			$this,
			'execute_block_context',
			100,
			3
		);
		$loader->add_action(
			'pre_get_posts',
			$this,
			'execute_on_main_query',
		);

		// Register the block.
		$loader->add_action( 'init', $this, 'block_init' );
	}

	/**
	 * Register the Block Area Context Provider block.
	 *
	 * @hook init
	 */
	public function block_init() {
		register_block_type_from_metadata(
			PRC_BLOCK_AREA_MODULES_DIR . '/build/block-area-context-provider',
			array(
				'render_callback' => array( $this, 'render_block_area' ),
			)
		);
	}

	/**
	 * Construct the block context.
	 *
	 * @hook render_block_context, 1
	 * @param mixed $context The context.
	 * @param mixed $parsed_block The parsed block.
	 * @param mixed $parent_block_obj The parent block object.
	 * @return mixed The context.
	 */
	public function construct_block_context( $context, $parsed_block, $parent_block_obj ) {
		if ( is_front_page() ) {
			return $context;
		}
		if ( 'prc-platform/block-area-context-provider' !== $parsed_block['blockName'] ) {
			return $context;
		}
		$attrs = $this->recursive_block_search( $parsed_block, 'prc-platform/block-area' );
		if ( null === $attrs ) {
			return $context;
		}
		$block_area_slug = array_key_exists( 'blockAreaSlug', $attrs ) ? $attrs['blockAreaSlug'] : null;
		$category_slug   = array_key_exists( 'categorySlug', $attrs ) ? $attrs['categorySlug'] : null;
		$this->query_block_module_for_story_items( $block_area_slug, $category_slug );
		return $context;
	}

	/**
	 * Filter out story items that have already been used in the block area module from the main query.
	 *
	 * @hook pre_get_posts
	 * @param mixed $query The query.
	 * @return mixed The query.
	 */
	public function execute_on_main_query( $query ) {
		if ( $query->is_front_page() ) {
			return $query;
		}
		if ( 1 === get_current_blog_id() ) {
			return $query;
		}
		if ( $query->is_paged() ) {
			return $query;
		}
		if ( $query->get( 'post__in' ) ) {
			return $query;
		}
		if ( $query->is_archive() && $query->is_category() && $query->is_main_query() ) {
			// Look for Topic-Lede block-area story items and exclude them from the main query.
			$this->query_block_module_for_story_items( 'topic-lede', $query->get_queried_object()->slug );
			$not_in = $query->get( 'post__not_in' );
			$query->set( 'post__not_in', array_merge( $not_in, $this->collected_story_item_ids ) );
			return $query;
		}
	}

	/**
	 * Handles when Query blocks are used and they do not inherit WP_Query
	 *
	 * @hook render_block_context, 100
	 * @param mixed $context The context.
	 * @param mixed $parsed_block The parsed block.
	 * @param mixed $parent_block_obj The parent block object.
	 * @return mixed The context.
	 */
	public function execute_block_context( $context, $parsed_block, $parent_block_obj ) {
		if ( is_front_page() ) {
			return $context;
		}
		if ( 'core/post-template' === $parsed_block['blockName'] ) {
			$story_item_ids = $this->collected_story_item_ids;
			// Quit early if no story item ids.
			if ( ! is_array( $story_item_ids ) ) {
				return $context;
			}

			$query_args = $context['query'] ?? array();

			$not_in = array_key_exists( 'post__not_in', $query_args ) ? $query_args['post__not_in'] : array();

			$query_args['post__not_in'] = array_merge( $not_in, $story_item_ids );

			$context['query'] = $query_args;
		}

		return $context;
	}

	/**
	 * Render the block area.
	 *
	 * @param mixed $attributes The attributes.
	 * @param mixed $content The content.
	 * @param mixed $block The block.
	 * @return mixed The content.
	 */
	public function render_block_area( $attributes, $content, $block ) {
		return $content;
	}

	/**
	 * Recursively search for a block by name.
	 *
	 * @param mixed  $block The block.
	 * @param string $block_name The block name.
	 * @return mixed The block attributes.
	 */
	public function recursive_block_search( $block, $block_name ) {
		if ( array_key_exists( 'blockName', $block ) && $block['blockName'] === $block_name ) {
			return $block['attrs'];
		}
		if ( ! array_key_exists( 'innerBlocks', $block ) ) {
			return null;
		}
		foreach ( $block['innerBlocks'] as $inner_block ) {
			$inner_block_attrs = $this->recursive_block_search( $inner_block, $block_name );
			if ( null !== $inner_block_attrs ) {
				return $inner_block_attrs;
			}
		}
		return null;
	}

	/**
	 * Get query args.
	 *
	 * @param string|null $taxonomy_name The taxonomy name.
	 * @param string|null $taxonomy_term_slug The taxonomy term slug.
	 * @param string|null $block_area_slug The block area slug.
	 * @param bool        $inherit_term_from_template Whether to inherit the term from the template.
	 * @param string|null $reference_id The reference id.
	 * @return array|bool The query args.
	 */
	public static function get_query_args(
		$taxonomy_name = null,
		$taxonomy_term_slug = null,
		$block_area_slug = null,
		$inherit_term_from_template = false,
		$reference_id = false
	) {

		if ( null === $block_area_slug && false === $reference_id ) {
			return false;
		}

		if ( true === $inherit_term_from_template ) {
			global $wp_query;
			if ( null !== $taxonomy_name ) {
				if ( 'category' === $taxonomy_name ) {
					$category_name = $taxonomy_term_slug ?? '';
					$tax_check     = $wp_query->is_category( $category_name );
				} else {
					$tax_check = $wp_query->is_tax( $taxonomy_name );
				}
			}
			if ( $wp_query->is_main_query() && true === $tax_check ) {
				$queried_object     = $wp_query->get_queried_object();
				$taxonomy_term_slug = $queried_object->slug;
			}
		}

		$tax_query = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'block_area',
				'field'    => 'slug',
				'terms'    => array( $block_area_slug ),
			),
		);

		if ( null !== $taxonomy_term_slug ) {
			array_push(
				$tax_query,
				array(
					'taxonomy'         => $taxonomy_name,
					'field'            => 'slug',
					'terms'            => array( $taxonomy_term_slug ),
					'include_children' => false,
				)
			);
		}

		$block_module_query_args = array(
			'post_type'              => 'block_module',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'tax_query'              => $tax_query,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);

		if ( false !== $reference_id ) {
			$block_module_query_args['post__in'] = array( $reference_id );
			unset( $block_module_query_args['tax_query'] );
		}

		return $block_module_query_args;
	}

	/**
	 * Get the cache id.
	 *
	 * @param string $block_area_slug The block area slug.
	 * @param string $category_slug The category slug.
	 * @return string The cache id.
	 */
	public static function get_cache_id( $block_area_slug, $category_slug ) {
		$to_return = md5( wp_json_encode( array( $block_area_slug, $category_slug ) ) );
		return $to_return;
	}

	/**
	 * Query the block module for story items.
	 *
	 * @param string $block_area_slug The block area slug.
	 * @param string $category_slug The category slug.
	 * @return void
	 */
	public function query_block_module_for_story_items( $block_area_slug = '', $category_slug = '' ) {
		$cache_id = self::get_cache_id( $block_area_slug, $category_slug );
		$cached   = wp_cache_get( $cache_id, self::$cache_key );

		if ( false !== $cached && ! is_preview() && is_array( $cached ) ) {
			$this->collected_story_item_ids = $cached;
		} else {
			$query_args    = self::get_query_args( 'category', $category_slug, $block_area_slug );
			$block_modules = new WP_Query( $query_args );
			if ( $block_modules->have_posts() ) {
				$block_module_id = $block_modules->posts[0];
				$story_item_ids  = get_post_meta( $block_module_id, '_story_item_ids', true );
				if ( is_array( $story_item_ids ) ) {
					$this->collected_story_item_ids = $story_item_ids;
				}
			}
			wp_reset_postdata();

			wp_cache_set(
				$cache_id,
				$this->collected_story_item_ids,
				self::$cache_key,
				1 * HOUR_IN_SECONDS
			);
		}
	}
}
