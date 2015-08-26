	<!--
		pfSense_MODULE:	footer
	-->
<?php
/*
 * We put jquery in the footer; it is the preferred position for speed
 * and helps developers to use events[] instead of executing scripts directly.
 */
?>
	</div>

	<footer class="footer">
		<div class="container">
			<p class="text-muted">
				<a target="_blank" href="<?=$g['product_website_footer']?>"><?=$g['product_name']?></a> is &copy;
				<?=$g['product_copyright_years']?> by <a href="<?=$g['product_copyright_url']?>" class="tblnk"><?=$g['product_copyright']?></a>. All Rights Reserved.
				[<a href="/license.php" class="tblnk">view license</a>]
			</p>
		</div>
	</footer>

	<script src="/jquery/jquery-1.11.2.min.js"></script>
	<script src="/jquery/jquery-ui-1.11.2.min.js"></script>
	<script src="/bootstrap/js/bootstrap.min.js"></script>
	<script src="/jquery/pfSense.js"></script>
</body>
</html>