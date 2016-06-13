<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php echo $PAGETITLE;?></title>
        <link rel="stylesheet" media="screen" href="css/style.css"/>
        <script src="js/jquery.js" type="text/javascript"></script>
    </head>
    <body>
       <div class="spinner">
            <div class="loading">
                <div class="loading_img">
                    <p>Processing Request...</p>
                </div>
            </div>
        </div>
	<form autocomplete="<?php echo AUTO_COMPLETE; ?>"  method="post" name="appForm" id="appForm" action="<?php echo $pageRedirectURL; ?>">
                <?php foreach ($postParams as $keyPostName => $valuePostName){ ?>
                 <input type="hidden" name="<?php echo $keyPostName; ?>" id="<?php echo $keyPostName; ?>" value="<?php echo $valuePostName; ?>">
                 <?php } ?>
                  <?php foreach ($errorArray as $keyError => $valueError){ ?>
                 <input type="hidden" name="<?php echo $keyError; ?>" id="<?php echo $keyError; ?>" value="<?php echo $valueError; ?>">
                 <?php } ?>
        </form>
	    </body>
</html>
<script type='text/javascript'>
    document.onload = manageProcess();
    function manageProcess(){
      	//$('.spinner').css('display','block');
      	document.appForm.submit();
    }
</script>
