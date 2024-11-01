<?php
$wa_wps_display_image = get_option('wa_wps_display_image'); 
$wa_wps_display_price = get_option('wa_wps_display_price');
$wa_wps_display_add_to_cart = get_option('wa_wps_display_add_to_cart');
$wa_wps_display_title = get_option('wa_wps_display_title');
$wa_wps_display_excerpt = get_option('wa_wps_display_excerpt');
$wa_wps_display_read_more_text = get_option('wa_wps_display_read_more_text');
$wa_wps_word_limit = get_option('wa_wps_word_limit');
$wa_wps_query_posts_showposts = get_option('wa_wps_query_posts_showposts');
$wa_wps_query_posts_orderby= get_option('wa_wps_query_posts_orderby');
$wa_wps_query_posts_order= get_option('wa_wps_query_posts_order');
$wa_wps_query_posts_category= get_option('wa_wps_query_posts_category');
$wa_wps_posts_image_width = get_option('wa_wps_posts_image_width');
$wa_wps_posts_image_height = get_option('wa_wps_posts_image_height');
$wa_wps_display_page = get_option('wa_wps_display_page');
$wa_wps_display_nav = get_option('wa_wps_display_nav');

if (@$_POST['wa_wps_submit']) {
	$wa_wps_display_image = isset($_POST['wa_wps_display_image'])?$_POST['wa_wps_display_image']:1;
	$wa_wps_display_price = isset($_POST['wa_wps_display_price'])?$_POST['wa_wps_display_price']:1;
	$wa_wps_display_add_to_cart = isset($_POST['wa_wps_display_add_to_cart'])?$_POST['wa_wps_display_add_to_cart']:1;
	$wa_wps_display_title = isset($_POST['wa_wps_display_title'])?$_POST['wa_wps_display_title']:1;
	$wa_wps_display_excerpt = isset($_POST['wa_wps_display_excerpt'])?$_POST['wa_wps_display_excerpt']:0;
	$wa_wps_display_read_more_text = isset($_POST['wa_wps_display_read_more_text'])?$_POST['wa_wps_display_read_more_text']:0;
	$wa_wps_word_limit = stripslashes($_POST['wa_wps_word_limit']);
	$wa_wps_query_posts_showposts = stripslashes($_POST['wa_wps_query_posts_showposts']);
	$wa_wps_query_posts_orderby = stripslashes($_POST['wa_wps_query_posts_orderby']);
	$wa_wps_query_posts_order = stripslashes($_POST['wa_wps_query_posts_order']);
	$wa_wps_query_posts_category = stripslashes($_POST['wa_wps_query_posts_category']);
	$wa_wps_posts_image_width = stripslashes($_POST['wa_wps_posts_image_width']);
	$wa_wps_posts_image_height = stripslashes($_POST['wa_wps_posts_image_height']);
	$wa_wps_display_page = stripslashes($_POST['wa_wps_display_page']);
	$wa_wps_display_nav = stripslashes($_POST['wa_wps_display_nav']);

	update_option('wa_wps_display_image', $wa_wps_display_image );
	update_option('wa_wps_display_price', $wa_wps_display_price );
	update_option('wa_wps_display_add_to_cart', $wa_wps_display_add_to_cart );
	update_option('wa_wps_display_title', $wa_wps_display_title );
	update_option('wa_wps_display_excerpt', $wa_wps_display_excerpt );
	update_option('wa_wps_display_read_more_text', $wa_wps_display_read_more_text );
	update_option('wa_wps_word_limit', $wa_wps_word_limit );
	update_option('wa_wps_query_posts_showposts', $wa_wps_query_posts_showposts );
	update_option('wa_wps_query_posts_orderby', $wa_wps_query_posts_orderby );
	update_option('wa_wps_query_posts_order', $wa_wps_query_posts_order );
	update_option('wa_wps_query_posts_category', $wa_wps_query_posts_category );
	update_option('wa_wps_posts_image_width', $wa_wps_posts_image_width );
	update_option('wa_wps_posts_image_height', $wa_wps_posts_image_height );
	update_option('wa_wps_display_page', $wa_wps_display_page );
	update_option('wa_wps_display_nav', $wa_wps_display_nav );

	$wa_wps_success = "Settings saved.";
}

$display_image = array(1=>'Yes',0=>'No');
$display_title = array(1=>'Yes',0=>'No');
$display_price = array(1=>'Yes',0=>'No');
$display_add_to_cart = array(1=>'Yes',0=>'No');
$display_controls = array(1=>'Yes',0=>'No');
$display_page = array(0=>'No',1=>'Yes');
$display_excerpt = array(1=>'Yes',0=>'No');
$display_read_more = array(1=>'Yes',0=>'No');
?>
<div class="wrap">
<h2>WooCommerce Product Slider</h2>
<?php if(isset($wa_wps_success)){?>
<div class="updated fade"><p><strong><?php echo $wa_wps_success; ?></strong></p></div><? } ?>
<form name="wa_wps_form" method="post" action="" style="border:1px solid #ccc;padding:10px;background:#fff;margin:0; width:50%;">
<table class="form-table">
<tbody>

