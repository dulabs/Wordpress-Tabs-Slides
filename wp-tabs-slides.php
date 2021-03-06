<?php
/*
Plugin Name: WP Tabs Slides
Plugin URI: http://wts.dulabs.com/
Description: Wordpress Tabs Slides is plugin based on "<a href="http://www.joomlaworks.gr/">joomlaworks Tabs & Slides Mambots</a>" for Mambo/Joomla. Tabs and Slides (in content items) Plugin gives you the ability to easily add content tabs and/or content slides. The tabs emulate a multi-page structure, while the slides emulate an accordion-like structure, inside a single page!
Version: 2.0.3
Author: Abdul Ibad
Author URI: http://dulabs.com

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
	
*/

// Show tags html on title, 
// REPLACE = < will be replace with &lt;
// STRIP = Strip html tags
// NOFILTER = Don't filter (Not Recommend) 

define('WP_TABS_SLIDES_VERSION','2.0.3');

define('SHOW_TITLE_HTML','REPLACE');

class tabs_slides{
	
	function init(){
		
		$frontdisable = self::getOption("frontdisable");
		
		if(empty($frontdisable)){
			self::activation();
		}
		
		add_action('wp_print_scripts', array($this,"wp_head_scripts"));
		add_action('wp_print_styles', array($this,"wp_head_styles"));
		//add_action('wp_head', array($this,"custom_head"));
		add_action('admin_menu', array($this,"admin_menu"));
		/* Use the save_post action to do something with the data entered */
		add_action('save_post', array($this,'savepost'));

		add_filter('the_content', array($this,"formatting"));
		add_filter('the_excerpt', array($this,"formatting"));
		add_filter('widget_text', array($this,"formatting"));

		// Tabs

		add_shortcode('easytabs',array($this,"easytabs_tag"));
		add_shortcode('easytab',array($this,"easytab_tag"));

		add_shortcode('tabs',array($this,"easytabs_tag"));
		add_shortcode('tab',array($this,"easytab_tag"));

		add_shortcode('boottabs',array($this,"boottabs_tag"));
		add_shortcode('boottab',array($this,"boottab_tag"));
	

		// Collapse
		add_shortcode('collapse',array($this,"bootcollapse_tag"));
	}
	
	function activation(){
		$options['sliderspeed'] =  600;
		$options['optimized'] = "on";
		$options['frontdisable'] = "off";
		$options['postdisable'] = "off";
		$options['style'] = "default.css";
		$options['boottabs'] = "on";
		add_option("wp_tabs_slides",$options);
	}
	
	function filter_title( $text ){
		switch(SHOW_TITLE_HTML){
			case 'REPLACE':
				$text = str_replace('<','&lt;',$text);
			break;
			case 'STRIP':
				$text = strip_tags($text);
			break;
			case 'NOFILTER':
				$text = $text;
			break;
		}

		return $text;
	}
	
	function strip_punctuation( $text ){
		 $text = strip_tags($text);
		 $text = preg_replace('/[^a-zA-Z0-9-\s]/', '', $text); 
		 return preg_replace("/[^A-Za-z0-9\s\s+\.\:\-\/%+\(\)\*\&\$\#\!\@\"\';\n\t\r\~]/","",$text);
	}
	
	
	function getSetting( $name ){
		switch( strtoupper( $name ) ){
			case "PLUGIN_URL":
				$dir = self::getSetting("PLUGIN_PATH");
				$home = get_option('siteurl');
				$start = strpos($dir,'/wp-content/');
				$end = strlen($dir);
				$plugin_url = $home.substr($dir,$start,$end);
				return $plugin_url;
			break;
			case "PLUGIN_PATH":
				$dir = str_replace('\\','/',dirname(__FILE__));
				return $dir;
			break;
		}
	}
	
	function getOption( $name ){
		$options = get_option('wp_tabs_slides');
		return $options[ $name ];
	}
	
	function getStyles(){
		
		$dir = self::getSetting("PLUGIN_PATH")."/style";
		
		$opendir = opendir($dir);
		$styles = array();

		while($file = readdir($opendir)){

			if($file != "." && $file != ".."){
				$ext = end(explode(".",$file));
				if(strtoupper($ext) == "CSS"){
					$styles[] = $file;
				}
			}

		}

		closedir($opendir);

		return $styles;
	}
	
