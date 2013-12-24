<?php
	move_uploaded_file($_FILES["file"]["tmp_name"],"upload/".$_FILES["file"]["name"]);

	$osm = simplexml_load_file("upload/".$_FILES["file"]["name"]);
	$node = $osm->node;
	$way = $osm->way;
	
	$node_list = array();
	$way_list = array();
	$check_list = array();
	
	$numNode = count($node);
	$numWay = count($way);
	//echo $numWay;
	
	$node_content = '';
	$edge_content = '';
	$offset_x = array();
	$offset_y = array();
	
	for($r = 0;$r < $numNode;$r++) {
		$transCod = trans(0, 0, (float)$node[$r]['lon'], (float)$node[$r]['lat']);	
		array_push($offset_x, $transCod['x']);
		array_push($offset_y, $transCod['y']);
		
	}
	
	sort($offset_x, SORT_NUMERIC);
	sort($offset_y, SORT_NUMERIC);
		$off_x = abs($offset_x[0]);	
		$off_y = abs($offset_y[0]);	
		//var_dump($offset_x);
		//var_dump($offset_y);
	
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
	
	//var_dump(trans(0, 0, 120.22, -23.34));
	
	
	
	for($r = 0;$r < $numWay;$r++) {
		$count_nd = count($way[$r]->nd);
		$nd_index = $way[$r]->nd;
		for($r_nd = 0;$r_nd < $count_nd-1;$r_nd++) {
			array_push($check_list, (string)$nd_index[$r_nd]['ref'], (string)$nd_index[$r_nd+1]['ref']);
			$string = "\n<edge id='".$r.'w'.$r_nd."-1' fromnode='".$nd_index[$r_nd]['ref']."' tonode='".$nd_index[($r_nd+1)]['ref']."' priority='75' nolanes='2' speed='40' />".
					"\n<edge id='".$r.'w'.$r_nd."-2' fromnode='".$nd_index[($r_nd+1)]['ref']."' tonode='".$nd_index[$r_nd]['ref']."' priority='75' nolanes='2' speed='40' />";
			$edge_content = $edge_content.$string;
		}
	}
	//var_dump($check_list);
	for($r = 0;$r < $numNode;$r++) {
		$index = $node[$r]['id'];
		if(in_array((string)$index, $check_list)) {
			$transCod = trans(0, 0, (float)$node[$r]['lon'], (float)$node[$r]['lat']);	
			$node_list["$index"] = array(bcadd($transCod['x'], $off_x, 6), bcadd($transCod['y'], $off_y, 6));
			//$string = "\n<node id='".$node[$r]['id']."' x='".$node[$r]['lon']."' y='".$node[$r]['lat']."' type='traffic_light' />";
			$string = "\n<node id='".$node[$r]['id']."' x='".bcadd($transCod['x'], $off_x, 6)."' y='".bcadd($transCod['y'], $off_y, 6)."' type='traffic_light' />";
			$node_content = $node_content.$string; 
			//array_push($node[$r]['lon'], $node_list[$node[$r]['id']]);
		}
	}
	
	$ori = "\n<node id='00' x='0' y='0' type='traffic_light' />";
	
	$node_file = '<nodes>'.$node_content."\n</nodes>\n<!-- offset_x: $off_x, offset_y: $off_y -->";
	$edge_file = '<edges>'.$edge_content."\n</edges>";
	
	$node_name = 'xml/nodes_om.xml';
	$edge_name = 'xml/edges_om.xml';
	file_put_contents($node_name, $node_file);
	file_put_contents($edge_name, $edge_file);
	
	//var_dump($node_list);
	//var_dump($way_list);
	//var_dump($offset_x);
	//echo $offset_x[0];
	echo '<a href="xml/nodes_om.xml" target="_blank">nodes_om.xml</a><br />';
	echo '<a href="xml/edges_om.xml" target="_blank">edges_om.xml</a><br />';
	echo '<a href="index.html">Home</a>';
	
	