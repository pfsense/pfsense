<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<?php
/* 
 *   if user has selected a custom template, use it.
 *   otherwise default to pfsense tempalte
 */
if($config['template'] <> "")
	$g['template'] = $config['template'];
else
	$g['template'] = "pfsense";
?>

<html>
<head>
    <title><?=gentitle($pgtitle);?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
    <link rel="stylesheet" href="/templates/<?php echo $g['template']; ?>/all.css" media="all" />
    <link rel="stylesheet" type="text/css" href="/niftycssprintCode.css" media="print">
    <script type="text/javascript" src="/niftyjsCode.js"></script>
<?php if (!isset($closehead)){ ?>
</head>
<?php } ?>
