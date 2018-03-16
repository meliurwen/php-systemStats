<?php



	if(1) { #Add here login check


if (filemtime("results.json") < time() - 5){


$data['title'] = "ServerShortStats";
$data['hostname'] = get_genericValues("/etc/hostname");
$data['timeUnixUtcMilliseconds'] = round(microtime(true) * 1000);
$data['timezone'] = get_genericValues("/etc/timezone");
#$data['timeLocal'] = date(DATE_ISO8601, time());
$data['services'] = array();
$data['services']['transmission'] = runningProcCheck("transmission-daemon");
$data['services']['ts3server'] = runningProcCheck("ts3server");
$data['services']['starbound'] = runningProcCheck("starbound_server");
$data['services']['minecraft'] = runningProcCheck("minecraft");
$data['services']['openvpn'] = runningProcCheck("openvpn");
$data['services']['infinoted'] = runningProcCheck("infinoted-0.6");
$data['resources'] = array();
$data['resources']['uptime'] = get_systemUptime();
$data['resources']['systemUsage'] = get_systemUsage();






/*
Idle0 = idle0 + iowait0
Idle = idle + iowait

NonIdle0 = user0 + nice0 + system0 + irq0 + softirq0 + steal0
NonIdle = user + nice + system + irq + softirq + steal

Total0 = Idle0 + NonIdle0
Total = Idle + NonIdle

# differentiate: actual value minus the previous one
totald = Total - Total0
idled = Idle - Idle0

CPU_Percentage = (totald - idled)/totald
*/

#See documentation: https://www.kernel.org/doc/Documentation/filesystems/proc.txt

$cpuStatTemp = array();

$fileCpu = file('/proc/stat');
$i = 0;
foreach($fileCpu as $riga){
	if(preg_match("/cpu[0-9] ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*)/", $riga, $cpuArray)){
		$cpuStatTemp[$i] = array();
		$cpuStatTemp[$i]['user'] = $cpuArray[1];
		$cpuStatTemp[$i]['nice'] = $cpuArray[2];
		$cpuStatTemp[$i]['system'] = $cpuArray[3];
		$cpuStatTemp[$i]['idle'] = $cpuArray[4];
		$cpuStatTemp[$i]['iowait'] = $cpuArray[5];
		$cpuStatTemp[$i]['irq'] = $cpuArray[6];
		$cpuStatTemp[$i]['softirq'] = $cpuArray[7];
		$cpuStatTemp[$i]['steal'] = $cpuArray[8];
		$cpuStatTemp[$i]['guest'] = $cpuArray[9];
		$cpuStatTemp[$i]['guest_nice'] = $cpuArray[10];
		$i++;
	}
}

##########
preg_match_all("/(.*[a-zA-Z0-9_\(\)\[\]])\s*: ?(.*)?/", file_get_contents('/proc/cpuinfo'), $cpuInfos);
$numRows = count($cpuInfos[1]);

$cpuPhysicalSockets = 0;
$cpuThreads = 1;
$cpuCores = 1;

$i = 0;
foreach($cpuInfos[1] as $cpuKey){
	if($cpuKey == "physical id"){
		if($cpuInfos[2][$i] > $cpuPhysicalSockets){
			$cpuPhysicalSockets = $cpuInfos[2][$i];
		}
	}
	if($cpuKey == "siblings"){
		if($cpuInfos[2][$i] > $cpuThreads){
			$cpuThreads = $cpuInfos[2][$i];
		}
	}
	if($cpuKey == "cpu cores"){
		if($cpuInfos[2][$i] > $cpuCores){
			$cpuCores = $cpuInfos[2][$i];
		}
	}
$i++;
}
//Sommo perchè "SocketFisici = idFisici + 1" e poi calcolo i quanti thread ci sono per core
$cpuPhysicalSockets++;
$cpuThreadsPerCore = $cpuThreads / $cpuCores;
########





$data['resources']['cpu'] = array();
$y = 0;
for($i = 0;$i < $cpuPhysicalSockets; $i++){
	$data['resources']['cpu'][$i] = array();
	$numRows = count($cpuInfos[1]);
	$coresFreq = array();
	$u = 0;
	for($z = 0; $z < $numRows; $z++){
		$info = strtolower(preg_replace('/( |_)/', '', $cpuInfos[1][$z]));
		if(($info != "processor") & ($info != "siblings") & ($info != "physicalid") & ($info != "cpucores") & ($info != "coreid") & ($info != "powermanagement") & ($info != "flags")){
			if($info == "cpumhz"){
				$coresFreq[$u] = $cpuInfos[2][$z];
				$u++;
			}
			else{
				$data['resources']['cpu'][$i][$info] = $cpuInfos[2][$z];
			}
		}
	}
	for($j = 0;$j < $cpuCores; $j++){
		$data['resources']['cpu'][$i][$j] = array();
		for($x = 0;$x < $cpuThreadsPerCore; $x++){

			$data['resources']['cpu'][$i][$j][$x] = array();
			$data['resources']['cpu'][$i][$j][$x]['coreFreq'] = $coresFreq[$y];
			$data['resources']['cpu'][$i][$j][$x]['user'] = $cpuStatTemp[$y]['user'];
			$data['resources']['cpu'][$i][$j][$x]['nice'] = $cpuStatTemp[$y]['nice'];
			$data['resources']['cpu'][$i][$j][$x]['system'] = $cpuStatTemp[$y]['system'];
			$data['resources']['cpu'][$i][$j][$x]['idle'] = $cpuStatTemp[$y]['idle'];
			$data['resources']['cpu'][$i][$j][$x]['iowait'] = $cpuStatTemp[$y]['iowait'];
			$data['resources']['cpu'][$i][$j][$x]['irq'] = $cpuStatTemp[$y]['irq'];
			$data['resources']['cpu'][$i][$j][$x]['softirq'] = $cpuStatTemp[$y]['softirq'];
			$data['resources']['cpu'][$i][$j][$x]['steal'] = $cpuStatTemp[$y]['steal'];
			$data['resources']['cpu'][$i][$j][$x]['guest'] = $cpuStatTemp[$y]['guest'];
			$data['resources']['cpu'][$i][$j][$x]['guestNice'] = $cpuStatTemp[$y]['guest_nice'];
			$y++;
		}
	}
}





$data['resources']['ram'] = array();
$data['resources']['ram']['total'] = get_totalRam();
$data['resources']['ram']['used'] = get_usedRam();
#Network
$array = array();
$directory = "/sys/class/net/";
if ($handle = opendir($directory)) {
	while (false !== ($file = readdir($handle))){
		if ($file != "." && $file != ".." && is_dir($directory . "/" . $file)){
			array_push($array,$file);
		}
	}
	closedir($handle);

	$data['resources']['network'] = array();
	foreach($array as $dispositivo){
		$data['resources']['network'][$dispositivo] = array();
		$data['resources']['network'][$dispositivo]['operstate'] = get_networkValues($dispositivo, "operstate");
		$data['resources']['network'][$dispositivo]['address'] = get_networkValues($dispositivo, "address");
		$data['resources']['network'][$dispositivo]['negotiatedSpeed'] = get_networkValues($dispositivo, "speed");
		$data['resources']['network'][$dispositivo]['total'] = array();
		$data['resources']['network'][$dispositivo]['total']['rx'] = get_networkValues($dispositivo, 'statistics/rx_bytes');
		$data['resources']['network'][$dispositivo]['total']['tx'] = get_networkValues($dispositivo, 'statistics/tx_bytes');
	}
}
#Disk Drives
$array = array();
$directory = "/sys/block/";
if ($handle = opendir($directory)) {
	while (false !== ($file = readdir($handle))){
		if ($file != "." && $file != ".." && is_dir($directory . "/" . $file) && substr($file, 0, 2) == 'sd'){
			array_push($array,$file);
		}
	}
	closedir($handle);

	$data['resources']['disk'] = array();
	foreach($array as $dispositivo){
		$data['resources']['disk'][$dispositivo] = array();
		$data['resources']['disk'][$dispositivo]['vendor'] = get_diskValues($dispositivo, "device/vendor");
		$data['resources']['disk'][$dispositivo]['model'] = get_diskValues($dispositivo, "device/model");
		$data['resources']['disk'][$dispositivo]['sectorSize'] = get_diskValues($dispositivo, "queue/hw_sector_size");
		#Stats
		#Documentation: https://www.kernel.org/doc/Documentation/block/stat.txt
		$arrayStats = preg_split('/\\s+/', get_diskValues($dispositivo, "stat"));
		$data['resources']['disk'][$dispositivo]['readTotalSectors'] = $arrayStats[2];
		$data['resources']['disk'][$dispositivo]['writeTotalSectors'] = $arrayStats[6];
		#Partizioni
		$subArray = array();
		$subDirectory = "/sys/block/" . $dispositivo . "/";
		if ($handle = opendir($subDirectory)) {
			while (false !== ($file = readdir($handle))){
				if ($file != "." && $file != ".." && is_dir($subDirectory . "/" . $file) && substr($file, 0, strlen($dispositivo)) == $dispositivo){
					array_push($subArray,$file);
				}
			}
			closedir($handle);


			
			foreach($subArray as $partizione){
				$data['resources']['disk'][$dispositivo][$partizione] = array();
				$data['resources']['disk'][$dispositivo][$partizione]['firstSector'] = get_diskValues($dispositivo, $partizione . "/start");
				$data['resources']['disk'][$dispositivo][$partizione]['totalSectors'] = get_diskValues($dispositivo, $partizione . "/size");




			}
		}
	}
}

#Mount points
preg_match_all("/\/dev\/(sd[Aa-zZ][0-9]*) (\S*) (\S*)/", shell_exec("cat /proc/mounts"), $mountArray);
#Multidimensional arrays NEED FIX
$y = count($mountArray[0]);
for ($i = 0; $i < $y; $i++){
	$disp = str_replace(range(0,9),'',$mountArray[1][$i]);
	$data['resources']['disk'][$disp][$mountArray[1][$i]]['freeSectors'] = (disk_free_space($mountArray[2][$i]))/(get_diskValues(str_replace(range(0,9),'',$mountArray[1][$i]), "queue/hw_sector_size"));
	$data['resources']['disk'][$disp][$mountArray[1][$i]]['mountPoint'] = $mountArray[2][$i];
	$data['resources']['disk'][$disp][$mountArray[1][$i]]['fileSystem'] = $mountArray[3][$i];
}





$data['applications'] = array();

#OpenVPN
$fileName = "/etc/openvpn/openvpn-status.log";
if (file_exists($fileName)){
$openVpnData = parseLog ($fileName, "UDP");
$numUtenti = sizeof($openVpnData["users"]["0"]['RealAddress']);

$data['applications']['openvpn'] = array();
$data['applications']['openvpn']['uptime'] = processUptime("openvpn");
$data['applications']['openvpn']['onlineUsers'] = $numUtenti;
$data['applications']['openvpn']['lastUpdate'] = $openVpnData["updated"];
}

#Transmission

$pop = is_file('~/.config/transmission-daemon/stats.json');
$j = '{"downloaded-bytes": 359313302626,"files-added": 20779,"seconds-active": 5458300,"session-count": 12,"uploaded-bytes": 2184162144231}';

$transmissionObj = json_decode($j, true);
#fclose($fop);

$data['applications']['transmission'] = array();
$data['applications']['transmission']['uptime'] = processUptime("transmission-daemon");
$data['applications']['transmission']['started'] = $transmissionObj['session-count'];
$data['applications']['transmission']['uptimeTotal'] = $transmissionObj['seconds-active'];
$data['applications']['transmission']['downTotal'] = $transmissionObj['downloaded-bytes'];
$data['applications']['transmission']['upTotal'] = $transmissionObj['uploaded-bytes'];





$fp = fopen('results.json', 'w');
fwrite($fp, json_encode($data));
fclose($fp);





$fp = fopen('results.json', 'r');
$json = fgets($fp);
fclose($fp);

echo $json;


}
else{

$fp = fopen('results.json', 'r');
$json = fgets($fp);
fclose($fp);

echo $json;

}

}
else{

	header("HTTP/1.1 401 Unauthorized");
	exit;

}







