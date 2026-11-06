<?php
/**
 * Topic lede screenshot and Publish button in Slack.
 *
 * After the curator saves a module from a Slack turn, a background job
 * screenshots the module, uploads the image to the same thread, and posts a
 * Publish button. A click on Publish makes the draft module live.
 *
 * @package PRC\Platform\Block_Area_Modules
 */

declare( strict_types=1 );

namespace PRC\Platform\Block_Area_Modules;

use PRC\Platform\Slack\Action_Button;
use PRC\Platform\Slack\Connection;
use PRC\Platform\Slack\Nexus\Session\Session_Key;
use PRC\Platform\Slack\Nexus\Turn_Context;
use PRC\Platform\Slack\Response_Url;
use WP_Error;
use WP_Post;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Screenshots a curated module into its Slack thread and publishes it on click.
 */
class Topic_Lede_Preview {

	public const ACTION_HOOK  = 'prc_block_area_modules_topic_lede_preview';
	public const ACTION_GROUP = 'prc-block-area-modules';

	public const PUBLISH_ACTION_ID = 'prc_topic_lede_publish';
	public const LINK_ACTION_ID    = 'prc_topic_lede_link';
	public const VIEW_ACTION_ID    = 'prc_topic_lede_view';

	public const SIGNED_FETCH_PURPOSE = 'topic-lede-preview';

	public const SELECTOR        = '.wp-block-post-content';
	public const VIEWPORT_WIDTH  = 1200;
	public const VIEWPORT_HEIGHT = 1000;
	public const WAIT_MS         = 8000;

	/**
	 * Register hooks.
	 *
	 * @param Loader|null $loader Plugin loader. Null in tests.
	 */
	public function __construct( $loader = null ) {
		if ( null === $loader ) {
			return;
		}
		$loader->add_action( self::ACTION_HOOK, $this, 'capture', 10, 1 );
		$loader->add_action( 'prc_slack_block_action', $this, 'handle_publish', 10, 1 );
	}

