<?php
/**
 * Test case for MarkdownToBlocks class.
 *
 * @package GratisAiAgent
 * @subpackage Tests
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Tests\Models;

use GratisAiAgent\Models\MarkdownToBlocks;
use WP_UnitTestCase;

/**
 * Test MarkdownToBlocks functionality.
 */
class MarkdownToBlocksTest extends WP_UnitTestCase {

	/**
	 * Test parse returns array.
	 */
	public function test_parse_returns_array() {
		$result = MarkdownToBlocks::parse( 'Hello world' );

		$this->assertIsArray( $result );
	}

	/**
	 * Test parse_inline converts bold text.
	 */
	public function test_parse_inline_bold_with_asterisks() {
		$result = MarkdownToBlocks::parse_inline( '**bold text**' );

		$this->assertSame( '<strong>bold text</strong>', $result );
	}

	/**
	 * Test parse_inline converts bold text with underscores.
	 */
	public function test_parse_inline_bold_with_underscores() {
		$result = MarkdownToBlocks::parse_inline( '__bold text__' );

		$this->assertSame( '<strong>bold text</strong>', $result );
	}

	/**
	 * Test parse_inline converts italic text.
	 */
	public function test_parse_inline_italic_with_asterisks() {
		$result = MarkdownToBlocks::parse_inline( '*italic text*' );

		$this->assertSame( '<em>italic text</em>', $result );
	}

	/**
	 * Test parse_inline converts italic text with underscores.
	 */
	public function test_parse_inline_italic_with_underscores() {
		$result = MarkdownToBlocks::parse_inline( '_italic text_' );

		$this->assertSame( '<em>italic text</em>', $result );
	}

	/**
	 * Test parse_inline converts inline code.
	 */
	public function test_parse_inline_code() {
		$result = MarkdownToBlocks::parse_inline( '`code snippet`' );

		$this->assertSame( '<code>code snippet</code>', $result );
	}

	/**
	 * Test parse_inline converts links.
	 */
	public function test_parse_inline_links() {
		$result = MarkdownToBlocks::parse_inline( '[Link Text](https://example.com)' );

		$this->assertSame( '<a href="https://example.com">Link Text</a>', $result );
	}

	/**
	 * Test make_paragraph returns correct block structure.
	 */
	public function test_make_paragraph_structure() {
		$block = MarkdownToBlocks::make_paragraph( 'Test paragraph' );

		$this->assertSame( 'core/paragraph', $block['blockName'] );
		$this->assertIsArray( $block['attrs'] );
		$this->assertIsArray( $block['innerBlocks'] );
		$this->assertStringContainsString( '<p>Test paragraph</p>', $block['innerHTML'] );
	}

	/**
	 * Test make_heading returns correct block structure.
	 */
	public function test_make_heading_structure() {
		$block = MarkdownToBlocks::make_heading( 'Test Heading', 2 );

		$this->assertSame( 'core/heading', $block['blockName'] );
		$this->assertStringContainsString( '<h2', $block['innerHTML'] );
		$this->assertStringContainsString( 'Test Heading', $block['innerHTML'] );
	}

	/**
	 * Test make_heading with different levels.
	 */
	public function test_make_heading_levels() {
		$h1 = MarkdownToBlocks::make_heading( 'H1', 1 );
		$h3 = MarkdownToBlocks::make_heading( 'H3', 3 );

		$this->assertStringContainsString( '<h1', $h1['innerHTML'] );
		$this->assertStringContainsString( '<h3', $h3['innerHTML'] );
		$this->assertSame( 1, $h1['attrs']['level'] );
		$this->assertSame( 3, $h3['attrs']['level'] );
	}

	/**
	 * Test make_list creates unordered list.
	 */
	public function test_make_list_unordered() {
		$block = MarkdownToBlocks::make_list( [ 'Item 1', 'Item 2' ], false );

		$this->assertSame( 'core/list', $block['blockName'] );
		$this->assertStringContainsString( '<ul>', $block['innerHTML'] );
		$this->assertStringContainsString( '<li>Item 1</li>', $block['innerHTML'] );
		$this->assertStringContainsString( '<li>Item 2</li>', $block['innerHTML'] );
	}

	/**
	 * Test make_list creates ordered list.
	 */
	public function test_make_list_ordered() {
		$block = MarkdownToBlocks::make_list( [ 'First', 'Second' ], true );

		$this->assertSame( 'core/list', $block['blockName'] );
		$this->assertStringContainsString( '<ol>', $block['innerHTML'] );
		$this->assertTrue( $block['attrs']['ordered'] );
	}

	/**
	 * Test make_quote returns correct block structure.
	 */
	public function test_make_quote_structure() {
		$block = MarkdownToBlocks::make_quote( 'Quote content' );

		$this->assertSame( 'core/quote', $block['blockName'] );
		$this->assertStringContainsString( '<blockquote', $block['innerHTML'] );
		$this->assertStringContainsString( 'Quote content', $block['innerHTML'] );
	}

