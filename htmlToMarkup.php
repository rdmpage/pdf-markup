<?php

// Simple HTML to markedup HTML

require_once(dirname(__FILE__) . '/core.php');
require_once(dirname(__FILE__) . '/spatial.php');



//----------------------------------------------------------------------------------------
function get($url)
{
	$data = null;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);   

	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	if ($http_code == 200)
	{
		$json = $response;
		$data = json_decode($json);
	}
	
	return $data;
}

//----------------------------------------------------------------------------------------
// Annotation as a highlight 
// This is a bit of a nightmare. PDF documentation is hard to find, and inconsistent.
// We can add annotations as pdfmarks using GhostScript.
// The annotations need to use the PDF coordinate system, where the origin (0,0)
// is bottom left, whereas the origin for PDFXML, HTML, and SVG is top left is (0,0).
// Locations for annotations are defined by QuadPoints,
function annotation_pdfmark($document, &$annotation)
{
	// get tokens for this text block
	$data = json_decode($document->current_paragraph_node->data);
	
	$page_num = $document->node_type_counter['page'];
	
	// match tokens to text spanned by annotation
	$tokens = array();
	for ($j = $annotation->range[0]; $j <= $annotation->range[1]; $j++)
	{
		$tokens[] = $data->text_to_blocks[$j];
	}
	$tokens = array_unique($tokens);
	
	// page dimensions
	$display_width 	= $data->page_width;
	$display_height = $data->page_height;
	
	// PDF
	$quadPoints = array();
	
	$pdfRect = new BBox(0,0,0,0);
	
	// PDF style	
	foreach ($tokens as $t)
	{
		$tokenPdfRect = new BBox(
			$data->xywh[$t]->x,				
			1 - ($data->xywh[$t]->y + $data->xywh[$t]->h),
			$data->xywh[$t]->x + $data->xywh[$t]->w,
			1 - $data->xywh[$t]->y
			);
		
		$pdfRect->merge($tokenPdfRect);

		$v = $tokenPdfRect->toQuadPoints(1/$display_width, 1/$display_height);
	
		$quadPoints = array_merge($quadPoints, $v);
		
	}
	$pdf = "[\n";
	$pdf .= "/SrcPg " . $page_num  . "\n"; // how do we know this?
	
	// Bounding rectangle
	$minx = $pdfRect->minx * $display_width;
	$maxx = $pdfRect->maxx * $display_width;
	$miny = $pdfRect->miny * $display_height;
	$maxy = $pdfRect->maxy * $display_height;
	$pdf .= "/Rect[$minx $miny $maxx $maxy]\n";
	
	$pdf .= "/Type /Annot\n";
	$pdf .= "/Subtype /Highlight\n";
	$pdf .= "/Color [1 1 0]\n";
	$pdf .= "/F 4\n";
	$pdf .=  "/QuadPoints [" . join(' ', $quadPoints) . "]\n";
	$pdf .= "/ANN pdfmark\n";
	
	//echo $pdf . "\n";
	
	$annotation->pdfmark = $pdf;
}

//----------------------------------------------------------------------------------------
// Annotation as a highlight in SVG 
function annotation_svg($document, &$annotation)
{
	// get tokens for this text block
	$data = json_decode($document->current_paragraph_node->data);
	
	$page_num = $document->node_type_counter['page'];
	
	// match tokens to text spanned by annotation
	$tokens = array();
	for ($j = $annotation->range[0]; $j <= $annotation->range[1]; $j++)
	{
		$tokens[] = $data->text_to_blocks[$j];
	}
	$tokens = array_unique($tokens);
	
	// page dimensions
	$display_width 	= $data->page_width;
	$display_height = $data->page_height;
	
	// Set viewbox to be page dimensions
	$svg = '<svg viewBox="0 0 ' . $display_width. ' ' . $display_height . '">' . "\n"; 
	foreach ($tokens as $t)
	{
		// annotation as a series of rects, note that token positions are normalised 0-1
		// so we need to multiply by page dimensions
		$svg .= '<rect x="' . round($display_width * $data->xywh[$t]->x) . '" y="' . round($display_height * $data->xywh[$t]->y) . '" width="' . round($display_width * $data->xywh[$t]->w) . '" height ="' . round($display_height * $data->xywh[$t]->h) . '" />' . "\n";
	}
	$svg .= '</svg>' . "\n";

	
	$annotation->svg = $svg;
}



