<h2>
	<?php esc_html_e( 'Alpha', 'gatherpress-alpha' ); ?>
</h2>
<p class="description">
	<?php esc_html_e( 'Fix breaking changes to GatherPress', 'gatherpress-alpha' ); ?>
</p>
<p class="submit">
	<button id="gatherpress-alpha" class="button button-primary">
		<?php esc_html_e( 'Fix GatherPress!', 'gatherpress-alpha' ); ?>
	</button>
</p>
<script>
	const gatherPressAlphaButton = document.getElementById('gatherpress-alpha');

	document.getElementById('gatherpress-alpha').addEventListener('click', function(e) {
		e.preventDefault();
		gatherPressAlphaButton.disabled = true;

		// Define the data to be sent in the request
		const data = { action: 'gatherpress_alpha', nonce: '<?php echo wp_create_nonce( 'gatherpress_alpha_nonce' ); ?>' };

		// Create a configuration object for the fetch call
		const fetchConfig = {
			method: 'POST', // Set HTTP method to POST
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded', // Set appropriate content type
			},
			body: new URLSearchParams(data) // Encode your data as URL-encoded string
		};

		// Perform the fetch call to the specified URL
		fetch(window.ajaxurl, fetchConfig)
		.then(response => {
			if (!response.ok) {
				throw new Error('<?php esc_html_e( 'Network response was not ok.', 'gatherpress-alpha' ); ?>'); // Throw an error if response is not ok.
			}

			return response.json(); // Parse JSON from the response.
		})
		.then(response => {
			// Handle the JSON response.
			if (response.success) {
				alert('<?php esc_html_e( 'Success!', 'gatherpress-alpha' ); ?>');
				gatherPressAlphaButton.disabled = false;
			} else {
				alert('<?php esc_html_e( 'Something went wrong!', 'gatherpress-alpha' ); ?>');
				gatherPressAlphaButton.disabled = false;
			}
		})
		.catch(error => {
			console.error(error); // Handle any errors.
		});
	});
</script>