	/**
	 * Whether the screenshot service and Slack can both be reached.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		if ( ! class_exists( '\PRC\Platform\Firebase' ) || ! class_exists( Connection::class ) ) {
			return false;
		}
		$endpoints = apply_filters( 'prc_platform_firebase_render_endpoints', array() );
		return is_array( $endpoints ) && ! empty( $endpoints['screenshot_element'] );
	}

	/**
	 * Queue the screenshot job when the write ran in a Slack turn.
	 *
	 * @param array $module   Module summary from Topic_Lede_Writer::write_module().
	 * @param int   $actor_id Editor credited with the module.
	 * @return bool Whether a job was queued.
	 */
	public static function maybe_schedule( array $module, int $actor_id ): bool {
		$module_id = (int) ( $module['id'] ?? 0 );
		if ( $module_id <= 0 || ! class_exists( Turn_Context::class ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}

		$context = Turn_Context::current();
		if ( null === $context || ! self::is_configured() ) {
			return false;
		}

		$action_id = as_enqueue_async_action(
			self::ACTION_HOOK,
			array(
				array(
					'module_id' => $module_id,
					'thread'    => $context['key']->reference(),
					'user_id'   => $actor_id,
				),
			),
			self::ACTION_GROUP
		);

		return is_numeric( $action_id ) && (int) $action_id > 0;
	}

	/**
	 * Action Scheduler callback: screenshot the module and post it in the thread.
	 *
	 * Failures post one warning in the thread and do not throw, so a retry
	 * cannot post the same warning twice.
	 *
	 * @hook prc_block_area_modules_topic_lede_preview
	 *
	 * @param array $args Job args: module_id, thread, user_id.
	 */
	public function capture( $args ): void {
		if ( ! class_exists( Session_Key::class ) || ! class_exists( Connection::class ) ) {
			return;
		}

		$args      = is_array( $args ) ? $args : array();
		$module_id = (int) ( $args['module_id'] ?? 0 );
		$key       = Session_Key::from_reference( (string) ( $args['thread'] ?? '' ) );
		$post      = $module_id > 0 ? get_post( $module_id ) : null;
		if ( null === $key || ! $post instanceof WP_Post || Content_Type::$post_type !== $post->post_type ) {
			return;
		}

		$summary = self::module_summary( $post );
		$png     = $this->screenshot( self::capture_url( $post ) );
		if ( is_wp_error( $png ) ) {
			$this->warn( $key, $summary, $png );
			return;
		}

		$file = $this->upload(
			$png,
			sprintf( 'topic-lede-%s-%d.png', sanitize_file_name( '' !== $summary['term_slug'] ? $summary['term_slug'] : 'module' ), $module_id ),
			$key,
			sprintf( 'Topic lede preview: %s', $summary['term_name'] ),
			sprintf( '*Preview: %s topic lede*', $summary['term_name'] )
		);
		if ( is_wp_error( $file ) ) {
			$this->warn( $key, $summary, $file );
			return;
		}

		$this->post_message( self::buttons_payload( $key->channel_id, $key->thread_ts, $summary ) );
	}

	/**
	 * Publish a curated draft when an editor clicks Publish.
	 *
	 * @hook prc_slack_block_action
	 *
	 * @param array $payload Verified Slack interactivity payload.
	 */
	public function handle_publish( $payload ): void {
		$payload = is_array( $payload ) ? $payload : array();
		$action  = is_array( $payload['actions'][0] ?? null ) ? $payload['actions'][0] : array();
		if ( self::PUBLISH_ACTION_ID !== ( $action['action_id'] ?? null ) ) {
			return;
		}

		$response_url = is_string( $payload['response_url'] ?? null ) ? $payload['response_url'] : '';
		$member_id    = is_string( $payload['user']['id'] ?? null ) ? strtoupper( $payload['user']['id'] ) : '';
		$module_id    = is_string( $action['value'] ?? null ) && ctype_digit( $action['value'] ) ? (int) $action['value'] : 0;
		if ( '' === $member_id || ! Response_Url::accepts( $response_url ) ) {
			return;
		}

		[ $text, $published ] = $this->publish( $module_id, $member_id );

		$this->reply(
			$response_url,
			$published
				? array(
					'replace_original' => true,
					'text'             => $text,
				)
				: array(
					'response_type'    => 'ephemeral',
					'replace_original' => false,
					'text'             => $text,
				)
		);
	}

	/**
	 * Check the click and publish the module.
	 *
	 * @param int    $module_id Module from the button value.
	 * @param string $member_id Slack member who clicked.
	 * @return array{0:string,1:bool} Reply text, and whether the module went live.
	 */
	public function publish( int $module_id, string $member_id ): array {
		$not_published = ' Nothing was published.';

		$user = $this->user_for_member( $member_id );
		if ( ! $user instanceof WP_User || $user->ID <= 0 ) {
			return array( 'Your Slack account is not linked to a WordPress editor. Connect Slack on your WordPress profile and try again.' . $not_published, false );
		}

		$post = $module_id > 0 ? get_post( $module_id ) : null;
		if ( ! $post instanceof WP_Post || Content_Type::$post_type !== $post->post_type ) {
			return array( 'This module no longer exists.' . $not_published, false );
		}

		$summary = self::module_summary( $post );
		if ( 'publish' === $post->post_status ) {
			return array( sprintf( 'The %s topic lede is already live.', $summary['term_name'] ), false );
		}
		if ( 'draft' !== $post->post_status ) {
			return array( 'This module is no longer a draft.' . $not_published, false );
		}
		if ( ! get_post_meta( $post->ID, Topic_Lede_Writer::AUDIT_META, true ) ) {
			return array( 'Only modules made by the topic lede curator can be published from Slack.' . $not_published, false );
		}
		if ( ! user_can( $user, 'publish_post', $post->ID ) ) {
			return array( 'You cannot publish block modules. Ask an editor who can, or publish it from WordPress.' . $not_published, false );
		}

		$previous = get_current_user_id();
		wp_set_current_user( $user->ID );
		try {
			$updated = wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'publish',
				),
				true
			);
		} finally {
			wp_set_current_user( $previous );
		}

		if ( is_wp_error( $updated ) ) {
			return array( 'Publishing failed: ' . $updated->get_error_message() . $not_published, false );
		}

