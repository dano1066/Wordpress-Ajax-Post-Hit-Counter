function resetCount()
{
	jQuery.ajax( {
		type: "POST",
		url : reset.pageurl,
		data: { postid: reset.postid, action: "clear_hit_count"},
		cache: false,
		success: function( data ) {
			console.log(data);
			jQuery("#dx2_posthitcount").html("0");
		}
	});
}

