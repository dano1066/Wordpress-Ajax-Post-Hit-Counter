<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/*
 * Plugin Name: DX2 Post Hit Counter
 * Version: 1.2
 * Description: A lightweight counter to track the number of hits on all posts on the website.
 * Author: Dan Hastings
 * Author URI: http://dx2systems.com
 * Tested up to: 4.7.3
 */
 
 
################################################################################################
##										HIT COUNTER
################################################################################################

add_action( 'wp_enqueue_scripts', 'dx2hits_style' );
function dx2hits_style()
{
	wp_register_style( 'dx2hits_maincss', plugins_url('/style.css', __FILE__), false, '1.0.0' );
    wp_enqueue_style( 'dx2hits_maincss' );
	
	global $post;	
	if( dx2hits_UserIsABot() == false && (is_single() || $post->post_type == "page") && !is_admin())
	{		
		wp_register_script( 'dx2hits_counthit', plugins_url('/scripts/counthit.js', __FILE__), array( 'jquery' ), '1.0.0' );
		$hitvariables = array(
			'pageurl' => wp_nonce_url( admin_url( 'admin-ajax.php' ), 'count_hit_'.$post->ID ),
			'postid' => $post->ID,
			'posttype' => $post->post_type
		);
		wp_localize_script( 'dx2hits_counthit', 'hitdata', $hitvariables );
		
		wp_enqueue_script( 'dx2hits_counthit' );	
	} 
}

add_action( 'wp_ajax_count_hit', 'dx2hits_count_hit_callback' );
add_action( 'wp_ajax_nopriv_count_hit', 'dx2hits_count_hit_callback' );
function dx2hits_count_hit_callback() {

	$safeinput = true;
	if(!is_numeric($_POST["postid"])) $safeinput = false;
	
	if($safeinput == true && check_ajax_referer( 'count_hit_'.$_POST["postid"] ))
	{
		if(!current_user_can( 'manage_options' ))
		{
			global $wpdb;
			$posttype = "post";
			if(isset($_POST['posttype']) && $_POST['posttype'] != "") $posttype = sanitize_text_field($_POST['posttype']);
			
			$dailycountkey = 'hitsday_'.date('Y-m-d');
			
			$table_name = $wpdb->prefix . 'dx2hits_posthits';
			$dailyhits = $wpdb->query("INSERT INTO $table_name (postid, itemkey,hitcount,posttype) VALUES (". $_POST["postid"].", '".$dailycountkey."', 1, '".$posttype."') ON DUPLICATE KEY UPDATE hitcount = hitcount + 1, posttype = '".$posttype."'");
			$alltimehits = $wpdb->query("INSERT INTO $table_name (postid, itemkey,hitcount,posttype) VALUES (". $_POST["postid"].", 'hits_alltime', 1, '".$posttype."') ON DUPLICATE KEY UPDATE hitcount = hitcount + 1, posttype = '".$posttype."'");
			$week = $wpdb->query("INSERT INTO $table_name (postid, itemkey,hitcount,posttype) VALUES (". $_POST["postid"].", 'hitsweek_".date('W-Y')."', 1, '".$posttype."') ON DUPLICATE KEY UPDATE hitcount = hitcount + 1, posttype = '".$posttype."'");
			echo "hit ".$_POST["postid"];
		}
		else echo "Admin hit not counted";
	}
	else echo "Data invalid";

	wp_die();
}


################################################################################################
##										CUSTOM COULMN
################################################################################################
add_action( 'manage_posts_custom_column' , 'dx2_posthitcolumns', 10, 2 );
function dx2_posthitcolumns( $column, $post_id ) {
	switch ( $column ) {
		case 'dx2_posthits': 
			$hits = dx2hits_GetPostHits($post_id);
			echo $hits;
		break;
	}
}

add_filter('manage_posts_columns', 'dx2_posthit_columns');
function dx2_posthit_columns($columns){
		$columns['dx2_posthits'] = __('Page Hits', 'ctct');
	return $columns;
}

################################################################################################
##										HITS ADMINISTRATION
################################################################################################

