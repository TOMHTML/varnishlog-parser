<?php

include 'VarnishlogParser.class.php';
include 'kint/Kint.class.php';

$FILEPATH = "";
$FILENAME = "";
$error = "";
$transactions_list = "";
$transactions_string = "";
$transactions_diagram_url = "";
try {
  // Check requirements
  if(!class_exists("VarnishlogParser\VarnishlogParser"))
    throw new Exception("Please, include VarnishlogParser in this directory!");
  if(!class_exists("\Kint"))
    throw new Exception("Please, include Kint library.");

  // Check input file
  if(!empty($_FILES["fileselected"]['tmp_name']) && !$_FILES["fileselected"]['error']){
    $FILEPATH = $_FILES["fileselected"]['tmp_name'];
    $FILENAME = $_FILES["fileselected"]['name'];
  }
  elseif(!empty($_REQUEST["filepath"])){
    $FILEPATH = $_REQUEST["filepath"]; // Obvious XSS flaw here
    $FILENAME = $FILEPATH;
  }
  else {
    throw new InvalidArgumentException("No filepath provided");
  }

  // Parse Varnishlog file
  $transactions_list = VarnishlogParser\VarnishlogParser::parse($FILEPATH);
  if(empty($transactions_list))
    throw new Exception("Unable to parse the provided file: did you use <code>varnishlog -g raw</code> to generate this file?", 1);
  // Output text representation of transactions
  $transactions_string = VarnishlogParser\VarnishlogParser::simpleAnalysis($transactions_list,1);
  // Reorder for future use
  ksort($transactions_list);
  // Get URL for sequence diagram
  $transactions_diagram_url = VarnishlogParser\VarnishlogParser::getSequenceDiagram($transactions_string);
  if(!$transactions_diagram_url)
    throw new Exception("Error while generating image with websequencediagrams.com.");
}
catch(\InvalidArgumentException $e){
  $error = "";
}
catch(\Exception $e){
  $error = $e->getMessage();
}

?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <?php if(empty($FILENAME) && empty($FILEPATH)): ?>
    <title>Varnishlog analysis</title>
  <?php else: ?>
    <title>Varnishlog analysis for : <?php echo $FILENAME ?></title>
  <?php endif; ?>
  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <div class="container">
    <div class="jumbotron">
      <h1>Varnishlog Analysis</h1>
      <?php if($FILENAME): ?>
        <p><em><?php echo $FILENAME // Obvious XSS flaw here ?></em></p>
        <form method="get" action="<?php echo $_SERVER['PHP_SELF']?>">
          <button type="submit" class="btn btn-primary">Try another file</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if(empty($FILEPATH)) : ?>
      <!-- No filepath provided -->
      <form class="form-horizontal" method="post" action="<?php echo $_SERVER['PHP_SELF']?>" enctype="multipart/form-data">
        <div class="form-group">
          <label for="filepath" class="col-sm-4 control-label">Local path of varnishlog file...</label>
          <div class="col-sm-8">
            <input type="textfield" class="form-control" id="filepath" name="filepath" placeholder="./examples/vsltrans_gist.log">
          </div>

          <label for="fileselected" class="col-sm-4 control-label">...or upload a file</label>
          <div class="col-sm-8">
            <input type="file" id="fileselected" name="fileselected">
          </div>
        </div>
        <div class="form-group">
          <label for="show_debug" class="col-sm-4 control-label">Display debug data</label>
          <div class="col-sm-8">
            <input type="checkbox" id="show_debug" name="show_debug" value="1">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
        <button type="button" class="btn" onclick="this.form.filepath.value='./examples/vsltrans_gist.log';this.form.submit();">See example</button>
      </form>

    <?php elseif($error):?>
      <!-- An error occured -->
      <div class="alert alert-danger" role="alert"><?php echo $error ?></div>

    <?php else: ?>
      <!-- Everything is fine -->
      <div class="container">
        <h2>Sequence diagram</h2>
        <p>
          <a href="<?php echo $transactions_diagram_url ?>" target="_black" title="See this image at full size"><img alt="Sequence diagram, explained below" src="<?php echo $transactions_diagram_url ?>" class="img-responsive center-block" /></a>
          <p><strong>Note :</strong> this image will be destroyed in two minutes.</p>
        </p>
      </div>

      <?php if(!empty($_REQUEST['show_debug']) && $_REQUEST['show_debug'] == "1"): ?>
        <div class="container">
          <h2>All transactions recorded</h2>
          <?php \Kint::dump( $transactions_list, "Transaction list" ); ?>
        </div>

        <div class="container">
          <h2>Simple representation</h2>
          <pre class="pre-scrollable"><?php print $transactions_string ?></pre>
        </div>
      <?php endif; // end display debugs ?>
    <?php endif; // end display results ?>
  </div>
</body>
</html>
