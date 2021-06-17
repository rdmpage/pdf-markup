<?php

// Convert PDFTOXML XML to OCR JSON

require_once (dirname(__FILE__) . '/spatial.php');

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
	
	$output_filename = basename($filename, '.xml') . '.json';
}



// $segments = array();

$obj = new stdclass;

$obj = new stdclass;
$obj->pages = array();


$xml = file_get_contents($filename);
				
$dom = new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
				
$pages = $xpath->query ('//PAGE');
foreach($pages as $xml_page)
{

	// page level
	$page = new stdclass;	
	$page->type = 'page';
	$page->blocks = array();
	$page->images = array();

	// coordinates
	if ($xml_page->hasAttributes()) 
	{ 
		$attributes = array();
		$attrs = $xml_page->attributes; 
		
		foreach ($attrs as $i => $attr)
		{
			$attributes[$attr->name] = $attr->value; 
		}
	}
	
	$page->width = $attributes['width'];
	$page->height = $attributes['height'];	
	
	$page->bbox = new BBox(0, 0, $page->width, $page->height);
	$page->text_bbox = new BBox($page->width, $page->height, 0, 0);
		
	// images (figures) from born native PDF ---------------------------------------------
	$images = $xpath->query ('IMAGE', $xml_page);
	foreach($images as $image)
	{
		// coordinates
		if ($image->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $image->attributes; 
			
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
		}
		
		// ignore block x=0, y=0 as this is the whole page(?)
		if (($attributes['x'] != 0) && ($attributes['y'] != 0))
		{
		
			// save
			$image_obj = new stdclass;
			$image_obj->bbox = new BBox(
			$attributes['x'], 
			$attributes['y'],
			$attributes['x'] + $attributes['width'],
			$attributes['y'] + $attributes['height']
			);
		
			$image_obj->href = $attributes['href'];
		
			$page->images[] = $image_obj;
		}		
	}

	// text from born native PDF ---------------------------------------------------------
	
	// Get blocks	
	$line_counter = 0; // global line counter
	$blocks = $xpath->query ('BLOCK', $xml_page);
	foreach($blocks as $block)
	{
		$b = new stdclass;
		$b->type = 'block';
		$b->bbox = new BBox($page->width, $page->height, 0, 0); 
		$b->tokens = array();
		$b->text_strings = array();
		
		// Get lines of text
		$lines = $xpath->query ('TEXT', $block);
		
		foreach($lines as $line)
		{
			// coordinates
			if ($line->hasAttributes()) 
			{ 
				$attributes = array();
				$attrs = $line->attributes; 
		
				foreach ($attrs as $i => $attr)
				{
					$attributes[$attr->name] = $attr->value; 
				}
			}
	
			$text = new stdclass;
			$text->type = 'text';
	
			$text->id = $line_counter++;
	
			$text->bbox = new BBox(
				$attributes['x'], 
				$attributes['y'],
				$attributes['x'] + $attributes['width'],
				$attributes['y'] + $attributes['height']
				);
		
			$b->bbox->merge($text->bbox);
	
			// text	
			$text->tokens = array();

			$nc = $xpath->query ('TOKEN', $line);
				
			foreach($nc as $n)
			{
				// coordinates
				if ($n->hasAttributes()) 
				{ 
					$attributes = array();
					$attrs = $n->attributes; 
			
					foreach ($attrs as $i => $attr)
					{
						$attributes[$attr->name] = $attr->value; 
					}
				}
		
				$token = new stdclass;
				$token->type = 'token';
				
				$token->bbox = new BBox(
					$attributes['x'], 
					$attributes['y'],
					$attributes['x'] + $attributes['width'],
					$attributes['y'] + $attributes['height']
					);				
				
				$token->bold 		= $attributes['bold'] == 'yes' ? true : false;
				$token->italic		= $attributes['italic'] == 'yes' ? true : false;
				$token->font_size 	= $attributes['font-size'];
				$token->font_name 	= $attributes['font-name'];			
				$token->text 		= $n->firstChild->nodeValue;
				
				$token->rotation 	= $attributes['rotation'] == '1' ? true : false;
				$token->angle 		= $attributes['angle'];
						
				$text->tokens[] = $token;	
				$b->tokens[] = $token;		
				
				$b->text_strings[] = $token->text;
			}	
			
		}
		
		$page->text_bbox->merge($b->bbox);	
		$b->text = join(' ', $b->text_strings);
		unset($b->text_strings);
		
		
		// $segments[] = $b->text;
		
		$page->blocks[] = $b;
	}
	

	$obj->pages[] = $page;
}


//print_r($obj);

// print_r($segments);

file_put_contents($output_filename, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));


?>
