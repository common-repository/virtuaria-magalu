jQuery(document).ready(function($) {
    $( '#bulk_edit' ).on( 'click', function() {
        // define the bulk edit row
        var $bulk_row = $( '#bulk-edit' );

        // get the selected post ids that are being edited
        var $magalu_post_ids = new Array();
        $bulk_row.find( '#bulk-titles .button-link' ).each( function() {
            $magalu_post_ids.push( jQuery( this ).attr( 'id' ).replace( /\D/, '' ) );
        });


        // get the custom fields
        var $sync_magalu_product  = $bulk_row.find( 'input[name="sync_magalu_product"]:checked' ).val();
        var $magalu_product_nonce = $bulk_row.find( 'input[name="magalu_product_nonce"]' ).val();

        // save the data
        $.ajax({
            url: ajaxurl, // this is a variable that WordPress has already defined for us
            type: 'POST',
            async: false,
            cache: false,
            data: {
                action: 'multi_add_magalu_products', // this is the name of our WP AJAX function that we'll set up next
                post_ids: $magalu_post_ids,
                sync_magalu_product: $sync_magalu_product,
                magalu_product_nonce: $magalu_product_nonce
            }
        });
    });
});