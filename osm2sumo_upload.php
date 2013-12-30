<?php
	require('gPoint.php');
	
	move_uploaded_file($_FILES["file"]["tmp_name"],"upload/".$_FILES["file"]["name"]);
	
	$north = $_POST['north'];
	$south = $_POST['south'];
	$west = $_POST['west'];
	$east = $_POST['east'];
	
	
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
	
	$temp_compare = array();
	
	for($r = 0;$r < $numNode;$r++) {
		$id = (int)$node[$r]['id'];
		$temp_compare[sprintf("%d",$id)] = array((float)$node[$r]['lon'], (float)$node[$r]['lat']);
	}
	
	function Trans_UTM($lat, $lon) {
		$temp = new gPoint();
		$temp->setLongLat($lon, $lat);
		$temp->convertLLtoTM();
		
		$ans['x'] = $temp->utmEasting;
		$ans['y'] = $temp->utmNorthing;
		return $ans;
	}
	
	for($r = 0;$r < $numWay;$r++) {
		$count_nd = count($way[$r]->nd);
		$nd_index = $way[$r]->nd;
		for($r_nd = 0;$r_nd < $count_nd-1;$r_nd++) {
			array_push($check_list, (string)$nd_index[$r_nd]['ref'], (string)$nd_index[$r_nd+1]['ref']);
			//if($temp_compare[$nd_index[$r_nd]['ref']]<$north && $nd_index[$r_nd]['ref'])
			$id1 = $nd_index[$r_nd]['ref'];
			if((float)$temp_compare[sprintf("%d",$id1)][0]>(float)$west && (float)$temp_compare[sprintf("%d",$id1)][0]<(float)$east && (float)$temp_compare[sprintf("%d",$id1)][1]>(float)$south && (float)$temp_compare[sprintf("%d",$id1)][1]<(float)$north) {
				$string = "\n<edge id='".$r.'w'.$r_nd."-1' fromnode='".$nd_index[$r_nd]['ref']."' tonode='".$nd_index[($r_nd+1)]['ref']."' priority='75' nolanes='2' speed='40' />".
						"\n<edge id='".$r.'w'.$r_nd."-2' fromnode='".$nd_index[($r_nd+1)]['ref']."' tonode='".$nd_index[$r_nd]['ref']."' priority='75' nolanes='2' speed='40' />";
				$edge_content = $edge_content.$string;
			}
		}
	}
	
	
	for($r = 0;$r < $numNode;$r++) {
		$index = $node[$r]['id'];
		if(in_array((string)$index, $check_list)) {
			$transCod = Trans_UTM((float)$node[$r]['lat'], (float)$node[$r]['lon']);
			$node_list["$index"] = array($transCod['x'], $transCod['y']);
			//$string = "\n<node id='".$node[$r]['id']."' x='".$node[$r]['lon']."' y='".$node[$r]['lat']."' type='traffic_light' />";
			$string = "\n<node id='".$node[$r]['id']."' x='".$transCod['x']."' y='".$transCod['y']."' type='traffic_light' />";
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
	
	
	//var_dump($way_list);
	//var_dump($offset_x);
	//echo $offset_x[0];
	echo '<a href="xml/nodes_om.xml" target="_blank">nodes_om.xml</a><br />';
	echo '<a href="xml/edges_om.xml" target="_blank">edges_om.xml</a><br />';
	echo '<a href="index.html">Home</a>';
	
	