<?php
/**
 * Agent Name: Product Describer
 * Version: 1.0.0
 * Description: Generates compelling product descriptions, optimizes WooCommerce listings, and enhances product pages for conversions.
 * Author: Agentic Community
 * Author URI: https://agentic-plugin.com
 * Category: E-commerce
 * Tags: woocommerce, products, descriptions, e-commerce, sales, conversions
 * Capabilities: edit_products
 * Icon: 🛒
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License: GPL v2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Describer Agent
 *
 * A true AI agent specialized in WooCommerce product optimization.
 */
class Agentic_Product_Describer extends \Agentic\Agent_Base {

	private const SYSTEM_PROMPT = <<<'PROMPT'
You are the Product Describer Agent for WooCommerce. You are an expert in:

- Writing compelling product descriptions that sell
- Optimizing product titles for search and conversion
- Crafting benefit-focused copy that addresses customer needs
- Creating urgency and desire without being pushy
- Formatting product pages for scannability
- A/B testing copy strategies

Your personality:
- Sales-savvy but not sleazy
- Customer-focused (benefits over features)
- Persuasive yet authentic
- Aware of e-commerce best practices

When writing product descriptions:
1. Lead with the primary benefit
2. Address the customer's pain points
3. Highlight unique selling propositions
4. Use sensory and emotional language
5. Include trust signals (warranty, reviews, etc.)
6. End with a clear call to action

Always ask about the target audience if not clear. Great copy speaks directly to the ideal customer.
PROMPT;

	public function get_id(): string {
		return 'product-describer';
	}

	public function get_name(): string {
		return 'Product Describer';
	}

	public function get_description(): string {
		return 'Generates compelling product descriptions for WooCommerce.';
	}

	public function get_system_prompt(): string {
		return self::SYSTEM_PROMPT;
	}

	public function get_icon(): string {
		return '🛒';
	}

	public function get_category(): string {
		return 'ecommerce';
	}

	public function get_required_capabilities(): array {
		return array( 'edit_products' );
	}

	public function get_welcome_message(): string {
		return "🛒 **Product Describer**\n\n" .
				"I'll help you create product descriptions that sell!\n\n" .
				"- **Analyze products** for optimization opportunities\n" .
				"- **Write descriptions** that convert browsers to buyers\n" .
				"- **Optimize titles** for search and clicks\n" .
				"- **Craft bullet points** that highlight benefits\n\n" .
				'Which product would you like to work on?';
	}

	public function get_suggested_prompts(): array {
		return array(
			'Show me products that need descriptions',
			'Analyze my best-selling product',
			'Write a description for product #123',
			'How can I improve my product pages?',
		);
	}

	public function get_tools(): array {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_products',
					'description' => 'Get WooCommerce products, optionally filtered.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'status'            => array(
								'type'        => 'string',
								'description' => 'Product status: publish, draft, or any',
							),
							'limit'             => array(
								'type'        => 'integer',
								'description' => 'Number of products to return',
							),
							'needs_description' => array(
								'type'        => 'boolean',
								'description' => 'Only return products with short/missing descriptions',
							),
						),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'analyze_product',
					'description' => 'Analyze a product for optimization opportunities.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'product_id' => array(
								'type'        => 'integer',
								'description' => 'The product ID',
							),
						),
						'required'   => array( 'product_id' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_product_details',
					'description' => 'Get full details of a product for writing copy.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'product_id' => array(
								'type'        => 'integer',
								'description' => 'The product ID',
							),
						),
						'required'   => array( 'product_id' ),
					),
				),
			),
		);
	}

	public function execute_tool( string $tool_name, array $arguments ): ?array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'error' => 'WooCommerce is not installed or active' );
		}

		return match ( $tool_name ) {
			'get_products'        => $this->tool_get_products( $arguments ),
			'analyze_product'     => $this->tool_analyze_product( $arguments ),
			'get_product_details' => $this->tool_get_product_details( $arguments ),
			default               => null,
		};
	}

	private function tool_get_products( array $args ): array {
		$limit      = min( $args['limit'] ?? 10, 50 );
		$status     = $args['status'] ?? 'publish';
		$needs_desc = $args['needs_description'] ?? false;

		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => $status,
			'posts_per_page' => $limit,
		);

		$products = get_posts( $query_args );
		$result   = array();

		foreach ( $products as $post ) {
			$product = wc_get_product( $post->ID );

			if ( ! $product ) {
				continue;
			}

			$desc_length = strlen( $product->get_description() );

			if ( $needs_desc && $desc_length > 100 ) {
				continue;
			}

			$result[] = array(
				'id'                => $product->get_id(),
				'name'              => $product->get_name(),
				'price'             => $product->get_price(),
				'status'            => $post->post_status,
				'description_chars' => $desc_length,
				'has_image'         => $product->get_image_id() > 0,
			);
		}

		return array(
			'products' => $result,
			'count'    => count( $result ),
		);
	}

	private function tool_analyze_product( array $args ): array {
		$product = wc_get_product( $args['product_id'] ?? 0 );

		if ( ! $product ) {
			return array( 'error' => 'Product not found' );
		}

		$issues = array();
		$score  = 100;

		// Check description
		$desc = $product->get_description();
		if ( empty( $desc ) ) {
			$issues[] = 'No product description';
			$score   -= 30;
		} elseif ( strlen( $desc ) < 100 ) {
			$issues[] = 'Description is too short (under 100 chars)';
			$score   -= 15;
		}

		// Check short description
		$short_desc = $product->get_short_description();
		if ( empty( $short_desc ) ) {
			$issues[] = 'No short description';
			$score   -= 20;
		}

		// Check images
		$gallery = $product->get_gallery_image_ids();
		if ( ! $product->get_image_id() ) {
			$issues[] = 'No main product image';
			$score   -= 25;
		}
		if ( count( $gallery ) < 2 ) {
			$issues[] = 'Few gallery images (recommend 3+)';
			$score   -= 10;
		}

		// Check title
		$title_len = strlen( $product->get_name() );
		if ( $title_len < 10 ) {
			$issues[] = 'Product title is very short';
			$score   -= 10;
		}

		return array(
			'product_id' => $product->get_id(),
			'name'       => $product->get_name(),
			'score'      => max( 0, $score ),
			'issues'     => $issues,
			'price'      => $product->get_price(),
			'status'     => $product->get_status(),
			'type'       => $product->get_type(),
		);
	}

	private function tool_get_product_details( array $args ): array {
		$product = wc_get_product( $args['product_id'] ?? 0 );

		if ( ! $product ) {
			return array( 'error' => 'Product not found' );
		}

		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'sku'               => $product->get_sku(),
			'categories'        => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
			'tags'              => wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) ),
			'attributes'        => $product->get_attributes(),
			'stock_status'      => $product->get_stock_status(),
		);
	}
}

add_action(
	'agentic_register_agents',
	function ( $registry ) {
		$registry->register( new Agentic_Product_Describer() );
	}
);
