<?php
/**
 * Plugin Name: SEO Internal Links Revisited
 * Plugin URI: https://vietdex.net/
 * Description: SEO Internal Links provides automatic SEO internal links for your site, keyword lists, nofollow and much more. Now support PHP 7.x
 * Version:       1.0
 * Author:        vietdex
 * Author URI:    https://vietdex.net/
 * License:       GNU General Public License, v2 (or newer)
 * License URI:  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
/*  Copyright 2011 Pankaj Jha (onlinewebapplication.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation using version 2 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
// Avoid name collisions.
if ( !class_exists('SEOLinks') ) :

class SEOLinks {

	// Name for our options in the DB
	var $SEOLinks_DB_option = 'SEOLinks';
	var $SEOLinks_options;

	// Initialize WordPress hooks
	function SEOLinks() {
	  $options = $this->get_options();
	  if ($options)
	  {
	  	if ($options['post'] || $options['page'])
				add_filter('the_content',  array(&$this, 'SEOLinks_the_content_filter'), 10);
			if ($options['comment'])
				add_filter('comment_text',  array(&$this, 'SEOLinks_comment_text_filter'), 10);
		}

		add_action( 'create_category', array(&$this, 'SEOLinks_delete_cache'));
		add_action( 'edit_category',  array(&$this,'SEOLinks_delete_cache'));
		add_action( 'edit_post',  array(&$this,'SEOLinks_delete_cache'));
		add_action( 'save_post',  array(&$this,'SEOLinks_delete_cache'));
		// Add Options Page
		add_action('admin_menu',  array(&$this, 'SEOLinks_admin_menu'));

		//if ($options['notice'])
		 // add_action('admin_notices', array(&$this,'admin_notice'));

	}


function SEOLinks_process_text($text, $mode)
{

	global $wpdb, $post;

	$options = $this->get_options();

	$links=0;



        if (is_feed() && !$options['allowfeed'])
             return $text;
	else if ($options['onlysingle'] && !(is_single() || is_page()))
		return $text;

    $arrignorepost=$this->explode_trim(",", ($options['ignorepost']));

    if (is_page($arrignorepost) || is_single($arrignorepost)) {
        return $text;
    }

	if (!$mode)
	{
		if ($post->post_type=='post' && !$options['post'])
			return $text;
		else if ($post->post_type=='page' && !$options['page'])
			return $text;

		if (($post->post_type=='page' && !$options['pageself']) || ($post->post_type=='post' && !$options['postself'])) {

			$thistitle=$options['casesens'] ? $post->post_title : strtolower($post->post_title);
			$thisurl=trailingslashit(get_permalink($post->ID));
		}
		else {
			$thistitle='';
			$thisurl='';
		}

	}


	$maxlinks=($options['maxlinks']>0) ? $options['maxlinks'] : 0;
	$maxsingle=($options['maxsingle']>0) ? $options['maxsingle'] : -1;
	$maxsingleurl=($options['maxsingleurl']>0) ? $options['maxsingleurl'] : 0;
	$minusage = ($options['minusage']>0) ? $options['minusage'] : 1;

	$urls = array();

	$arrignore=$this->explode_trim(",", ($options['ignore']));
	if ($options['excludeheading'] == "on") {
		//Here insert special characters
		/*$text = preg_replace('%(<h.*?>)(.*?)(</h.*?>)%sie', "'\\1'.vd_insertspecialchars('\\2').'\\3'", $text); */

		$text = preg_replace_callback( '@(<h.*?>)(.*?)(</h.*?>)@si', function ( $match ) {
			return ( $match[1].vd_insertspecialchars($match[2]) . $match[3] );
		}, $text );
	}


