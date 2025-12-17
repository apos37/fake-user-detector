jQuery( function ( $ ) {
    console.log( 'Fudetector Quick Scan JS loaded...' );

    let running = false;
    let processedTotal = 0;
    let flaggedTotal   = 0;
    let lastId         = 0;
    let currentRequest = null; // store the current AJAX request

    function runBatch() {
        currentRequest = $.post( ajaxurl, {
            action: 'fudetector_full_scan',
            nonce: fudetector_quick_scan.nonce_scan,
            last_id: lastId,
            batch: fudetector_quick_scan.batch_size
        }, function ( response ) {
            currentRequest = null;

            if ( !response.success ) {
                $( '#fudetector-progress-text' ).text( 'Scan failed.' );
                running = false;
                $( '#fudetector-start-scan' ).prop( 'disabled', false ).text( 'Run Full Scan' );
                $( '#fudetector-cancel-scan' ).hide();
                return;
            }

            if ( !running ) {
                // cancelled during the request, do nothing
                return;
            }

            const data = response.data;

            processedTotal += parseInt( data.processed, 10 ) || 0;
            flaggedTotal   += parseInt( data.flagged_count, 10 ) || 0;

            $( '#fudetector-progress-text' ).text(
                'Processed: ' + processedTotal + ' | Flagged: ' + flaggedTotal
            );

            const percent = Math.min( 100, ( processedTotal / fudetector_quick_scan.total_users ) * 100 );
            $( '#fudetector-progress-bar' ).css( 'width', percent + '%' );

            if ( data.done ) {
                $( '#fudetector-progress-text' ).append( ' | Scan complete.' );
                running = false;
                $( '#fudetector-start-scan' ).prop( 'disabled', false ).text( 'Run Full Scan' );
                $( '#fudetector-cancel-scan' ).hide();

                // Add flagged users link
                if ( !$( '#fudetector-view-flagged' ).length ) {
                    const flaggedUrl = '/wp-admin/users.php?suspicious=flagged&fudetector_filter_nonce=' + fudetector_quick_scan.nonce_filter;
                    $( '#fudetector-scan-progress' ).append(
                        '<p><a id="fudetector-view-flagged" class="button button-primary" href="' + flaggedUrl + '">View Flagged Users</a></p>'
                    );
                }
            } else {
                lastId = data.last_id;
                runBatch();
            }
        } );
    }

    $( '#fudetector-start-scan' ).on( 'click', function () {
        if ( running ) return;
        running = true;
        processedTotal = 0;
        flaggedTotal   = 0;
        lastId         = 0;

        $( '.fudetector-flagged-notice' ).remove();

        $( this ).prop( 'disabled', true ).text( 'Scanning... Please wait.' );
        $( '#fudetector-cancel-scan' ).show();
        $( '#fudetector-scan-progress' ).show();
        $( '#fudetector-progress-bar' ).css( 'width', '0' );
        $( '#fudetector-progress-text' ).text( 'Starting...' );
        runBatch();
    } );

    $( '#fudetector-cancel-scan' ).on( 'click', function () {
        if ( currentRequest ) {
            currentRequest.abort(); // abort the in-progress AJAX request
            currentRequest = null;
        }
        running = false;
        $( '#fudetector-start-scan' ).prop( 'disabled', false ).text( 'Run Full Scan' );
        $( this ).hide();
        $( '#fudetector-progress-text' ).append( ' | Scan cancelled.' );
    } );
} );
