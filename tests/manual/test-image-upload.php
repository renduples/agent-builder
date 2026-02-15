<?php
/**
 * Manual test: image upload to LLM via REST API.
 *
 * Usage: cd /Users/r3n13r/Code/agentic && wp eval-file wp-content/plugins/agent-builder/tests/manual/test-image-upload.php
 */

// Set current user to admin.
wp_set_current_user( 1 );

// 8x8 red PNG as base64 data URL.
$img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAEklEQVR4nGP4z8CAFWEXHbQSACj/P8Fu7N9hAAAAAElFTkSuQmCC';

// Build the REST request.
$request = new WP_REST_Request( 'POST', '/agentic/v1/chat' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body(
	wp_json_encode(
		array(
			'message'    => 'What color is this image? Reply in one word only.',
			'agent_id'   => 'content-builder',
			'session_id' => wp_generate_uuid4(),
			'history'    => array(),
			'image'      => $img,
		)
	)
);

echo "Sending image to LLM (xAI/grok-3)...\n";

// Temporarily switch to vision-capable model.
$original_model = get_option( 'agentic_model' );

// Try multiple xAI vision model names.
$vision_models = array( 'grok-2-vision-latest' );

foreach ( $vision_models as $model_name ) {
	echo "\n--- {$model_name}: base64 via REST API (temp upload) ---\n";
	update_option( 'agentic_model', $model_name );

	// Build the REST request with image.
	$request2 = new WP_REST_Request( 'POST', '/agentic/v1/chat' );
	$request2->set_header( 'Content-Type', 'application/json' );
	$request2->set_body(
		wp_json_encode(
			array(
				'message'    => 'What color is this image? Reply in one word only.',
				'agent_id'   => 'content-builder',
				'session_id' => wp_generate_uuid4(),
				'history'    => array(),
				'image'      => $img,
			)
		)
	);

	$rest     = new \Agentic\REST_API();
	$response = $rest->handle_chat( $request2 );
	$data     = $response->get_data();

	echo 'Status: ' . $response->get_status() . "\n";
	if ( ! empty( $data['error'] ) ) {
		echo 'Error: ' . ( $data['response'] ?? 'unknown' ) . "\n";
	} else {
		echo 'Response: ' . substr( $data['response'] ?? 'N/A', 0, 500 ) . "\n";
		echo 'Tokens: ' . ( $data['tokens_used'] ?? 'N/A' ) . "\n";
	}
}

// Restore original model.
update_option( 'agentic_model', $original_model );

echo "\nDone.\n";
echo 'Tokens: ' . ( $data['tokens_used'] ?? 'N/A' ) . "\n";