//----------------------------------------------------------------------------------------
function markup(&$document)
{
	// Preliminaries	
	$page_num = $document->node_type_counter['page'];
	
	$text = $document->current_paragraph_node->content;
	
	// Do searches
	
	// 1. Geocoding
	
	$url = 'http://localhost/~rpage/pdf-markup/geocode.php?text=' . urlencode($text);
	
	$results = get($url);
	
	if ($results)
	{
		foreach ($results as $hit)
		{
			$annotation = new_annotation($document, 'geopoint', false);
			$annotation->pre 		= $hit->pre;
			$annotation->mid 		= $hit->mid;
			$annotation->post 		= $hit->post;
			$annotation->range 		= $hit->range;
			$annotation->feature 	= $hit->feature;
			
			add_annotation($document, $annotation);	
		
		
		}
	
	}
	
	// 2. Entities
	
	$url = 'http://localhost/~rpage/pdf-markup/highlight.php?text=' . urlencode($text);
	
	$results = get($url);
	
	if ($results)
	{
		foreach ($results as $hit)
		{
			$annotation = new_annotation($document, 'highlight', false);
			
			// text locators
			if (isset($hit->pre))
			{
				$annotation->pre = $hit->pre;
			}
			$annotation->mid = $hit->mid;
			if (isset($hit->post))
			{
				$annotation->post= $hit->post;
			}
			
			// local range
			$annotation->range = $hit->range;
			
			// to do: global range
			
			// SVG
			annotation_svg($document, $annotation);
			
			// PDF
			annotation_pdfmark($document, $annotation);
			
			add_annotation($document, $annotation);	
		}
	
	}
	
	
}


/*
//----------------------------------------------------------------------------------------
function markup(&$document)
{
	// Preliminaries	
	$page_num = $document->node_type_counter['page'];

 	$search_text = 'Eriocaulon vamanae';
	
	//echo $document->current_paragraph_node->content . "\n";

	$pos = mb_strpos($document->current_paragraph_node->content, $search_text);
		
	if ($pos === false)
	{
	
	}
	else
	{
		// print_r($data);				
		
		// Create text location annotation -----------------------------------------------
		$annotation = new_annotation($document, 'highlight', false);
		$annotation->mid = $search_text;
		
		$text_length = mb_strlen($search_text , mb_detect_encoding($search_text));
				
		// start
		$offsetStart = $pos;		
		// end
		$offsetEnd = $offsetStart + $text_length;				
		// range
		$annotation->range = array($offsetStart, $offsetEnd);
				
		// need to think in terms of global document text position, this is local
		// to this node
		
		// what else do we need to add to be able to export this in other formats?


		// annotation as SVG -------------------------------------------------------------
		
		// SVG can be complex shape 
		
		// We need to map annotation text to x,y space so get list of tokens in OCR/PDF	
		$data = json_decode($document->current_paragraph_node->data);
		
		// matching tokens
		$tokens = array();
		for ($j = $annotation->range[0]; $j <= $annotation->range[1]; $j++)
		{
			$tokens[] = $data->text_to_blocks[$j];
		}
		$tokens = array_unique($tokens);
		
		//print_r($annotation);
		//echo json_encode($data->text_to_blocks);
		//echo json_encode($data->tokens);
		//print_r($tokens);
		//exit();
		
		
		// page dimensions
		$display_width 	= $data->page_width;
		$display_height = $data->page_height;
		
		// Set viewbox to be page dimensions
		$svg = '<svg viewBox="0 0 ' . $display_width. ' ' . $display_height . '">' . "\n"; 
		
		foreach ($tokens as $t)
		{
			// annotation as a series of rects, note that token positions are normalised 0-1
			// so we need to multiple by page dimensions
			$svg .= '<rect x="' . round($display_width * $data->xywh[$t]->x) . '" y="' . round($display_height * $data->xywh[$t]->y) . '" width="' . round($display_width * $data->xywh[$t]->w) . '" height ="' . round($display_height * $data->xywh[$t]->h) . '" />' . "\n";
		}
		
		$svg .= '</svg>' . "\n";
		
		//echo $svg;	
		
		$annotation->svg = $svg;
		
		// annotation for PDF-------------------------------------------------------------
			
		// Annotation as a highlight 
		// This is a bit of a nightmare. PDF documentation is hard to find, and inconsistent.
		// We can add annotations as pdfmarks using GhostScript.
		// The annotations need to use the PDF coordinate system, where the origin (0,0)
		// is bottom left, whereas the origin for PDFXML, HTML, and SVG is top left is (0,0).
		// Locations for annotations ar defined by QuadPoints,
		
		$quadPoints = array();
		
		$pdfRect = new BBox(0,0,0,0);
		
		// PDF style	
		foreach ($tokens as $t)
		{
			$tokenPdfRect = new BBox(
				$data->xywh[$t]->x,				
				1 - ($data->xywh[$t]->y + $data->xywh[$t]->h),
				$data->xywh[$t]->x + $data->xywh[$t]->w,
				1 - $data->xywh[$t]->y
				);
			
			$pdfRect->merge($tokenPdfRect);

			$v = $tokenPdfRect->toQuadPoints(1/$display_width, 1/$display_height);
		
			$quadPoints = array_merge($quadPoints, $v);
			
		}
		$pdf = "[\n";
		$pdf .= "/SrcPg " . $page_num  . "\n"; // how do we know this?
		
		// Bounding rectangle
		$minx = $pdfRect->minx * $display_width;
		$maxx = $pdfRect->maxx * $display_width;
		$miny = $pdfRect->miny * $display_height;
		$maxy = $pdfRect->maxy * $display_height;
		$pdf .= "/Rect[$minx $miny $maxx $maxy]\n";
		
		$pdf .= "/Type /Annot\n";
		$pdf .= "/Subtype /Highlight\n";
		$pdf .= "/Color [1 1 0]\n";
		$pdf .= "/F 4\n";
		$pdf .=  "/QuadPoints [" . join(' ', $quadPoints) . "]\n";
		$pdf .= "/ANN pdfmark\n";
		
		//echo $pdf . "\n";
		
		$annotation->pdfmark = $pdf;
			
		
		add_annotation($document, $annotation);	
	}
}
*/

