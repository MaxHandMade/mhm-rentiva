<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Makes a second render inside one PHP process look like a fresh page request.
 *
 * WP_Scripts/WP_Styles are KEPT (replacing them loses the registrations that
 * AssetManager made at boot and breaks dependency resolution). Only the
 * per-request state is cleared, and the queue goes through the official
 * dequeue() because WP_Dependencies caches its dependency walk in a private
 * property that only enqueue()/dequeue() invalidate: emptying `queue` by hand
 * leaves query( $handle, 'enqueued' ) answering from the stale cache.
 */
final class RequestSimulator
{
	/**
	 * @param string[] $inline_handles Handles whose inline before/after data must be forgotten.
	 */
	public static function new_request(array $inline_handles = array()): void
	{
		AbstractShortcode::reset_enqueued_assets_for_tests();

		foreach (array( wp_scripts(), wp_styles() ) as $deps) {
			// Copy first: dequeue() mutates the array being walked.
			foreach (array_values($deps->queue) as $handle) {
				$deps->dequeue($handle);
			}
			$deps->done  = array();
			$deps->to_do = array();

			foreach ($inline_handles as $handle) {
				if (isset($deps->registered[ $handle ])) {
					unset($deps->registered[ $handle ]->extra['before'], $deps->registered[ $handle ]->extra['after']);
				}
			}
		}
	}

	/**
	 * Snapshot of the per-request asset state, to put back with restore().
	 *
	 * @param string[] $inline_handles Handles whose inline before/after data is touched.
	 * @return array<string,mixed>
	 */
	public static function save(array $inline_handles = array()): array
	{
		$state = array();
		foreach (array(
			'scripts' => wp_scripts(),
			'styles'  => wp_styles(),
		) as $kind => $deps) {
			$queue = array();
			foreach ($deps->queue as $handle) {
				$queue[] = isset($deps->args[ $handle ]) ? $handle . '?' . $deps->args[ $handle ] : $handle;
			}
			$extra = array();
			foreach ($inline_handles as $handle) {
				if (isset($deps->registered[ $handle ])) {
					$extra[ $handle ] = array(
						'before' => $deps->registered[ $handle ]->extra['before'] ?? null,
						'after'  => $deps->registered[ $handle ]->extra['after'] ?? null,
					);
				}
			}
			$state[ $kind ] = array(
				'queue' => $queue,
				'done'  => $deps->done,
				'to_do' => $deps->to_do,
				'extra' => $extra,
			);
		}

		return $state;
	}

	/**
	 * @param array<string,mixed> $state Result of save().
	 */
	public static function restore(array $state): void
	{
		foreach (array(
			'scripts' => wp_scripts(),
			'styles'  => wp_styles(),
		) as $kind => $deps) {
			foreach (array_values($deps->queue) as $handle) {
				$deps->dequeue($handle);
			}
			// enqueue() re-adds the handle and invalidates the query cache again.
			$deps->enqueue($state[ $kind ]['queue']);
			$deps->done  = $state[ $kind ]['done'];
			$deps->to_do = $state[ $kind ]['to_do'];

			foreach ($state[ $kind ]['extra'] as $handle => $parts) {
				foreach ($parts as $key => $value) {
					if (null === $value) {
						unset($deps->registered[ $handle ]->extra[ $key ]);
					} else {
						$deps->registered[ $handle ]->extra[ $key ] = $value;
					}
				}
			}
		}
	}
}
