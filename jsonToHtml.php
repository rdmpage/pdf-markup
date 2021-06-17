<?php

// OCR JSON to simple HTML that only has text in document

$filename = '';
$output_filename = '';

if ($argc < 2)
{
	echo "Usage: " . $argv[0] . " <input file>\n";
	exit(1);
}
else
{
	$filename = $argv[1];
	
	$output_filename = basename($filename, '.json') . '.htm';	
}


$json = file_get_contents($filename);

$obj = json_decode($json);

$html = '';


foreach ($obj->pages as $page)
{
	// page
	$page_width 	= $page->bbox->maxx - $page->bbox->minx;
	$page_height 	= $page->bbox->maxy - $page->bbox->miny;
	
	$html .= '<section>';
	
	foreach ($page->blocks as $block)
	{	
		$html .= '<p>';
		
		// Map each character in combined text string to corresponding XML token element on page
		$p_data = new stdclass;
		$p_data->text_to_blocks = array();
		$p_data->tokens = array();
		$p_data->xywh = array();
		$p_data->page_width 	= $page_width;
		$p_data->page_height 	= $page_height;
		
		// Keep track of tokens
		$counter = 0;		
	
		foreach ($block->tokens as $token)
		{			
			$x = $token->bbox->minx;
			$y = $token->bbox->miny;
			$w = $token->bbox->maxx - $token->bbox->minx;
			$h = $token->bbox->maxy - $token->bbox->miny;
			
			// Output token
			if ($token->italic)
			{
				$html .= '<i>';
			}

			$html .= htmlentities($token->text);
			
						
			if ($token->italic)
			{
				$html .= '</i>';
			}
			
			$html .=  " \n";
			
			// store token info 
			
			// add to array of token text
			$p_data->tokens[] = $token->text;

			// Mark off part of text that matches this token
			$length = mb_strlen($token->text, mb_detect_encoding($token->text)) + 1;			
			for ($i = 0; $i < $length; $i++)
			{
				$p_data->text_to_blocks[] = $counter;
			}
			
			// Store relative coordinates of this token on page image
			$t = new stdclass;
			$t->x = $token->bbox->minx / $page_width;
			$t->y = $token->bbox->miny / $page_height;
			$t->w = ($token->bbox->maxx - $token->bbox->minx) / $page_width;
			$t->h = ($token->bbox->maxy - $token->bbox->miny) / $page_height;
						
			$p_data->xywh[] = $t;
			
			$counter++;
	
							
		}
		// Store token info as JSON
		$html .=  '<script type="application/json">';
		$html .=  json_encode($p_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$html .=  '</script>';		
		
		
		$html .= '</p>';
	
	}
	
	$html .= '</section>';

}


file_put_contents($output_filename, $html);



?>
