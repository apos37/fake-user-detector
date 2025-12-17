jQuery( function ( $ ) {
    console.log( 'Fudetector Retry User Registration JS loaded...' );

    $( document ).on( 'click', '.fudetector-retry-user', function( e ) {
		e.preventDefault();

		const $link   = $( this );
		const entryId = $link.data( 'entry-id' );
		const formId  = $link.data( 'form-id' );

		$link.text( fudetector_retry_user_registration.creating ).prop( 'disabled', true );

		$.ajax( {
			url: fudetector_retry_user_registration.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'fudetector_retry_user_registration',
				entry_id: entryId,
				form_id: formId,
				nonce: fudetector_retry_user_registration.nonce
			},
			success: function( response ) {
				console.group( 'Fudetector Retry User Registration' );
				console.log( 'Full Response:', response );

				if ( response.success ) {
					console.log( 'Mapped Fields:', response.data.mapped_fields );
					console.log( 'User Data Extracted:', response.data.user_data );
					console.groupEnd();

					$link.closest( 'tr' ).find( '.fudetector-user-not-created' ).replaceWith(
						'<span class="fudetector-user-exists" style="color:green; font-weight:bold;">' +
						fudetector_retry_user_registration.success + ' (User ID: ' + response.data.user_id + ') ' +
						'</span>'
					);
					$link.remove();
				} else {
					console.groupEnd();
					alert( fudetector_retry_user_registration.error + ' ' + response.data );
					$link.text( 'Retry' ).prop( 'disabled', false );
				}
			},
			error: function( xhr, status, error ) {
				console.error( 'AJAX Error:', error, xhr.responseText );
				alert( fudetector_retry_user_registration.error + ' ' + error );
				$link.text( 'Retry' ).prop( 'disabled', false );
			}
		} );
	} );
} );
