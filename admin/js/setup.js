jQuery(document).ready(function($) {
    $('.auth.button-primary').on('click', function(e) {
        e.preventDefault();
        if ($(this).hasClass('connected')) {
            $('#authorization').val('connected');
        } else {
            $('#authorization').val('disconnected');
        }
        $('#mainform').submit();
    });
});