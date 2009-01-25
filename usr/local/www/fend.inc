		</div> <!-- Right DIV -->
	
	</div> <!-- Content DIV -->
	
	<div id="footer">
	<a target="_blank" href="<?=$g['product_website']?>" class="redlnk"><?=$g['product_name']?></a> is &copy;
	 <?=$g['product_copyright_years']?> by <a href="<?=$g['product_copyright_url']?>" class="tblnk"><?=$g['product_copyright']?></a>. All Rights Reserved.
	[<a href="/license.php" class="tblnk">view license</a>] 
	</div> <!-- Footer DIV -->

</div> <!-- Wrapper Div -->

<?php outputJavaScriptFileInline("themes/{$g['theme']}/bottom-loader.js"); ?>

