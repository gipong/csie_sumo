<?php
	require('gPoint.php');
	
	move_uploaded_file($_FILES["file"]["tmp_name"],"upload/".$_FILES["file"]["name"]);
	
	$kml = simplexml_load_file("upload/".$_FILES["file"]["name"]);
	if($kml->Document->Folder)
		$allLine = $kml->Document->Folder->Placemark;
	else
		$allLine = $kml->Document->Placemark;
	$numLine = count($allLine);
	$tolerance = $_POST['tolerance']; // unit: meters
	
	$node_content = '';
	$edge_content = '';
	$rel = 0;
	$rel_count = 0;
	
	$crossCount = 0;
	
	$node_list = array();
	$rel_list = array();
	$output_list = array();
	
	$ori = array();
	$del = array();
	
	
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
			if(min($x1, $x2) < $its['x'] && $its['x'] < max($x1, $x2) && min($y1, $y2) < $its['y'] && $its['y'] < max($y1, $y2)
			&& min($x3, $x4) < $its['x'] && $its['x'] < max($x3, $x4) && min($y3, $y4) < $its['y'] && $its['y'] < max($y3, $y4))
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
	/*
		$earthRadius = 3958.75;
		$dLat = deg2rad($lat2-$lat1);
		$dLng = deg2rad($lng2-$lng1);

		$a = sin($dLat/2) * sin($dLat/2) +
		   cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
		   sin($dLng/2) * sin($dLng/2);
		$c = 2 * atan2(sqrt($a), sqrt(1-$a));
		$dist = $earthRadius * $c;
		return $dist;
	*/
	
		$theta = $lng1 - $lng2;
		$dist = sin(deg2rad($lat1))*sin(deg2rad($lat2))+cos(deg2rad($lat1))*cos(deg2rad($lat2))*cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		
		return $miles*1.609344*1000;
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
			
			// $rel_list[$r][1] : '0n0'
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
				
				$dist_bin = distance(
					$node_list[$rel_list[$r][0]][0], 
					$node_list[$rel_list[$r][0]][1],
					$a['x'],
					$a['y']
					);
					
				$dist_end = distance(
					$node_list[$rel_list[$r][1]][0], 
					$node_list[$rel_list[$r][1]][1],
					$a['x'],
					$a['y']
					);
				if($dist_bin == 0 or $dist_end == 0) continue;
				
				$temp_cross = array();	
				
				if($dist_bin > $tolerance && $dist_end > $tolerance) {
					array_push($output_list[$r], $crosName);
				}elseif($dist_bin > $tolerance && $dist_end < $tolerance) {
					$key = array_search($rel_list[$r][1], $output_list[$r]);
					array_push($ori, $output_list[$r][$key]);
					array_push($del, $crosName);
					//unset($output_list[$r][$key]);
					//foreach($output_list as $key => $value) {
					//	if(array_search($rel_list[$r][1], $value)) {
					//		$value[array_search($rel_list[$r][1], $value)] = $crosName;
					//	}
					//}
					//array_push($output_list[$r], $crosName);
				}elseif($dist_bin < $tolerance && $dist_end > $tolerance) {
				/*
					$key = array_search($rel_list[$r][1], $output_list[$r]);
					unset($output_list[$r][$key]);
						//echo "unset: "+$node_list[$rel_list[$r][1]]+"<br />";
					array_push($output_list[$r], $crosName);
				*/
					$key = array_search($rel_list[$r][0], $output_list[$r]);
					array_push($ori, $output_list[$r][$key]);
					array_push($del, $crosName);
					//unset($output_list[$r][$key]);
					//foreach($output_list as $key => $value) {
					//	if(array_search($rel_list[$r][0], $value))
					//		$value[array_search($rel_list[$r][0], $value)] = $crosName;
					//}
					//array_push($output_list[$r], $crosName);
				
				}else {
					$key = array_search($rel_list[$r][0], $output_list[$r]);
					array_push($ori, $output_list[$r][$key]);
					array_push($del, $crosName);
					//unset($output_list[$r][$key]);
					$key = array_search($rel_list[$r][1], $output_list[$r]);
					array_push($ori, $output_list[$r][$key]);
					array_push($del, $crosName);
					//unset($output_list[$r][$key]);
					//foreach($output_list as $key => $value) {
					//	if(array_search($rel_list[$r][0], $value))
					//		$value[array_search($rel_list[$r][0], $value)] = $crosName;
					//	if(array_search($rel_list[$r][1], $value))
					//		$value[array_search($rel_list[$r][1], $value)] = $crosName;
					//}
						//echo "unset: "+$node_list[$rel_list[$r][0]]+"<br />";
						//echo "unset: "+$node_list[$rel_list[$r][1]]+"<br />";
					//array_push($output_list[$r], $crosName);
				}
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
	
	for($r=0; $r < $IR; $r++) {
		//echo "$key, $value<br>";
		if(in_array($output_list[$r][0], $ori)) {
			$ans = array_search($output_list[$r][0], $ori);
			//echo "$ans, ".$output_list[$r][$ans].", change to , $del[$ans]<br />";
			$output_list[$r][0] = $del[$ans];
		}
		if(in_array($output_list[$r][1], $ori)) {
			$ans = array_search($output_list[$r][1], $ori);
			//echo "$ans, ".$output_list[$r][$ans].", change to , $del[$ans]<br />";
			$output_list[$r][1] = $del[$ans];
		}
	}
	

	for($r=0; $r < count($output_list); $r++) {	
		if($output_list[$r][1] == $output_list[$r][0]) {
			array_splice($output_list, $r, 1);
			
		}
		
		
	}
	$IR = count($output_list);
	for($r=0; $r < $IR; $r++) {
		$sort = array();
		$cont = count($output_list[$r]);
		/*
		for($y=0; $y < $cont; $y++){
			var_dump($output_list[$r]);
			if(!isset($output_list[$r][0])) {
				$Y = $y+1;
				$temp = $output_list[$r][$Y];
			}else 
				$temp = $output_list[$r][$y];
				
			echo "temp: $temp<br />";
			$sort[$temp] = $node_list[$temp][0];
		}
		*/
		foreach($output_list[$r] as $key => $value) {
			$sort[$value] = $node_list[$value][0];
		}
		asort($sort);
		
		$output_array = array();
		
		foreach($sort as $key => $value) {
			$output_array[] = $key;
		}
		
		$idNum = 0;
		for($y=0; $y < $cont; $y++){
			if($cont == 1) break;
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
	
	//var_dump($sort);
	
	
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
	
	$node_name = 'xml/nodes_m.nod.xml';
	$edge_name = 'xml/edges_m.edg.xml';
	
	file_put_contents($node_name, $node_file);
	file_put_contents($edge_name, $edge_file);
	
	
	//var_dump($node_list);
	//var_dump($rel_list);
	//var_dump($offset_x);
	echo '<a href="xml/nodes_m.nod.xml" target="_blank">nodes_m.nod.xml</a><br />';
	echo '<a href="xml/edges_m.edg.xml" target="_blank">edges_m.edg.xml</a><br />';
	echo '<a href="index.html">Home</a>';
	