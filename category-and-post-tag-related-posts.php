<?php
ob_start();
 /*
* Plugin Name: Category and post tag related posts
* Plugin URI: http://online-source.net/2012/11/10/category-and-post-tag-related-posts
* Description: Show related posts matching category slugs or names with post tags
* Author: Laurens ten Ham (MrXHellboy)
* Version: 1.0.1
* Author URI: http://online-source.net
*/

class os_rp_tag_cat extends WP_Widget
{
	function os_rp_tag_cat()
	{
		$widget_ops = array( 'classname' => 'widget_rp_tag_cat', 'description' => 'Related posts (categories - post tags)' );
		$this->WP_Widget( 'os_rp_tag_cat', 'Related posts (categories - post tags)', $widget_ops );
	}

	function widget( $args, $instance )
	{
	   global $wp_query;
        if (is_category())
            $this->os_related_posts_set_cookie($wp_query->query_vars['cat']);
            
		if ( ( is_single() && $instance['show_on'] == 'single' ) xor ( is_category() && $instance['show_on'] == 'category' ) 
                xor ( ( is_category() || is_single() ) && $instance['show_on'] == 'both' ) )
		{       
            global $post, $wp_query;
            if (is_single() || is_category())
            {
                $post_in_cats = $this->os_rp_single_get_category_ids($post->ID, $instance);
                $post_tag_as_cat_name = $this->os_rp_match_cat2post_tag($instance, $post_in_cats);
                    if (count($post_tag_as_cat_name) > 0)
                    {
                        $related_post_ids = $this->os_rp_get_related_post_ids($instance, $post_tag_as_cat_name);
                        echo $this->os_rp_show_widget($args, $instance, $related_post_ids, $post_in_cats);
                    }
            }
            
		}
	}
    
    private function os_related_posts_set_cookie($cat){
            @setcookie('related_posts_cat_post_tag', $cat, time()+60*60*24*30, '/');
    }
    
    /**
     * Return the widget
     */
    private function os_rp_show_widget($args, $instance, $related_post_ids, $post_cats)
    {
        $title_append = '';
        foreach ($post_cats as $cat){
            $title_append .= $cat['cat_name'].', ';
        }

        $widget = $args['before_widget'];
            $widget .= $args['before_title'].$instance['title'];
                if ($instance['show_cat_tag_name'] == 'yes'){
                    $widget .= rtrim($title_append, ', ');
                }
            $widget .= $args['after_title'];
            $widget .= $this->os_rp_list_related_posts($related_post_ids, $instance);
        $widget .= $args['after_widget'];
            return $widget;
    }
    
    /**
     * Return string for SQL IN clause
     */
    private function os_rp_sql_in($input)
    {
        $sql_in = '(';
            foreach ($input as $var)
            {
                $sql_in .= '\''.$var.'\',';
            }
                $sql_in .= ')';
        $sql_in = rtrim($sql_in, ',)').')';
            return $sql_in;
    }
    
    private function os_rp_list_related_posts($a, $instance)
    {
        global $wpdb;
        $sql_in = $this->os_rp_sql_in($a);
        $sql_limit = ($instance['show_amount'] == '-1') ? '' : 'LIMIT 0, '.$instance['show_amount'];
                $related_post_info = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title FROM {$wpdb->prefix}posts WHERE ID IN {$sql_in} AND post_status = 'publish' ".$sql_limit), ARRAY_N);
            $list = '<ul>';
                foreach ($related_post_info as $post_info)
                {
                    $list .= '<li><a href="'. get_permalink($post_info[0]) .'" title="'. $post_info[1] .'">'. $post_info[1] .'</a></li>';
                }
            $list .= '</ul>';
                return $list;
    }
    
    /**
     * Retuns numeric array containing tag objects
     */
    private function os_rp_single_get_post_tag_ids($p_id)
    {
        return wp_get_post_tags($p_id, array('fields' => 'ids'));
    }
    
