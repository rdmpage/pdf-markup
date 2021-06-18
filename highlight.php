<?php

// Highlight a string

//------------------------------------------------------------------------------

$text = (isset($_GET['text']) ? $_GET['text'] : '');

if ($text) 
{
	$results = array();
	
 	$search_text = 'Scinax';
 	
 	$starting_position = 0; 	
 	$text_length = mb_strlen($text , mb_detect_encoding($text));	
 	$flanking_length = 50;
 	
 	while ($starting_position < $text_length)
 	{
	
		$pos = mb_strpos($text, $search_text, $starting_position);
		
		if ($pos === false)
		{
			$starting_position = $text_length;
		}
		else
		{
			$hit = new stdclass;
		
			$hit->text = $text;
		
			$hit->mid = $search_text;
		
			$mid_length = mb_strlen($search_text , mb_detect_encoding($search_text));		
		
			// start
			$start = $pos;
				
			// end
			$end = $start + $mid_length;	
					
			// range
			$hit->range = array($start, $end);
			
			$pre_length = min($start, $flanking_length);
			$pre_start = $start - $pre_length;
			
			$hit->pre = mb_substr($text, $pre_start, $pre_length, mb_detect_encoding($text)); 
			
			$post_length = min(mb_strlen($text, mb_detect_encoding($text)) - $end, $flanking_length);		
			
			$hit->post = mb_substr($text, $end, $post_length, mb_detect_encoding($text)); 
			
			$results[] = $hit;
		
			$starting_position = $end;
		}
	}
		
	header("Content-type: text/plain");
	echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

}
else
{

?>

<html>
<head>
	<meta charset="utf-8" />
	<title>Highlight</title>
</head>
<body>
<h1>
	Parse text
</h1>

<div>

	<form id="form" action="./highlight.php" method="get">
<textarea id="text"  name="text" rows="5" cols="80">
Figura 3. Scinax caprarius sp. nov. Holótipo IAvH-Am-11363, macho adulto preservado en etanol 70 %, LRC= 29.6 mm. A. Vista ventral. B. Vista dorsal. C. Vista laterales. Escala = 5 mm. Fotos: Andrés Acosta.
</textarea>

    <br />
   <input type="submit" value="Parse" />
   </form>
</div>



</body>
</html>

<?php
}
?>