// add a link to the WP Toolbar
function dx2hits_custom_toolbar_link($wp_admin_bar) {
	global $post;
	if(!is_admin() && (is_single() || $post->post_type == "page"))
	{
		$hits = dx2hits_GetPostHits($post->ID);
		
		$args = array(
			'id' => 'dx2_page_hits',
			'title' => '<span id="ab-dx2hits" style="content: \'\f115\'"></span> Hits '.$hits
		);
		$wp_admin_bar->add_node($args);
	}
}
add_action('admin_bar_menu', 'dx2hits_custom_toolbar_link', 999);

//clear post count link
add_action( 'post_submitbox_misc_actions', 'dx2hits_clear_count_link' );
function dx2hits_clear_count_link() {
	global $post;
	
	$hits = dx2hits_GetPostHits($post->ID);
	?>

	<div class="misc-pub-section misc-pub-section-last">
		<span class="dashicons dashicons-welcome-view-site" style=" color:#82878c;margin-right: 6px;"></span>Hits: <span style="font-weight:bold" id="dx2_posthitcount"><?php echo $hits; ?></span> <span style="font-size:11px;"><a href='javascript:resetCount()'>Reset Count</a></span>
	</div>
	<?php 
}

add_action( 'wp_ajax_clear_hit_count', 'dx2hits_clear_hit_count_callback' );
add_action( 'wp_ajax_nopriv_clear_hit_count', 'dx2hits_clear_hit_count_callback' );
function dx2hits_clear_hit_count_callback() {
	if(is_numeric($_POST["postid"]) && check_ajax_referer( 'clear_hit_count'.$_POST["postid"] ))
	{
		if(current_user_can( 'manage_options' )) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'dx2hits_posthits';
			$wpdb->query("DELETE FROM $table_name WHERE postid = '". $_POST["postid"]."'");
			echo "hits reset";
		}
		else echo "Invalid permissions";
	}
	else echo "Nonce invalid";
	
	wp_die();
	
}	
################################################################################################
##										ACTIVATION HOOKS
################################################################################################
function dx2posthitcounter_dbsetup() {
	global $wpdb;
	
	$charset_collate = $wpdb->get_charset_collate();

	// $table_name = $wpdb->prefix . 'dx2hits_activevisitors';
	// $activevisitorssql = "CREATE TABLE $table_name (
		// ip varchar(32) NOT NULL,
		// unixtime int NOT NULL,
		// PRIMARY KEY (ip)
	// ) $charset_collate;";
	
	$table_name = $wpdb->prefix . 'dx2hits_posthits';
	$viewstatssql = "CREATE TABLE $table_name (
		postid INT NOT NULL,
		itemkey varchar(30),
		hitcount INT,
		posttype varchar(50),
		PRIMARY KEY (postid, itemkey)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	//dbDelta( $activevisitorssql );
	dbDelta( $viewstatssql );
}
register_activation_hook( __FILE__, 'dx2posthitcounter_dbsetup' );

// function dx2hits_dbdestroy()
// {
	// global $wpdb;
	// $table_name = $wpdb->prefix . 'dx2hits_activevisitors';
	// $activevisitorssql = "DROP TABLE IF EXISTS $table_name";
	// $table_name = $wpdb->prefix . 'dx2hits_posthits';
	// $viewstatssql = "DROP TABLE IF EXISTS $table_name";
	// $wpdb->query($activevisitorssql);
	// $wpdb->query($viewstatssql);