function runningProcCheck($processo){
	if(shell_exec("pidof " . $processo)){ return True; }else{ return False;}
}


function get_systemUptime(){
	$tmp = explode(' ', file_get_contents('/proc/uptime'));
	$uptime = intval($tmp[0]);
	return $uptime;
}


function get_systemUsage(){
	$load = sys_getloadavg();
	return $load[1];
}


function get_totalRam(){
	$fh = fopen('/proc/meminfo','r');
	$memTotal = 0;
	while ($line = fgets($fh)) {
		$pieces = array();
		if (preg_match('/^MemTotal:\s+(\d+)\skB$/', $line, $pieces)) {
			$memTotal = $pieces[1]*1024;
			break;
		}
	}
	fclose($fh);
	return $memTotal;
}


function get_usedRam(){
	$fh = fopen('/proc/meminfo','r');
	$memUsed = 0;
	while ($line = fgets($fh)) {
		$pieces = array();
		if (preg_match('/^MemTotal:\s+(\d+)\skB$/', $line, $pieces)) {
			$memTotal = $pieces[1];
		}
		if (preg_match('/^MemFree:\s+(\d+)\skB$/', $line, $pieces)) {
			$memFree = $pieces[1];
		}
		if (preg_match('/^Cached:\s+(\d+)\skB$/', $line, $pieces)) {
			$memCached = $pieces[1];
		}
		if(isset($memTotal) && isset($memFree) && isset($memCached)){
			break;
		}
	}
	fclose($fh);
	$memUsed = ($memTotal - $memFree - $memCached)*1024;
	return $memUsed;
}


