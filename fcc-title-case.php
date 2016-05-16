<?php
/*
Plugin Name: FCC Title Case
Plugin URI: https://github.com/openfcci/fcc-title-case
Description: Automatic title casing of post titles on saved or published.
Author: Forum Communications Company
Version: 1.16.05.16
Author URI: http://forumcomm.com/
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/* Edge cases:
notes and observations regarding apple's announcements from 'the beat goes on' special event
notes and observations regarding apple's announcements from ‘the beat goes on’ special event --- This works
*/

/*--------------------------------------------------------------
# Change title case when publishing or updating a post.
--------------------------------------------------------------*/

/**
 * Automatically have post titles be capitalized using Chicago Style
 * @author Josh Slebodnik <josh.slebodnik@forumcomm.com>
 * @author Ryan Veitch <ryan.veitch@forumcomm.com>
 * @since 1.16.05.03
 * @version 1.16.05.16
 */
 function fcc_title_case($post_id){

   //$manual_post_title_casing = get_post_meta( $post_id,'manual_post_title_casing', True);

   if ( ! get_post_meta($post_id,'manual_post_title_casing', True) ){
     remove_action('save_post', 'fcc_title_case');

     $post_title = get_the_title( $post_id );

     // Updates post to capitalize title
     $title_post = array(
       'ID'           => $post_id,
       'post_title'   => toTitleCase($post_title)
     );

     wp_update_post( $title_post );
     add_action('save_post', 'fcc_title_case');
   }

 }
 add_action('save_post', 'fcc_title_case');

/**
 * Capitalizes what needs to be capitalized on a string.
 *
 * original Title Case script © John Gruber <daringfireball.net>
 * javascript port © David Gouch <individed.com>
 * PHP port of the above by Kroc Camen <camendesign.com>
 *
 * @since 1.16.05.03
 * @version 1.16.05.16
 */
 function toTitleCase($title) {

   # Remove HTML, storing it for later
   ## HTML elements to ignore: | tags  | entities
   $regx = '/<(code|var)[^>]*>.*?<\/\1>|<[^>]+>|&\S+;/';
   preg_match_all ($regx, $title, $html, PREG_OFFSET_CAPTURE);
   $title = preg_replace ($regx, '', $title);

   # Find each word (including punctuation attached)
   preg_match_all('/[\w\p{L}&`\'‘’"“\.@:\/\{\(\[<>_]+-? */u', $title, $m1, PREG_OFFSET_CAPTURE);

     foreach ($m1[0] as &$m2) {

       # Shorthand these: "match" and "index"
       list($m, $i) = $m2;

       # Correct offsets for multi-byte characters (`PREG_OFFSET_CAPTURE` returns *byte*-offset)
       ## We fix this by recounting the text before the offset using multi-byte aware `strlen`
       $i = mb_strlen( substr($title, 0, $i), 'UTF-8' );

       # Find words that should always be lowercase…
       ## (never on the first word, and never if preceded by a colon)
       $m = $i>0 && mb_substr ($title, max (0, $i-2), 1, 'UTF-8') !== ':' &&
         !preg_match ('/[\x{2014}\x{2013}] ?/u', mb_substr ($title, max (0, $i-2), 2, 'UTF-8')) &&
          preg_match ('/^(a(nd?|s|t)?|b(ut|y)|en|for|i[fn]|o[fnr]|t(he|o)|vs?\.?|via|up|\'(s|t|ll))[ \-]/i', $m)
       ?	//…and convert them to lowercase

       mb_strtolower ($m, 'UTF-8')

          // Brackets and other wrappers
       : (	preg_match ('/[\'"_{(\[‘“]/u', mb_substr ($title, max (0, $i-1), 3, 'UTF-8'))
       ?	// Convert first letter within wrapper to uppercase
         mb_substr ($m, 0, 1, 'UTF-8').
         mb_strtoupper (mb_substr ($m, 1, 1, 'UTF-8'), 'UTF-8').
         mb_substr ($m, 2, mb_strlen ($m, 'UTF-8')-2, 'UTF-8')
          // Do not uppercase these cases
       : (	preg_match('/[\])}]/', mb_substr($title, max(0, $i-1), 3, 'UTF-8')) ||
         preg_match('/[A-Z]+|&|\w+[._]\w+/u', mb_substr($m, 1, mb_strlen ($m, 'UTF-8')-1, 'UTF-8'))
       ?	$m
          // if all else fails, then no more fringe-cases; uppercase the word
       :	mb_strtoupper( mb_substr($m, 0, 1, 'UTF-8'), 'UTF-8').
         mb_substr( $m, 1, mb_strlen($m, 'UTF-8'), 'UTF-8')
       ));
          // resplice the title with the change (`substr_replace` is not multi-byte aware)
       $title = mb_substr($title, 0, $i, 'UTF-8').$m.
          mb_substr($title, $i+mb_strlen ($m, 'UTF-8'), mb_strlen($title, 'UTF-8'), 'UTF-8')
       ;
     } // end For Each

    # Restore the HTML
    ## (Reference: In order to be able to directly modify array elements within the loop precede $value with &.)
    foreach ( $html[0] as &$tag ) {
      $title = substr_replace ( $title, $tag[0], $tag[1], 0 );
    }
    return $title;
}

/*--------------------------------------------------------------
# Metabox
--------------------------------------------------------------*/

class Post_Title_Casing_Metabox {

	public function __construct() {

		if ( is_admin() ) {
			add_action( 'load-post.php',     array( $this, 'init_metabox' ) );
			add_action( 'load-post-new.php', array( $this, 'init_metabox' ) );
		}

	}

	public function init_metabox() {

		add_action( 'add_meta_boxes',        array( $this, 'add_metabox' )         );
		add_action( 'save_post',             array( $this, 'save_metabox' ), 10, 2 );

	}

	public function add_metabox() {

		add_meta_box(
			'manual_post_title_casing',
			'Post Title Casing',
			array( $this, 'fcc_edit_post_title_case_metabox' ),
			'post',
			'side',
			'default'
		);

	}

	public function fcc_edit_post_title_case_metabox( $post ) {

		// Retrieve an existing value from the database.
		$manual_post_title_casing = get_post_meta( $post->ID, 'manual_post_title_casing', true );

		// Set default values.

		// Form fields.
		echo '<table class="form-table">';

		echo '	<tr>';
		//echo '		<th><label for="manual_post_title_casing" class="manual_post_title_casing_label">' . 'Enable Manual Title Casing' . '</label></th>';
    echo '		<td>Enable Manual Title Casing</td>';
		echo '		<td>';
		echo '			<label><input type="checkbox" name="manual_post_title_casing" class="manual_post_title_casing_field" value="' . $manual_post_title_casing . '" ' . checked( $manual_post_title_casing, 'checked', false ) . '> ' . '' . '</label><br>';
		echo '		</td>';
		echo '	</tr>';
		echo '</table>';
	}

	public function save_metabox( $post_id, $post ) {
		// Sanitize user input.
		$manual_post_title_casingnew_ = isset( $_POST[ 'manual_post_title_casing' ] ) ? 'checked' : '';
		// Update the meta field in the database.
		update_post_meta( $post_id, 'manual_post_title_casing', $manual_post_title_casingnew_ );
	}
}
new Post_Title_Casing_Metabox;
