<?php

// OCR JSON to HTML with WYSWYG layout

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
	
	$output_filename = basename($filename, '.json') . '.hocr.xml';	
}


$json = file_get_contents($filename);

$obj = json_decode($json);

$html = '';

$html .= '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
 <head>
  <title></title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
</head>
<body>';

$page_count = 1;

foreach ($obj->pages as $page)
{
	// page
	/*
	$x = $page->bbox->minx;
	$y = $page->bbox->miny;
	$w = $page->bbox->maxx - $page->bbox->minx;
	$h = $page->bbox->maxy - $page->bbox->miny;
	*/
	
	$bbox = array(
		$page->bbox->minx,
		$page->bbox->miny,
		$page->bbox->maxx,
		$page->bbox->maxy
	);
	
	
	$html .= '<div class="ocr_page" id="page_' . $page_count . '" title="image &quot;page' . $page_count . '.jpg&quot;; bbox ' . join(" ", $bbox) . '">'  . "\n";
	
	$block_count = 1;
	
	foreach ($page->blocks as $block)
	{
		/*
		$x = $block->bbox->minx;
		$y = $block->bbox->miny;
		$w = $block->bbox->maxx - $block->bbox->minx;
		$h = $block->bbox->maxy - $block->bbox->miny;
		*/
		
		$bbox = array(
			$block->bbox->minx,
			$block->bbox->miny,
			$block->bbox->maxx,
			$block->bbox->maxy
		);
			
		$html .= '<div class="ocr_carea" id="block_' . $page_count . '_' . $block_count . '" title="bbox ' . join(" ", $bbox) . '">' . "\n";

		// if "block" is a line (e.g., OCR)
		$html .= '<span class="ocr_line" id="line_' . $page_count . '_' . $block_count . '" title="bbox ' . join(" ", $bbox) . '">' . "\n";
				
		$token_count = 1;
		
		foreach ($block->tokens as $token)
		{			
			$bbox = array(
				$token->bbox->minx,
				$token->bbox->miny,
				$token->bbox->maxx,
				$token->bbox->maxy
			);
		
			$x = $token->bbox->minx;
			$y = $token->bbox->miny;
			$w = $token->bbox->maxx - $token->bbox->minx;
			$h = $token->bbox->maxy - $token->bbox->miny;
			
			$html .= '<span class="ocrx_word" id="' . $page_count . '_' . $block_count . '_' . $token_count . '" title="bbox ' . join(" ", $bbox) . '">';
			$html .= $token->text . "\n";			
			$html .= '</span>' . "\n";			
			$token_count++;
			
			/*
			$styles = array();
			
			if ($token->rotation)
			{
				if ($token->angle == 90)
				{
					$styles[] = 'writing-mode: vertical-rl';
					$styles[] = 'transform: rotate(-180deg)';
				}
			
			}	
			
			if ($token->bold)
			{
				$styles[] = 'font-weight:bold';
			}
			if ($token->italic)
			{
				$styles[] = 'font-style:italic';
			}
			
			$styles[] = 'font-size:' . $token->font_size . 'px';
					
			$html .= '<div class="token" style="' 
				. 'left:' . $x . 'px;'
				. 'top:' . $y . 'px;'
				. 'width:' . $w . 'px;'
				. 'height:' . $h . 'px;'
				. '">'  . "\n";
			
			$html .= '<span class="token-text" style="' . join(';', $styles) . '">';


			$html .= $token->text . "\n";
			
			$html .= '</span>'  . "\n";
			
			$html .= '</div>'  . "\n";
			*/
							
		}
		$html .= '</span>'  . "\n";
		$html .= '</div>'  . "\n";
		
		$block_count++;
	
	}
	
	$html .= '</div>'  . "\n";
	
	$page_count++;

}


$html .= '</body>
</html>'  . "\n";

file_put_contents($output_filename, $html);



?>
