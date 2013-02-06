<!--
	pfSense_MODULE:	footer
-->

		</div> <!-- Right DIV -->
	
	</div> <!-- Content DIV -->
	
	<div id="footer">
	<a target="_blank" href="<?=$g['product_website_footer']?>" class="redlnk"><?=$g['product_name']?></a> is &copy;
	 <?=$g['product_copyright_years']?> by <a href="<?=$g['product_copyright_url']?>" class="tblnk"><?=$g['product_copyright']?></a>. All Rights Reserved.
	[<a href="/license.php" class="tblnk">view license</a>] 
	</div> <!-- Footer DIV -->

</div> <!-- Wrapper Div -->

<?php outputJavaScriptFileInline("/usr/local/www/themes/{$g['theme']}/bottom-loader.js");
/* Disable form autocomplete on all but the login screen. */
if (basename($_SERVER["SCRIPT_FILENAME"] != "index.php") && !$allowautocomplete): ?>
<script type="text/javascript">
//<![CDATA[
(function ($) {
	$("input").attr("autocomplete","off");
})(jQuery);
//]]>
</script>
<?php endif; ?>
