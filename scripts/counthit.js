jQuery(document).ready(function(){
	setTimeout(function() {
		jQuery.ajax( {
			type: "POST",
			url : hitdata.pageurl,
			data: { postid: hitdata.postid, posttype: hitdata.posttype, action: 'count_hit'},
			cache: false,
			success: function( data ) {
				console.log(data);
			}
		})}, 1500);
});
