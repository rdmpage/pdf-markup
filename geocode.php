<?php

// Extract point localities from text


//------------------------------------------------------------------------------
/**
 * @brief Convert a decimal latitude or longitude to deg° min' sec'' format in HTML
 *
 * @param decimal Latitude or longitude as a decimal number
 *
 * @return Degree format
 */
function decimal_to_degrees($decimal)
{
	$decimal = abs($decimal);
	$degrees = floor($decimal);
	$minutes = floor(60 * ($decimal - $degrees));
	$seconds = round(60 * (60 * ($decimal - $degrees) - $minutes));
	
	if ($seconds == 60)
	{
		$minutes++;
		$seconds = 0;
	}
	
	// &#176;
	$result = $degrees . '&deg;' . $minutes . '&rsquo;';
	if ($seconds != 0)
	{
		$result .= $seconds . '&rdquo;';
	}
	return $result;
}

//------------------------------------------------------------------------------
/**
 * @brief Convert decimal latitude, longitude pair to deg° min' sec'' format in HTML
 *
 * @param latitude Latitude as a decimal number
 * @param longitude Longitude as a decimal number
 *
 * @return Degree format
 */
function format_decimal_latlon($latitude, $longitude)
{
	$html = decimal_to_degrees($latitude);
	$html .= ($latitude < 0.0 ? 'S' : 'N');
	$html .= '&nbsp;';
	$html .= decimal_to_degrees($longitude);
	$html .= ($longitude < 0.0 ? 'W' : 'E');
	return $html;
}

//--------------------------------------------------------------------------------------------------
/**
 * @brief Convert degrees, minutes, seconds to a decimal value
 *
 * @param degrees Degrees
 * @param minutes Minutes
 * @param seconds Seconds
 * @param hemisphere Hemisphere (optional)
 *
 * @result Decimal coordinates
 */
function degrees2decimal($degrees, $minutes=0, $seconds=0, $hemisphere='N')
{
	$result = $degrees;
	$result += $minutes/60.0;
	$result += $seconds/3600.0;
	
	//echo "seconds=$seconds|<br/>";
	
	if ($hemisphere == 'S')
	{
		$result *= -1.0;
	}
	if ($hemisphere == 'W')
	{
		$result *= -1.0;
	}
	// Spanish
	if ($hemisphere == 'O')
	{
		$result *= -1.0;
	}
	// Spainish OCR error
	if ($hemisphere == '0')
	{
		$result *= -1.0;
	}
	
	return $result;
}

//------------------------------------------------------------------------------
function toPoint($matches)
{
	$feature = new stdclass;
	$feature->type = "Feature";
	$feature->geometry = new stdclass;
	$feature->geometry->type = "Point";
	$feature->geometry->coordinates = array();
			
	$degrees = $minutes = $seconds = 0;		
		
	if (isset($matches['latitude_seconds']))
	{
		$seconds = $matches['latitude_seconds'];
		
		if ($seconds == '')
		{
			$seconds = 0;
		}
		
	}
	$minutes = $matches['latitude_minutes'];
	$degrees = $matches['latitude_degrees'];
	
	$feature->geometry->coordinates[1] = degrees2decimal($degrees, $minutes, $seconds, $matches['latitude_hemisphere']);

	$degrees = $minutes = $seconds = 0;	
	
	if (isset($matches['longitude_seconds']))
	{
		$seconds = $matches['longitude_seconds'];
		
		if ($seconds == '')
		{
			$seconds = 0;
		}
	}
	$minutes = $matches['longitude_minutes'];
	$degrees = $matches['longitude_degrees'];
	
	$feature->geometry->coordinates[0] = degrees2decimal($degrees, $minutes, $seconds, $matches['longitude_hemisphere']);
	
	// ensures that JSON export treats coordinates as an array
	ksort($feature->geometry->coordinates);
	
	return $feature;
}

