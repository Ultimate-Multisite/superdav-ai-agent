<?php

declare(strict_types=1);

namespace SdAiAgent\Tests\Core;

use SdAiAgent\Core\TurnContext;
use WP_UnitTestCase;

/** Provider-only turn context is appended and removed exactly. */
final class TurnContextTest extends WP_UnitTestCase {

	/** Appending then stripping returns the user's text unchanged. */
	public function test_strip_removes_only_the_trailing_context_block(): void {
		$block = "<context>\n\n## Current Context\n- **Email**: dev@example.test\n\n</context>";
		$text  = TurnContext::append( 'tell me an original joke', $block );

		$this->assertSame( "tell me an original joke\n\n" . $block, $text );
		$this->assertSame( 'tell me an original joke', TurnContext::strip( $text ) );
		$this->assertSame( 'plain text', TurnContext::append( 'plain text', '' ) );
		$this->assertSame( 'Explain <context> tags in HTML.', TurnContext::strip( 'Explain <context> tags in HTML.' ) );
	}

	/** Input items before the boundary lose their context; later items keep it. */
	public function test_strip_input_items_respects_the_boundary(): void {
		$with  = TurnContext::append( 'second', "<context>\n\nnew\n\n</context>" );
		$input = array(
			array(
				'role'    => 'user',
				'content' => TurnContext::append( 'first', "<context>\n\nold\n\n</context>" ),
			),
			array(
				'type'   => 'function_call_output',
				'output' => '<context> literal in a tool result',
			),
			array(
				'role'    => 'user',
				'content' => $with,
			),
		);

		$stripped = TurnContext::strip_input_items( $input, 2 );

		$this->assertSame( 'first', $stripped[0]['content'] );
		$this->assertSame( $input[1], $stripped[1] );
		$this->assertSame( $with, $stripped[2]['content'] );
		$this->assertSame( 'second', TurnContext::strip_input_items( $input )[2]['content'] );
	}
}
