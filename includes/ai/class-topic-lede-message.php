<?php
/**
 * Topic lede Slack messages.
 *
 * Formats curator results as Slack mrkdwn.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the markdown the curator returns to Nexus.
 */
class Topic_Lede_Message {

	/**
	 * Markdown asking which term the editor means.
	 *
	 * @param string $input      Editor input.
	 * @param array  $candidates Candidate terms.
	 * @return string
	 */
	public static function clarification_markdown( string $input, array $candidates ): string {
		$lines = array( sprintf( 'More than one topic matches "%s". Which one?', $input ) );
		foreach ( $candidates as $candidate ) {
			$lines[] = sprintf( '• %s (`%s`)', $candidate['name'], $candidate['slug'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Slack-ready summary of the result.
	 *
	 * @param array    $result Result.
	 * @param string[] $notes  Notes.
	 * @return string
	 */
	public static function result_markdown( array $result, array $notes ): string {
		$term = $result['term'];
		$md   = '*Topic lede for ' . $term['name'] . '*';

		if ( isset( $result['module'] ) ) {
			$module = $result['module'];
			$md    .= 'published' === $result['status']
				? "\nPublished. It is live on <" . $module['archive_url'] . '|the topic page>.'
				: "\nSaved as a draft. <" . $module['edit_url'] . '|Review and publish it>.';
			if ( $module['replaces_module'] ) {
				$md .= ' It replaces module ' . $module['replaces_module'] . ', which stays in place for rollback.';
			}
			if ( $module['stories_in_layout'] < count( $result['picks'] ) ) {
				$md .= "\nThe current layout has " . $module['stories_in_layout'] . ' story slots, so only the first ' . $module['stories_in_layout'] . ' picks were placed.';
			}
			if ( ! empty( $module['preview_pending'] ) ) {
				$md .= 'published' === $result['status']
					? "\nA screenshot will follow in this thread."
					: "\nA screenshot and a Publish button will follow in this thread.";
			}
		}

		if ( array() !== $result['headlines'] ) {
			$md .= "\n\n*In the news*";
			foreach ( $result['headlines'] as $index => $headline ) {
				$md .= "\n" . ( $index + 1 ) . '. <' . $headline['url'] . '|' . $headline['title'] . '>' . ( '' !== $headline['source'] ? ' (' . $headline['source'] . ')' : '' );
			}
		}

		$md .= "\n\n*Picks*";
		foreach ( $result['picks'] as $index => $pick ) {
			$label = 0 === $index ? 'Lead' : (string) ( $index + 1 );
			$md   .= "\n" . $label . ': <' . $pick['url'] . '|' . $pick['title'] . '> (' . $pick['date'] . ', ID ' . $pick['post_id'] . ')';
			if ( '' !== $pick['reason'] ) {
				$md .= "\n   " . $pick['reason'];
			}
			if ( $pick['headline_index'] >= 0 && isset( $result['headlines'][ $pick['headline_index'] ] ) ) {
				$md .= ' Ties to headline ' . ( $pick['headline_index'] + 1 ) . '.';
			}
		}

		foreach ( $notes as $note ) {
			$md .= "\n\n_" . $note . '_';
		}

		if ( 'preview' === $result['status'] ) {
			$md .= "\n\nSay \"save it\" for a draft or \"publish it\" to make it live.";
		}

		return $md;
	}
}
