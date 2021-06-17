<?php

// Convert PDF to page images, use OCR JSON file to scale images to match HTML version of PDF

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
	
	$output_filename = basename($filename, '.pdf') . '.images.html';
}

$prefix 		= "page";

// Extract page images from PDF
$command = './xpdf-xpdfreader/xpdf-tools-mac-4.00/bin64/pdftopng'
	. ' -r 96 ' . $filename . ' ' . $prefix;
	

echo $command . "\n";
system($command);


// Get PDFXML JSON so we can scale images to match

$json_filename = basename($filename, '.pdf') . '.json';

$json = file_get_contents($json_filename);
$doc = json_decode($json);


// Process each image, convert to base64 then delete file
$page_images = array();

$files = scandir(dirname(__FILE__));

$counter = 0;
foreach ($files as $fname)
{
	if (preg_match('/\.png$/', $fname))
	{	
		echo $fname . "\n";
						
		$details = getimagesize($fname);
		
		
		$image_obj = new stdclass;
		
		$image_obj->width 	= $details[0];
		$image_obj->height 	= $details[1];
		$image_obj->mime 	= $details['mime'];
		
		$image = file_get_contents($fname);
		
		
		$page = $doc->pages[$counter];
		$page_width 	= $page->bbox->maxx - $page->bbox->minx;
		$page_height 	= $page->bbox->maxy - $page->bbox->miny;
		
		
		// rescale?
		$scale = $page_width  / $image_obj->width;
		$image_obj->width 	= $page_width;
		$image_obj->height *= $scale;	
			
				
		$image_obj->base64  = base64_encode($image);
		
		$page_images[] = $image_obj;
		
		if (1)
		{
			unlink($fname);
		}
		
		$counter++;
	}
}

$html = '';

$html .= '<html>
<head>
    <style>
	body {
		background:rgb(188,188,188);
		margin:0;
		padding:0;
	}
	
	.page {
		background-color:white;
		position:relative;
		margin: 0 auto;
		border:1px solid rgb(150,150,150); 
		margin-bottom:1em;
		margin-top:1em;
	
	}

	.page svg {
		position: absolute;
		top: 0;
		left: 0;	
	} 	
				
	rect {
		fill: blue;
		opacity: 0.4;	
		stroke: none;		
	}				
	
	/* visible text */
	.token {
		position:absolute;
	}	
	
	.token-text {
	/*	rgba(19,19,19,1);
		vertical-align:baseline;
		white-space:nowrap; */

	  position:absolute;
	  overflow:hidden;
	  color:rgba(0,0,0,0);
		
	}	
	
.unselectable {
	position:absolute;
}
	
*.unselectable {
   user-select: none;
}

*.unselectable * {
   user-select: text;
}						
			
   #viewport {
        background: rgb(188,188,188); /* #e5e5e5; */
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


$counter = 0;

foreach ($page_images as $image)
{
	$html .= '<div class="page" style="width:' . $image->width .'px;height:' . $image->height .'px">'  . "\n";
	$html .= '<img src="data:' . $image->mime . ';base64,' . $image->base64 . '" width="' . $image->width . '">'  . "\n";
	
	// add annotations here 


	$page = $doc->pages[$counter];
	
	// add text that user can copy and paste
	
	
	$html .= '<div class="unselectable" style="left:0px;top:0px;width:' . $image->width . 'px;height:' . $image->height . 'px;' . '">';
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
	
	$counter++;
		
	$html .= '</div>'  . "\n";
}

$html .= '</div>'  . "\n";
$html .= '</div>'  . "\n";


$html .= '</body>
</html>';

file_put_contents($output_filename, $html);

?>