//	$reg_post		=	$options['casesens'] ? '/(?!(?:[^<]+>|[^>]+<\/a>))($name)/msU' : '/(?!(?:[^<]+>|[^>]+<\/a>))($name)/imsU';
//	$reg			=	$options['casesens'] ? '/(?!(?:[^<]+>|[^>]+<\/a>))\b($name)\b/msU' : '/(?!(?:[^<]+>|[^>]+<\/a>))\b($name)\b/imsU';
	$reg_post		=	$options['casesens'] ? '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))($name)/msU' : '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))($name)/imsU';
	$reg			=	$options['casesens'] ? '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))\b($name)\b/msU' : '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))\b($name)\b/imsU';
	$strpos_fnc		=	$options['casesens'] ? 'strpos' : 'stripos';

	$text = " $text ";

	// custom keywords
	if (!empty($options['customkey']))
	{
		$kw_array = array();

		// thanks PK for the suggestion
		foreach (explode("\n", $options['customkey']) as $line) {



			if($options['customkey_preventduplicatelink'] == TRUE) {  //Prevent duplicate links for grouped custom keywords

				$line = trim($line);
				$lastDelimiterPos=strrpos($line, ',');
				$url = substr($line, $lastDelimiterPos + 1 );
				$keywords = substr($line, 0, $lastDelimiterPos);

				if(!empty($keywords) && !empty($url)){
					$kw_array[$keywords] = $url;
				}

				$keywords='';
				$url='';

			} else {  //Old custom keywords behaviour


			$chunks = array_map('trim', explode(",", $line));
			$total_chuncks = count($chunks);
			if($total_chuncks > 2) {
				$i = 0;
				$url = $chunks[$total_chuncks-1];
				while($i < $total_chuncks-1) {
					if (!empty($chunks[$i])) $kw_array[$chunks[$i]] = $url;
						$i++;
					}
				} else {
					list($keyword, $url) = array_map('trim', explode(",", $line, 2));
					if (!empty($keyword)) $kw_array[$keyword] = $url;
				}

			}

		}


		foreach ($kw_array as $name=>$url)
		{

			if ((!$maxlinks || ($links < $maxlinks)) && (trailingslashit($url)!=$thisurl) && !in_array( $options['casesens'] ? $name : strtolower($name), $arrignore) && (!$maxsingleurl || $urls[$url]<$maxsingleurl) )
			{
				if (($options['customkey_preventduplicatelink'] == TRUE) || $strpos_fnc($text, $name) !== false) {		// credit to Dominik Deobald -- TODO: change string search for preg_match
					$name= preg_quote($name, '/');

					if($options['customkey_preventduplicatelink'] == TRUE) $name = str_replace(',','|',$name); //Modifying RegExp for count all grouped keywords as the same one

					$replace="<a title=\"$1\" href=\"$url\">$1</a>";
					$regexp=str_replace('$name', $name, $reg);
					//$regexp="/(?!(?:[^<]+>|[^>]+<\/a>))(?<!\p{L})($name)(?!\p{L})/imsU";
					$newtext = preg_replace($regexp, $replace, $text, $maxsingle);
					if ($newtext!=$text) {
						$links++;
						$text=$newtext;
                                                if (!isset($urls[$url])) $urls[$url]=1; else $urls[$url]++;
					}
				}
			}
		}
	}


	// posts and pages
	if ($options['lposts'] || $options['lpages'])
	{
		if ( !$posts = wp_cache_get( 'seo-links-posts', 'seo-internal-links' ) ) {
			$query="SELECT post_title, ID, post_type FROM $wpdb->posts WHERE post_status = 'publish' AND LENGTH(post_title)>3 ORDER BY LENGTH(post_title) DESC LIMIT 2000";
			$posts = $wpdb->get_results($query);

			wp_cache_add( 'seo-links-posts', $posts, 'seo-internal-links', 86400 );
		}


		foreach ($posts as $postitem)
		{
			if ((($options['lposts'] && $postitem->post_type=='post') || ($options['lpages'] && $postitem->post_type=='page')) &&
			(!$maxlinks || ($links < $maxlinks))  && (($options['casesens'] ? $postitem->post_title : strtolower($postitem->post_title))!=$thistitle) && (!in_array( ($options['casesens'] ? $postitem->post_title : strtolower($postitem->post_title)), $arrignore))
			)
				{
					if ($strpos_fnc($text, $postitem->post_title) !== false) {		// credit to Dominik Deobald
						$name = preg_quote($postitem->post_title, '/');

						$regexp=str_replace('$name', $name, $reg);


						$replace='<a title="$1" href="$$$url$$$">$1</a>';

						$newtext = preg_replace($regexp, $replace, $text, $maxsingle);
						if ($newtext!=$text) {
							$url = get_permalink($postitem->ID);
                                                        if (!$maxsingleurl || $urls[$url]<$maxsingleurl)
                                                        {
							  $links++;
							  $text=str_replace('$$$url$$$', $url, $newtext);
                                                          if (!isset($urls[$url])) $urls[$url]=1; else $urls[$url]++;
                                                        }
						}
					}
				}
		}
	}

	// categories
	if ($options['lcats'])
	{
		if ( !$categories = wp_cache_get( 'seo-links-categories', 'seo-internal-links' ) ) {

			$query="SELECT $wpdb->terms.name, $wpdb->terms.term_id FROM $wpdb->terms LEFT JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id WHERE $wpdb->term_taxonomy.taxonomy = 'category'  AND LENGTH($wpdb->terms.name)>3 AND $wpdb->term_taxonomy.count >= $minusage ORDER BY LENGTH($wpdb->terms.name) DESC LIMIT 2000";
			$categories = $wpdb->get_results($query);

			wp_cache_add( 'seo-links-categories', $categories, 'seo-internal-links',86400 );
		}

		foreach ($categories as $cat)
		{
			if ((!$maxlinks || ($links < $maxlinks)) &&  !in_array( $options['casesens'] ?  $cat->name : strtolower($cat->name), $arrignore)  )
			{
				if ($strpos_fnc($text, $cat->name) !== false) {		// credit to Dominik Deobald
					$name= preg_quote($cat->name, '/');
					$regexp=str_replace('$name', $name, $reg);	;
					$replace='<a title="$1" href="$$$url$$$">$1</a>';

					$newtext = preg_replace($regexp, $replace, $text, $maxsingle);
					if ($newtext!=$text) {
						$url = (get_category_link($cat->term_id));
						if (!$maxsingleurl || $urls[$url]<$maxsingleurl)
                                                {
						  $links++;
						  $text=str_replace('$$$url$$$', $url, $newtext);
						   if (!isset($urls[$url])) $urls[$url]=1; else $urls[$url]++;
						}
					}
				}
			}
		}
	}

	// tags
	if ($options['ltags'])
	{
		if ( !$tags = wp_cache_get( 'seo-links-tags', 'seo-internal-links' ) ) {

			$query="SELECT $wpdb->terms.name, $wpdb->terms.term_id FROM $wpdb->terms LEFT JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id WHERE $wpdb->term_taxonomy.taxonomy = 'post_tag'  AND LENGTH($wpdb->terms.name)>3 AND $wpdb->term_taxonomy.count >= $minusage ORDER BY LENGTH($wpdb->terms.name) DESC LIMIT 2000";
			$tags = $wpdb->get_results($query);

			wp_cache_add( 'seo-links-tags', $tags, 'seo-internal-links',86400 );
		}

		foreach ($tags as $tag)
		{
			if ((!$maxlinks || ($links < $maxlinks)) && !in_array( $options['casesens'] ? $tag->name : strtolower($tag->name), $arrignore) )
			{
				if ($strpos_fnc($text, $tag->name) !== false) {		// credit to Dominik Deobald
					$name = preg_quote($tag->name, '/');
					$regexp=str_replace('$name', $name, $reg);	;
					$replace='<a title="$1" href="$$$url$$$">$1</a>';

					$newtext = preg_replace($regexp, $replace, $text, $maxsingle);
					if ($newtext!=$text) {
						$url = (get_tag_link($tag->term_id));
						if (!$maxsingleurl || $urls[$url]<$maxsingleurl)
                                                {
						  $links++;
						  $text=str_replace('$$$url$$$', $url, $newtext);
                                                  if (!isset($urls[$url])) $urls[$url]=1; else $urls[$url]++;
						}
					}
				}
			}
		}
	}

	if ($options['excludeheading'] == "on") {
		//Here insert special characters
		/*$text = preg_replace('%(<h.*?>)(.*?)(</h.*?>)%sie', "'\\1'.vd_removespecialchars('\\2').'\\3'", $text); */
		$text = preg_replace_callback('@(<h.*?>)(.*?)(</h.*?>)@si', function ( $match ) {
			return ( $match[1].vd_removespecialchars($match[2]) . $match[3] );
		}, $text);
		$text = stripslashes($text);
	}
	return trim( $text );

}

