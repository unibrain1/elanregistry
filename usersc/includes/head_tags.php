<meta property="og:url"                content="https://www.elanregistry.org/" /> <!-- URL for website (link address) -->
<meta property="og:type"               content="website" /> <!-- type of site -->
<meta property="og:title"              content="Lotus Elan Registry" /> <!-- title of site (title of share) -->
<meta property="og:description"        content="Registry for the Lotus Elan and Elan +2" /> <!-- description of site (text which appears when sharing) -->
<meta property="og:image"              content="" /> <!-- URL for preview image -->	

<!-- Add car validation JS -->
<!-- TODO this should be only on tha pages needed -->
<?php
	if(file_exists($abs_us_root.$us_url_root.'app/js/cardefinition.js')){
			?>	 <script language="JavaScript" src=<?=$us_url_root.'app/js/cardefinition.js'?> type="text/javascript"></script> <?php
	}
?>
