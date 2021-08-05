<?php

error_reporting(E_ALL);

// Convert ABBYY XML to OCR JSON

require_once (dirname(__FILE__) . '/spatial.php');

/*
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
*/

$filename = 'acta-arachnologica-57-001-043-045_abbyy.xml';
$output_filename = basename($filename, '.xml') . '.json';


$obj = new stdclass;

$obj = new stdclass;
$obj->pages = array();


$xml = file_get_contents($filename);
				
$dom = new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

$xpath->registerNamespace('abbyy', 'http://www.abbyy.com/FineReader_xml/FineReader10-schema-v1.xml');

$pages = $xpath->query ('//abbyy:page');
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
	
	$page->dpi = $attributes['resolution'];	
	
	$page->bbox = new BBox(0, 0, $page->width, $page->height);
	$page->text_bbox = new BBox($page->width, $page->height, 0, 0);
	
	$line_counter = 0; // global line counter
	
	$blocks = $xpath->query ('abbyy:block', $xml_page);
	foreach($blocks as $block)
	{
	
		// attributes
		if ($block->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $block->attributes; 
		
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
		}
	

		// what type of block?
		switch ($attributes['blockType'])
		{
			case 'Picture':
				$block->type = 'image';
				break;
		
			case 'Table':
				$block->type = 'table';
				break;		
		
			case 'Text':
			default:
				$block->type = 'text';
				break;
		}
		
		// images
		if ($block->type == 'image')
		{
			$image_obj = new stdclass;
			$image_obj->bbox = new BBox(
			$attributes['l'], 
			$attributes['t'],
			$attributes['r'],
			$attributes['b']
			);
		
			$page->images[] = $image_obj;
		
		}
		
		
		if ($block->type == 'table')
		{
			// huh?
		}

		if ($block->type == 'text')
		{		
			$b = new stdclass;
			$b->type = 'block';
			$b->bbox = new BBox($page->width, $page->height, 0, 0); 
			$b->tokens = array();
			$b->text_strings = array();
			
			// Get lines of text
			$lines = $xpath->query ('abbyy:text/abbyy:par/abbyy:line', $block);
		
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
					$attributes['l'], 
					$attributes['t'],
					$attributes['r'],
					$attributes['b']
					);
		
				$b->bbox->merge($text->bbox);
				
				// text
				$text->tokens = array();
								
				$formattings = $xpath->query ('abbyy:formatting', $line);
				foreach($formattings as $formatting)
				{
				
					if ($formatting->hasAttributes()) 
					{ 
						$attributes = array();
						$attrs = $formatting->attributes; 
			
						foreach ($attrs as $i => $attr)
						{
							$attributes[$attr->name] = $attr->value; 
						}
					}
				
					$bold 		= isset($attributes['bold']);
					$italic 	= isset($attributes['italic']);
					$font_size 	= $attributes['fs'];
					$font_name 	= $attributes['ff'];
					
					// pts to pixels
					$font_size *= $page->dpi / 72; 
				
					$nc = $xpath->query ('abbyy:charParams', $formatting);
					
					$token = null;
					
					$word = array();
					
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
						
						$char_box = new BBox(
							$attributes['l'], 
							$attributes['t'],
							$attributes['r'],
							$attributes['b']
							);			
					
						// If no token create one
						if ($token == null)					
						{
							$token = new stdclass;
							$token->type = 'token';
				
							$token->bbox = new BBox($page->width, $page->height, 0, 0); 			
				
							$token->bold 		= $bold;
							$token->italic		= $italic;
							$token->font_size 	= $font_size;
							$token->font_name 	= $font_name;	
							
							$token->word = array();								
						}
						
						$char = $n->firstChild->nodeValue;
												
						if ($char == ' ' && $token)
						{
							// if space then we have finished a word
						
							$token->text = join('', $token->word);
							$text->tokens[] = $token;	
							$b->tokens[] = $token;		
				
							$b->text_strings[] = $token->text;
						
							$token = null;
						
						
						}
						else
						{
							// grow word and bounding box
							$token->word[] = $char;
							$token->bbox->merge($char_box);
						}
						
					}
					
					if ($token)
					{
							$token->text = join('', $token->word);
							$text->tokens[] = $token;	
							$b->tokens[] = $token;		
				
							$b->text_strings[] = $token->text;
						
							$token = null;
					
					}
						
						
					
				
					/*
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
							$attributes['l'], 
							$attributes['t'],
							$attributes['r'],
							$attributes['b']
							);				
				
						$token->bold 		= $bold;
						$token->italic		= $italic;
						$token->font_size 	= $font_size;
						$token->font_name 	= $font_name;			
						$token->text 		= $n->firstChild->nodeValue;
				
						//$token->rotation 	= $attributes['rotation'] == '1' ? true : false;
						//$token->angle 		= $attributes['angle'];
						
						$text->tokens[] = $token;	
						$b->tokens[] = $token;		
				
						$b->text_strings[] = $token->text;
					}	
					*/				
				
				}
			
			}
			
			// Grow the page bounding box
			$page->text_bbox->merge($b->bbox);
		
			// Get text for this block and cleanup
			$b->text = join(' ', $b->text_strings);
			unset($b->text_strings);			
			
			// Add block to this page
			$page->blocks[] = $b;
		
		
/*
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
		
		// Grow the page bounding box
		$page->text_bbox->merge($b->bbox);
		
		// Get text for this block and cleanup
		$b->text = join(' ', $b->text_strings);
		unset($b->text_strings);
		
		// Add block to this page
		$page->blocks[] = $b;
*/					
		}
	
	
	}	
	
	$obj->pages[] = $page;
}

print_r($obj);

file_put_contents($output_filename, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));



?>