	function custom_box($post=""){
		global $post;
	
		echo '<input type="hidden" name="enabletabs_noncename" id="enabletabs_noncename" value="' . 
		    wp_create_nonce( plugin_basename(__FILE__) ) . '" />'; 

			$enabletabs = get_post_meta($post->ID,'enabletabs',true);
			
			if($enabletabs=="on"){
				$checked = ' checked="checked" ';
			}else{
				$checked = '';
			}
			
		?>
		<p><input type="checkbox" id="enabletabs" name="enabletabs" value="on"<?php echo $checked;?>/>&nbsp;<label for="enabletabs"><strong><?php _e("Enable Tabs & Slides on this post");?></strong></label></p>
		<?php
	}
	
	function old_custom_box(){
		 echo '<div class="dbx-b-ox-wrapper">' . "\n";
		  echo '<fieldset id="wptabsslides-box" class="dbx-box">' . "\n";
		  echo '<div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">' . 
		        __( 'Enable Tabs & Slides' ) . "</h3></div>";   

		  echo '<div class="dbx-c-ontent-wrapper"><div class="dbx-content">';

		  // output editing form

		  self::custom_box();

		  // end wrapper

		  echo "</div></div></fieldset></div>\n";
	}
	
	function savepost($post_ID){
		
		
		$post_id = $post_ID;

		// verify this came from the our screen and with proper authorization,
		  // because save_post can be triggered at other times

		  if ( !wp_verify_nonce( $_POST['enabletabs_noncename'], plugin_basename(__FILE__) )) {
		    return $post_id;
		  }

		  // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
		  // to do anything
		  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		    return $post_id;


		  // Check permissions
		  if ( 'page' == $_POST['post_type'] ) {
		    if ( !current_user_can( 'edit_page', $post_id ) )
		      return $post_id;
		  } else {
		    if ( !current_user_can( 'edit_post', $post_id ) )
		      return $post_id;
		  }

		  // OK, we're authenticated: we need to find and save the data
		$data =  ($_POST['enabletabs'] == "on") ? "on" : "off";
		
		update_post_meta($post_id, 'enabletabs', $data);

		return $post_id;
	}
	
	function wp_head_styles(){
		
		if($this->disableThisPost()){
			return;
		}
	
				$plugin_url = self::getSetting("PLUGIN_URL");
				$style = self::getOption("style");
				$boottabs = self::getOption("boottabs");
				$customstyle = trim(self::getOption('custom_style'));

				$style = $plugin_url."/style/".strtolower($style);

				if(!empty($customstyle)){
					$style = $customstyle;
				}
				
				$hacks = $plugin_url.'/hacks.css';
				
				if($boottabs == "on")
				{
					$bootstrap = "https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css";
					wp_enqueue_style('bootstrap',$bootstrap,array(),WP_TABS_SLIDES_VERSION);
				}

				$easytabs = $plugin_url."/easytabs/css/easy-responsive-tabs.css";
				wp_enqueue_style('easytabs',$easytabs,array(),WP_TABS_SLIDES_VERSION);
	
				wp_enqueue_style('tabs-slides',$style,array(),WP_TABS_SLIDES_VERSION);
				wp_enqueue_style('tabs-slides-hacks',$hacks,array(),WP_TABS_SLIDES_VERSION);
	
	
	}
	
	function wp_head_scripts(){
		
		if($this->disableThisPost()){
			return;
		}
		
		wp_enqueue_script('jquery');
		
		$plugin_url = self::getSetting("PLUGIN_URL");
		$optimized = self::getOption("optimized");
		$boottabs = self::getOption("boottabs");
					
		$tabs_slides = 	$plugin_url.'/ts/tabs_slides.js';
		
		wp_enqueue_script('tabs-slides',$tabs_slides,array(),WP_TABS_SLIDES_VERSION);
		
		$use_optimized_loader = ($optimized=="on") ? true:false;

		if($use_optimized_loader) {
			$loader = $plugin_url.'/ts/tabs_slides_opt_loader.js';
		} else {
			$loader = $plugin_url.'/ts/tabs_slides_def_loader.js';
		}
		
		wp_enqueue_script('tabs-slides-loader',$loader,array(),WP_TABS_SLIDES_VERSION);
	
		if($boottabs == "on"){
			$bootstrap = "https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js";
			wp_enqueue_script('bootstrap',$bootstrap,array(),WP_TABS_SLIDES_VERSION);
		}

		$easytabs = $plugin_url."/easytabs/js/easyResponsiveTabs.js";
		wp_enqueue_script('easytabs',$easytabs,array(),WP_TABS_SLIDES_VERSION);
	}
	