//----------------------------------------------------------------------------------------


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
	
	$output_filename = basename($filename, '.htm') . '.shtml';	
}


$html = file_get_contents($filename);


$dom = html_to_dom($html);

// process document by going through each node and calling a function to add markup

$document = create_document();

$counter = 0;
foreach ($dom->documentElement->childNodes as $node) {
    dive($node, $document, 'markup'); 
}

cleanup_document($document);


// debugging
file_put_contents('htmlToMarkup.json', json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));




// create HTML that includes markup
$markedup_html = to_html($document, true, true);
file_put_contents($output_filename, $markedup_html);

if (0)
{
	echo '<pre>';
	print_r($document);
	echo '</pre>';
}

if (0)
{
	// Dump list of annotations
	//echo '<h1>Annotations</h1>';
	
	//echo '<ul>';	
	foreach ($document->nodes as $node)
	{

		// highlight
		if ($node->type == 'highlight')
		{
			/*
			echo '<li><pre>';
			
			print_r($node);
			
			echo '</pre></li>';
			*/
			
			if (isset($node->pdfmark))
			{
				echo $node->pdfmark . "\n";
			}
		
		}

	}
		
	//echo '</ul>';

}

if (0)
{
	// Dump list of points
	
	$geojson = new stdclass;
	$geojson->type = 'MultiPoint';
	$geojson->coordinates = array();
	
	foreach ($document->nodes as $node)
	{
		// highlight
		if ($node->type == 'geopoint')
		{
			if (isset($node->feature))
			{
				$geojson->coordinates[] = $node->feature->geometry->coordinates;
			}
		}
	}
	
	echo json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

}

if (1)
{
	// Dump list of SVG
	foreach ($document->nodes as $node)
	{
		// highlight
		if ($node->type == 'highlight')
		{
			print_r($node);
			
			if (isset($node->svg))
			{
				echo $node->svg . "\n";
			}
		}
	}

}


?>