function get_genericValues($path){
	$value = fopen($path,'r');
	$tx = trim(fgets($value));
	fclose($value);
	return $tx;
}


function get_networkValues($interface, $file){
	$value = fopen('/sys/class/net/' . $interface . "/" . $file,'r');
	$tx = trim(fgets($value));
	fclose($value);
	return $tx;
}


function get_diskValues($interface, $file){
	$value = fopen('/sys/block/' . $interface . "/" . $file,'r');
	$tx = trim(fgets($value));
	fclose($value);
	return $tx;
}






#OpenVPN

function parseLog ($log, $proto) {
	$handle = fopen($log, "r");

	$uid = 0;

		while (!feof($handle)) {
			$buffer = fgets($handle, 4096);

			unset($match);

			if (ereg("^Updated,(.+)", $buffer, $match)) {
				$status['updated'] = $match[1];
			}

			if (preg_match("/^(.+),(\d+\.\d+\.\d+\.\d+\:\d+),(\d+),(\d+),(.+)$/", $buffer, $match)) {
				if ($match[1] <> "Common Name") {
					$cn = $match[1];

					// associative array to store a numeric id
					// for each remote ip:port because smarty doesnt
					// like looping on strings in a section
					$userlookup[$match[2]] = $uid;

					$status['users'][$uid]['CommonName'] = $match[1];
					$status['users'][$uid]['RealAddress'] = $match[2];
					$status['users'][$uid]['BytesReceived'] = $match[3];
					$status['users'][$uid]['BytesSent'] = $match[4];
					$status['users'][$uid]['Since'] = $match[5];
					$status['users'][$uid]['Proto'] = $proto;

					$uid++;
				}
			}


			if (preg_match("/^(\d+\.\d+\.\d+\.\d+),(.+),(\d+\.\d+\.\d+\.\d+\:\d+),(.+)$/", $buffer, $match)) {
				if ($match[1] <> "Virtual Address") {
					$address = $match[3];

					// find the uid in the lookup table
					$uid = $userlookup[$address];

					$status['users'][$uid]['VirtualAddress'] = $match[1];
					$status['users'][$uid]['LastRef'] = $match[4];
				}
			}

		}

	fclose($handle);

	return($status);
}



#Uptime Processes
function processUptime($processo){
	$pid = trim(shell_exec("pidof " . $processo));
	if(!$pid){
		return false;
	}
	return trim(shell_exec('ps -p "' . $pid . '" -o etimes='));
}




?>