	function disableThisPost(){
			
			$plugin_url = self::getSetting("PLUGIN_URL");
			
			global $post;
			
			$disableFront = (self::getOption("frontdisable") == "on") ? true : false;
			$disableThisPost = (self::getOption("postdisable") == "on") ? true : false;

			$postID = $post->ID;
					
			$enableThisPost = get_post_meta($postID,'enabletabs',true);
			
			$enableThisPost = ($enableThisPost=="on") ? true : false;
		
			/* End get options */

			
			if((is_front_page() || is_home()) && ($disableFront)){
				return true;
			}
			
			
			if((is_page() || is_single()) && ($enableThisPost)){
					return false;
			}
		
			
			if((is_page() || is_single()) && ($disableThisPost)){
					return true;
			}
			
			return false;
	}
		

	/* Admin */	
	function optionsAction(){
		
		$options = $newoptions = get_option('wp_tabs_slides');
		
		if(isset($_POST['submit'])){
						
			$newoptions['sliderspeed'] = intval($_POST['speed']);
			$newoptions['optimized'] = (isset($_POST['optimized']))?$_POST['optimized']:'off';
			$newoptions['frontdisable'] = (isset($_POST['frontdisable']))?$_POST['frontdisable']:'off';
			$newoptions['postdisable'] = (isset($_POST['postdisable']))?$_POST['postdisable']:'off';
			$newoptions['style'] = $_POST['style'];
			$newoptions['custom_style'] = (isset($_POST['custom_style'])) ? $_POST['custom_style'] : "";
			$newoptions['boottabs'] = $_POST['boottabs'];

			if($options != $newoptions){
				update_option('wp_tabs_slides',$newoptions);
				return '<div class="updated fade" id="message"><p><strong>'.__("Options Saved").'</strong></p></div>';
			}
		}
		
	}
	
	function optionsView(){
		
			$output = self::optionsAction();
			
			$sliderpeed = self::getOption("sliderspeed");
			$optimized = (self::getOption("optimized") =="on") ? " checked=\"checked\" ":" ";
			$frontdisable = (self::getOption("frontdisable") =="on") ? " checked=\"checked\" ":" ";
			$postdisable = (self::getOption("postdisable") =="on") ? " checked=\"checked\" ":" ";
			$defaultstyle = self::getOption("style");
			$customstyle = self::getOption("custom_style");

			$styles = self::getStyles();
			$boottabs = (self::getOption("boottabs") =="on") ? " checked=\"checked\" ":" ";

			include_once(__DIR__.'/admin.php');
	}
	
	function admin_menu(){
		add_options_page('Wordpress Tabs Slides','Tabs Slides',10,'tabs-slides',array($this,"optionsView"));
		
		$postdisable = (self::getOption("postdisable")=="on") ? true : false;
		
		if($postdisable){
			if( function_exists( 'add_meta_box' ) ) {
	    		add_meta_box( 'wptabsslides_box', __( 'Wordpress Tabs Slides' ), array($this,"custom_box"), 'post', 'side','high' );
				//add_meta_box( $id,                  $title,                                      $callback,                  $page, $context, $priority ); 
	    		add_meta_box( 'wptabsslides_box', __( 'Wordpress Tabs Slides' ), array($this,"custom_box"), 'page', 'advanced' );
	   		} else {
	    		add_action('dbx_post_advanced', array($this,'old_custom_box') );
	    		add_action('dbx_page_advanced', array($this,'old_custom_box') );
	  		}
	
		}
	
	}

	/* Formatting */
	function formatting( $content )
	{

		$content = self::format_default($content);

		return $content;
	}

