<?php
///---- read fax messages then download attachments -----
// Set the default timezone to use.
date_default_timezone_set('UTC');

require_once(__DIR__ . '/_bootstrap.php');

use RingCentral\SDK\SDK;

//-- Parse command line argument by $argv 
//print_r($argv);

if(isset($argv[1])){
  echo $dateFrom = $argv[1];
}else{
  echo $dateFrom = date('Y-m-d',strtotime("-1 days")); //yesterdays date
}

echo  '
';

if(isset($argv[2])){
  echo $dateTo = $argv[2];
}else{
  echo $dateTo = date('Y-m-d'); // Today Date
}

echo  '
';


// Create SDK instance

$credentials = require(__DIR__ . '/_credentials.php');

print_r($credentials);//die('1111111');


$rcsdk = new SDK($credentials['clientId'], $credentials['clientSecret'], $credentials['server'], 'brightway', '1.0.0');

$platform = $rcsdk->platform();

// Authorize

$platform->login($credentials['username'], $credentials['extension'], $credentials['password'], true);

// Find call log records with recordings For extension

$faxRecords = $platform->get('/account/~/extension/~/message-store', array(
                             'messageType'          => 'Fax',
                             //'messageType'        => 'SMS',
                             //'withRecording' => 'True',
                             'dateFrom'      => $dateFrom,
                             //'dateTo'        => $dateTo
                              )
                            )
                           ->json()->records;


//print_r($faxRecords);

// Create a CSV file to log the records

  $status = "Success";
  $dir = $dateFrom;
  $fname = __DIR__."/Fax/fax_${dir}.csv";
  $fdir = __DIR__."/Fax/${dir}";

  if (is_dir($fdir) === false)
  {
    mkdir($fdir, 0777, true);
  }

  $file = fopen($fname,'w');

  $fileHeaders = array("ID","ContentURI","Filename","DownloadStatus");

  fputcsv($file, $fileHeaders);

  $fileContents = array();


  $timePerRecording = 60;

  foreach ($faxRecords as $i => $faxRecord) {

    print_r($faxRecord);

    $id = $faxRecord->attachments[0]->id;


    echo  $uri = $faxRecord->attachments[0]->uri;


   $apiResponse = $platform->get($uri);
//die('======================================================================================');
    $ext = ($apiResponse->response()->getHeader('Content-Type')[0] == 'application/pdf')
      ? 'pdf' : 'jpg';

    $start = microtime(true);

    file_put_contents("${fdir}/fax_${id}.${ext}", $apiResponse->raw());

    $filename = "fax_${id}.${ext}";

    if(filesize("${fdir}/fax_${id}.${ext}") == 0) {
        $status = "failure";
    }

    file_put_contents("${fdir}/fax_${id}.json", json_encode($faxRecord));

    $end=microtime(true);

    // Check if the recording completed wihtin 6 seconds.
    $time = ($end*1000 - $start * 1000);
    if($time < $timePerRecording) {
      sleep($timePerRecording-$time);
    }

    echo $id.' '.$uri.' '.$filename;
    $fileContents = array($id, $uri, $filename, $status);
    print_r($fileContents);
    fputcsv($file, $fileContents);

  }

  fclose($file);

?>