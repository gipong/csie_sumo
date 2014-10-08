<?php
	require('gPoint.php');
	
	move_uploaded_file($_FILES["file"]["tmp_name"],"upload/".$_FILES["file"]["name"]);
	$osm = simplexml_load_file("upload/".$_FILES["file"]["name"]);
	
	// 如果使用者有填寫特定邊界才使用，否則預設為抓取整個osm檔的邊界大小
	if($_POST['north'] || $_POST['south'] || $_POST['west'] || $_POST['east']) {
		$north = $_POST['north'];
		$south = $_POST['south'];
		$west = $_POST['west'];
		$east = $_POST['east'];
	}else {
		$north = $osm->bounds[0]['maxlat'];
		$south = $osm->bounds[0]['minlat'];
		$west = $osm->bounds[0]['minlon'];
		$east = $osm->bounds[0]['maxlon'];
	}
	
	$cent_x = ($north + $south)/2;
	$dis_x = abs($north - $cent_x);
	
	$cent_y = ($west + $east)/2;
	$dis_y = abs($east - $cent_y);
	
	//$tolerance = $_POST['tolerance']; // unit: meters 
	
	// 擷取所有node、way，並存放陣列中
	$node = $osm->node;
	$way = $osm->way;
	
	$node_list = array();
	$way_list = array();
	$check_list = array();
	
	$numNode = count($node);
	$numWay = count($way);
	
	$node_content = '';
	$edge_content = '';
	$offset_x = array();
	$offset_y = array();
	
	$temp_compare = array();
	
	/* 
	 * 計算兩點之間距離，distance(點1緯度, 點1經度, 點2緯度, 點2經度)
	 * return 兩點距離 (單位: 公尺)
	 */
	function distance($lat1, $lng1, $lat2, $lng2) {	
		$theta = $lng1 - $lng2;
		$dist = sin(deg2rad($lat1))*sin(deg2rad($lat2))+cos(deg2rad($lat1))*cos(deg2rad($lat2))*cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		
		return $miles*1.609344*1000;
	}
	
	
	for($r = 0;$r < $numNode;$r++) {
		$id = (int)$node[$r]['id'];
		$temp_compare[sprintf("%d",$id)] = array((float)$node[$r]['lon'], (float)$node[$r]['lat']);
	}
	
	/* 
	 * 將經緯度轉換為直角坐標系
	 * return  轉換後坐標
	 */
	function Trans_UTM($lat, $lon) {
		$temp = new gPoint();
		$temp->setLongLat($lon, $lat);
		$temp->convertLLtoTM();
		
		$ans['x'] = $temp->utmEasting;
		$ans['y'] = $temp->utmNorthing;
		return $ans;
	}
	
	$numWay_node = array();
	
	// boundary checking
	for($r = 0;$r < $numWay;$r++) {
		$count_nd = count($way[$r]->nd);
		$nd_index = $way[$r]->nd;
		
		// 檢查是否為符合欲擷取項目 判斷依據為主鍵為 'highway'
		if($way[$r]->tag->attributes()['k'] != 'highway') continue;
		$numWay_node[$r] = array();
		
		// 將符合 邊界範圍 條件記錄至暫時陣列中
		for($r_nd = 0;$r_nd < $count_nd-1;$r_nd++) {
			array_push($check_list, (string)$nd_index[$r_nd]['ref'], (string)$nd_index[$r_nd+1]['ref']);
			//if($temp_compare[$nd_index[$r_nd]['ref']]<$north && $nd_index[$r_nd]['ref'])
			$id1 = $nd_index[$r_nd]['ref'];
			$id2 = $nd_index[$r_nd+1]['ref'];
			
			if(
				((float)$temp_compare[sprintf("%d",$id1)][1] > (float)$south) && 
				((float)$temp_compare[sprintf("%d",$id1)][1] < (float)$north) &&
				((float)$temp_compare[sprintf("%d",$id1)][0] > (float)$west) &&
				((float)$temp_compare[sprintf("%d",$id1)][0] < (float)$east)
			){
			/*	
				$string = "\n<edge id='".$r.'w'.$r_nd."-1' fromnode='".$nd_index[$r_nd]['ref']."' tonode='".$nd_index[($r_nd+1)]['ref']."' priority='75' nolanes='2' speed='40' />".
						"\n<edge id='".$r.'w'.$r_nd."-2' fromnode='".$nd_index[($r_nd+1)]['ref']."' tonode='".$nd_index[$r_nd]['ref']."' priority='75' nolanes='2' speed='40' />";
				$edge_content = $edge_content.$string;
			*/
				
				array_push($numWay_node[$r], (string)$nd_index[$r_nd]['ref'], (string)$nd_index[$r_nd+1]['ref']);
			}else {

				continue;
			}
		}
		$numWay_node[$r] = array_values(array_unique($numWay_node[$r]));
		
	}
	
	
	// merge road by tolerance	
	/* 
	 * 以單一線段上單一點來，與其他線段上各點進行比對
	 * 若兩點之距離小於預設容忍值，則進行合併，
	 * 單一線段上各點則忽略不進行計算，藉以維持線段形狀
	 */
	for($r = 0;$r < $numWay;$r++) {
		if($way[$r]->tag->attributes()['k'] != 'highway') continue;
		$count_calTor = count($numWay_node[$r]);
		
		// 線段內各點與其他線段之各點比較
		for($cmp = 0;$cmp < $count_calTor;$cmp++) {
			$m = $numWay_node[$r][$cmp];
			for($rr = 0;$rr < $numWay;$rr++) {
			if($way[$rr]->tag->attributes()['k'] != 'highway') continue;
				if($r == $rr) continue;
				$count_cmp = count($numWay_node[$rr]);
				for($bcmp = 0;$bcmp < $count_cmp;$bcmp++) {
					$t = $numWay_node[$rr][$bcmp];
					$cmp_dist = distance(
						(float)$temp_compare[sprintf("%d",$m)][1], 
						(float)$temp_compare[sprintf("%d",$m)][0],
						(float)$temp_compare[sprintf("%d",$t)][1], 
						(float)$temp_compare[sprintf("%d",$t)][0]						
					);
					if($cmp_dist < 75)
						$numWay_node[$rr][$bcmp] = $numWay_node[$r][$cmp];
				}
			}
		}
		
	}
	
	$val = array();
	$intersectNodeList = array();
	
	// 將暫存陣列中各點、線段寫入預定寫入檔案中內容
	for($r = 0;$r < $numWay;$r++) {
		if($way[$r]->tag->attributes()['k'] != 'highway') continue;
		$count_nd = count($numWay_node[$r]);		
		
		for($r_nd = 0;$r_nd < $count_nd-1;$r_nd++) {
			if($numWay_node[$r][$r_nd] == $numWay_node[$r][($r_nd+1)]) continue;
			
			$tempString = $numWay_node[$r][$r_nd]."-".$numWay_node[$r][($r_nd+1)];
			$tempString2 = $numWay_node[$r][($r_nd+1)]."-".$numWay_node[$r][$r_nd];
			if(in_array($tempString, $val) || in_array($tempString2, $val)) {
				continue;
			} else {
				array_push($val, $tempString);		
				$string = "\n<edge id='".$r.'w'.$r_nd."-1' fromnode='".$numWay_node[$r][$r_nd]."' tonode='".$numWay_node[$r][($r_nd+1)]."' priority='75' nolanes='2' speed='40' />".
						"\n<edge id='".$r.'w'.$r_nd."-2' fromnode='".$numWay_node[$r][($r_nd+1)]."' tonode='".$numWay_node[$r][$r_nd]."' priority='75' nolanes='2' speed='40' />";
				$edge_content = $edge_content.$string;
				
				if(isset($intersectNodeList[sprintf("%d",$numWay_node[$r][$r_nd])]))
					$intersectNodeList[sprintf("%d",$numWay_node[$r][$r_nd])] = $intersectNodeList[sprintf("%d",$numWay_node[$r][$r_nd])]+2;
				else
					$intersectNodeList[sprintf("%d",$numWay_node[$r][$r_nd])] = 2;
					
				if(isset($intersectNodeList[sprintf("%d",$numWay_node[$r][($r_nd+1)])]))
					$intersectNodeList[sprintf("%d",$numWay_node[$r][($r_nd+1)])] = $intersectNodeList[sprintf("%d",$numWay_node[$r][($r_nd+1)])]+2;
				else
					$intersectNodeList[sprintf("%d",$numWay_node[$r][($r_nd+1)])] = 2;
			}
		}
		
	}
	
	$check_list = array_unique($check_list);
	
	// 統一進行坐標轉換
	for($r = 0;$r < $numNode;$r++) {
		$index = $node[$r]['id'];
		if(in_array((string)$index, $check_list) && isset($intersectNodeList[sprintf("%d",$node[$r]['id'])])) {
			$transCod = Trans_UTM((float)$node[$r]['lat'], (float)$node[$r]['lon']);
			$node_list["$index"] = array($transCod['x'], $transCod['y']);
			//$string = "\n<node id='".$node[$r]['id']."' x='".$node[$r]['lon']."' y='".$node[$r]['lat']."' type='traffic_light' />";
			if($intersectNodeList[sprintf("%d",$node[$r]['id'])] <= 4)
				$string = "\n<node id='".$node[$r]['id']."' x='".$transCod['x']."' y='".$transCod['y']."' type='priority' />";
			else 
				$string = "\n<node id='".$node[$r]['id']."' x='".$transCod['x']."' y='".$transCod['y']."' type='traffic_light' />";
			$node_content = $node_content.$string; 
			//array_push($node[$r]['lon'], $node_list[$node[$r]['id']]);
		}
	}
	
	$ori = "\n<node id='00' x='0' y='0' type='traffic_light' />";
	
	$node_file = '<nodes>'.$node_content."\n</nodes>\n";
	$edge_file = '<edges>'.$edge_content."\n</edges>";
	
	$node_name = 'xml/nodes_om.nod.xml';
	$edge_name = 'xml/edges_om.edg.xml';
	file_put_contents($node_name, $node_file);
	file_put_contents($edge_name, $edge_file);
	
	$osm2 = simplexml_load_file('xml/edges_om.edg.xml');
	$edges = $osm2->edge;
	$numEdges = count($edges);	
	$check_mainbody = array();
	
	// 額外加入圖徵主體選取，由不同點作為起始點，記錄與其相連節點最多者視為 Mainbody
	$mainBody = array();
	for($c = 0; $c < $numEdges; $c++) {
		
		$temp = array();
		array_push($temp, (string)$edges[$c]['fromnode'], (string)$edges[$c]['tonode']);
		
		for($c2 = 0; $c2 < $numEdges; $c2++) {
			if($c == $c2) continue;
			
			$cmp = array();
			array_push($cmp, (string)$edges[$c2]['fromnode'], (string)$edges[$c2]['tonode']);
			if(in_array($cmp[0], $temp) || in_array($cmp[1], $temp)) {
				array_push($temp, (string)$edges[$c2]['fromnode'], (string)$edges[$c2]['tonode']);
			}
		}
		$mainBody[$c] = count($temp);
		
	}
	arsort($mainBody);
	//var_dump($mainBody);
	
	// 將決定之 Mainbody 中各點、線段寫入預定寫入檔案中內容
	$node_content_mainBody = '';
	foreach($mainBody as $k => $a) {
		$temp = array();
		array_push($temp, (string)$edges[$k]['fromnode'], (string)$edges[$k]['tonode']);
		
		$string = "\n<edge id='".$edges[$k]['id']."' fromnode='".$edges[$k]['fromnode']."' tonode='".$edges[$k]['tonode']."' priority='75' nolanes='2' speed='40' />";
			$node_content_mainBody = $node_content_mainBody.$string;
			
		for($c = 0; $c < $numEdges; $c++) {
			if($c == $k) continue;
			
			$cmp = array();
			array_push($cmp, (string)$edges[$c]['fromnode'], (string)$edges[$c]['tonode']);
			if(in_array($cmp[0], $temp) || in_array($cmp[1], $temp)) {
				array_push($temp, (string)$edges[$c]['fromnode'], (string)$edges[$c]['tonode']);
				
				$string = "\n<edge id='".$edges[$c]['id']."' fromnode='".$edges[$c]['fromnode']."' tonode='".$edges[$c]['tonode']."' priority='75' nolanes='2' speed='40' />";
				$node_content_mainBody = $node_content_mainBody.$string;
			}
			
		}
		//echo $k." : ".$a;
		break;
	}
	
	$edge_file_mainBody = '<edges>'.$node_content_mainBody."\n</edges>";
	
	$node_name_mainBody = 'xml/nodes_om.mainBody.nod.xml';
	$edge_name_mainBody = 'xml/edges_om.mainBody.edg.xml';
	file_put_contents($edge_name_mainBody, $edge_file_mainBody);
	file_put_contents($node_name_mainBody, $node_file);
	
	
	echo '<a href="xml/nodes_om.nod.xml" target="_blank">nodes_om.nod.xml</a><br />';
	echo '<a href="xml/edges_om.edg.xml" target="_blank">edges_om.edg.xml</a><br />';
	echo '<br  />';
	echo '<a href="xml/nodes_om.mainBody.nod.xml" target="_blank">nodes_om.mainBody.nod.xml</a><br />';
	echo '<a href="xml/edges_om.mainBody.edg.xml" target="_blank">edges_om.mainBody.edg.xml</a><br />';
	echo '<hr  />';
	echo '<a href="index.html">Home</a>';
	
	