	function format_default( $content ){
		
	global $post;
				
	// if post empty (check from the title) then return false
	if(empty($post->post_title)){
		return $content;
	}
	
	$sliderspeed = intval(self::getOption("sliderspeed"));

	// if slider speed <= 0 than change speed to normal
	if($sliderspeed <= 0){
			$sliderspeed = '"normal"';
	}
				
	$b=1;
   if (preg_match_all("/{tab=.+?}{tab=.+?}|{tab=.+?}|{\/tabs}/", $content, $matches, PREG_PATTERN_ORDER) > 0) { 	
    foreach ($matches[0] as $match) {	
      if($b==1 && $match!="{/tabs}") {
    	$tabs[] = 1;
    	$b=2;
      }
      elseif($match=="{/tabs}"){
      	$tabs[]=3;
      	$b=1;
      }
      elseif(preg_match("/{tab=.+?}{tab=.+?}/", $match)){
      	$tabs[]=2;
      	$tabs[]=1;
      	$b=2;
      }
      else {
      	$tabs[]=2;
      }
    }
   }
   @reset($tabs);
   $tabscount = 0;
  if (preg_match_all("/{tab=.+?}|{\/tabs}/", $content, $matches, PREG_PATTERN_ORDER) > 0) {
    foreach ($matches[0] as $match) {
      if($tabs[$tabscount]==1) {
      	$match = str_replace("{tab=", "", $match);
        $match = str_replace("}", "", $match);
        $content = str_replace( "{tab=".$match."}", "
		<div class=\"jwts_tabber\" id=\"jwts_tab".$tabid."\"><div class=\"jwts_tabbertab\" title=\"".$match."\"><h2><a href=\"#".urlencode($match)."\" name=\"advtab\">".$match."</a></h2>", $content );        
        $tabid++;
      } elseif($tabs[$tabscount]==2) {
      	$match = str_replace("{tab=", "", $match);
        $match = str_replace("}", "", $match);
      	$content = str_replace( "{tab=".$match."}", "<div class=\"jwts_clearfix\">&nbsp;</div></div><div class=\"jwts_tabbertab\" title=\"".$match."\"><h2><a href=\"#".urlencode($match)."\">".$match."</a></h2>", $content );
      } elseif($tabs[$tabscount]==3) {
      	$content = str_replace( "{/tabs}", "<div class=\"jwts_clearfix\">&nbsp;</div></div></div><div class=\"jwts_clr\">&nbsp;</div>", $content );
      }
      $tabscount++;
    }   
	  
  }    	
	$uniqueSlideID = 0;
	$uniqueToggleID = 0;
	
	
	// Make toggle id more unique with post id
	$pid = "p".$post->ID;
	
 if (preg_match_all("/{slide=.+?}/", $content, $matches, PREG_PATTERN_ORDER) > 0) {
    foreach ($matches[0] as $match) {
      $match = str_replace("{slide=", "", $match);
      $match = str_replace("}", "", $match);
      $title =  self::filter_title($match);
      $link = self::strip_punctuation(str_replace(" ","-",strtolower($match)));
      
      $content = str_replace( "{slide=".$match."}", "<div class=\"wts_title\"><div class=\"wts_title_left\"><a id=\"".$link."\" href=\"javascript:void(null);\" title=\"Click to open!\" class=\"jtoggle\" onclick=\"wtsslide('#hideslide".$uniqueToggleID.$pid."',$sliderspeed);\">".$title."</a></div></div><div class=\"wts_slidewrapper sliderwrapper".$uniqueSlideID."\" id=\"hideslide".$uniqueSlideID.$pid."\">", $content );


      $content = str_replace( "{/slide}", "</div>", $content );
      $uniqueSlideID++;
	  $uniqueToggleID++;
    }   
	
   }

 if (preg_match_all("/{accordion=.+?}/", $content, $matches, PREG_PATTERN_ORDER) > 0) {
    foreach ($matches[0] as $match) {
      $match = str_replace("{accordion=", "", $match);
      $match = str_replace("}", "", $match);
      $title =  self::filter_title($match);
       $link = self::strip_punctuation(str_replace(" ","-",strtolower($match)));

     $content = str_replace( "{accordion=".$match."}", "<div class=\"wts_title\"><div class=\"wts_title_left\"><a id=\"".$link."\" href=\"javascript:void(null);\" title=\"Click to open!\" class=\"jtoggle\" onclick=\"wtsaccordion('.wts_accordionwrapper".$pid."','#hideslide".$uniqueSlideID.$pid."',$sliderspeed);\">".
$title."</a></div></div><div class=\"wts_accordionwrapper".$pid." slideraccordion\" id=\"hideslide".$uniqueSlideID.$pid."\">", $content );

      $content = str_replace( "{/accordion}", "</div>", $content );
      $uniqueSlideID++;
	  $uniqueToggleID++;
    }   

 
   }

	return $content;
		
	}

	// With easy responsive tabs
	public $easytabs;
	public $currentEasyTabIndex;

