<?php

error_reporting(E_ALL);

// Convert ABBYY XML to OCR JSON

// extract possible figures

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
$filename = 'biostor-147723_abbyy.xml';
$filename = 'annales-zoologici-47-215-241_abbyy.xml';


$output_filename 	= basename($filename, '.xml') . '.json';

$archive 			= basename($filename, '_abbyy.xml');
$jp2_prefix 		= $archive . '_jp2';



$obj = new stdclass;

$obj = new stdclass;
$obj->pages = array();


$image_counter = 1;

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
			
			$image_obj->href = 'image-' . $image_counter++ . '.jpeg';

			$page->images[] = $image_obj;		
		}
		
		
		if ($block->type == 'table')
		{
			// huh?
			$table_obj = new stdclass;
			$table_obj->bbox = new BBox(
			$attributes['l'], 
			$attributes['t'],
			$attributes['r'],
			$attributes['b']
			);
		
			$page->tables[] = $table_obj;
			
		}

		if ($block->type == 'text')
		{		
			$pars = $xpath->query ('abbyy:text/abbyy:par', $block);
			foreach ($pars as $par)
			{
		
				$b = new stdclass;
				$b->type = 'block';
				$b->bbox = new BBox($page->width, $page->height, 0, 0); 
				$b->tokens = array();
				$b->text_strings = array();
			
				// Get lines of text
				$lines = $xpath->query ('abbyy:line', $par);
		
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
						
							if (0)
							{
								// take coordinates for this character 
								$char_box = new BBox(
									$attributes['l'], 
									$attributes['t'],
									$attributes['r'],
									$attributes['b']
									);	
							}
							else
							{
								// use line top and bottom to ensure smooth display of text
								$char_box = new BBox(
									$attributes['l'], 
									$text->bbox->miny,
									$attributes['r'],
									$text->bbox->maxy
									);			
							}		
					
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
			
				
					}
			
				}
			
				// Grow the page bounding box
				$page->text_bbox->merge($b->bbox);
		
				// Get text for this block and cleanup
				$b->text = join(' ', $b->text_strings);
				unset($b->text_strings);			
			
				// Add block to this page
				$page->blocks[] = $b;
			}
		}
	
	
	}	
	
	$obj->pages[] = $page;
}

print_r($obj);

file_put_contents($output_filename, json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// extract images

$n = count($obj->pages);
for ($i = 0; $i < $n; $i++)
{
	$page_number = $i + 1;
	
	if (isset($obj->pages[$i]->images))
	{
		foreach ($obj->pages[$i]->images as $image)
		{
			$fragment = 
				($image->bbox->maxx - $image->bbox->minx) 
				. 'x' 
				. ($image->bbox->maxy - $image->bbox->miny) 				
				. '+'
				. $image->bbox->minx
				. '+'
				. $image->bbox->miny;
				
		
			$jp2_filename = $jp2_prefix . '/' . $archive . '_' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.jp2';

			$image_filename  = $image->href;

			$command = 'convert -extract ' . $fragment . ' ' . $jp2_filename . ' ' . $image_filename;
			echo $command . "\n";
			
			system($command);
		
		}
	
	
	}

}


?>