function SEOLinks_the_content_filter($text) {

	$result=$this->SEOLinks_process_text($text, 0);

	$options = $this->get_options();
	$link=parse_url(get_bloginfo('wpurl'));
	$host='http://'.$link['host'];

	if ($options['blanko'])
		$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a target="_blank"\\1', $result); // credit to  Kaf Oseo

	if ($options['nofolo'])
		$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a rel="nofollow"\\1', $result);
	return $result;
}

function SEOLinks_comment_text_filter($text) {
	$result = $this->SEOLinks_process_text($text, 1);

	$options = $this->get_options();
	$link=parse_url(get_bloginfo('wpurl'));
	$host='http://'.$link['host'];

	if ($options['blanko'])
		$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a target="_blank"\\1', $result); // credit to  Kaf Oseo

	if ($options['nofolo'])
		$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a rel="nofollow"\\1', $result);

	return $result;
}

	function explode_trim($separator, $text)
{
    $arr = explode($separator, $text);

    $ret = array();
    foreach($arr as $e)
    {
      $ret[] = trim($e);
    }
    return $ret;
}

	// Handle our options
	function get_options() {

 $options = array(
	 'post' => 'on',
	 'postself' => '',
	 'page' => 'on',
	 'pageself' => '',
	 'comment' => '',
	 'excludeheading' => 'on',
	 'lposts' => 'on',
	 'lpages' => 'on',
	 'lcats' => '',
	 'ltags' => '',
	 'ignore' => 'about,',
   'ignorepost' => 'contact',
	 'maxlinks' => 3,
	 'maxsingle' => 1,
	 'minusage' => 1,
	 'customkey' => '',
	 'customkey_preventduplicatelink' => FALSE,
	 'nofoln' =>'',
	 'nofolo' =>'',
	 'blankn' =>'',
	 'blanko' =>'',
	 'onlysingle' => 'on',
	 'casesens' =>'',
         'allowfeed' => '',
         'maxsingleurl' => '1',
	 'notice'=>'1'
	 );

        $saved = get_option($this->SEOLinks_DB_option);


 if (!empty($saved)) {
	 foreach ($saved as $key => $option)
 			$options[$key] = $option;
 }

 if ($saved != $options)
 	update_option($this->SEOLinks_DB_option, $options);

 return $options;

	}



	// Set up everything
	function install() {
		$SEOLinks_options = $this->get_options();


	}

	function handle_options()
	{

		$options = $this->get_options();
		if (isset($_GET['notice']))
		{
		    if ($_GET['notice']==1)
		      {
			  		$options['notice']=0;
			  		update_option($this->SEOLinks_DB_option, $options);
		      }
		}
		if ( isset( $_POST['submitted'] ) ) {

			check_admin_referer('seo-internal-links');

			$options['post']=vd_post('post');
			$options['postself']=vd_post('postself');
			$options['page']=vd_post('page');
			$options['pageself']=vd_post('pageself');
			$options['comment']=vd_post('comment');
			$options['excludeheading']=vd_post('excludeheading');
			$options['lposts']=vd_post('lposts');
			$options['lpages']=vd_post('lpages');
			$options['lcats']=vd_post('lcats');
			$options['ltags']=vd_post('ltags');
			$options['ignore']=vd_post('ignore');
			$options['ignorepost']=vd_post('ignorepost');
			$options['maxlinks']=(int) vd_post('maxlinks');
			$options['maxsingle']=(int) vd_post('maxsingle');
			$options['maxsingleurl']=(int) vd_post('maxsingleurl');
			$options['minusage']=(int) vd_post('minusage');			// credit to Dominik Deobald
			$options['customkey']=vd_post('customkey');
			$options['customkey_preventduplicatelink']=vd_post('customkey_preventduplicatelink');
			$options['nofoln']=vd_post('nofoln');
			$options['nofolo']=vd_post('nofolo');
			$options['blankn']=vd_post('blankn');
			$options['blanko']=vd_post('blanko');
			$options['onlysingle']=vd_post('onlysingle');
			$options['casesens']=vd_post('casesens');
			$options['allowfeed']=vd_post('allowfeed');


			update_option($this->SEOLinks_DB_option, $options);
			$this->SEOLinks_delete_cache(0);
			echo '<div class="updated fade"><p>Plugin settings saved.</p></div>';
		}




		$action_url = $_SERVER['REQUEST_URI'];

		$post=$options['post']=='on'?'checked':'';
		$postself=$options['postself']=='on'?'checked':'';
		$page=$options['page']=='on'?'checked':'';
		$pageself=$options['pageself']=='on'?'checked':'';
		$comment=$options['comment']=='on'?'checked':'';
		$excludeheading=$options['excludeheading']=='on'?'checked':'';
		$lposts=$options['lposts']=='on'?'checked':'';
		$lpages=$options['lpages']=='on'?'checked':'';
		$lcats=$options['lcats']=='on'?'checked':'';
		$ltags=$options['ltags']=='on'?'checked':'';
		$ignore=$options['ignore'];
		$ignorepost=$options['ignorepost'];
		$maxlinks=$options['maxlinks'];
		$maxsingle=$options['maxsingle'];
		$maxsingleurl=$options['maxsingleurl'];
		$minusage=$options['minusage'];
		$customkey=stripslashes($options['customkey']);
		$customkey_preventduplicatelink=$options['customkey_preventduplicatelink'] == TRUE ? 'checked' : '';
		$nofoln=$options['nofoln']=='on'?'checked':'';
		$nofolo=$options['nofolo']=='on'?'checked':'';
		$blankn=$options['blankn']=='on'?'checked':'';
		$blanko=$options['blanko']=='on'?'checked':'';
		$onlysingle=$options['onlysingle']=='on'?'checked':'';
		$casesens=$options['casesens']=='on'?'checked':'';
		$allowfeed=$options['allowfeed']=='on'?'checked':'';

		if (!is_numeric($minusage)) $minusage = 1;

		$nonce=wp_create_nonce( 'seo-internal-links');

		$imgpath=trailingslashit(get_option('siteurl')). 'wp-content/plugins/seo-internal-links/i';
		echo <<<END

<div class="wrap" style="">
	<h2>SEO Internal Links</h2>

	<div id="poststuff" style="margin-top:10px;">

	 <div id="mainblock">

		<div class="dbx-content">
		 	<form name="SEOLinks" action="$action_url" method="post">
		 		  <input type="hidden" id="_wpnonce" name="_wpnonce" value="$nonce" />
					<input type="hidden" name="submitted" value="1" />
					<h2>SEO Internal Links</h2>
					<p>SEO Iinternal Links can process your posts, pages and comments in search for keywords to automatically interlink.</p>
					<input type="checkbox" name="post"  $post/><label for="post"> Posts</label>
					<ul>&nbsp;<input type="checkbox" name="postself"  $postself/><label for="postself"> Allow links to self</label></ul>
					<br />
					<input type="checkbox" name="page"  $page/><label for="page"> Pages</label>
					<ul>&nbsp;<input type="checkbox" name="pageself"  $pageself/><label for="pageself"> Allow links to self</label></ul>
					<br />
					<input type="checkbox" name="comment"  $comment /><label for="comment"> Comments</label> (may slow down performance) <br>

					<h4>Excluding</h4>
					<input type="checkbox" name="excludeheading"  $excludeheading/><label for="excludeheading">Prevent linking in heading tags (h1,h2,h3,h4,h5,h6).</label>

					<h4>Target</h4>
					<p>The targets SEO Iinternal links should consider. The match will be based on post/page title or category/tag name, case insensitive.</p>
					<input type="checkbox" name="lposts" $lposts /><label for="lposts"> Posts</label>  <br>
					<input type="checkbox" name="lpages" $lpages /><label for="lpages"> Pages</label>  <br>
					<input type="checkbox" name="lcats" $lcats /><label for="lcats"> Categories</label> (may slow down performance)  <br>
					<input type="checkbox" name="ltags" $ltags /><label for="ltags"> Tags</label> (may slow down performance)  <br>
					<br>
					Link tags and categories that have been used at least <input type="text" name="minusage" size="2" value="$minusage"/> times.
					<br>

					<h2>Settings</h2>
					<p>To reduce database load you can choose to have SEO SMART links work only on single posts and pages (for example not on main page or archives).</p>
					<input type="checkbox" name="onlysingle" $onlysingle /><label for="onlysingle"> Process only single posts and pages</label>  <br>
					<br />
					<p>Allow processing of RSS feeds. SEO Iinternal links will embed links in all posts in your RSS feed (according to other options)</p>
					<input type="checkbox" name="allowfeed" $allowfeed /><label for="allowfeed"> Process RSS feeds</label>  <br>
					<br />
					<p>Set whether matching should be case sensitive.</p>
					<input type="checkbox" name="casesens" $casesens /><label for="casesens"> Case sensitive matching</label>  <br>

					<h4>Ignore Posts and Pages</h4>
					<p>You may wish to forbid automatic linking on certain posts or pages. Seperate them by comma. (id, slug or name)</p>
					<input type="text" name="ignorepost" size="90" value="$ignorepost"/>
					<br>

					<h4>Ignore keywords</h4>
					<p>You may wish to ignore certain words or phrases from automatic linking. Seperate them by comma.</p>
					<input type="text" name="ignore" size="90" value="$ignore"/>
					<br><br>

					<h4>Custom Keywords</h4>
					<p>Here you can enter manually the extra keywords you want to automaticaly link. Use comma to seperate keywords and add target url at the end. Use a new line for new url and set of keywords. You can have these keywords link to any url, not only your site.</p>
					<p>Example:<br />
					vladimir prelovac, http://onlinewebapplication.com/tag/social-media/<br />
					cars, car, autos, auto, http://onlinewebapplication.com/<br />
					</p>

					<input type="checkbox" name="customkey_preventduplicatelink" $customkey_preventduplicatelink /><label for="customkey_preventduplicatelink"> Prevent Duplicate links for grouped keywords (will link only first of the keywords found in text)</label>  <br>

					<textarea name="customkey" id="customkey" rows="10" cols="90"  >$customkey</textarea>
					<br><br>

					<h4>Limits</h4>
					<p>You can limit the maximum number of different links SEO Iinternal Links will generate per post. Set to 0 for no limit. </p>
					Max Links: <input type="text" name="maxlinks" size="2" value="$maxlinks"/>
					<p>You can also limit maximum number of links created with the same keyword. Set to 0 for no limit. </p>
					Max Single: <input type="text" name="maxsingle" size="2" value="$maxsingle"/>
                                       <p>Limit number of same URLs the plugin will link to. Works only when Max Single above is set to 1. Set to 0 for no limit. </p>
					Max Single URLs: <input type="text" name="maxsingleurl" size="2" value="$maxsingleurl"/>
					<br><br>

					<h4>External Links</h4>
					<p>SEO Iinternal links can open external links in new window and add nofollow attribute.</p>


					<input type="checkbox" name="nofolo" $nofolo /><label for="nofolo"> Add nofollow attribute</label>  <br>


					<input type="checkbox" name="blanko" $blanko /><label for="blanko"> Open in new window</label>  <br>


					<div class="submit"><input type="submit" name="Submit" value="Update options" class="button-primary" /></div>
			</form>
		</div>

		<br/><br/><h3>&nbsp;</h3>
	 </div>

	</div>

<h5>Another WordPress plugin by <a href="http://onlinewebapplication.com/">Pankaj Jha</a>, modified and fix by <a href="https://vietdex.net">Vietdex Teams</a></h5>
</div>
END;


	}

	function SEOLinks_admin_menu()
	{
		add_options_page('SEO Iinternal Links Options', 'SEO Iinternal Links', 8, basename(__FILE__), array(&$this, 'handle_options'));
	}

