<?php
/**
 * Registers the topic lede curator as a PRC Nexus system.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

use PRC\Platform\Slack\Nexus\Suggested_Prompt;
use PRC\Platform\Slack\Nexus\System;
use PRC\Platform\Slack\Nexus\Systems;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The curator's Slack footprint: one System declaration.
 */
class Topic_Lede_System {

	/**
	 * Model-facing instructions for this system.
	 *
	 * @var string
	 */
	public const INSTRUCTIONS = 'Topic lede curator: editors ask you to refresh the lede (featured stories) at the top of a topic page with what is in the news, for example "refresh the immigration lede".

Use prc-block-area-modules/curate-topic-lede:
1. Always call it first with mode="preview". Pass the topic the editor named as term.
2. If status is "needs_clarification", list the candidate topics and ask which one. Do not guess.
3. Show the preview: post the message field as is. It lists the headlines and the picks with reasons.
4. Write only after the editor confirms in this thread. Call it again with the same term and post_ids set to the preview post_ids, in the same order. Use mode="draft" when they say save, draft, or yes. Use mode="publish" only when they say publish, go live, or ship it.
5. If the editor wants changes (drop a story, swap the lead, use a post ID), edit post_ids to match and preview again with those post_ids before you write.
6. After a write, post the message field. It has the edit link or the live topic page link. A screenshot of the module, and a Publish button for a draft, then come to the thread by themselves. Do not post them or promise anything more. After a draft, do not call mode="publish" unless the editor asks in text; the Publish button publishes the draft.
Never call mode="publish" or mode="draft" in the same turn as the first preview.';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'prc_nexus_systems', $this, 'register' );
	}

	/**
	 * Register the system.
	 *
	 * @hook prc_nexus_systems
	 *
	 * @param Systems $systems Registry.
	 */
	public function register( $systems ): void {
		if ( ! $systems instanceof Systems || ! defined( 'PRC_NEXUS_TRENDING_NEWS_FILE' ) ) {
			return;
		}

		$systems->add(
			new System(
				id: 'topic-lede-curator',
				label: __( 'Topic Lede Curator', 'prc-block-area-modules' ),
				summary: __( 'Refreshes topic page ledes with PRC posts that fit the current news.', 'prc-block-area-modules' ),
				instructions: self::INSTRUCTIONS,
				tools: array( Curate_Topic_Lede_Ability::ABILITY ),
				suggested_prompts: array(
					new Suggested_Prompt(
						'Refresh a topic lede with the news',
						'Refresh the immigration topic lede with what is in the news right now'
					),
				),
				required_capability: 'edit_posts'
			)
		);
	}
}