    /**
     * Single post || Category page
     * Step 1 - get the category ID, name and slug from the single post
     * Returns associative array cat_ID => cat_name
     */
    private function os_rp_single_get_category_ids($p_id, $instance)
    {
        if (is_single()){
            if ($instance['show_last_viewed_cat'] == 'no'){
                $cat_objs = get_the_category($p_id);
            } elseif ($instance['show_last_viewed_cat'] == 'yes'){
                if (!isset($_COOKIE['related_posts_cat_post_tag'])){
                    $cat_objs = get_the_category($p_id);
                } else {
                    $category_id = @$_COOKIE['related_posts_cat_post_tag'];
                    $cat_objs = get_term_by('id', $category_id, 'category');
                    $cat_objs = array($cat_objs);
                    # Make compatible for foreach() - overwrite default properties
                } }

            $cats = array();
                foreach ($cat_objs as $obj)
                {
                    $cats[$obj->term_id] = array('cat_name' => $obj->name, 'cat_slug' => $obj->slug);
                }
                    return $cats;            
        } elseif(is_category()) {
            $category_name = single_cat_title('', false);
                $cat_term = get_term_by('name', $category_name, 'category');
            $cats[$cat_term->term_id] = array('cat_name' => $cat_term->name, 'cat_slug' => $cat_term->slug);
                return $cats;
        }

    }
    
    /**
     * Single post || Category page
     * Step 2 - search for post tags just like the category slug
     * Returns numeric array cotaining associative row results
     */
    private function os_rp_match_cat2post_tag($instance, $cat_slug = array())
    {
        global $wpdb;
        $matched_post_tags = array(); $return_post_tags = array();
        
            foreach ($cat_slug as $cat_id => $cat_array)
            {
                if ($instance['match_cat_post_tag'] == 'slug')
                {
                    $matched_post_tags[] = get_term_by('slug', $cat_array['cat_slug'], 'post_tag');
                } 
                    elseif ($instance['match_cat_post_tag'] == 'title') 
                {
                    $matched_post_tags[] = get_term_by('name', $cat_array['cat_name'], 'post_tag');
                }
            }
                foreach ($matched_post_tags as $index => $object)
                {
                    if (is_bool($object))
                    {
                        continue;
                    } else {
                        $return_post_tags[] = $object;
                    }
                }
            return $return_post_tags;
    }
    
    /**
     * Single post || Category page
     * Step 3 - Get the related posts to the post tags
     */
    private function os_rp_get_related_post_ids($instance, $post_tags)
    {
        global $wpdb;
            $tag_slugs = array(); 
                foreach ($post_tags as $tag_obj)
                {
                    $tag_slugs[$tag_obj->term_id] = $tag_obj->slug;
                }
                $terms_id = array();
                    foreach ($tag_slugs as $tag_id => $tag_slug)
                    {
                        $terms_id[] = $tag_id;
                    }
            $sql_in = $this->os_rp_sql_in($terms_id);
                $term_tax_ids = $wpdb->get_results($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy WHERE term_id IN {$sql_in} and taxonomy = 'post_tag'"), ARRAY_A);
                $object_ids = null;
                foreach ($term_tax_ids as $tax_id_arr)
                {
                    foreach ($tax_id_arr as $tax_id)
                    {
                        $object_ids[] = $wpdb->get_results($wpdb->prepare("SELECT object_id FROM {$wpdb->prefix}term_relationships WHERE term_taxonomy_id = '".$tax_id."'"), ARRAY_N);
                    }
                }
                $final_ids = array();
            foreach ($object_ids as $index => $arr)
            {
                foreach ($arr as $a)
                {
                    $final_ids[] = $a[0];
                }
            }
            return $final_ids;        
    }

	function update( $new_instance, $old_instance )
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
        $instance['show_on'] = strip_tags( $new_instance['show_on'] );
        $instance['match_cat_post_tag'] = strip_tags( $new_instance['match_cat_post_tag'] );
        $instance['show_cat_tag_name'] = strip_tags( $new_instance['show_cat_tag_name'] );
        $instance['show_amount'] = strip_tags( $new_instance['show_amount'] );
        $instance['show_last_viewed_cat'] = strip_tags( $new_instance['show_last_viewed_cat'] );
      
		return $instance;
	}