// }
// register_deactivation_hook ( __FILE__, 'dx2hits_dbdestroy' );
################################################################################################
##										DASHBOARD MANAGEMENT
################################################################################################
add_action( 'admin_enqueue_scripts', 'dx2hits_dashboard_scripts' );
function dx2hits_dashboard_scripts($hook)
{
	wp_register_script( 'dx2hits_graph', plugins_url('/chartjs/Chart.bundle.min.js', __FILE__), false, '1.0.0' );
    wp_enqueue_script( 'dx2hits_graph' );
	
	global $post;
	if($post != null)
	{
		wp_register_script( 'dx2hits_functions', plugins_url('/scripts/functions.js', __FILE__), false, '1.0.0' );
		
		$resetvariables = array(
			'pageurl' => wp_nonce_url( admin_url( 'admin-ajax.php' ), 'clear_hit_count'.$post->ID ),
			'postid' => $post->ID,
		);
		wp_localize_script( 'dx2hits_functions', 'reset', $resetvariables );
		wp_enqueue_script( 'dx2hits_functions' );
	}
	
	
	if($hook == "index.php")
	{
		global $wpdb;
		$tablename = $wpdb->prefix . 'dx2hits_posthits';
		
		$hitsbyday = array();
		for($i = 0; $i < 15; $i++)
		{		
			$date = date("Y-m-d", strtotime('-'. $i .' days'));
			$daycount = $wpdb->get_results ("SELECT hitcount FROM ".$tablename." WHERE itemkey = 'hitsday_".$date."'", 'ARRAY_A');
			$hits = 0;
			foreach($daycount as $postcount) $hits = $hits + $postcount['hitcount'];
			$hitsbyday[] = array($date, $hits);
		}
		$hitsbyday = array_reverse($hitsbyday);
		
		$labels = array();
		foreach($hitsbyday as $dayhit)
		{		
			$labels[] = date("M j", strtotime($dayhit[0]));
		}
		
		$datasetsdata = array();
		foreach($hitsbyday as $dayhit)
		{		
			$datasetsdata[] = $dayhit[1];
		}
		
		wp_register_script( 'dx2hits_dashwidget', plugins_url('/scripts/dashboardwidget.js', __FILE__), false, '1.0.0' );
	
		$dashvars = array(
			'labels' => $labels,
			'data' => $datasetsdata,
		);
		wp_localize_script( 'dx2hits_dashwidget', 'dashwidget', $dashvars );
		wp_enqueue_script( 'dx2hits_dashwidget' );
	}
}

function dx2hits_dashboardhitmanager() {
	wp_add_dashboard_widget(
                 'dx2_sitehits',         // Widget slug.
                 'Visitor Summary',         // Title.
                 'dx2hits_sitehithistory' // Display function.
        );	
}
add_action( 'wp_dashboard_setup', 'dx2hits_dashboardhitmanager' );

function dx2hits_sitehithistory() {
	?>
	<canvas id="hitcanvas"></canvas>
	<?php 

	global $wpdb;
	$tablename = $wpdb->prefix . 'dx2hits_posthits';
	echo "<strong>Todays Top Posts</strong><span style='float:right'><a href='admin.php?page=dx2-top-posts'>View Top 50</a></span><br><hr>";	
	
	$limit = 7;
	$dayposts = $wpdb->get_results ("SELECT postid, hitcount FROM ".$tablename." WHERE itemkey = 'hitsday_".date('Y-m-d')."' AND hitcount != 0 ORDER BY hitcount DESC LIMIT ".$limit , 'ARRAY_A');

	$postids = array();

	foreach($dayposts as $postid)
	{
		$postids[] = $postid['postid'];
	}

	
	if(count($postids) != 0) {
		$posttypes = get_post_types();
		$cleantypes = array();
		foreach($posttypes as $posttype) $cleantypes[] = $posttype;
			
		$args = array(
			'post__in' => $postids,  
			'post_type'=> $cleantypes,
			'orderby' => 'post__in',
			'posts_per_page' => $limit 
		);
		
		$posts = get_posts( $args );
		$count = 0;
		foreach($posts as $post)
		{
			$dailyhits = $dayposts[$count]["hitcount"];
			?>
			<div class='dx2hits_todaytoppost'><a href="<?php echo get_home_url()."/".$post->post_type;?>/<?php echo $post->post_name;?>"><?php echo $post->post_title?></a><br>Views : <?php echo $dailyhits;?></div>
			<?php
			$count ++;
		}
	}
	else {
		echo "No traffic data found for today, please check later.";
	}
}