	function easytabs_tag($attr,$content)
	{
		$index = count($this->easytabs);
		$this->currentEasyTabIndex = $index;
		
		do_shortcode($content);
		$tabs = $this->easytabs;
		
		$id = $attr['id'];
		$type = isset($attr['type']) ? $attr['type'] : 'default';

		if(empty($id))
		{
			$id = "tab-".$index;
		}

		$markup  = '<div id="'.$id.'">          
        			<ul class="resp-tabs-list '.$id.'">';
		$markup .= implode("",$tabs[$index]['tab']);
		$markup .= '</ul>';

		$markup .= ' <div class="resp-tabs-container '.$id.'">';
		$markup .= implode("",$tabs[$index]['pane']);
		$markup .= '</div>';

		$markup .= '</div>';

		ob_start();
		include(__DIR__.'/easytabs/script.php');
		$script = ob_get_contents();
		ob_end_clean();

		$markup .= $script;

		return $markup;
	}

	function easytab_tag($attr,$content)
	{
			if(!isset($attr['title'])) return $content;
			
			$temp_tabs = $this->easytabs;

		 	$index = $this->currentEasyTabIndex;
		 	
			$tabs = $temp_tabs[$index];

		    if (!isset($tabs['tab'])) {
		        $tabs['tab'] = array();
		    }


		    $id = 'pane-' . $index . '-' .  count($tabs['tab']);

			//self::$tabs[] = array('id' => $id,'content' => $content);
			$active = isset($attr['active']) ? "active" : "";

			$tabs['tab'][] = '<li role="presentation" class="'.$active.'">
					'.$attr['title'].'
				</li>';

			$tabs['pane'][] = '<div role="tabpanel" class="tab-pane '.$active.'" id="'.$id.'">
								  '.do_shortcode($content).'</div>';

			$this->easytabs[$index] = $tabs;
			
	}


	// With Bootstrap

	public $tabs;
	public $currentTabIndex;
	function boottabs_tag($atts,$content)
	{
		$boottabs = self::getOption('boottabs');
		if($boottabs != "on") return;

		$index = count($this->tabs);
		$this->currentTabIndex = $index;
		
		do_shortcode($content);
		$tabs = $this->tabs;
		
		$markup  = '<ul class="nav nav-tabs" role="tablist">';
		$markup .= implode("",$tabs[$index]['tab']);
		$markup .= '</ul>';

		$markup .= ' <div class="tab-content">';
		$markup .= implode("",$tabs[$index]['pane']);
		$markup .= '</div>';

		return $markup;
	}

	function boottab_tag($attr,$content)
	{
			$boottabs = self::getOption('boottabs');

			if($boottabs != "on") return;

			if(!isset($attr['title'])) return;
			
			$temp_tabs = $this->tabs;

		 	$index = $this->currentTabIndex;
		 	
			$tabs = $temp_tabs[$index];

		    if (!isset($tabs['tab'])) {
		        $tabs['tab'] = array();
		    }


		    $id = 'pane-' . $index . '-' .  count($tabs['tab']);

			//self::$tabs[] = array('id' => $id,'content' => $content);
			$active = isset($attr['active']) ? "active" : "";

			$tabs['tab'][] = '<li role="presentation" class="'.$active.'">
					<a href="#'.$id.'" aria-controls="'.$id.'" role="tab" data-toggle="tab">
					'.$attr['title'].'
					</a>
				</li>';

			$tabs['pane'][] = '<div role="tabpanel" class="tab-pane '.$active.'" id="'.$id.'">
								  '.do_shortcode($content).'</div>';

			$this->tabs[$index] = $tabs;
			
	}

	public $collapse;
	function bootcollapse_tag($attr,$content)
	{

		if(isset($attr['target']))
		{
			$target = $attr['target'];

			$markup = self::bootcollapse_create_link($target,$content);

			return $markup;
		}

		if(isset($attr['name']))
		{
			$name = $attr['name'];
			$markup = self::bootcollapse_create_wrapper($name,$content);
			return $markup;
		}

		if(isset($attr['title']))
		{
			$id = count($this->collapse);
			$target = "collapse-".$id;
			$title = $attr['title'];
			$markup  = self::bootcollapse_create_link($target,$title);
			$markup .= self::bootcollapse_create_wrapper($target,$content);
			
			$this->collapse[] = $target;

			return $markup;
		}
	}

	function bootcollapse_create_link($target,$content)
	{
		return '<a role="button" data-toggle="collapse" href="#'.$target.'" aria-expanded="false" aria-controls="'.$target.'">
  						'.$content.'
						</a>
				   	  ';
	}

	function bootcollapse_create_wrapper($target,$content)
	{
		return '<div class="collapse" id="'.$target.'">'.
				do_shortcode($content).
				'</div>';
	}


}

add_action('init',array(new tabs_slides,"init"));

?>