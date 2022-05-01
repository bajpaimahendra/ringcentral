<?php
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


$rcsdk = new SDK($credentials['clientId'], $credentials['clientSecret'], $credentials['server'], 'RD', '1.0.0');

$platform = $rcsdk->platform();

// Authorize

$platform->login($credentials['username'], $credentials['extension'], $credentials['password'], true);

// Find call log records with recordings For extension

$callLogRecords = $platform->get('/account/~/extension/~/call-log', array(
                             'type'          => 'Voice',
                             'withRecording' => 'True',
                             'dateFrom'      => $dateFrom,
                             'dateTo'        => $dateTo
                              )
                            )
                           ->json()->records;


// $callLogRecords = $platform->get('/account/~/call-log', array(
//                              'type'          => 'Voice',
//                              'withRecording' => 'True',
//                              'dateFrom'      => $dateFrom,
//                              'dateTo'        => $dateTo
//                               )
//                             )
//                            ->json()->records;

// Create a CSV file to log the records

  $status = "Success";
  $dir = $dateFrom;
  $fname = __DIR__."/Csv/recordings_${dir}.csv";
  $fdir = __DIR__."/Recordings/${dir}";

  if (is_dir($fdir) === false)
  {
    mkdir($fdir, 0777, true);
  }

  $file = fopen($fname,'w');

  $fileHeaders = array("RecordingID","ContentURI","Filename","DownloadStatus");

  fputcsv($file, $fileHeaders);

  $fileContents = array();


  $timePerRecording = 6;

  foreach ($callLogRecords as $i => $callLogRecord) {

    $id = $callLogRecord->recording->id;


    $uri = $callLogRecord->recording->contentUri;


    $apiResponse = $platform->get($callLogRecord->recording->contentUri);

    $ext = ($apiResponse->response()->getHeader('Content-Type')[0] == 'audio/mpeg')
      ? 'mp3' : 'wav';

    $start = microtime(true);

    file_put_contents("${fdir}/recording_${id}.${ext}", $apiResponse->raw());

    $filename = "recording_${id}.${ext}";

    if(filesize("${fdir}/recording_${id}.${ext}") == 0) {
        $status = "failure";
    }

    file_put_contents("${fdir}/recording_${id}.json", json_encode($callLogRecord));

    $end=microtime(true);

    // Check if the recording completed wihtin 6 seconds.
    $time = ($end*1000 - $start * 1000);
    if($time < $timePerRecording) {
      sleep($timePerRecording-$time);
    }

    $fileContents = array($id, $uri, $filename, $status);
    fputcsv($file, $fileContents);

  }

  fclose($file);

?>