add_action('admin_menu', 'dx2_add_pages');
function dx2_add_pages() {
    add_submenu_page(null, 'Todays Top Posts', 'Todays Top Posts', "administrator", "dx2-top-posts", 'dx2_top_posts_today');
}
function dx2_top_posts_today()
{
	global $wpdb;
	$tablename = $wpdb->prefix . 'dx2hits_posthits';
	
	$limit = 50;
	$dayposts = $wpdb->get_results ("SELECT postid, hitcount FROM ".$tablename." WHERE itemkey = 'hitsday_".date('Y-m-d')."' ORDER BY hitcount DESC LIMIT ".$limit , 'ARRAY_A');
	$postids = array();

	foreach($dayposts as $postid)
	{
		$postids[] = $postid['postid'];
	}

	
	$posttypes = get_post_types();
	$cleantypes = array();
	foreach($posttypes as $posttype) $cleantypes[] = $posttype;
	$args = array(
		'post__in' => $postids,  
		'post_type'=> $cleantypes,
		'orderby' => 'post__in',
		'posts_per_page' => $limit 
	);
	
	$posts = get_posts( $args );

	echo "<h1>Todays Top ".$limit." Posts</h1>";
	$count = 0;
	foreach($posts as $post)
	{
		$dailyhits = $dayposts[$count]["hitcount"];
		?>
		<div class='dx2hits_todaytoppost'><a href="<?php echo get_home_url()."/".$post->post_type;?>/<?php echo $post->post_name;?>"><?php echo $post->post_title?></a><br>Views : <?php echo $dailyhits;?></div>
		<?php
		$count ++;
	}
}

################################################################################################
##										WIDGETS
################################################################################################

class dx2hits_popular_posts_widget extends WP_Widget {

	// constructor
	function dx2hits_popular_posts_widget() {
		parent::__construct(false, $name = __('DX2 Popular Posts', 'dx2hits_popular_widget') );
	}

	// widget form creation
	function form($instance) {	
		if( $instance) {
			$title = esc_attr($instance['title']);
			$limit = esc_attr($instance['limit']);
			$frequency = esc_attr($instance['frequency']);
			$imagesize = esc_attr($instance['imgsize']);
			$currentposttype = esc_attr($instance['currentposttype']);
		} else {
			$title = "Popular Posts";
			$limit = '5';
			$frequency = '';
			$imagesize = "";
			$currentposttype = "";
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'dx2hits_popular_widget'); ?></label>
			<input id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('frequency'); ?>"><?php _e('Frequency', 'dx2hits_popular_widget'); ?></label>
			<select name="<?php echo $this->get_field_name('frequency'); ?>" id="<?php echo $this->get_field_name('frequency'); ?>">
				<?php if($frequency == "") echo "<option></option>"; ?>
				<option value="day" <?php if($frequency == "day") echo "selected";?>>Day</option>
				<option value="week" <?php if($frequency == "week") echo "selected";?>>Week</option>
				<option value="alltime" <?php if($frequency == "alltime") echo "selected";?>>All Time</option>
			</select>
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id('imgsize'); ?>"><?php _e('Image Size', 'dx2hits_popular_widget'); ?></label>
			<select name="<?php echo $this->get_field_name('imgsize'); ?>" id="<?php echo $this->get_field_name('imgsize'); ?>">
				<?php if($imagesize == "") echo "<option></option>"; ?>
				<option value="dx2popularlargeimg" <?php if($imagesize == "dx2popularlargeimg") echo "selected";?>>Large</option>
				<option value="dx2popularsmallimg" <?php if($imagesize == "dx2popularsmallimg") echo "selected";?>>Small</option>
			</select>
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id('currentposttype'); ?>"><?php _e('Post Type ', 'dx2hits_popular_widget'); ?></label>
			<select name="<?php echo $this->get_field_name('currentposttype'); ?>" id="<?php echo $this->get_field_name('currentposttype'); ?>">
				<?php if($currentposttype == "") echo "<option></option>"; ?>
				<option value="any" <?php if($currentposttype == "any") echo "selected";?>>Any</option>
				<?php 
				foreach(get_post_types() as $posttype) {
					echo '<option value="'.$posttype.'" ';
					if($currentposttype == $posttype) echo "selected";
					echo '>'.$posttype.'</option>';
				} ?>
			</select>
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Post Limit', 'dx2hits_popular_widget'); ?></label>
			<input id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" />
		</p>
		<?php 
	}

	// widget update
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		// Fields
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['limit'] = strip_tags($new_instance['limit']);
		$instance['frequency'] = strip_tags($new_instance['frequency']);
		$instance['imgsize'] = strip_tags($new_instance['imgsize']);
		$instance['currentposttype'] = strip_tags($new_instance['currentposttype']);
		