	function form( $instance )
	{
		$instance = wp_parse_args( ( array )$instance, array( 
                                        'title' => 'See also',
                                        'show_on' => 'both',
                                        'match_cat_post_tag' => 'title',
                                        'show_cat_tag_name' => 'no',
                                        'show_amount' => 5,
                                        'show_last_viewed_cat' => 'no'
                                       ) 
                                        );
                                        
		$title = strip_tags( $instance['title'] );
        $show_on = strip_tags( $instance['show_on'] );
        $match_cat_post_tag = strip_tags( $instance['match_cat_post_tag'] );
        $show_cat_tag_name = strip_tags( $instance['show_cat_tag_name'] );
        $show_amount = strip_tags( $instance['show_amount'] );
        $show_last_viewed_cat = strip_tags( $instance['show_last_viewed_cat'] );
        
        ?>
			<p><label for="<?php echo $this->get_field_id( 'title' ); ?>">Title <small>(before the category name(s))</small>:<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo attribute_escape( $title ); ?>" /></label></p>
            <p><label for="<?php echo $this->get_field_id( 'show_cat_tag_name' ); ?>">Show category/post-tag name:<select class="widefat" id="<?php echo $this->get_field_id( 'show_cat_tag_name' ); ?>" name="<?php echo $this->get_field_name( 'show_cat_tag_name' ); ?>">
																					<option value="yes" <?php if ( $show_cat_tag_name == 'yes' ) echo 'selected="selected"'; ?>>Yes</option>
                                                                                    <option value="no" <?php if ( $show_cat_tag_name == 'no' ) echo 'selected="selected"'; ?>>No</option>
																					</select>
																					</label>
																					</p>
			<p><label for="<?php echo $this->get_field_id( 'show_amount' ); ?>">Show how many posts in the widget:<input class="widefat" id="<?php echo $this->get_field_id( 'show_amount' ); ?>" name="<?php echo $this->get_field_name( 'show_amount' ); ?>" type="text" value="<?php echo attribute_escape( $show_amount ); ?>" /><small>-1 for all posts</small></label></p>
            <p><label for="<?php echo $this->get_field_id( 'show_on' ); ?>">Show widget on: <select class="widefat" id="<?php echo $this->get_field_id( 'show_on' ); ?>" name="<?php echo $this->get_field_name( 'show_on' ); ?>">
																					<option value="single" <?php if ( $show_on == 'single' ) echo 'selected="selected"'; ?>>Single posts</option>
                                                                                    <option value="category" <?php if ( $show_on == 'category' ) echo 'selected="selected"'; ?>>Category pages</option>
																					<option value="both" <?php if ( $show_on == 'both' ) echo 'selected="selected"'; ?>>Single posts and category pages</option>
																					</select>
																					</label>
																					</p>
            <p><label for="<?php echo $this->get_field_id( 'match_cat_post_tag' ); ?>">Match the category against post-tags on:<select class="widefat" id="<?php echo $this->get_field_id( 'match_cat_post_tag' ); ?>" name="<?php echo $this->get_field_name( 'match_cat_post_tag' ); ?>">
																					<option value="title" <?php if ( $match_cat_post_tag == 'title' ) echo 'selected="selected"'; ?>>Category title</option>
                                                                                    <option value="slug" <?php if ( $match_cat_post_tag == 'slug' ) echo 'selected="selected"'; ?>>Category slug</option>
																					</select>
																					</label>
																					</p>
			<p><label for="<?php echo $this->get_field_id( 'show_last_viewed_cat' ); ?>">Post has multiple categories - what now: <select class="widefat" id="<?php echo $this->get_field_id( 'show_last_viewed_cat' ); ?>" name="<?php echo $this->get_field_name( 'show_last_viewed_cat' ); ?>">
                                                                                    <option value="yes" <?php if ( $show_last_viewed_cat == 'yes' ) echo 'selected="selected"'; ?>>Show the last category viewed</option>
																					<option value="no" <?php if ( $show_last_viewed_cat == 'no' ) echo 'selected="selected"'; ?>>Show all categories</option>
																					</select><small>In case a post has multiple categories</small><br /><small>!!!Uses cookies!!!</small>
																					</label>
																					</p>
<?php }

}

add_action( 'widgets_init', 'register_os_rp_tag_cat' );

function register_os_rp_tag_cat()
{
	register_widget( 'os_rp_tag_cat' );
}
ob_get_contents();?>