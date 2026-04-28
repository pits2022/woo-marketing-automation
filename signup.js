//
var _token = '';
function getToken(token) {
    _token = token;
    jQuery("#signup_form").submit();
}

jQuery(document).ready(function($) {

	jQuery("#signup_form").submit(function(e) {
		e.preventDefault();
		var $form = jQuery(this),
		nyeremenyjatek = $form.find('input[name="nyeremenyjatek"]').val(),
		name = $form.find('input[name="name"]').val(),
		email = $form.find('input[name="email"]').val(),
		url = $form.attr('action'),
		redirectUrl = $form.data('redirect');
		var missing = false;

        jQuery(".sendy_form_error").remove();
        
        $form.find('input').each(function(){
		   if (jQuery(this).prop('required') && (! jQuery(this).val() || (jQuery(this).attr('type') == 'checkbox' && ! jQuery(this).is(':checked')))) { 
		       jQuery(this).parent().append('<div class="sendy_form_error" style="color:red;">Kötelező mező!</div>');
		       jQuery(this).css('border', '1px solid red');
		       missing = true;
		       return false;
		   } else {
		       jQuery(this).css('border', '');
		   }
		});
		    
		if (! missing) {
			console.log("Sending POST");
		$.post(url, {name:name, email:email, nyeremenyjatek:nyeremenyjatek, token:_token},
		    function(data) {
		      if(data != 0) {
		      	if(data=="Some fields are missing.")
		      	{
			      	jQuery("#status").text("Név és e-mail megadása kötelező!");
			      	jQuery("#status").css("color", "red");
		      	}
		      	else if(data=="Invalid email address.")
		      	{
			      	jQuery("#status").text("Érvénytelen e-mail cím!");
			      	jQuery("#status").css("color", "red");
		      	}
		      	else if(data=="Invalid list ID.")
		      	{
			      	jQuery("#status").text("Nem létező lista ID!");
			      	jQuery("#status").css("color", "red");
		      	}
		      	else if(data=="Already subscribed.")
		      	{
			      	jQuery("#status").text("Már feliratkoztál erre a hírlevélre!");
			      	jQuery("#status").css("color", "red");
		      	}
		      	else
		      	{
			      	jQuery("#status").text("Sikeres feliratkozás!");
			      	jQuery("#status").css("color", "green");
				if (nyeremenyjatek && redirectUrl) {
				    window.location.href = redirectUrl;
				}
		      	}
		      } else {
		      	alert(data);
		      }
		  });
	   }
	return false;
	});
});
