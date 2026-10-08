<?php

declare(strict_types=1);

namespace SdAiAgent\Infrastructure\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Invalid PSR-16 cache key. */
final class CacheInvalidArgumentException extends \InvalidArgumentException {}
