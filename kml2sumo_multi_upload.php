<?php
	require('gPoint.php');
	
	move_uploaded_file($_FILES["file"]["tmp_name"],"upload/".$_FILES["file"]["name"]);
	
	$kml = simplexml_load_file("upload/".$_FILES["file"]["name"]);
	if($kml->Document->Folder)
		$allLine = $kml->Document->Folder->Placemark;
	else
		$allLine = $kml->Document->Placemark;
	$numLine = count($allLine);
	
	$node_content = '';
	$edge_content = '';
	$rel = 0;
	$rel_count = 0;
	
	$crossCount = 0;
	
	$node_list = array();
	$rel_list = array();
	$output_list = array();
	
	/* array('x'=>xdis, 'y'=>ydis, 's'=>dis) */
	function trans($lng1,$lat1,$lng2,$lat2) {
	
		$radLat1=deg2rad($lat1);
		$radLat2=deg2rad($lat2);
		$radLng1=deg2rad($lng1);
		$radLng2=deg2rad($lng2);
		$a=$radLat1-$radLat2;
		$b=$radLng1-$radLng2;
		$s=2*asin(sqrt(pow(sin($a/2),2)+cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)))*6378.137*1000;
		
		$ang = atan((cos($radLat2)*sin($b/2))/(cos($radLat1)*sin($radLat2)-sin($radLat1)*cos($radLat2)*cos($b/2)));
		//$ang = atan($lat2/$lng2);
		
		
		//$ans['x'] = $s*cos($ang);
		$ans['x'] = 6378.137*1000*cos($lat2)*cos($lng2);
		//$ans['y'] = $s*sin($ang);
		$ans['y'] = 6378.137*1000*cos($lat2)*sin($lng2);
		$ans['s'] = $s;

		return $ans;
	}
	
	function trans_utm2($lng, $lat) {
		$k0 = 0.9996;
		$a = 6378137.0;
		$b = 6356752.314;
		$f = 0.003352811;	
		$e2 = 2*$f - $f*$f;
		$falseEasting = 0.0;
		
		$LongTemp = ($lng+180)-(integer)(($lng+180)/360)*360-180; // -180.00 .. 179.9;
		$LatRad = deg2rad($lat);
		$LongRad = deg2rad($LongTemp);
		
		$LongOriginRad = deg2rad($lng);
 
		$eccPrimeSquared = ($e2)/(1-$e2);
 
		$N = $a/sqrt(1-$e2*sin($LatRad)*sin($LatRad));
		$T = tan($LatRad)*tan($LatRad);
		$C = $eccPrimeSquared*cos($LatRad)*cos($LatRad);
		$A = cos($LatRad)*($LongRad-$LongOriginRad);
 
		$M = $a*((1	- $e2/4		- 3*$e2*$e2/64	- 5*$e2*$e2*$e2/256)*$LatRad 
							- (3*$e2/8	+ 3*$e2*$e2/32	+ 45*$e2*$e2*$e2/1024)*sin(2*$LatRad)
												+ (15*$e2*$e2/256 + 45*$e2*$e2*$e2/1024)*sin(4*$LatRad) 
												- (35*$e2*$e2*$e2/3072)*sin(6*$LatRad));
	
		$utmEasting = ($k0*$N*($A+(1-$T+$C)*$A*$A*$A/6
						+ (5-18*$T+$T*$T+72*$C-58*$eccPrimeSquared)*$A*$A*$A*$A*$A/120)
						+ $falseEasting);
 
		$utmNorthing = ($k0*($M+$N*tan($LatRad)*($A*$A/2+(5-$T+9*$C+4*$C*$C)*$A*$A*$A*$A/24
					 + (61-58*$T+$T*$T+600*$C-330*$eccPrimeSquared)*$A*$A*$A*$A*$A*$A/720)));
		
		if($lat < 0) $utmNorthing += 10000000.0; //10000000 meter offset for southern hemisphere
		
		$ans['x'] = $utmEasting;
		$ans['y'] = $utmNorthing;
		return $ans;
	}
	
	function addNode($lineNum, $i, $x,$y) {
		global $node_list;
		$id = $lineNum.'n'.$i;
		$node_list["$id"] = array($x, $y);
	}

	function crosNode($cross, $crossCount) {
		global $node_list;
		$id = 'c'.$crossCount;
		foreach($node_list as $key => $value) {
			if($cross['x'] == $node_list[$key][0])
				$id = $key;
		}
		$node_list["$id"] = array($cross['x'], $cross['y']);
		return $id;
	}
	
	function addRel($lineNum, $rel) {
		global $edge_content;
		
		for($i = 0;$i < $rel;$i++) {
			global $rel_list;
			global $rel_count;
			global $output_list;

			$fromnode = $lineNum.'n'.$i;
			$tonode = $lineNum.'n'.($i+1);
			$rel_list[$rel_count] = array($fromnode, $tonode);
			$output_list[$rel_count] = array($fromnode, $tonode);
			$rel_count++;
		}
	}
	
	function crosLine($cross, $r, $R, $r_node, $R_node) {
		global $rel_list;
		global $crossCount;
		
		$rel_list['c'.$crossCount.'-1'] = array($r_node[0], 'c'.$crossCount);
		$rel_list['c'.$crossCount.'-2'] = array($r_node[1], 'c'.$crossCount);
		$rel_list['c'.$crossCount.'-3'] = array($R_node[0], 'c'.$crossCount);
		$rel_list['c'.$crossCount.'-4'] = array($R_node[1], 'c'.$crossCount);
				
	}
	
	function check($x1, $y1, $x2, $y2, $x3, $y3, $x4, $y4) {
		(double)$a1 = ($y1 - $y2);
		(double)$a2 = ($y3 - $y4);
		(double)$b1 = ($x2 - $x1);
		(double)$b2 = ($x4 - $x3);
		(double)$c1 = $x2*$y1 - $x1*$y2;
		(double)$c2 = $x4*$y3 - $x3*$y4;
		(double)$D = $a1*$b2 - $a2*$b1;
		
		//if((($x3-$x1)+($x2-$x3)+($x4-$x2)+($x1-$x4)) == 0) {
		if($D != 0) {
			$D1 = $c1*$b2 - $c2*$b1;
			$D2 = $a1*$c2 - $a2*$c1;
			
			$its['x'] = (double)($D1/$D);
			$its['y'] = (double)($D2/$D);
			if(min($x1, $x2) < $its['x'] && $its['x'] < max($x1, $x2) && min($y1, $y2) < $its['y'] && $its['y'] < max($y1, $y2))
				return $its;
			else {
			$its['x'] = '';
			$its['y'] = '';
			return $its;
			}
		}else {
			$its['x'] = '';
			$its['y'] = '';
			return $its;
		}
	}
	
	function distance($lat1, $lng1, $lat2, $lng2) {

		$earthRadius = 3958.75;
		$dLat = deg2rad($lat2-$lat1);
		$dLng = deg2rad($lng2-$lng1);

		$a = sin($dLat/2) * sin($dLat/2) +
		   cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
		   sin($dLng/2) * sin($dLng/2);
		$c = 2 * atan2(sqrt($a), sqrt(1-$a));
		$dist = $earthRadius * $c;
		return $geopointDistance;
	}

	
	for($num=0; $num < $numLine; $num++) {
		$coord = $allLine[$num]->LineString->coordinates;
		$coords = explode(' ', (string)$coord);
		$count = count($coords);

		for($i = 0;$i < $count;$i++) {
			$coords[$i] = trim($coords[$i]);
			$setCord = explode(',', $coords[$i]);
			//var_dump($setCord);
			if($setCord[0]) {
				addNode($num, $i, $setCord[0], $setCord[1]);
				$rel = $i;
			}
		}
		
		addRel($num, $rel);
	}
	
	$ir = count($rel_list);	
	for($r=0;$r < $ir; $r++) {
		for ($i=0; $i < $ir; $i++) { 
			//if($i <= $r) continue;
			
			
			if($node_list[$rel_list[$r][1]][0] == $node_list[$rel_list[$i][0]][0]) continue; 
			$a = @check(
				$node_list[$rel_list[$r][0]][0], $node_list[$rel_list[$r][0]][1], 
				$node_list[$rel_list[$r][1]][0], $node_list[$rel_list[$r][1]][1],
				$node_list[$rel_list[$i][0]][0], $node_list[$rel_list[$i][0]][1],
				$node_list[$rel_list[$i][1]][0], $node_list[$rel_list[$i][1]][1]
				);
				
			if($a['x'] != '') {
				$crosName = crosNode($a, $crossCount);
				//crosLine($a, $r, $i, $rel_list[$r], $rel_list[$i]);	
				array_push($output_list[$r], $crosName);
				$crossCount++;
				//echo $a['x']." , ".$a['y']." , cross node Finish<br />";
			}
			
		}
				
	}
	
	$offset_x = array();
	$offset_y = array();
	
	/*
		Output xml format 
	 */
	
	$IR = count($output_list);
	for($r=0; $r < $ir; $r++) {
		$sort = array();
		$cont = count($output_list[$r]);
		for($y=0; $y < $cont; $y++){
			$temp = $output_list[$r][$y];
			$sort[$temp] = $node_list[$temp][0];
		}
		asort($sort);
		
		
		$output_array = array();
		foreach($sort as $key => $value) {
			$output_array[] = $key;
		}
		
		$idNum = 0;
		for($y=0; $y < $cont; $y++){
			if($y == $cont-1) continue;
			else $Y = $y+1;
			//if(distance($node_list[$output_array[$y]][1], $node_list[$output_array[$y]][0], $node_list[$output_array[$Y]][1], $node_list[$output_array[$Y]][0]) > $_POST['tolerance']) {
				$string = "\n<edge id='".$r.'_'.$idNum."-1' fromnode='".$output_array[$y]."' tonode='".$output_array[$Y]."' priority='75' nolanes='2' speed='40' />".
						"\n<edge id='".$r.'_'.$idNum."-2' fromnode='".$output_array[$Y]."' tonode='".$output_array[$y]."' priority='75' nolanes='2' speed='40' />";
				$edge_content = $edge_content.$string;
				$idNum++;
			//}
		}
		
	}
	//var_dump($output_list);
	//var_dump($sort);
	
	function ToLL($north, $east, $utmZone) { 
		// This is the lambda knot value in the reference
		$LngOrigin = Deg2Rad($utmZone * 6 - 183);

		// WGS84 datum  
		$FalseNorth = 0;   // South or North?
		//if (lat < 0.) FalseNorth = 10000000.  // South or North?
		//else          FalseNorth = 0.   

		$Ecc = 0.081819190842622;       // Eccentricity
		$EccSq = $Ecc * $Ecc;
		$Ecc2Sq = $EccSq / (1. - $EccSq);
		$Ecc2 = sqrt($Ecc2Sq);      // Secondary eccentricity
		$E1 = ( 1 - sqrt(1-$EccSq) ) / ( 1 + sqrt(1-$EccSq) );
		$E12 = $E1 * $E1;
		$E13 = $E12 * $E1;
		$E14 = $E13 * $E1;

		$SemiMajor = 6378137.0;         // Ellipsoidal semi-major axis (Meters)
		$FalseEast = 500000.0;          // UTM East bias (Meters)
		$ScaleFactor = 0.9996;          // Scale at natural origin

		// Calculate the Cassini projection parameters

		$M1 = ($north - $FalseNorth) / $ScaleFactor;
		$Mu1 = $M1 / ( $SemiMajor * (1 - $EccSq/4.0 - 3.0*$EccSq*$EccSq/64.0 - 5.0*$EccSq*$EccSq*$EccSq/256.0) );

		$Phi1 = $Mu1 + (3.0*$E1/2.0 - 27.0*$E13/32.0) * sin(2.0*$Mu1);
		+ (21.0*$E12/16.0 - 55.0*$E14/32.0)           * sin(4.0*$Mu1);
		+ (151.0*$E13/96.0)                          * sin(6.0*$Mu1);
		+ (1097.0*$E14/512.0)                        * sin(8.0*$Mu1);

		$sin2phi1 = sin($Phi1) * sin($Phi1);
		$Rho1 = ($SemiMajor * (1.0-$EccSq) ) / pow(1.0-$EccSq*$sin2phi1,1.5);
		$Nu1 = $SemiMajor / sqrt(1.0-$EccSq*$sin2phi1);

		// Compute parameters as defined in the POSC specification.  T, C and D

		$T1 = tan($Phi1) * tan($Phi1);
		$T12 = $T1 * $T1;
		$C1 = $Ecc2Sq * cos($Phi1) * cos($Phi1);
		$C12 = $C1 * $C1;
		$D  = ($east - $FalseEast) / ($ScaleFactor * $Nu1);
		$D2 = $D * $D;
		$D3 = $D2 * $D;
		$D4 = $D3 * $D;
		$D5 = $D4 * $D;
		$D6 = $D5 * $D;

		// Compute the Latitude and Longitude and convert to degrees
		$lat = $Phi1 - $Nu1*tan($Phi1)/$Rho1 * ( $D2/2.0 - (5.0 + 3.0*$T1 + 10.0*$C1 - 4.0*$C12 - 9.0*$Ecc2Sq)*$D4/24.0 + (61.0 + 90.0*$T1 + 298.0*$C1 + 45.0*$T12 - 252.0*$Ecc2Sq - 3.0*$C12)*$D6/720.0 );
		$lat = Rad2Deg($lat);
		$lon = $LngOrigin + ($D - (1.0 + 2.0*$T1 + $C1)*$D3/6.0 + (5.0 - 2.0*$C1 + 28.0*$T1 - 3.0*$C12 + 8.0*$Ecc2Sq + 24.0*$T12)*$D5/120.0) / cos($Phi1);
		$lon = Rad2Deg($lon);

		$PC_LatLon['lat'] = $lat;
		$PC_LatLon['lon'] = $lon;
		
		return $PC_LatLon;
	}
	
	function WGS84toUTM($lat_l, $lon_l) {
	//WGS84 info, a = 6378137.0 b = 6356752.314 f = 0.003352811 1/f = 298.2572236
		$a = 6378137.0;
		$b = 6356752.314;
		$f = 0.003352811;
		$in_f = 298.2572236;
		
		$lat = deg2rad($lat_l);
		$lon = deg2rad($lon_l);
		$lon0 = 6*((int)($lon_l/6)+31)-183;
		echo $lon0;
		$k0 = 0.9996;
		$e = sqrt(1-$b*$b/$a*$a);
		$e2 = $e*$e/(1-$e*$e);
		$n = ($a-$b)/($a+$b);
		$rho = $a*(1-$e*$e)/pow(1-pow(($e*sin($lat)), 2),(3.0/2));
		
		$nu = $a/pow((1-pow($e*sin($lat), 2)),0.5);
		$p = ($lon-$lon0)*3600/10000;
		$sin1 = M_PI/(180*60*60);
		
		$A = $a*(1 - $n + (5.0/4)*(pow($n, 2) - pow($n, 3)) + (81.0/64)*(pow($n, 4) - pow($n, 5)));
		$B = (3*$a*$n/2)*(1 - $n + (7.0/8)*(pow($n, 2) - pow($n, 3)) + (55.0/64)*(pow($n, 4) - pow($n, 5)));
		$C = (15*$a*pow($n, 2)/16)*(1 - $n + (3.0/4)*(pow($n, 2) - pow($n, 3)));
		$D = (35*$a*pow($n, 3)/48)*(1 - $n + (11.0/16)*(pow($n, 2) - pow($n, 3)));
		$E = (315*$a*pow($n, 4)/51)*(1 - $n);
		$S = $A*$lat - $B*sin(2*$lat) + $C*sin(4*$lat) - $D*sin(6*$lat) + $E*sin(8*$lat);
		
		$K1 = $S*$k0;
		$K2 = $nu*sin($lat)*cos($lat)*pow($sin1, 2)*$k0*100000000/2;
		$K3 = (pow($sin1, 4)*$nu*sin($lat)*pow(cos($lat), 3)/24)*(5 - pow(tan($lat), 2) + 9*$e2*pow(cos($lat), 2) + 4*pow($e2, 2)*pow(cos($lat), 4))*$k0*10000000000000000;
		$UTMNorthing = $K1 + $K2*pow($p, 2) + $K3*pow($p, 4);
		//south sphere 10000000m FN
		//$UTMNorthing += $FN;
		
		$K4 = $k0*$sin1*$nu*cos($lat)*10000;
		$K5 = ($sin1*pow(cos($lat), 3))*($nu/6)*(1 - pow(tan($lat), 2) + $e2*pow(cos($lat), 2))*$k0*1000000000000;
		$UTMEasting = $K4*$p + $K5*pow($p, 3) + 500000;
		
		$utm['lon'] = $UTMEasting;
		$utm['lat'] = $UTMNorthing;
		var_dump($utm);
		
		return $utm;
	}
	
	function UTM($lat, $lon) {
		$a = 6378137.0;
		$b = 6356752.314;
		$f = 0.003352811;	
		$eSquare = 2*$f - $f*$f;
		$k0 = 0.9996;
		
		$lonTemp = ($lon+180)-(int)(($lon+180)/360)*360-180;
		$latRad = deg2rad($lat);
		$lonRad = deg2rad($lonTemp);
		$lonOriginRad = 6*((int)($lon/6) +31)-183;
    	$e2Square = ($eSquare)/(1-$eSquare);

		$V = $a/sqrt( 1-$eSquare*pow(sin($latRad),2) );
		$T = pow(tan($latRad), 2);
		$C = $e2Square*pow(cos($latRad), 2);
		$A = cos($latRad)*($lonRad-$lonOriginRad);
		$M = $a*((1-$eSquare/4-3*pow($eSquare, 2)/64-5*pow($eSquare, 3)/256)*$latRad
		-(3*$eSquare/8+3*pow($eSquare, 2)/32+45*pow($eSquare, 3)/1024)*sin(2*$latRad)
		+(15*pow($eSquare, 2)/256+45*pow($eSquare, 3)/1024)*sin(4*$latRad)
		-(35*pow($eSquare, 3)/3072)*sin(6*$latRad));

		
		$UTMEasting = $k0*$V*($A+(1-$T+$C)*pow($A, 3)/6
		+ (5-18*$T+$T*$T+72*$C-58*$e2Square)*pow($A, 5)/120)+ 500000.0;
		
		$UTMNorthing = $k0*($M+$V*tan($latRad)*(pow($A, 2)/2+(5-$T+9*$C+4*pow($C, 2))*pow($A, 4)/24
		+(61-58*$T+pow($T, 2)+600*$C-330*$e2Square)*pow($A, 6)/720));
		
		//UTMNorthing += FN
		$utm['lon'] = $UTMEasting;
		$utm['lat'] = $UTMNorthing;
		
		
		return $utm;
	}
	
	function Trans_UTM($lat, $lon) {
		$temp = new gPoint();
		$temp->setLongLat($lon, $lat);
		$temp->convertLLtoTM();
		
		$ans['x'] = $temp->utmEasting;
		$ans['y'] = $temp->utmNorthing;
		return $ans;
	}
	
	foreach($node_list as $key => $value) {
		//$transCod = trans(0, 0, (float)$node_list[$key][0], (float)$node_list[$key][1]);	
		$transCod = Trans_UTM((float)$node_list[$key][0], (float)$node_list[$key][1]);	
		//$transCod = UTM((float)$node_list[$key][1], (float)$node_list[$key][0]);
		array_push($offset_x, $transCod['x']);
		array_push($offset_y, $transCod['y']);
	}
	
	sort($offset_x, SORT_NUMERIC);
	sort($offset_y, SORT_NUMERIC);
		$off_x = abs($offset_x[0]);	
		$off_y = abs($offset_y[0]);	
	
	$csv_content = '';
	foreach($node_list as $key => $value) {
		//$transCod = trans(0, 0, (float)$node_list[$key][0], (float)$node_list[$key][1]);	
		$transCod = Trans_UTM((float)$node_list[$key][0], (float)$node_list[$key][1]);	
		//$transCod = UTM((float)$node_list[$key][1], (float)$node_list[$key][0]);
		//var_dump($transCod);
		$string = "\n<node id='".$key."' x='".$transCod['x']."' y='".$transCod['y']."' type='traffic_light' />";
		
		//$string = "\n<node id='".$key."' x='".bcadd($transCod['x'], $off_x, 6)."' y='".bcadd($transCod['y'], $off_y, 6)."' type='traffic_light' />";
		//$string = "\n<node id='".$key."' x='".bcadd($transCod['lon'], $off_x, 6)."' y='".bcadd($transCod['lat'], $off_y, 6)."' type='traffic_light' />";
		$node_content = $node_content.$string;
		//$csv_string = bcadd($transCod['x'], $off_x, 6).", ".bcadd($transCod['y'], $off_y, 6)."\n";
		$csv_string = $transCod['y'].", ".$transCod['x']."\n";
		$csv_content = $csv_content.$csv_string;
	}
	
	
	
	$ori = "<node id='00' x='0' y='0' type='traffic_light' />";
	$node_file = '<nodes>'.$node_content."\n</nodes>\n<!-- offset_x: $off_x, offset_y: $off_y -->";
	$edge_file = '<edges>'.$edge_content."\n</edges>";
	
	$node_name = 'xml/nodes_m.xml';
	$edge_name = 'xml/edges_m.xml';
	$csv_name = 'xml/coor.txt';
	file_put_contents($node_name, $node_file);
	file_put_contents($edge_name, $edge_file);
	file_put_contents($csv_name, $csv_content);
	
	//var_dump($node_list);
	//var_dump($rel_list);
	//var_dump($offset_x);
	echo '<a href="xml/nodes_m.xml" target="_blank">nodes_m.xml</a><br />';
	echo '<a href="xml/edges_m.xml" target="_blank">edges_m.xml</a><br />';
	echo '<a href="xml/coor.txt" target="_blank">coor.txt</a><br />';
	echo '<a href="index.html">Home</a>';
	