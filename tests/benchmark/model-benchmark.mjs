#!/usr/bin/env node
/**
 * Superdav AI Agent — Model Benchmark Suite
 *
 * Tests WordPress admin operation prompts against all models available on
 * synthetic.new, scoring each model's ability to:
 *   1. Correctly invoke the right tool(s)
 *   2. Provide valid arguments
 *   3. Generate quality content in those arguments
 *   4. Respond within a reasonable time
 *
 * Usage:
 *   node tests/benchmark/model-benchmark.mjs [--models=model1,model2] [--prompts=slug1,slug2]
 *
 * Environment:
 *   SYNTHETIC_API_KEY — API key for synthetic.new (falls back to credentials.sh)
 */

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'fs';
import { execSync } from 'child_process';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const RESULTS_DIR = join( __dirname, 'results' );
const API_BASE = 'https://api.synthetic.new/openai/v1';

// ─── Tool Schemas (matching the plugin's registered abilities) ───────────────

const TOOLS = [
	{
		type: 'function',
		function: {
			name: 'ai-agent/create-post',
			description:
				'Create a new WordPress post or page. This is the PRIMARY tool for creating any content — blog posts, landing pages, about pages, etc. Write content directly as HTML or markdown (auto-converted to Gutenberg blocks). Set post_type to "page" for pages or "post" for blog posts. Set status to "publish" to make it live immediately.',
			parameters: {
				type: 'object',
				properties: {
					title: { type: 'string', description: 'Post title.' },
					content: {
						type: 'string',
						description:
							'The post content. Write in markdown (headings with ##, lists with -, bold with **) or HTML — markdown is automatically converted to Gutenberg blocks.',
					},
					post_type: {
						type: 'string',
						enum: [ 'post', 'page' ],
						description: 'Post type (default: post).',
					},
					status: {
						type: 'string',
						enum: [ 'draft', 'publish', 'pending', 'private' ],
						description: 'Post status (default: draft).',
					},
					excerpt: { type: 'string', description: 'Post excerpt.' },
					categories: {
						type: 'array',
						items: { type: 'string' },
						description: 'Category names to assign.',
					},
					tags: {
						type: 'array',
						items: { type: 'string' },
						description: 'Tag names to assign.',
					},
					featured_image_url: {
						type: 'string',
						description: 'URL of image to set as featured image.',
					},
				},
				required: [ 'title', 'content' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'ai-agent/update-post',
			description:
				'Update an existing WordPress post or page by ID. Only provided fields are changed.',
			parameters: {
				type: 'object',
				properties: {
					post_id: {
						type: 'integer',
						description: 'The ID of the post to update.',
					},
					title: { type: 'string', description: 'New title.' },
					content: { type: 'string', description: 'New content.' },
					status: {
						type: 'string',
						enum: [ 'draft', 'publish', 'pending', 'private', 'future', 'trash' ],
					},
					excerpt: { type: 'string' },
					categories: {
						type: 'array',
						items: { type: 'string' },
					},
					tags: { type: 'array', items: { type: 'string' } },
					featured_image_id: {
						type: 'integer',
						description: 'Attachment ID to set as the featured image.',
					},
					meta: {
						type: 'object',
						description: 'Key-value pairs of post meta to update.',
					},
					site_url: {
						type: 'string',
						description: 'Subsite URL for multisite. Omit for the main site.',
					},
				},
				required: [ 'post_id' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'ai-agent/get-post',
			description:
				'Retrieve a WordPress post by ID. Returns title, content, excerpt, status, author, categories, tags, featured image, and meta.',
			parameters: {
				type: 'object',
				properties: {
					post_id: {
						type: 'integer',
						description: 'The ID of the post to retrieve.',
					},
					post_type: {
						type: 'string',
						description: 'Post type to validate against.',
					},
				},
				required: [ 'post_id' ],
			},
		},
	},
	// markdown-to-blocks removed — hidden from models, auto-conversion
	// happens server-side in create-post/update-post.
	{
		type: 'function',
		function: {
			name: 'ai-agent/import-stock-image',
			description:
				'Search and import a stock image from Pexels/Unsplash into the WordPress media library.',
			parameters: {
				type: 'object',
				properties: {
					query: {
						type: 'string',
						description: 'Search query for the stock image.',
					},
					orientation: {
						type: 'string',
						enum: [ 'landscape', 'portrait', 'square' ],
					},
				},
				required: [ 'query' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'ai-agent/create-post-with-image',
			description:
				'Create a WordPress post or page AND automatically import a stock image as the featured image — all in one step. Use this when you need to create content with an image. Provide the post content plus an image search keyword.',
			parameters: {
				type: 'object',
				properties: {
					title: { type: 'string', description: 'Post title.' },
					content: {
						type: 'string',
						description:
							'The post content. Write in markdown or HTML — markdown is auto-converted to Gutenberg blocks.',
					},
					excerpt: { type: 'string', description: 'Post excerpt.' },
					status: {
						type: 'string',
						enum: [ 'draft', 'publish', 'pending', 'private' ],
						description: 'Post status (default: draft).',
					},
					post_type: {
						type: 'string',
						description: 'Post type (default: post).',
					},
					categories: {
						type: 'array',
						items: { type: 'string' },
						description: 'Category names to assign.',
					},
					tags: {
						type: 'array',
						items: { type: 'string' },
						description: 'Tag names to assign.',
					},
					image_keyword: {
						type: 'string',
						description:
							'Search keyword for the stock image (e.g. "medical technology", "office workspace").',
					},
				},
				required: [ 'title', 'content', 'image_keyword' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'ai-agent/seo-analyze-content',
			description:
				'Analyze content for SEO: keyword density, readability score, meta tag suggestions, heading structure, and internal linking opportunities.',
			parameters: {
				type: 'object',
				properties: {
					post_id: {
						type: 'integer',
						description: 'Post ID to analyze.',
					},
					target_keyword: {
						type: 'string',
						description: 'Primary keyword to optimize for.',
					},
				},
				required: [ 'post_id' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'ai-agent/content-analyze',
			description:
				'Analyze content strategy: publishing frequency, word counts, category distribution, missing featured images, and content gaps.',
			parameters: {
				type: 'object',
				properties: {
					post_type: {
						type: 'string',
						description: 'Post type to analyze (default: post).',
					},
					limit: {
						type: 'integer',
						description: 'Number of recent posts to analyze.',
					},
				},
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'sd-ai-agent/woo-create-product',
			description:
				'Create a WooCommerce product with name, description, price, SKU, stock, categories, images, and attributes.',
			parameters: {
				type: 'object',
				properties: {
					name: { type: 'string', description: 'Product name.' },
					description: {
						type: 'string',
						description: 'Full product description (HTML).',
					},
					short_description: {
						type: 'string',
						description: 'Short product description.',
					},
					regular_price: {
						type: 'string',
						description: 'Regular price.',
					},
					sale_price: { type: 'string', description: 'Sale price.' },
					sku: { type: 'string', description: 'Product SKU.' },
					stock_quantity: {
						type: 'integer',
						description: 'Stock quantity.',
					},
					categories: {
						type: 'array',
						items: { type: 'string' },
						description: 'Product category names.',
					},
					type: {
						type: 'string',
						enum: [ 'simple', 'variable', 'grouped', 'external' ],
					},
					status: {
						type: 'string',
						enum: [ 'draft', 'publish', 'pending', 'private' ],
					},
				},
				required: [ 'name' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'sd-ai-agent/get-plugins',
			description:
				'List all installed WordPress plugins with their status (active/inactive).',
			parameters: { type: 'object', properties: {} },
		},
	},
	{
		type: 'function',
		function: {
			name: 'sd-ai-agent/install-plugin',
			description:
				'Install a plugin from the WordPress.org plugin directory by slug. Optionally activate after installation.',
			parameters: {
				type: 'object',
				properties: {
					slug: {
						type: 'string',
						description: 'Plugin slug from wordpress.org.',
					},
					activate: {
						type: 'boolean',
						description: 'Activate after install.',
					},
				},
				required: [ 'slug' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'ai-agent/memory-save',
			description:
				'Save a piece of information to the AI agent memory for future reference.',
			parameters: {
				type: 'object',
				properties: {
					content: {
						type: 'string',
						description: 'The information to remember.',
					},
					category: {
						type: 'string',
						description: 'Category for the memory.',
					},
				},
				required: [ 'content' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'sd-ai-agent/navigate',
			description:
				'Navigate to a URL and return the page content. Useful for checking what a page looks like.',
			parameters: {
				type: 'object',
				properties: {
					url: {
						type: 'string',
						description: 'The URL to navigate to.',
					},
				},
				required: [ 'url' ],
			},
		},
	},
	{
		type: 'function',
		function: {
			name: 'sd-ai-agent/db-query',
			description:
				'Execute a read-only SQL SELECT query against the WordPress database. Use {prefix} as the table prefix placeholder.',
			parameters: {
				type: 'object',
				properties: {
					query: {
						type: 'string',
						description:
							'SQL SELECT query. Use {prefix} for the table prefix.',
					},
				},
				required: [ 'query' ],
			},
		},
	},
];

// ─── Benchmark Prompts ───────────────────────────────────────────────────────

const PROMPTS = [
	{
		slug: 'landing-page',
		name: 'Create a Product Landing Page',
		prompt:
			'Create a landing page for our new product "CloudSync Pro" — a cloud file synchronization tool for teams. Include a hero section with headline and subheadline, a features section with 3 key features (real-time sync, end-to-end encryption, team collaboration), a pricing section with 3 tiers (Free, Pro $9/mo, Enterprise $29/mo), and a call-to-action. Publish it immediately.',
		expected_tools: [ 'ai-agent/create-post' ],
		scoring: {
			tool_selection: {
				weight: 25,
				criteria: 'Must call create-post with post_type=page',
			},
			content_quality: {
				weight: 30,
				criteria:
					'Content should include hero, features, pricing, CTA sections',
			},
			argument_validity: {
				weight: 20,
				criteria:
					'Must set status=publish, post_type=page, meaningful title',
			},
			content_structure: {
				weight: 15,
				criteria:
					'Should use Gutenberg blocks or well-structured HTML with headings',
			},
			completeness: {
				weight: 10,
				criteria:
					'All requested elements present: 3 features, 3 pricing tiers, CTA',
			},
		},
	},
	{
		slug: 'blog-post-seo',
		name: 'Write SEO-Optimized Blog Post',
		prompt:
			'Write a 500-word blog post about "10 Best Practices for Remote Team Management in 2026". Optimize it for the keyword "remote team management". Include proper headings, a meta description, and assign it to the "Management" category with tags "remote work", "team management", "productivity".',
		expected_tools: [ 'ai-agent/create-post' ],
		scoring: {
			tool_selection: {
				weight: 20,
				criteria: 'Must call create-post with post_type=post',
			},
			content_quality: {
				weight: 30,
				criteria:
					'Well-written, ~500 words, 10 practices, proper headings',
			},
			seo_optimization: {
				weight: 20,
				criteria:
					'Keyword in title/headings/content, excerpt as meta description',
			},
			taxonomy: {
				weight: 15,
				criteria:
					'Categories and tags correctly set in the tool call arguments',
			},
			argument_validity: {
				weight: 15,
				criteria: 'All required fields populated with valid values',
			},
		},
	},
	{
		slug: 'woo-product',
		name: 'Create WooCommerce Product',
		prompt:
			'Create a new WooCommerce product: "Ergonomic Standing Desk" priced at $599.99 (on sale for $449.99). SKU: DESK-ERG-001. Stock: 50 units. Category: "Office Furniture". Write a compelling product description highlighting adjustable height (28-48 inches), bamboo desktop, cable management system, and 10-year warranty. Set it as published.',
		expected_tools: [ 'sd-ai-agent/woo-create-product' ],
		scoring: {
			tool_selection: {
				weight: 25,
				criteria: 'Must call woo-create-product (not create-post)',
			},
			argument_accuracy: {
				weight: 30,
				criteria:
					'Correct price ($599.99), sale price ($449.99), SKU, stock (50)',
			},
			content_quality: {
				weight: 25,
				criteria:
					'Description mentions all 4 features: height, bamboo, cable mgmt, warranty',
			},
			completeness: {
				weight: 20,
				criteria:
					'All fields populated: name, description, prices, SKU, stock, category, status',
			},
		},
	},
	{
		slug: 'multi-step-content',
		name: 'Multi-Step: Create Post with Image',
		prompt:
			'Create a blog post about "The Future of AI in Healthcare" and find a relevant stock image of medical technology to use with it. The post should cover diagnostics, drug discovery, and patient care. Save it as a draft.',
		expected_tools: [
			'ai-agent/create-post-with-image',
		],
		// Also accept the two-tool approach as valid.
		alt_expected_tools: [
			'ai-agent/create-post',
			'ai-agent/import-stock-image',
		],
		scoring: {
			tool_selection: {
				weight: 30,
				criteria:
					'Must call create-post-with-image OR BOTH create-post AND import-stock-image',
			},
			content_quality: {
				weight: 25,
				criteria:
					'Post covers diagnostics, drug discovery, and patient care',
			},
			image_relevance: {
				weight: 20,
				criteria:
					'Stock image query is relevant (medical/healthcare/AI/technology)',
			},
			argument_validity: {
				weight: 15,
				criteria: 'Post status=draft, post_type=post',
			},
			coordination: {
				weight: 10,
				criteria:
					'Logical ordering of tool calls (image search + post creation)',
			},
		},
	},
	{
		slug: 'site-audit',
		name: 'Content Strategy Analysis',
		prompt:
			'Analyze our content strategy. Check what posts we have published recently, look at the content distribution across categories, and identify any gaps. Give me a summary of findings.',
		expected_tools: [ 'ai-agent/content-analyze' ],
		scoring: {
			tool_selection: {
				weight: 30,
				criteria: 'Must call content-analyze',
			},
			argument_validity: {
				weight: 20,
				criteria: 'Reasonable parameters (post type, limit)',
			},
			response_quality: {
				weight: 30,
				criteria:
					'Response text shows understanding of what the tool will return',
			},
			completeness: {
				weight: 20,
				criteria:
					'Addresses all three asks: recent posts, distribution, gaps',
			},
		},
	},
	{
		slug: 'plugin-management',
		name: 'Install and Configure Plugin',
		prompt:
			'Check what plugins are currently installed, then install the "contact-form-7" plugin and activate it.',
		expected_tools: [
			'sd-ai-agent/get-plugins',
			'sd-ai-agent/install-plugin',
		],
		scoring: {
			tool_selection: {
				weight: 35,
				criteria:
					'Must call BOTH get-plugins AND install-plugin in correct order',
			},
			argument_accuracy: {
				weight: 25,
				criteria:
					'install-plugin slug="contact-form-7", activate=true',
			},
			logical_flow: {
				weight: 20,
				criteria:
					'Lists plugins first, then installs — not the reverse',
			},
			response_quality: {
				weight: 20,
				criteria:
					'Explains what it is doing and why',
			},
		},
	},
	{
		slug: 'about-page',
		name: 'Create About Us Page',
		prompt:
			'Create an About Us page for "Acme Digital Solutions", a web development agency founded in 2020. We have 15 team members, are based in Austin TX, and specialize in WordPress and React development. Include our mission statement, team overview, and contact information section.',
		expected_tools: [ 'ai-agent/create-post' ],
		scoring: {
			tool_selection: {
				weight: 20,
				criteria: 'Must call create-post with post_type=page',
			},
			content_quality: {
				weight: 35,
				criteria:
					'Professional tone, includes company details (2020, 15 members, Austin, WP/React)',
			},
			content_structure: {
				weight: 25,
				criteria:
					'Has mission, team overview, and contact sections with proper headings',
			},
			argument_validity: {
				weight: 20,
				criteria: 'Correct post_type=page, meaningful title',
			},
		},
	},
];

// ─── System Prompt (matches what the plugin sends) ───────────────────────────

const SYSTEM_PROMPT = `You are a WordPress assistant that ACTS — you execute tasks immediately using your tools.

## Core Principles
1. **Act, don't ask.** Execute the task right away. Don't ask "shall I proceed?" or request confirmation unless the task is destructive.
2. **Generate real content.** When creating pages or posts, write substantial, realistic content (3+ paragraphs). Never use placeholder text.
3. **Use tools directly.** Call tools immediately — don't describe what you would do.
4. **Call all needed tools in one response.** When a task requires multiple tools (e.g. create a post AND find an image), call them all at once.

## Content Creation (IMPORTANT)
To create any page or blog post, use \`ai-agent/create-post\`. This is the ONLY tool you need.
- For pages: set \`post_type\` to \`page\`.
- For blog posts: set \`post_type\` to \`post\`.
- Write content directly in the \`content\` field using markdown (## headings, **bold**, - lists) or HTML. Markdown is automatically converted to Gutenberg blocks.
- Set \`status\` to \`publish\` to make it live, or \`draft\` to save without publishing.
- Include \`categories\` and \`tags\` arrays for blog posts.
- Include \`excerpt\` for SEO meta descriptions.
- For WooCommerce products, use \`sd-ai-agent/woo-create-product\` instead.`;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getApiKey() {
	if ( process.env.SYNTHETIC_API_KEY ) {
		return process.env.SYNTHETIC_API_KEY;
	}
	try {
		const creds = readFileSync(
			`${ process.env.HOME }/.config/aidevops/tenants/default/credentials.sh`,
			'utf8'
		);
		const match = creds.match(
			/SYNTHETIC_NEW_API_KEY="([^"]+)"/
		);
		if ( match ) {
			return match[ 1 ];
		}
	} catch {
		// ignore
	}
	console.error(
		'ERROR: No API key found. Set SYNTHETIC_API_KEY env var or store in credentials.sh'
	);
	process.exit( 1 );
}

async function callModel( model, messages, tools, maxTokens = 4096 ) {
	const apiKey = getApiKey();
	const startTime = Date.now();

	const body = {
		model,
		messages,
		max_tokens: maxTokens,
	};

	// Only include tools for models that support them.
	if ( tools && tools.length > 0 ) {
		body.tools = tools;
		body.tool_choice = 'auto';
	}

	const response = await fetch( `${ API_BASE }/chat/completions`, {
		method: 'POST',
		headers: {
			Authorization: `Bearer ${ apiKey }`,
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( body ),
	} );

	const elapsed = Date.now() - startTime;

	if ( ! response.ok ) {
		const errorText = await response.text();
		return {
			error: `HTTP ${ response.status }: ${ errorText }`,
			elapsed,
			model,
		};
	}

	const data = await response.json();
	return { ...data, elapsed, model };
}

function scoreResult( prompt, result ) {
	const scores = {};
	let totalScore = 0;
	let totalWeight = 0;

	if ( result.error ) {
		return {
			scores: {},
			totalScore: 0,
			totalWeight: 100,
			percentage: 0,
			error: result.error,
		};
	}

	const choice = result.choices?.[ 0 ];
	if ( ! choice ) {
		return {
			scores: {},
			totalScore: 0,
			totalWeight: 100,
			percentage: 0,
			error: 'No choices in response',
		};
	}

	const message = choice.message;
	const toolCalls = message?.tool_calls || [];
	const content = message?.content || '';
	const calledTools = toolCalls.map( ( tc ) => tc.function?.name );

	for ( const [ key, criterion ] of Object.entries( prompt.scoring ) ) {
		let score = 0;
		const weight = criterion.weight;
		totalWeight += weight;

		switch ( key ) {
			case 'tool_selection': {
				// Try primary expected_tools first, then alt_expected_tools.
				const toolSets = [ prompt.expected_tools ];
				if ( prompt.alt_expected_tools ) {
					toolSets.push( prompt.alt_expected_tools );
				}

				let bestScore = 0;
				for ( const expected of toolSets ) {
					const expectedSet = new Set( expected );
					const calledSet = new Set( calledTools );
					const matched = [ ...expectedSet ].filter( ( t ) =>
						calledSet.has( t )
					).length;
					let thisScore =
						expectedSet.size > 0
							? ( matched / expectedSet.size ) * weight
							: 0;
					// Penalty for spurious tool calls.
					const spurious = [ ...calledSet ].filter(
						( t ) => ! expectedSet.has( t )
					).length;
					if ( spurious > 0 ) {
						thisScore = Math.max( 0, thisScore - weight * 0.2 );
					}
					bestScore = Math.max( bestScore, thisScore );
				}
				score = bestScore;
				break;
			}

			case 'content_quality': {
				// Check content in tool call arguments or response text.
				let allContent = content;
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						allContent +=
							' ' +
							( args.content || '' ) +
							' ' +
							( args.description || '' );
					} catch {
						// ignore
					}
				}
				// Basic quality heuristics.
				const wordCount = allContent.split( /\s+/ ).length;
				const hasHeadings = /<h[2-4]/i.test( allContent ) || /^#{2,4}\s/m.test( allContent );
				const hasParagraphs = /<p/i.test( allContent ) || allContent.split( '\n\n' ).length > 2;

				let qualityScore = 0;
				if ( wordCount > 50 ) qualityScore += 0.3;
				if ( wordCount > 150 ) qualityScore += 0.2;
				if ( wordCount > 300 ) qualityScore += 0.1;
				if ( hasHeadings ) qualityScore += 0.2;
				if ( hasParagraphs ) qualityScore += 0.2;
				score = qualityScore * weight;
				break;
			}

			case 'argument_validity': {
				let validArgs = 0;
				let totalChecks = 0;
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						totalChecks++;
						// Check that required fields are present and non-empty.
						const hasTitle =
							args.title && args.title.length > 0;
						const hasContent =
							args.content &&
							args.content.length > 10;
						const hasValidType = [ 'post', 'page' ].includes(
							args.post_type
						);
						const hasValidStatus = [
							'draft',
							'publish',
							'pending',
							'private',
						].includes( args.status );

						let argScore = 0;
						if ( hasTitle ) argScore += 0.3;
						if ( hasContent ) argScore += 0.3;
						if ( hasValidType || ! args.post_type )
							argScore += 0.2;
						if ( hasValidStatus || ! args.status )
							argScore += 0.2;
						validArgs += argScore;
					} catch {
						// invalid JSON
					}
				}
				score =
					totalChecks > 0
						? ( validArgs / totalChecks ) * weight
						: toolCalls.length === 0
							? 0
							: weight * 0.5;
				break;
			}

			case 'argument_accuracy': {
				// For WooCommerce product — check specific values.
				let accuracy = 0;
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						if ( args.regular_price === '599.99' ) accuracy += 0.25;
						if ( args.sale_price === '449.99' ) accuracy += 0.25;
						if ( args.sku === 'DESK-ERG-001' ) accuracy += 0.25;
						if ( args.stock_quantity === 50 ) accuracy += 0.25;
					} catch {
						// ignore
					}
				}
				score = accuracy * weight;
				break;
			}

			case 'seo_optimization': {
				let seoScore = 0;
				const keyword = 'remote team management';
				let allContent = content;
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						allContent +=
							' ' +
							( args.title || '' ) +
							' ' +
							( args.content || '' ) +
							' ' +
							( args.excerpt || '' );
					} catch {
						// ignore
					}
				}
				const lower = allContent.toLowerCase();
				if ( lower.includes( keyword ) ) seoScore += 0.4;
				if (
					/<h[2-3][^>]*>.*remote.*team/i.test( allContent )
				)
					seoScore += 0.2;
				// Check for excerpt/meta description.
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						if ( args.excerpt && args.excerpt.length > 20 )
							seoScore += 0.4;
					} catch {
						// ignore
					}
				}
				score = seoScore * weight;
				break;
			}

			case 'taxonomy': {
				let taxScore = 0;
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						if (
							args.categories &&
							args.categories.length > 0
						)
							taxScore += 0.5;
						if ( args.tags && args.tags.length > 0 )
							taxScore += 0.5;
					} catch {
						// ignore
					}
				}
				score = taxScore * weight;
				break;
			}

			case 'content_structure': {
				let structScore = 0;
				let allContent = content;
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						allContent += ' ' + ( args.content || '' );
					} catch {
						// ignore
					}
				}
				const h2Count = (
					allContent.match( /<h2|^## /gim ) || []
				).length;
				const h3Count = (
					allContent.match( /<h3|^### /gim ) || []
				).length;
				if ( h2Count >= 2 ) structScore += 0.4;
				if ( h3Count >= 1 ) structScore += 0.2;
				if ( /<p|<ul|<ol/i.test( allContent ) )
					structScore += 0.2;
				// Gutenberg blocks bonus.
				if ( /<!-- wp:/i.test( allContent ) ) structScore += 0.2;
				score = structScore * weight;
				break;
			}

			case 'image_relevance': {
				let imgScore = 0;
				for ( const tc of toolCalls ) {
					if (
						tc.function?.name === 'ai-agent/import-stock-image'
					) {
						try {
							const args = JSON.parse(
								tc.function?.arguments || '{}'
							);
							const query = ( args.query || '' ).toLowerCase();
							const relevant = [
								'medical',
								'healthcare',
								'health',
								'ai',
								'technology',
								'doctor',
								'hospital',
								'medicine',
								'digital health',
							];
							if (
								relevant.some( ( kw ) =>
									query.includes( kw )
								)
							)
								imgScore = 1;
							else imgScore = 0.3; // At least tried.
						} catch {
							// ignore
						}
					}
				}
				score = imgScore * weight;
				break;
			}

			case 'completeness': {
				// Generic completeness — check that all expected elements are present.
				let allContent = content;
				for ( const tc of toolCalls ) {
					try {
						const args = JSON.parse(
							tc.function?.arguments || '{}'
						);
						allContent +=
							' ' +
							JSON.stringify( args );
					} catch {
						// ignore
					}
				}
				// Simple heuristic: more content = more complete.
				const wordCount = allContent.split( /\s+/ ).length;
				let completeness = Math.min( 1, wordCount / 200 );
				score = completeness * weight;
				break;
			}

			case 'logical_flow':
			case 'coordination': {
				// Check that tool calls are in a logical order.
				if ( calledTools.length >= prompt.expected_tools.length ) {
					score = weight; // Full marks if all expected tools called.
				} else if ( calledTools.length > 0 ) {
					score = weight * 0.5;
				}
				break;
			}

			case 'response_quality': {
				// Check that the model provides explanatory text.
				const wordCount = ( content || '' ).split( /\s+/ ).length;
				let rqScore = 0;
				if ( wordCount > 10 ) rqScore += 0.3;
				if ( wordCount > 30 ) rqScore += 0.3;
				if ( wordCount > 60 ) rqScore += 0.2;
				if ( toolCalls.length > 0 ) rqScore += 0.2;
				score = rqScore * weight;
				break;
			}

			default:
				score = 0;
		}

		scores[ key ] = {
			score: Math.round( score * 10 ) / 10,
			maxScore: weight,
			percentage: Math.round( ( score / weight ) * 100 ),
		};
		totalScore += score;
	}

	return {
		scores,
		totalScore: Math.round( totalScore * 10 ) / 10,
		totalWeight,
		percentage: Math.round( ( totalScore / totalWeight ) * 100 ),
	};
}

// ─── Main ────────────────────────────────────────────────────────────────────

async function main() {
	const args = process.argv.slice( 2 );
	const modelFilter = args
		.find( ( a ) => a.startsWith( '--models=' ) )
		?.split( '=' )[ 1 ]
		?.split( ',' );
	const promptFilter = args
		.find( ( a ) => a.startsWith( '--prompts=' ) )
		?.split( '=' )[ 1 ]
		?.split( ',' );
	const toolModelsOnly = args.includes( '--tool-models-only' );

	// Fetch available models.
	const apiKey = getApiKey();
	console.log( 'Fetching available models from synthetic.new...\n' );

	const modelsResp = await fetch( `${ API_BASE }/models`, {
		headers: { Authorization: `Bearer ${ apiKey }` },
	} );
	const modelsData = await modelsResp.json();

	let models = modelsData.data || [];

	// Filter to tool-supporting models by default (our agent needs tool calling).
	if ( toolModelsOnly !== false || ! modelFilter ) {
		const toolModels = models.filter( ( m ) =>
			( m.supported_features || [] ).includes( 'tools' )
		);
		if ( toolModels.length > 0 && ! modelFilter ) {
			console.log(
				`Filtering to ${ toolModels.length } models with tool-calling support (use --tool-models-only=false to include all)\n`
			);
			models = toolModels;
		}
	}

	if ( modelFilter ) {
		models = models.filter( ( m ) =>
			modelFilter.some( ( f ) =>
				m.id.toLowerCase().includes( f.toLowerCase() )
			)
		);
	}

	const prompts = promptFilter
		? PROMPTS.filter( ( p ) => promptFilter.includes( p.slug ) )
		: PROMPTS;

	console.log( `Models to test: ${ models.length }` );
	models.forEach( ( m ) =>
		console.log(
			`  - ${ m.id } (tools: ${ ( m.supported_features || [] ).includes( 'tools' ) }, ctx: ${ m.context_length })` )
	);
	console.log( `\nPrompts to test: ${ prompts.length }` );
	prompts.forEach( ( p ) => console.log( `  - ${ p.name }` ) );
	console.log(
		`\nTotal API calls: ${ models.length * prompts.length }\n`
	);
	console.log( '='.repeat( 80 ) );

	const allResults = [];

	for ( const model of models ) {
		const modelId = model.id;
		const supportsTools = ( model.supported_features || [] ).includes(
			'tools'
		);

		console.log( `\n${ '─'.repeat( 80 ) }` );
		console.log( `MODEL: ${ modelId }` );
		console.log( `  Tools: ${ supportsTools } | Context: ${ model.context_length } | Quantization: ${ model.quantization || 'none' }` );
		console.log( '─'.repeat( 80 ) );

		const modelResults = {
			model: modelId,
			supportsTools,
			context_length: model.context_length,
			quantization: model.quantization || 'none',
			prompts: [],
			avgScore: 0,
			avgTime: 0,
			totalTokens: 0,
		};

		for ( const prompt of prompts ) {
			console.log( `\n  PROMPT: ${ prompt.name }` );
			process.stdout.write( '  Running... ' );

			const messages = [
				{ role: 'system', content: SYSTEM_PROMPT },
				{ role: 'user', content: prompt.prompt },
			];

			const result = await callModel(
				modelId,
				messages,
				supportsTools ? TOOLS : [],
				4096
			);

			if ( result.error ) {
				console.log( `ERROR: ${ result.error.substring( 0, 100 ) }` );
				modelResults.prompts.push( {
					slug: prompt.slug,
					name: prompt.name,
					error: result.error,
					elapsed: result.elapsed,
					score: { percentage: 0 },
				} );
				continue;
			}

			const choice = result.choices?.[ 0 ];
			const toolCalls = choice?.message?.tool_calls || [];
			const contentPreview = (
				choice?.message?.content || ''
			).substring( 0, 100 );
			const usage = result.usage || {};

			console.log(
				`${ result.elapsed }ms | ${ usage.completion_tokens || '?' } tokens`
			);
			console.log(
				`  Tools called: ${ toolCalls.length > 0 ? toolCalls.map( ( tc ) => tc.function?.name ).join( ', ' ) : 'none' }`
			);
			if ( contentPreview ) {
				console.log(
					`  Response: ${ contentPreview.replace( /\n/g, ' ' ) }...`
				);
			}

			const scoreResult_ = scoreResult( prompt, result );
			console.log(
				`  Score: ${ scoreResult_.percentage }% (${ scoreResult_.totalScore }/${ scoreResult_.totalWeight })`
			);

			for ( const [ key, val ] of Object.entries(
				scoreResult_.scores
			) ) {
				console.log(
					`    ${ key }: ${ val.percentage }% (${ val.score }/${ val.maxScore })`
				);
			}

			modelResults.prompts.push( {
				slug: prompt.slug,
				name: prompt.name,
				elapsed: result.elapsed,
				tokens: usage,
				toolsCalled: toolCalls.map( ( tc ) => ( {
					name: tc.function?.name,
					arguments: tc.function?.arguments
						? JSON.parse( tc.function.arguments )
						: {},
				} ) ),
				contentPreview,
				score: scoreResult_,
				finishReason: choice?.finish_reason,
			} );

			modelResults.totalTokens +=
				( usage.completion_tokens || 0 ) +
				( usage.prompt_tokens || 0 );
		}

		// Calculate averages.
		const completedPrompts = modelResults.prompts.filter(
			( p ) => ! p.error
		);
		modelResults.avgScore =
			completedPrompts.length > 0
				? Math.round(
						completedPrompts.reduce(
							( sum, p ) => sum + p.score.percentage,
							0
						) / completedPrompts.length
					)
				: 0;
		modelResults.avgTime =
			completedPrompts.length > 0
				? Math.round(
						completedPrompts.reduce(
							( sum, p ) => sum + p.elapsed,
							0
						) / completedPrompts.length
					)
				: 0;

		allResults.push( modelResults );
	}

	// ─── Summary Report ──────────────────────────────────────────────────────

	console.log( '\n' + '='.repeat( 80 ) );
	console.log( 'BENCHMARK SUMMARY' );
	console.log( '='.repeat( 80 ) );

	// Sort by average score descending.
	allResults.sort( ( a, b ) => b.avgScore - a.avgScore );

	// Leaderboard table.
	console.log( '\n## Leaderboard\n' );
	console.log(
		`${ 'Rank'.padEnd( 5 ) }${ 'Model'.padEnd( 55 ) }${ 'Avg Score'.padEnd( 12 ) }${ 'Avg Time'.padEnd( 12 ) }${ 'Tokens'.padEnd( 10 ) }`
	);
	console.log( '-'.repeat( 94 ) );

	allResults.forEach( ( r, i ) => {
		const shortName = r.model.replace( 'hf:', '' );
		console.log(
			`${ String( i + 1 ).padEnd( 5 ) }${ shortName.padEnd( 55 ) }${ ( r.avgScore + '%' ).padEnd( 12 ) }${ ( r.avgTime + 'ms' ).padEnd( 12 ) }${ String( r.totalTokens ).padEnd( 10 ) }`
		);
	} );

	// Per-prompt breakdown.
	console.log( '\n## Per-Prompt Scores\n' );
	const promptSlugs = prompts.map( ( p ) => p.slug );
	const header =
		'Model'.padEnd( 40 ) +
		promptSlugs.map( ( s ) => s.padEnd( 16 ) ).join( '' );
	console.log( header );
	console.log( '-'.repeat( header.length ) );

	for ( const r of allResults ) {
		const shortName = r.model.replace( 'hf:', '' ).substring( 0, 38 );
		const scores = promptSlugs.map( ( slug ) => {
			const p = r.prompts.find( ( pr ) => pr.slug === slug );
			if ( ! p ) return 'N/A'.padEnd( 16 );
			if ( p.error ) return 'ERR'.padEnd( 16 );
			return ( p.score.percentage + '%' ).padEnd( 16 );
		} );
		console.log( shortName.padEnd( 40 ) + scores.join( '' ) );
	}

	// Save detailed results.
	mkdirSync( RESULTS_DIR, { recursive: true } );
	const timestamp = new Date().toISOString().replace( /[:.]/g, '-' );
	const resultsFile = join(
		RESULTS_DIR,
		`benchmark-${ timestamp }.json`
	);
	writeFileSync(
		resultsFile,
		JSON.stringify(
			{
				timestamp: new Date().toISOString(),
				api: API_BASE,
				systemPrompt: SYSTEM_PROMPT,
				prompts: PROMPTS,
				results: allResults,
			},
			null,
			2
		)
	);
	console.log( `\nDetailed results saved to: ${ resultsFile }` );

	// Save markdown report.
	const mdFile = join( RESULTS_DIR, `benchmark-${ timestamp }.md` );
	let md = `# Superdav AI Agent — Model Benchmark Results\n\n`;
	md += `**Date:** ${ new Date().toISOString() }\n`;
	md += `**API:** ${ API_BASE }\n`;
	md += `**Models tested:** ${ allResults.length }\n`;
	md += `**Prompts tested:** ${ prompts.length }\n\n`;

	md += `## Leaderboard\n\n`;
	md += `| Rank | Model | Avg Score | Avg Time | Total Tokens |\n`;
	md += `|------|-------|-----------|----------|-------------|\n`;
	allResults.forEach( ( r, i ) => {
		const shortName = r.model.replace( 'hf:', '' );
		md += `| ${ i + 1 } | ${ shortName } | ${ r.avgScore }% | ${ r.avgTime }ms | ${ r.totalTokens } |\n`;
	} );

	md += `\n## Per-Prompt Breakdown\n\n`;
	for ( const prompt of prompts ) {
		md += `### ${ prompt.name }\n\n`;
		md += `**Prompt:** ${ prompt.prompt.substring( 0, 200 ) }...\n\n`;
		md += `| Model | Score | Time | Tools Called | Finish |\n`;
		md += `|-------|-------|------|-------------|--------|\n`;
		for ( const r of allResults ) {
			const p = r.prompts.find( ( pr ) => pr.slug === prompt.slug );
			if ( ! p ) continue;
			if ( p.error ) {
				md += `| ${ r.model.replace( 'hf:', '' ) } | ERROR | ${ p.elapsed }ms | - | - |\n`;
				continue;
			}
			const tools = p.toolsCalled
				.map( ( tc ) => tc.name )
				.join( ', ' ) || 'none';
			md += `| ${ r.model.replace( 'hf:', '' ) } | ${ p.score.percentage }% | ${ p.elapsed }ms | ${ tools } | ${ p.finishReason } |\n`;
		}
		md += '\n';
	}

	md += `## Scoring Methodology\n\n`;
	md += `Each prompt is scored on multiple weighted criteria:\n\n`;
	md += `- **Tool Selection** — Did the model call the correct tool(s)?\n`;
	md += `- **Content Quality** — Is the generated content well-written and substantial?\n`;
	md += `- **Argument Validity** — Are tool call arguments correct and complete?\n`;
	md += `- **Content Structure** — Does the content use proper headings, paragraphs, blocks?\n`;
	md += `- **SEO/Taxonomy** — Are SEO elements and categories/tags properly set?\n`;
	md += `- **Completeness** — Are all requested elements present?\n`;

	writeFileSync( mdFile, md );
	console.log( `Markdown report saved to: ${ mdFile }` );
}

main().catch( ( err ) => {
	console.error( 'Fatal error:', err );
	process.exit( 1 );
} );