		return array(
			sprintf(
				':white_check_mark: <@%s> published the %s topic lede. It is live on <%s|the topic page>.',
				$member_id,
				$summary['term_name'],
				$summary['archive_url']
			),
			true,
		);
	}

	/**
	 * URL the screenshot service loads. Drafts get a signed-fetch ticket.
	 *
	 * @param WP_Post $post Module.
	 * @return string
	 */
	public static function capture_url( WP_Post $post ): string {
		$url = (string) get_permalink( $post );
		if ( 'publish' === $post->post_status || ! function_exists( '\PRC\Primitives\Signed_Fetch\sign_url' ) ) {
			return $url;
		}
		return \PRC\Primitives\Signed_Fetch\sign_url( $url, self::SIGNED_FETCH_PURPOSE, (int) $post->ID );
	}

	/**
	 * Request body for screenshotElement.
	 *
	 * @param string $url Page URL.
	 * @return array<string, mixed>
	 */
	public static function screenshot_args( string $url ): array {
		return array(
			'url'            => $url,
			'selector'       => self::SELECTOR,
			'waitMs'         => self::WAIT_MS,
			'viewportWidth'  => self::VIEWPORT_WIDTH,
			'viewportHeight' => self::VIEWPORT_HEIGHT,
			'captureElement' => true,
		);
	}

	/**
	 * Fields the Slack messages need.
	 *
	 * @param WP_Post $post Module.
	 * @return array{id:int,status:string,edit_url:string,archive_url:string,term_name:string,term_slug:string}
	 */
	public static function module_summary( WP_Post $post ): array {
		$terms = get_the_terms( $post, 'category' );
		$term  = is_array( $terms ) && isset( $terms[0] ) ? $terms[0] : null;
		$link  = null !== $term ? get_term_link( $term ) : '';

		return array(
			'id'          => (int) $post->ID,
			'status'      => (string) $post->post_status,
			'edit_url'    => admin_url( 'post.php?post=' . (int) $post->ID . '&action=edit' ),
			'archive_url' => is_string( $link ) ? $link : '',
			'term_name'   => null !== $term ? html_entity_decode( $term->name, ENT_QUOTES ) : 'this',
			'term_slug'   => null !== $term ? (string) $term->slug : '',
		);
	}

	/**
	 * Message body (chat.postMessage) with the Publish and Edit buttons.
	 *
	 * A published module gets a topic page link instead of Publish.
	 *
	 * @param string $channel_id Conversation id.
	 * @param string $thread_ts  Thread root ts.
	 * @param array  $summary    Module summary.
	 * @return array<string, mixed>
	 */
	public static function buttons_payload( string $channel_id, string $thread_ts, array $summary ): array {
		$is_live = 'publish' === $summary['status'];
		$text    = $is_live
			? sprintf( 'The %s topic lede is live on <%s|the topic page>.', $summary['term_name'], $summary['archive_url'] )
			: sprintf( 'This is the draft %s topic lede. Publish makes it live on the topic page. The current module stays published, so you can roll back.', $summary['term_name'] );

		$buttons = array();
		if ( $is_live ) {
			if ( '' !== $summary['archive_url'] ) {
				$buttons[] = Action_Button::link( 'View topic page', $summary['archive_url'], self::VIEW_ACTION_ID );
			}
		} else {
			$buttons[] = Action_Button::action(
				'Publish',
				self::PUBLISH_ACTION_ID,
				(string) $summary['id'],
				sprintf( 'Publish the %s topic lede? It goes live on the topic page now.', $summary['term_name'] ),
				'Publish topic lede',
				'Publish',
				'primary'
			);
		}
		$buttons[] = Action_Button::link( 'Edit in WordPress', $summary['edit_url'], self::LINK_ACTION_ID );

		return array(
			'channel'      => $channel_id,
			'thread_ts'    => $thread_ts,
			'text'         => $text,
			'unfurl_links' => false,
			'unfurl_media' => false,
			'blocks'       => array(
				array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => $text,
					),
				),
				array(
					'type'     => 'actions',
					'elements' => array_map( static fn( Action_Button $button ): array => $button->to_block_element(), $buttons ),
				),
			),
		);
	}

	/**
	 * Post one warning in the thread.
	 *
	 * @param Session_Key $key     Thread.
	 * @param array       $summary Module summary.
	 * @param WP_Error    $error   Failure.
	 */
	private function warn( Session_Key $key, array $summary, WP_Error $error ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[prc-block-area-modules] Topic lede preview for module %d failed: %s', $summary['id'], $error->get_error_message() ) );

		$this->post_message(
			array(
				'channel'      => $key->channel_id,
				'thread_ts'    => $key->thread_ts,
				'unfurl_links' => false,
				'text'         => sprintf(
					':warning: I could not make a screenshot of the %s topic lede. <%s|Review it in WordPress>.',
					$summary['term_name'],
					$summary['edit_url']
				),
			)
		);
	}

	/**
	 * Capture the page.
	 *
	 * @param string $url Page URL.
	 * @return string|WP_Error PNG bytes.
	 */
	protected function screenshot( string $url ) {
		return ( new \PRC\Platform\Firebase() )->screenshot_element( self::screenshot_args( $url ) );
	}

	/**
	 * Upload the PNG to the thread.
	 *
	 * @param string      $png      PNG bytes.
	 * @param string      $filename File name.
	 * @param Session_Key $key      Thread.
	 * @param string      $title    File title.
	 * @param string      $comment  mrkdwn posted with the file.
	 * @return string|WP_Error Slack file id.
	 */
	protected function upload( string $png, string $filename, Session_Key $key, string $title, string $comment ) {
		return Connection::resolve()->upload_file( $png, $filename, $key->channel_id, $key->thread_ts, $title, $comment );
	}

	/**
	 * Post a chat.postMessage body.
	 *
	 * @param array<string, mixed> $payload Message.
	 */
	protected function post_message( array $payload ): void {
		$posted = Connection::resolve()->post_message( $payload );
		if ( is_wp_error( $posted ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[prc-block-area-modules] Topic lede preview message failed: ' . $posted->get_error_message() );
		}
	}

	/**
	 * Reply to a click.
	 *
	 * @param string               $response_url Slack response URL.
	 * @param array<string, mixed> $payload      Reply.
	 */
	protected function reply( string $response_url, array $payload ): void {
		Response_Url::post( $response_url, $payload );
	}

	/**
	 * WordPress user linked to a Slack member.
	 *
	 * @param string $member_id Slack member id.
	 * @return WP_User|null
	 */
	protected function user_for_member( string $member_id ): ?WP_User {
		return Connection::resolve()->wp_user_for_member( $member_id );
	}
}
