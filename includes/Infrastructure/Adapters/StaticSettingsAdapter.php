<?php

declare(strict_types=1);
/**
 * Transitional adapter: exposes the Settings singleton as an injectable
 * instance implementing SettingsProviderInterface.
 *
 * After t192, Settings methods are instance-only; this adapter delegates to
 * Settings::instance() so non-DI legacy code and DI-managed callers share the
 * same singleton. Update Plugin::configure() to \DI\autowire(Settings::class)
 * and remove this adapter once all callers receive Settings via DI injection.
 *
 * @package GratisAiAgent\Infrastructure\Adapters
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Infrastructure\Adapters;

use GratisAiAgent\Contracts\SettingsProviderInterface;
use GratisAiAgent\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper that satisfies SettingsProviderInterface by delegating every
 * call to the Settings singleton instance.
 *
 * Uses Settings::instance() so DI-managed callers receive the same singleton
 * as non-DI legacy code.
 */
class StaticSettingsAdapter implements SettingsProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get( ?string $key = null ): mixed {
		return Settings::instance()->get( $key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function update( array $data ): bool {
		return Settings::instance()->update( $data );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_defaults(): array {
		return Settings::instance()->get_defaults();
	}
}
