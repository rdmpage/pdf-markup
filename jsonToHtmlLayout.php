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
	
	$output_filename = basename($filename, '.json') . '.html';	
}


$json = file_get_contents($filename);

$obj = json_decode($json);

$html = '';

$html .= '<html>
<head>
<style>
	body {
		background:rgb(228,228,228);
		margin:0;
		padding:0;
	}
	
	.page {
		background-color:white;
		position:relative;
		margin: 0 auto;
		/* border:1px solid black; */
		margin-bottom:1em;
		margin-top:1em;
	
	}
	
	/* figure */
	.image {
		position:absolute;
		background-color:orange;
		/* Can invert image if needed https://stackoverflow.com/a/13325820/9684 */
		/* filter: invert(1); */
	}
	
	/* visible text */
	.token {
		position:absolute;
	}	
	
	.token-text {
		rgba(19,19,19,1);
		vertical-align:baseline;
		white-space:nowrap;
	}	
	
	/* SVG annotaiton markup */
	rect {
		fill: blue;
		opacity: 0.7;	
		stroke: none;		
	}	
	
    #viewport {
        background: #e5e5e5;
        position: relative;
        overflow: hidden;
        padding: 0px;
        
        /* experiments with scaling */
        
        /* note that we translate first then scale, which makes it easier to compute translate */
        transform: translate(0) scale(1, 1);
        /* transform:translate(25%) scale(0.5,0.5) ; */
        /* transform:translate(15%) scale(0.7,0.7) ; */
        transform-origin: 0 0;
        margin: 0px;
    }
</style>
</head>
<body>';

$html .= '<div style="width:100%;height:100%;overflow-y:auto;overflow-x:auto;text-align:center;">' . "\n";
$html .= '<div id="viewport">' . "\n";


foreach ($obj->pages as $page)
{

	// page
	$x = $page->bbox->minx;
	$y = $page->bbox->miny;
	$w = $page->bbox->maxx - $page->bbox->minx;
	$h = $page->bbox->maxy - $page->bbox->miny;
	
	
	$html .= '<div class="page" style="width:' . $w . 'px;height:' . $h . 'px;">'  . "\n";
		
	// images (figures) from born native PDF
	if (1)
	{
		foreach ($page->images as $image)
		{
			$x = $image->bbox->minx;
			$y = $image->bbox->miny;
			$w = $image->bbox->maxx - $image->bbox->minx;
			$h = $image->bbox->maxy - $image->bbox->miny;
		
			// ignore block x=0, y=0 as this is the whole page(?)
			if (($x != 0) && ($y != 0))
			{
				$html .= '<div class="image" style="' 
					. 'left:' 	. $x . 'px;'
					. 'top:' 	. $y . 'px;'
					. 'width:' 	. $w . 'px;'
					. 'height:' . $h . 'px;'
					. '">'  . "\n";
										
				$html .= '<img src="' . $image->href	. '"'
					. 'width="' . $w . '"'
					. '>'  . "\n";				
									
				$html .= '</div>'  . "\n";		
			}
		
		}
		
	}
	
	foreach ($page->blocks as $block)
	{
		foreach ($block->tokens as $token)
		{			
			$x = $token->bbox->minx;
			$y = $token->bbox->miny;
			$w = $token->bbox->maxx - $token->bbox->minx;
			$h = $token->bbox->maxy - $token->bbox->miny;
			
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
							
		}
	
	}
	
	$html .= '</div>'  . "\n";

}

$html .= '</div>'  . "\n";
$html .= '</div>'  . "\n";

$html .= '</body>
</html>'  . "\n";

file_put_contents($output_filename, $html);



?>
