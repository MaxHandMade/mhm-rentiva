<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use WP_UnitTestCase;

/**
 * Locks out the "core reads a key we never set" defect in the rating handler.
 *
 * wp_new_comment() reads comment_author, comment_author_email and
 * comment_author_url before it applies any default of its own, so an array that
 * omits them makes every review submitted on a WP_DEBUG site emit seven
 * "Undefined array key" warnings plus a trim()-on-null deprecation.
 *
 * What these tests actually assert: a live PHP error handler is installed around
 * a REAL wp_new_comment() call and every diagnostic PHP raises inside it is
 * captured. test_spy_sees_the_diagnostics_when_the_array_is_under_populated() is
 * the negative control -- it proves the spy fires in this environment, so the
 * zero-diagnostic assertions in the other tests mean something.
 */
class VehicleRatingNoticeTest extends WP_UnitTestCase
{
	/** @var array<int, string> */
	private array $diagnostics = array();

	private bool $spy_active = false;

	private int $vehicle_id = 0;

	private int $user_id = 0;

	/** @var string|null */
	private $remote_addr_backup = null;

	public function setUp(): void
	{
		parent::setUp();

		// The suite runs with backupGlobals="false" and SecurityHelperTest
		// unsets $_SERVER['REMOTE_ADDR'] without restoring it, so whether this
		// key exists here depends on test order. wp_new_comment() reads it
		// unguarded to default comment_author_IP, and a real web request always
		// carries it -- pin it so this file measures the handler, not the order
		// PHPUnit happened to pick.
		$this->remote_addr_backup = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
		$_SERVER['REMOTE_ADDR']   = '127.0.0.1';

		VehicleRatingForm::register();

		$this->vehicle_id = (int) $this->factory->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Rating Notice Vehicle',
			)
		);

		$this->user_id = (int) $this->factory->user->create(
			array(
				'role'         => 'customer',
				'user_login'   => 'rating_reviewer',
				'display_name' => 'Rating Reviewer',
				'user_email'   => 'rating-reviewer@example.com',
				'user_url'     => 'https://reviewer.example.com',
			)
		);
	}

	public function tearDown(): void
	{
		if ($this->spy_active) {
			restore_error_handler();
			$this->spy_active = false;
		}

		$_POST = array();
		$this->flush_comments_settings_cache();

		if (null === $this->remote_addr_backup) {
			unset($_SERVER['REMOTE_ADDR']);
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->remote_addr_backup;
		}

		parent::tearDown();
	}

	/**
	 * CommentsSettings memoises its merged settings in a private static, which
	 * only save_settings()/delete_settings() clear; a plain update_option() in a
	 * test would otherwise be invisible to the handler.
	 */
	private function flush_comments_settings_cache(): void
	{
		$property = new \ReflectionProperty(\MHMRentiva\Admin\Settings\Comments\CommentsSettings::class, 'runtime_cache');
		$property->setAccessible(true);
		$property->setValue(null, null);
	}

	/**
	 * Capture every PHP diagnostic raised until stop_spy() is called.
	 *
	 * Registered last, so it runs ahead of PHPUnit's own handler; returning true
	 * stops the chain, which keeps the surrounding test output clean while the
	 * diagnostics themselves stay visible to the assertions.
	 */
	private function start_spy(): void
	{
		$this->diagnostics = array();
		$this->spy_active  = true;

		set_error_handler(
			function (int $errno, string $errstr, string $errfile = '', int $errline = 0): bool {
				$this->diagnostics[] = sprintf('[%d] %s in %s:%d', $errno, $errstr, $errfile, $errline);
				return true;
			},
			E_ALL
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function stop_spy(): array
	{
		if ($this->spy_active) {
			restore_error_handler();
			$this->spy_active = false;
		}

		return $this->diagnostics;
	}

	/**
	 * Runs the AJAX handler and returns the diagnostics raised while it ran.
	 *
	 * @return array<int, string>
	 */
	private function run_handler_capturing_diagnostics(): array
	{
		// Two hard exits stand between this handler and PHPUnit, and both have to
		// be converted to exceptions or the process simply stops mid-test:
		//   1. wp_send_json() calls a bare die() unless wp_doing_ajax() is true;
		//   2. once it IS true, wp_die() dispatches through 'wp_die_ajax_handler'
		//      -- and WP_UnitTestCase only replaces 'wp_die_handler', so the
		//      default AJAX handler would still die().
		// wp_doing_ajax() being true is also the honest state for an AJAX handler.
		add_filter('wp_doing_ajax', '__return_true');
		add_filter('wp_die_ajax_handler', array( $this, 'get_throwing_ajax_die_handler' ));

		ob_start();

		$this->start_spy();

		try {
			VehicleRatingForm::ajax_submit_rating();
		} catch (\WPDieException $e) {
			// wp_send_json_*() ends the request; in the test suite that is a
			// WPDieException, which is the normal exit for this handler.
			unset($e);
		} finally {
			$captured = $this->stop_spy();
			ob_end_clean();
			remove_filter('wp_die_ajax_handler', array( $this, 'get_throwing_ajax_die_handler' ));
			remove_filter('wp_doing_ajax', '__return_true');
		}

		return $captured;
	}

	/**
	 * Replaces core's _ajax_wp_die_handler() with one that throws instead of
	 * calling die(), so the handler's normal exit stays inside PHPUnit.
	 *
	 * @return callable
	 */
	public function get_throwing_ajax_die_handler(): callable
	{
		return static function ($message = '', $title = '', $args = array()): void {
			unset($title, $args);
			throw new \WPDieException(is_string($message) ? $message : '');
		};
	}

	/**
	 * NEGATIVE CONTROL -- proves the spy can see this exact defect class.
	 *
	 * Passes wp_new_comment() the array shape the handler used before the fix
	 * (post, content, type, approval and user_id only) and asserts the captured
	 * diagnostics name the three keys core reads before defaulting. If this test
	 * ever goes green with an empty capture, the two locks below are vacuous.
	 */
	public function test_spy_sees_the_diagnostics_when_the_array_is_under_populated(): void
	{
		wp_set_current_user($this->user_id);

		// Exactly the array the handler built before the fix: the rating meta is
		// there (ReviewEnforcer rejects the comment outright without it), the
		// three author keys core reads before defaulting are not.
		$under_populated = array(
			'comment_post_ID'  => $this->vehicle_id,
			'comment_content'  => 'Under-populated control comment for the notice spy.',
			'comment_type'     => 'review',
			'comment_approved' => 1,
			'comment_meta'     => array( 'rating' => 5 ),
			'user_id'          => $this->user_id,
		);

		$this->start_spy();
		$comment_id = wp_new_comment($under_populated, true);
		$captured   = $this->stop_spy();

		$this->assertNotEmpty(
			$captured,
			'The error handler spy captured nothing at all -- it is not wired to PHP, so no "zero notices" assertion in this file would mean anything.'
		);

		$joined = implode("\n", $captured);

		$this->assertStringContainsString('comment_author', $joined, 'Expected core to complain about comment_author.');
		$this->assertStringContainsString('comment_author_email', $joined, 'Expected core to complain about comment_author_email.');
		$this->assertStringContainsString('comment_author_url', $joined, 'Expected core to complain about comment_author_url.');

		// Sanity: the control really did insert a comment, so the warnings above
		// came from the live insert path and not from an aborted call.
		$this->assertIsInt($comment_id);
		$this->assertGreaterThan(0, $comment_id);
	}

	/**
	 * THE LOCK -- a logged-in submission through the real handler raises nothing.
	 */
	public function test_logged_in_submission_raises_no_php_diagnostics(): void
	{
		wp_set_current_user($this->user_id);

		$_POST = array(
			'action'     => 'mhmrentiva_submit_rating',
			'nonce'      => wp_create_nonce('mhmrentiva_rating_nonce'),
			'vehicle_id' => (string) $this->vehicle_id,
			'rating'     => '5',
			'comment'    => 'A perfectly ordinary review written by a logged-in customer.',
		);

		$captured = $this->run_handler_capturing_diagnostics();

		$this->assertSame(
			array(),
			$captured,
			"Submitting a rating while logged in raised PHP diagnostics:\n" . implode("\n", $captured)
		);

		// The rating still has to land, or "zero notices" would just mean the
		// handler bailed out early.
		$comments = get_comments(
			array(
				'post_id' => $this->vehicle_id,
				'user_id' => $this->user_id,
			)
		);

		$this->assertCount(1, $comments);

		$comment = $comments[0];

		$this->assertSame('Rating Reviewer', $comment->comment_author);
		$this->assertSame('rating-reviewer@example.com', $comment->comment_author_email);
		$this->assertSame('https://reviewer.example.com', $comment->comment_author_url);
		$this->assertSame('5', (string) get_comment_meta((int) $comment->comment_ID, 'mhmrentiva_rating', true));
	}

	/**
	 * The author name falls back to the login exactly the way core does when the
	 * account carries no display name.
	 */
	public function test_author_falls_back_to_user_login_like_core_does(): void
	{
		$nameless_id = (int) $this->factory->user->create(
			array(
				'role'       => 'customer',
				'user_login' => 'nameless_reviewer',
				'user_email' => 'nameless@example.com',
			)
		);

		// factory->user always fills display_name; blank it to reproduce the
		// account state wp_handle_comment_submission() guards against.
		global $wpdb;
		$wpdb->update($wpdb->users, array( 'display_name' => '' ), array( 'ID' => $nameless_id ));
		clean_user_cache($nameless_id);

		wp_set_current_user($nameless_id);

		$_POST = array(
			'action'     => 'mhmrentiva_submit_rating',
			'nonce'      => wp_create_nonce('mhmrentiva_rating_nonce'),
			'vehicle_id' => (string) $this->vehicle_id,
			'rating'     => '4',
			'comment'    => 'A review from an account that has no display name set.',
		);

		$captured = $this->run_handler_capturing_diagnostics();

		$this->assertSame(
			array(),
			$captured,
			"Submitting a rating from a display-name-less account raised PHP diagnostics:\n" . implode("\n", $captured)
		);

		$comments = get_comments(
			array(
				'post_id' => $this->vehicle_id,
				'user_id' => $nameless_id,
			)
		);

		$this->assertCount(1, $comments);
		$this->assertSame('nameless_reviewer', $comments[0]->comment_author);
	}

	/**
	 * The guest branch has to be as complete as the logged-in one: the form
	 * collects no website field, so comment_author_url must still be present and
	 * empty rather than absent.
	 */
	public function test_guest_submission_raises_no_php_diagnostics(): void
	{
		$settings                                     = get_option('mhmrentiva_comments_settings', array());
		$settings['approval']['require_login']        = false;
		$settings['approval']['allow_guest_comments'] = true;
		update_option('mhmrentiva_comments_settings', $settings);
		$this->flush_comments_settings_cache();

		wp_set_current_user(0);

		$_POST = array(
			'action'      => 'mhmrentiva_submit_rating',
			'nonce'       => wp_create_nonce('mhmrentiva_rating_nonce'),
			'vehicle_id'  => (string) $this->vehicle_id,
			'rating'      => '3',
			'comment'     => 'A review left by a visitor who is not signed in at all.',
			'guest_name'  => 'Visiting Guest',
			'guest_email' => 'visiting-guest@example.com',
		);

		$captured = $this->run_handler_capturing_diagnostics();

		$this->assertSame(
			array(),
			$captured,
			"Submitting a guest rating raised PHP diagnostics:\n" . implode("\n", $captured)
		);

		$comments = get_comments(
			array(
				'post_id'      => $this->vehicle_id,
				'author_email' => 'visiting-guest@example.com',
				'status'       => 'all',
			)
		);

		$this->assertCount(1, $comments);
		$this->assertSame('Visiting Guest', $comments[0]->comment_author);
		$this->assertSame('', $comments[0]->comment_author_url);
	}
}
