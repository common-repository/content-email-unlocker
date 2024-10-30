function isEmail(email) {
  var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
  return regex.test(email);
}

jQuery(function($) {
	$(".ceu-send").click(function(e){
		var email = $('input[name="ceu_email"]').val();
		var post_id = $('input[name="ceu_postid"]').val();
		var permalink = $('input[name="ceu_permalink"]').val();
		if( !isEmail( email ) ) {
			$('.ceu-email').css('border-color', 'red');
			return false;
		}
       
        var data = {
            'action': 'view_content',
            'post_id': post_id,
            'email': email,
            'permalink': permalink
        };
		
        jQuery.post(ajax_options.ajax_url, data, function(response) {
			//alert(response);
			if( response ) {
				$('.cue-wrapper').append(response);
				$('body, html').animate({ scrollTop: $("#cue-wrapper").offset().top }, 1000);
			}
        });
		
		return false;
	});
});