	/**
	 * Test make_code returns correct block structure.
	 */
	public function test_make_code_structure() {
		$block = MarkdownToBlocks::make_code( 'console.log("test");', 'javascript' );

		$this->assertSame( 'core/code', $block['blockName'] );
		$this->assertStringContainsString( '<pre', $block['innerHTML'] );
		$this->assertStringContainsString( '<code>', $block['innerHTML'] );
		$this->assertSame( 'javascript', $block['attrs']['language'] );
	}

	/**
	 * Test make_code escapes HTML.
	 */
	public function test_make_code_escapes_html() {
		$block = MarkdownToBlocks::make_code( '<script>alert("xss")</script>' );

		$this->assertStringNotContainsString( '<script>', $block['innerHTML'] );
		$this->assertStringContainsString( '&lt;script&gt;', $block['innerHTML'] );
	}

	/**
	 * Test make_image returns correct block structure.
	 */
	public function test_make_image_structure() {
		$block = MarkdownToBlocks::make_image( 'https://example.com/image.jpg', 'Alt text' );

		$this->assertSame( 'core/image', $block['blockName'] );
		$this->assertSame( 'https://example.com/image.jpg', $block['attrs']['url'] );
		$this->assertSame( 'Alt text', $block['attrs']['alt'] );
		$this->assertStringContainsString( '<figure', $block['innerHTML'] );
		$this->assertStringContainsString( '<img', $block['innerHTML'] );
	}

	/**
	 * Test make_separator returns correct block structure.
	 */
	public function test_make_separator_structure() {
		$block = MarkdownToBlocks::make_separator();

		$this->assertSame( 'core/separator', $block['blockName'] );
		$this->assertStringContainsString( '<hr', $block['innerHTML'] );
	}

	/**
	 * Test make_table returns correct block structure.
	 */
	public function test_make_table_structure() {
		$headers = [
			[
				'cells' => [
					[ 'content' => 'Header 1', 'tag' => 'th' ],
					[ 'content' => 'Header 2', 'tag' => 'th' ],
				],
			],
		];
		$body = [
			[
				'cells' => [
					[ 'content' => 'Cell 1', 'tag' => 'td' ],
					[ 'content' => 'Cell 2', 'tag' => 'td' ],
				],
			],
		];

		$block = MarkdownToBlocks::make_table( $headers, $body );

		$this->assertSame( 'core/table', $block['blockName'] );
		$this->assertStringContainsString( '<table>', $block['innerHTML'] );
		$this->assertStringContainsString( '<thead>', $block['innerHTML'] );
		$this->assertStringContainsString( '<tbody>', $block['innerHTML'] );
	}

	/**
	 * Test parse handles headings.
	 */
	public function test_parse_headings() {
		$markdown = "# Heading 1\n\n## Heading 2\n\n### Heading 3";
		$blocks = MarkdownToBlocks::parse( $markdown );

		$this->assertCount( 3, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 1, $blocks[0]['attrs']['level'] );
	}

	/**
	 * Test parse handles code blocks.
	 */
	public function test_parse_code_blocks() {
		$markdown = "```php\necho 'Hello';\n```";
		$blocks = MarkdownToBlocks::parse( $markdown );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/code', $blocks[0]['blockName'] );
		$this->assertSame( 'php', $blocks[0]['attrs']['language'] );
	}

	/**
	 * Test parse handles horizontal rules.
	 */
	public function test_parse_horizontal_rules() {
		$markdown = "Before\n\n---\n\nAfter";
		$blocks = MarkdownToBlocks::parse( $markdown );

		$separator = array_filter( $blocks, fn( $b ) => $b['blockName'] === 'core/separator' );
		$this->assertNotEmpty( $separator );
	}

	/**
	 * Test parse handles standalone images.
	 */
	public function test_parse_standalone_images() {
		$markdown = '![Alt text](https://example.com/image.jpg)';
		$blocks = MarkdownToBlocks::parse( $markdown );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/image', $blocks[0]['blockName'] );
	}

	/**
	 * Test parse handles blockquotes.
	 */
	public function test_parse_blockquotes() {
		$markdown = "> This is a quote\n> spanning multiple lines";
		$blocks = MarkdownToBlocks::parse( $markdown );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/quote', $blocks[0]['blockName'] );
	}

	/**
	 * Test parse handles unordered lists.
	 */
	public function test_parse_unordered_lists() {
		$markdown = "- Item 1\n- Item 2\n- Item 3";
		$blocks = MarkdownToBlocks::parse( $markdown );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/list', $blocks[0]['blockName'] );
		$this->assertCount( 3, $blocks[0]['innerBlocks'] );
	}

	/**
	 * Test parse handles ordered lists.
	 */
	public function test_parse_ordered_lists() {
		$markdown = "1. First\n2. Second\n3. Third";
		$blocks = MarkdownToBlocks::parse( $markdown );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'core/list', $blocks[0]['blockName'] );
		$this->assertTrue( $blocks[0]['attrs']['ordered'] );
	}

	/**
	 * Test convert returns serialized block HTML.
	 */
	public function test_convert_returns_string() {
		$result = MarkdownToBlocks::convert( '# Test' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'wp:heading', $result );
	}
}