		return $instance;
	}

	// widget display
	function widget($args, $instance) {
		extract( $args );
		echo $args['before_widget'];
		if($instance['title'] == null || $instance['title'] == "") $instance['title'] = "Popular Posts";

		echo $args['before_title'] . $instance['title'] . $args['after_title'];
		$posts = dx2hits_GetPostsByHits($instance["frequency"], $instance["limit"], $instance['currentposttype']);
		$count = 0;
		$outputhtml = "";
		if($posts != null && count($posts) != 0) {
			foreach($posts as $post)
			{
				$outputhtml .= "<div class='dx2popularpostsidebar'>";
				$outputhtml .= "<a href='".$post->post_name."'>".$post->post_title;
				$thumb = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'post'); 
				if($thumb[0] != "") $outputhtml .=  "<img class='".$instance['imgsize']."' src='".$thumb[0]."'/>";
				$outputhtml .= "</a></div>";
			}
		}
		echo "<div class='dx2popularposts'>".$outputhtml."</div>";
		echo $args['after_widget'];
	}
}

// register widget
add_action('widgets_init', create_function('', 'return register_widget("dx2hits_popular_posts_widget");'));

################################################################################################
##										SHORTCODES
################################################################################################
// function dx2hits_popularposts( $atts ) {
	// $postids;
	// $imgsize = $atts['imgsize'];
	
	// $posts = dx2hits_GetPostsByHits($atts["frequency"], $atts["numposts"]);
	// $count = 0;
	// $outputhtml = "";
	// foreach($posts as $post)
	// {
		// $outputhtml .= "<div class='dx2popularpostsidebar'>";
		// $outputhtml .= "<a href='".$post->post_name."'>".$post->post_title;
		// $thumb = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'post'); 
		// if($thumb[0] != "") $outputhtml .=  "<img class='".$imgsize."' src='".$thumb[0]."'/>";
		// $outputhtml .= "</a></div>";
	// }
	// return "<div class='dx2popularposts'>".$outputhtml."</div>";
// }
// add_shortcode( 'dx2popularposts', 'dx2hits_popularposts' );


################################################################################################
##										HELPERS
################################################################################################

function dx2hits_GetPostHits($postid, $type = "hits_alltime")
{
	global $wpdb;
	$tablename = $wpdb->prefix . 'dx2hits_posthits';
	$result = $wpdb->get_results("SELECT hitcount FROM $tablename WHERE postid = ".$postid." AND itemkey = '".$type."'", 'ARRAY_A');
	if($result == null) return 0;
	else return $result[0]["hitcount"];
}

function dx2hits_GetPostsByHits($frequency, $numposts, $selectedposttype = "any")
{
	global $wpdb;
	$tablename = $wpdb->prefix . 'dx2hits_posthits';
	switch($frequency)
	{
		case "day": 
			$dayposts = $wpdb->get_results ("SELECT postid FROM ".$tablename." WHERE itemkey = 'hitsday_".date('Y-m-d')."' ORDER BY hitcount DESC LIMIT ".($numposts*3), 'ARRAY_A');
		break;
		case "week": 
			$dayposts = $wpdb->get_results ("SELECT postid FROM ".$tablename." WHERE itemkey = 'hitsweek_".date('W-Y')."' ORDER BY hitcount DESC LIMIT ".($numposts*3), 'ARRAY_A');
		break; 
		case "alltime":
			$dayposts = $wpdb->get_results ("SELECT postid FROM ".$tablename." WHERE itemkey = 'hits_alltime' ORDER BY hitcount DESC LIMIT ".($numposts*3), 'ARRAY_A');
		break;
	}
	
	$postids = array();
	if(count($dayposts) != 0)
	{
		foreach($dayposts as $postid)
		{
			$postids[] = $postid['postid'];
		}

		
		$cleantypes = array();
		if($selectedposttype == "any"){
			$posttypes = get_post_types();
			foreach($posttypes as $posttype) $cleantypes[] = $posttype;
		}
		else $cleantypes[] = $selectedposttype;
	
		$args = array(
			'post__in' => $postids,  
			'post_type'=> $cleantypes,
			'orderby' => 'post__in',
			'posts_per_page' => $numposts
		);
		
		return get_posts( $args );
	}
	else return null;
}

function dx2hits_UserIsABot() {
  if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT'])) return true;
  else return false;
}
