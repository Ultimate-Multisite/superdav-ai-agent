<?php

declare(strict_types=1);
/**
 * Transitional adapter: exposes the static BudgetManager class as an
 * injectable instance implementing BudgetCheckerInterface.
 *
 * Remove this class if/when BudgetManager is refactored into a proper DI
 * service — at that point, update Plugin::configure() to bind
 * BudgetCheckerInterface directly to BudgetManager::class.
 *
 * @package SdAiAgent\Infrastructure\Adapters
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Infrastructure\Adapters;

use SdAiAgent\Contracts\BudgetCheckerInterface;
use SdAiAgent\Core\BudgetManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper that satisfies BudgetCheckerInterface by delegating the
 * single check_budget() call to the existing static BudgetManager method.
 *
 * This bridge exists so code can depend on the interface (and receive a fake
 * in tests) without modifying BudgetManager's public static API, which is
 * covered by PHPUnit tests that call it statically.
 */
class BudgetManagerAdapter implements BudgetCheckerInterface {

	/**
	 * {@inheritdoc}
	 */
	public function check_budget(): true|WP_Error {
		return BudgetManager::check_budget();
	}
}
