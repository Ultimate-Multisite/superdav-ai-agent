<?php

declare(strict_types=1);
/**
 * Contract for AI API spend budget enforcement.
 *
 * Decouples callers (AgentLoop, REST controllers) from the static
 * BudgetManager class so tests can inject a fake that always allows or
 * always blocks, without touching WordPress options or the database.
 *
 * @package GratisAiAgent\Contracts
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Contracts;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface BudgetCheckerInterface
 *
 * Single-method contract that matches BudgetManager::check_budget().
 * The interface is intentionally narrow — callers need only ask "may I
 * proceed?" and act on the WP_Error message when the answer is no.
 */
interface BudgetCheckerInterface {

	/**
	 * Check whether the current spend is within the configured budget caps.
	 *
	 * Returns true when the request may proceed, or a WP_Error describing
	 * which cap (daily / monthly) has been exceeded and when it resets.
	 *
	 * @return true|WP_Error True to proceed; WP_Error when budget is exhausted.
	 */
	public function check_budget(): true|WP_Error;
}
