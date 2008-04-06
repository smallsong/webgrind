<?php

require 'config.php';
require 'lib/FileHandler.php';
// Errorhandling.
// No files, outputdir not writabel
// Show self traces in option group
set_time_limit(0);

// Make sure we have a timezone for date functions.
if (ini_get('date.timezone') == '')
    date_default_timezone_set( Config::$defaultTimezone );


switch(get('op')){
	case 'file_list':
		echo json_encode(FileHandler::getInstance()->getTraceList());
		break;	
	case 'function_list':
		$dataFile = get('dataFile');
		if($dataFile=='0'){
			$files = FileHandler::getInstance()->getTraceList();
			$dataFile = $files[0]['filename'];
		}
		$reader = FileHandler::getInstance()->getTraceReader($dataFile);
		$count = $reader->getFunctionCount();
		$functions = array();
		$totalCost = array('self' => 0, 'inclusive' => 0);
        $result['totalRunTime'] = $reader->getHeader('summary');

		for($i=0;$i<$count;$i++) {
		    $functionInfo = $reader->getFunctionInfo($i);
		    
		    if (!(int)get('hideInternals', 0) || strpos($functionInfo['functionName'], 'php::') === false) {
    			$totalCost['self'] += $functionInfo['totalSelfCost'];
    			$totalCost['inclusive'] += $functionInfo['totalInclusiveSelfCost'];
    			$functions[$i] = $functionInfo;
    			$functions[$i]['nr'] = $i;
    		}
		}
		usort($functions,'costCmp');
		
		$remainingCost = $totalCost['self']*get('showFraction');
		
		$result['functions'] = array();
		foreach($functions as $function){
			$remainingCost -= $function['totalSelfCost'];
			if(get('costFormat')=='percentual'){
				$function['totalSelfCost'] = percentCost($function['totalSelfCost'], $result['totalRunTime']);
				$function['totalInclusiveSelfCost'] = percentCost($function['totalInclusiveSelfCost'], $result['totalRunTime']);
			}
			$result['functions'][] = $function;
			if($remainingCost<0)
				break;
		}
		$result['dataFile'] = $dataFile;
		$result['invokeUrl'] = $reader->getHeader('cmd');
		$result['mtime'] = date(Config::$dateFormat,filemtime(Config::$xdebugOutputDir.$dataFile));
		$result['totalSelftime'] = $totalCost['self'];
		echo json_encode($result);
	break;
	case 'invocation_list':
		$reader = FileHandler::getInstance()->getTraceReader(get('file'));
		$functionNr = get('functionNr');
 		$function = $reader->getFunctionInfo($functionNr);
		$start = get('start',0);
		$end = $start+Config::$numberOfInvocations;
		if($end>$function['invocationCount'])
			$end = $function['invocationCount'];
			
		echo '[';
		for($i=$start;$i<$end;$i++){
			$invo = $reader->getInvocation($functionNr, $i, get('costFormat', 'absolute'));
			if($invo['calledFromFunction']==-1){
				$invo['callerInfo'] = false;
			} else {
				$invo['callerInfo'] = $reader->getFunctionInfo($invo['calledFromFunction'], get('costFormat', 'absolute'));				
			}
			echo json_encode($invo).',';
		}
		echo ']';
	break;
	default:
		require 'templates/index.phtml';
}

function get($param, $default=false){
	return (isset($_GET[$param])? $_GET[$param] : $default);
}

function costCmp($a, $b){
	$a = $a['totalSelfCost'];
	$b = $b['totalSelfCost'];

	if ($a == $b) {
	    return 0;
	}
	return ($a > $b) ? -1 : 1;
}

function percentCost($cost, $total){
	$result = ($total==0) ? 0 : ($cost*100)/$total;
	return number_format($result, 3, '.', '');
}