function SEOLinks_delete_cache($id) {
	 wp_cache_delete( 'seo-links-categories', 'seo-iinternal-links' );
	 wp_cache_delete( 'seo-links-tags', 'seo-iinternal-links' );
	 wp_cache_delete( 'seo-links-posts', 'seo-iinternal-links' );
}
//add_action( 'comment_post', 'SEOLinks_delete_cache');
//add_action( 'wp_set_comment_status', 'SEOLinks_delete_cache');


}

endif;

if ( class_exists('SEOLinks') ) :

	$SEOLinks = new SEOLinks();
	if (isset($SEOLinks)) {
		register_activation_hook( __FILE__, array(&$SEOLinks, 'install') );
	}
endif;

function vd_insertspecialchars($str) {
	$strarr = vd_str2arr($str);
    $str = implode("<!---->", $strarr);
    return $str;
}
function vd_removespecialchars($str) {
	$strarr = explode("<!---->", $str);
    $str = implode("", $strarr);
	$str = stripslashes($str);
    return $str;
}
function vd_str2arr($str) {
    $chararray = array();
    for($i=0; $i < strlen($str); $i++){
        array_push($chararray,$str{$i});
    }
    return $chararray;
}

function vd_get( $var = '') {
	return sanitize_text_field( $_GET["$var"]);
}

function vd_post( $var = '') {
	return htmlentities( $_POST["$var"]);
}