<!-- show post image -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Show post image:</label></th>
<td>	<select name="wa_wps_display_image" id="wa_wps_display_image">
<?php foreach ($display_image  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_image){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- display title -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Display Title:</label></th>
<td>	<select name="wa_wps_display_title" id="wa_wps_display_title">
<?php foreach ($display_title  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_title){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- display excerpt -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Display excerpt:</label></th>
<td>	<select name="wa_wps_display_excerpt" id="wa_wps_display_excerpt">
<?php foreach ($display_excerpt  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_excerpt){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- Read more text -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Display Read More text:</label></th>
<td>	<select name="wa_wps_display_read_more_text" id="wa_wps_display_read_more_text">
<?php foreach ($display_read_more  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_read_more_text){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- Add to cart -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Display add to cart:</label></th>
<td>	<select name="wa_wps_display_add_to_cart" id="wa_wps_display_add_to_cart">
<?php foreach ($display_add_to_cart  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_add_to_cart){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- Product price -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Display product price:</label></th>
<td>	<select name="wa_wps_display_price" id="wa_wps_display_price">
<?php foreach ($display_price  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_price){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- left and right controls -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Display left and right controls*:</label></th>
<td>	<select name="wa_wps_display_nav" id="wa_wps_display_nav">
<?php foreach ($display_controls  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_nav){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- pagination -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Display pagination*:</label></th>
<td>	<select name="wa_wps_display_page" id="wa_wps_display_page">
<?php foreach ($display_page  as $key => $value) {?>
<option value="<?php echo $key; ?>" <?php if($key==$wa_wps_display_page){echo "selected";}?>><?php echo $value;?></option>
<?php }?>
</select></td>
</tr>

<!-- product image sizes -->
<tr class="form-field form-required">
<th scope="row"><label for="name">Product image width:</label></th>
<td><input name="wa_wps_posts_image_width" type="text" id="wa_wps_posts_image_width" value="<?php echo $wa_wps_posts_image_width; ?>"  />
<p class="description">Width of image in the slider. e.g. 150px, 20%, 100</p></td>
</tr>

<tr class="form-field form-required">
<th scope="row"><label for="name">Product image height:</label></th>
<td><input name="wa_wps_posts_image_height" type="text" id="wa_wps_posts_image_height" value="<?php echo $wa_wps_posts_image_height; ?>"  />
<p class="description">Height of image in the slider. e.g. 150px, 20%, 100</p></td>
</tr>

<tr class="form-field form-required"><th scope="row"><label for="name">Excerpt Length</label></th><td><input  style="width: 200px;" maxlength="4" type="text" value="<?php echo $wa_wps_word_limit; ?>" name="wa_wps_word_limit" id="wa_wps_word_limit" /> 
<p class="description">Character Limit. e.g. 10</p></td></tr>

<tr class="form-field form-required"><th scope="row"><label for="name">Number of products to be shown in the slider</label></th><td><input  style="width: 200px;" maxlength="2" type="text" value="<?php echo $wa_wps_query_posts_showposts; ?>" name="wa_wps_query_posts_showposts" id="wa_wps_query_posts_showposts" />
<p class="description">e.g. 20</p></td></tr>

<tr class="form-field form-required"><th scope="row"><label for="name">Product order by</label></th><td><input  style="width: 200px;" maxlength="100" type="text" value="<?php echo $wa_wps_query_posts_orderby; ?>" name="wa_wps_query_posts_orderby" id="wa_wps_query_posts_orderby" /> <p class="description">e.g. ID (Possible values: id, author, title, date, category, modified)</p></td></tr>

<tr class="form-field form-required"><th scope="row"><label for="name">Product order</label></th><td><input  style="width: 200px;" maxlength="100" type="text" value="<?php echo $wa_wps_query_posts_order; ?>" name="wa_wps_query_posts_order" id="wa_wps_query_posts_order" /> 
<p class="description">e.g. rand (Possible values: rand, asc, desc)</p></td></tr>

<tr class="form-field form-required"><th scope="row"><label for="name">Products of which category will be displayed</label></th><td><input  style="width: 200px;" maxlength="100" type="text" value="<?php echo $wa_wps_query_posts_category;?>" name="wa_wps_query_posts_category" id="wa_wps_query_posts_category" /> <p class="description">terms/slugs names seperated by comma e.g. clothing, hoodies</p></td></tr>
</tbody>
</table>
<input name="wa_wps_submit" id="wa_wps_submit" class="button-primary" value="Save Changes" type="submit" />

</form>
<br/>
<form name="wa_wps_form" method="post" action="" style="border:1px solid #ccc;padding:10px;background:#fff;margin:0; width:50%;">
<p>Shortcode: [wa-wps]</p>
<p>Template tag: <?php echo htmlspecialchars("<?php if(function_exists('wa_wps')){ echo wa_wps();} ?>"); ?></p>
</form>


<br/>

<form name="tchpcs_form" method="post" action="" style="border:1px solid #ccc;padding:10px;background:#fff;margin:0; width:50%;">
<a style ="text-decoration:none; font-size:14px;" href="http://weaveapps.com/shop/wordpress-plugins/woocommerce-product-slider-pro/" target="_blank">Need more sliders? Looking for more features? Upgrade WooCommerce Product Slider "Pro" here.</a></form>

</div>