//------------------------------------------------------------------------------
// Series of regular expressions to extract point localities from text
// Note that we don't use PREG_OFFSET_CAPTURE as it gives incorrect values
// for some strings (depending on encoding), so we compute positions ourselves.
function find_points($text)
{

	$DEGREES_SYMBOL 		=  '[˚|°|º]';
	$MINUTES_SYMBOL			= '(\'|’|\′|\´)';
	$SECONDS_SYMBOL			= '("|\'\'|’’|”|\′\′|\´\´|″)';
	
	$INTEGER				= '\d+';
	$FLOAT					= '\d+(\.\d+)?';
	
	$LATITUDE_DEGREES 		= '[0-9]{1,2}';
	$LONGITUDE_DEGREES 		= '[0-9]{1,3}';
	
	$LATITUDE_HEMISPHERE 	= '[N|S]';
	$LONGITUDE_HEMISPHERE 	= '[W|E]';
	
	$flanking_length = 50;
	
	$results = array();
		
	if (preg_match_all("/
		(?<latitude_degrees>$LATITUDE_DEGREES)
		$DEGREES_SYMBOL
		\s*
		(?<latitude_minutes>$FLOAT)
		$MINUTES_SYMBOL?
		\s*
		(
		(?<latitude_seconds>$FLOAT)
		$SECONDS_SYMBOL
		)?
		\s*
		(?<latitude_hemisphere>$LATITUDE_HEMISPHERE)
		,?
		(\s+-)?
		;?
		\s*
		(?<longitude_degrees>$LONGITUDE_DEGREES)
		$DEGREES_SYMBOL
		\s*
		(?<longitude_minutes>$FLOAT)
		$MINUTES_SYMBOL?
		\s*
		(
		(?<longitude_seconds>$FLOAT)
		$SECONDS_SYMBOL
		)?
		\s*
		(?<longitude_hemisphere>$LONGITUDE_HEMISPHERE)
		
	/xu",  $text, $matches, PREG_SET_ORDER))
	{
		//print_r($matches);
		
		$last_pos = 0;
		
		foreach ($matches as $match)
		{
			$hit = new stdclass;
			$hit->text = $text;
			$hit->type = 'geopoint';
			
			// verbatim text we have matched
			$hit->mid = $match[0];
			
			$start = mb_strpos($text, $hit->mid, $last_pos, mb_detect_encoding($text));
			$end = $start + mb_strlen($hit->mid, mb_detect_encoding($hit->mid)) - 1;
			
			// update position so we don't find this point again
			$last_pos = $end;
			
			$hit->range = array($start, $end);
			
			$pre_length = min($start, $flanking_length);
			$pre_start = $start - $pre_length;
			
			$hit->pre = mb_substr($text, $pre_start, $pre_length, mb_detect_encoding($text)); 
			

			$post_length = 	min(mb_strlen($text, mb_detect_encoding($text)) - $end, $flanking_length);		
			
			$hit->post = mb_substr($text, $end + 1, $post_length, mb_detect_encoding($text)); 
			
			$hit->feature = toPoint($match);
			
			
			$results[] = $hit;
		}
	}
	
	// 29.6° N, 101.8° E
	if (preg_match_all("/
		(?<latitude_degrees>$FLOAT)
		$DEGREES_SYMBOL
		\s*
		(?<latitude_hemisphere>$LATITUDE_HEMISPHERE)
		,
		\s+
		(?<longitude_degrees>$FLOAT)
		$DEGREES_SYMBOL
		\s*
		(?<longitude_hemisphere>$LONGITUDE_HEMISPHERE)		
	/xu",  $text, $matches, PREG_SET_ORDER))
	{
		//print_r($matches);
		
		$last_pos = 0;
		
		foreach ($matches as $match)
		{
			$hit = new stdclass;
			$hit->text = $text;
			$hit->type = 'geopoint';
			
			// verbatim text we have matched
			$hit->mid = $match[0];
			
			$start = mb_strpos($text, $hit->mid, $last_pos, mb_detect_encoding($text));
			$end = $start + mb_strlen($hit->mid, mb_detect_encoding($hit->mid)) - 1;
			
			// update position so we don't find this point again
			$last_pos = $end;
			
			$hit->range = array($start, $end);
			
			$pre_length = min($start, $flanking_length);
			$pre_start = $start - $pre_length;
			
			$hit->pre = mb_substr($text, $pre_start, $pre_length, mb_detect_encoding($text)); 
			

			$post_length = 	min(mb_strlen($text, mb_detect_encoding($text)) - $end, $flanking_length);		
			
			$hit->post = mb_substr($text, $end + 1, $post_length, mb_detect_encoding($text)); 
			
			$hit->feature = toPoint($match);
			
			
			$results[] = $hit;
		}
	}
	
	
	// N27.21234º, E098.69601º
	if (preg_match_all("/
		(?<latitude_hemisphere>$LATITUDE_HEMISPHERE)
		(?<latitude_degrees>$FLOAT)
		$DEGREES_SYMBOL
		,
		\s+
		(?<longitude_hemisphere>$LONGITUDE_HEMISPHERE)
		(?<longitude_degrees>$FLOAT)
		$DEGREES_SYMBOL		
	/xu",  $text, $matches, PREG_SET_ORDER))
	{
		//print_r($matches);
		
		$last_pos = 0;
		
		foreach ($matches as $match)
		{
			$hit = new stdclass;
			$hit->text = $text;
			$hit->type = 'geopoint';
			
			// verbatim text we have matched
			$hit->mid = $match[0];
			
			$start = mb_strpos($text, $hit->mid, $last_pos, mb_detect_encoding($text));
			$end = $start + mb_strlen($hit->mid, mb_detect_encoding($hit->mid)) - 1;
			
			// update position so we don't find this point again
			$last_pos = $end;
			
			$hit->range = array($start, $end);
			
			$pre_length = min($start, $flanking_length);
			$pre_start = $start - $pre_length;
			
			$hit->pre = mb_substr($text, $pre_start, $pre_length, mb_detect_encoding($text)); 
			

			$post_length = 	min(mb_strlen($text, mb_detect_encoding($text)) - $end, $flanking_length);		
			
			$hit->post = mb_substr($text, $end + 1, $post_length, mb_detect_encoding($text)); 
			
			$hit->feature = toPoint($match);
			
			
			$results[] = $hit;
		}
	}
	
	
	// N25°59', E98°40'
	if (preg_match_all("/
		(?<latitude_hemisphere>$LATITUDE_HEMISPHERE)
		\s*
		(?<latitude_degrees>$LATITUDE_DEGREES)
		$DEGREES_SYMBOL
		(?<latitude_minutes>$INTEGER)
		$MINUTES_SYMBOL
		(
		(?<latitude_seconds>$FLOAT)
		$SECONDS_SYMBOL
		)?		
		,
		\s+
		(?<longitude_hemisphere>$LONGITUDE_HEMISPHERE)
		\s*
		(?<longitude_degrees>$LONGITUDE_DEGREES)
		$DEGREES_SYMBOL		
		(?<longitude_minutes>$INTEGER)
		$MINUTES_SYMBOL
		(
		(?<longitude_seconds>$FLOAT)
		$SECONDS_SYMBOL
		)?		
	/xu",  $text, $matches, PREG_SET_ORDER))
	{
		//print_r($matches);
		
		$last_pos = 0;
		
		foreach ($matches as $match)
		{
			$hit = new stdclass;
			$hit->text = $text;
			$hit->type = 'geopoint';
			
			// verbatim text we have matched
			$hit->mid = $match[0];
			
			$start = mb_strpos($text, $hit->mid, $last_pos, mb_detect_encoding($text));
			$end = $start + mb_strlen($hit->mid, mb_detect_encoding($hit->mid)) - 1;
			
			// update position so we don't find this point again
			$last_pos = $end;
			
			$hit->range = array($start, $end);
			
			$pre_length = min($start, $flanking_length);
			$pre_start = $start - $pre_length;
			
			$hit->pre = mb_substr($text, $pre_start, $pre_length, mb_detect_encoding($text)); 
			

			$post_length = 	min(mb_strlen($text, mb_detect_encoding($text)) - $end, $flanking_length);		
			
			$hit->post = mb_substr($text, $end + 1, $post_length, mb_detect_encoding($text)); 
			
			$hit->feature = toPoint($match);
			
			$results[] = $hit;
		}
	}
	
	
	return $results;
}

//------------------------------------------------------------------------------

$text = (isset($_GET['text']) ? $_GET['text'] : '');

if ($text) 
{
	$results = find_points($text);
	
	echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

}
else
{

?>

<html>
<head>
	<meta charset="utf-8" />
	<title>Geocoding</title>
</head>
<body>
<h1>
	Parse text
</h1>

<div>

	<form id="form" action="./geocode.php" method="get">
<textarea id="text"  name="text" rows="5" cols="80">
Maria I., Tasmania (42°37´S, 148°20´E), 102 m, WHOI
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
