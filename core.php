<?php

error_reporting(E_ALL);

// Process a simple HTML document and add annotations
// Heavily based on early version of substance.io, where we represent the document
// as a JSON tree, and any markup is stored as an annotation. We support only basic
// tags, such as <div> or <section> (representing a page) and <p> (a block).
//


date_default_timezone_set('Europe/London');
mb_internal_encoding("UTF-8");


define ('WHITESPACE_CHARS', ' \f\n\r\t\x{00a0}\x{0020}\x{1680}\x{180e}\x{2028}\x{2029}\x{2000}\x{2001}\x{2002}\x{2003}\x{2004}\x{2005}\x{2006}\x{2007}\x{2008}\x{2009}\x{200a}\x{202f}\x{205f}\x{3000}');


//----------------------------------------------------------------------------------------
// Clean up text so that we have single spaces between text, 
// see https://github.com/readmill/API/wiki/Highlight-locators
function clean_text($text)
{
	
	$text = preg_replace('/[' . WHITESPACE_CHARS . ']+/u', ' ', $text);
	
	return $text;
}

//----------------------------------------------------------------------------------------
// Load possibly ropey HTML into DOM
function html_to_dom($html)
{
	// http://stackoverflow.com/a/2671410/9684
	$html = mb_convert_encoding($html, 'utf-8', mb_detect_encoding($html));

	// if you have not escaped entities use
	$html = mb_convert_encoding($html, 'html-entities', 'utf-8'); 

	$dom = new DOMDocument('1.0', 'UTF-8');

	// http://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags
	libxml_use_internal_errors(true);
	$dom->loadHTML($html);
	libxml_clear_errors();
	
	return $dom;
}

//----------------------------------------------------------------------------------------
// create object to hold parsed document
function create_document()
{
	$document = new stdclass;
	$document->nodes = new stdclass;

	// house keeping
	$document->counter = 0;
	$document->node_type_counter = array();
	$document->current_paragraph_node = null;
	$document->current_text_node = null;
	$document->current_page_node = null;

	//$document->italic_strings = array();
	
	return $document;
}

//----------------------------------------------------------------------------------------
// cleanup document
function cleanup_document(&$document)
{
	// remove housekeeping
	unset($document->counter);
	unset($document->node_type_counter);
	unset($document->current_paragraph_node);
	unset($document->current_text_node);
	unset($document->current_page_node);
	unset($document->current_node);
}

//----------------------------------------------------------------------------------------
// Add an annotation 
function new_annotation(&$document, $type, $store = true)
{
	if (!isset($document->node_type_counter[$type]))
	{
		$document->node_type_counter[$type] = 0;
	}
	$document->node_type_counter[$type]++;
	$id = $type . "_" . $document->node_type_counter[$type];
	$document->nodes->{$id} = new stdclass;
	$document->nodes->{$id}->type = $type;
	$document->nodes->{$id}->id = $id;
	
	$document->nodes->{$id}->range = array();
	$document->nodes->{$id}->range[0] = $document->counter;
	
	$document->nodes->{$id}->path = array();
	$document->nodes->{$id}->path[0] = $document->current_paragraph_node->id;
	$document->nodes->{$id}->path[1] = 'content';
	
	if ($store)
	{
		$document->current_node[] = $document->nodes->{$id};
	}
	
	return $document->nodes->{$id};
}

//--------------------------------------------------------------------------------------------------
// Store text span that annotation applies to
function add_annotation(&$document, $annotation)
{
	if (!isset($document->current_paragraph_node->open_annotation[$annotation->range[0]]))
	{
		$document->current_paragraph_node->open_annotation[$annotation->range[0]] = array();
	}
	
	if (!isset($document->current_paragraph_node->open_annotation[$annotation->range[0]][$annotation->range[1]]))
	{
		$document->current_paragraph_node->open_annotation[$annotation->range[0]][$annotation->range[1]] = array();
	}
	$document->current_paragraph_node->open_annotation[$annotation->range[0]][$annotation->range[1]][] = $annotation->id;

	krsort($document->current_paragraph_node->open_annotation[$annotation->range[0]], SORT_NUMERIC);
	
	asort($document->current_paragraph_node->open_annotation[$annotation->range[0]][$annotation->range[1]]);
	
	if (!isset($document->current_paragraph_node->close_annotation[$annotation->range[1]]))
	{
		$document->current_paragraph_node->close_annotation[$annotation->range[1]] = array();
	}
	
	if (!isset($document->current_paragraph_node->close_annotation[$annotation->range[1]][$annotation->range[0]]))
	{
		$document->current_paragraph_node->close_annotation[$annotation->range[1]][$annotation->range[0]] = array();
	}	
	
	$document->current_paragraph_node->close_annotation[$annotation->range[1]][$annotation->range[0]][] = $annotation->id;
	
	ksort($document->current_paragraph_node->close_annotation[$annotation->range[1]], SORT_NUMERIC);
	
	arsort($document->current_paragraph_node->close_annotation[$annotation->range[1]][$annotation->range[0]]);	
}



//--------------------------------------------------------------------------------------------------
// A page, which may contain the entire article, or a single page
function create_page_node(&$document)
{
	if (!isset($document->node_type_counter['page']))
	{
		$document->node_type_counter['page'] = 0;
	}
	$document->node_type_counter['page']++;
	
	$id = 'page_' . $document->node_type_counter['page'];
	$document->nodes->{$id} = new stdclass;
	$document->nodes->{$id}->type = 'page';
	$document->nodes->{$id}->id = $id;	
	$document->nodes->{$id}->children = array();
		
	$document->current_page_node = $document->nodes->{$id};
}

//--------------------------------------------------------------------------------------------------
// Recursively traverse DOM and process tags
function dive($node, &$document, $callback_func = null )
{
	switch ($node->nodeName)
	{
		//case 'div':
		case 'section':
			// pages 
			create_page_node($document);
			break;
	
		case 'p':
			if (!isset($document->node_type_counter['p']))
			{
				$document->node_type_counter['p'] = 0;
			}
			$document->node_type_counter['p']++;
			
			$document->counter = 0;
			
			$id = 'paragraph_' . $document->node_type_counter['p'];
			$document->nodes->{$id} = new stdclass;
			$document->nodes->{$id}->type = 'paragraph';
			$document->nodes->{$id}->id = $id;
			$document->nodes->{$id}->children=array();
			$document->nodes->{$id}->content = '';
						
			// HTML attributes
			if ($node->hasAttributes()) 
			{ 
				$attributes = $node->attributes; 
				
				foreach ($attributes as $attribute)
				{
					switch ($attribute->name)
					{
						case 'style':
							$document->nodes->{$id}->style = $attribute->value;
							break;
							
						default:
							break;
					}
				}
			}			
			
			// support for annotations
			$document->nodes->{$id}->open_annotation = array();
			$document->nodes->{$id}->close_annotation = array();			
			
			$document->current_node[] = $document->nodes->{$id};
			$document->current_paragraph_node = $document->nodes->{$id};
			
			// add paragraph to current page
			if (!$document->current_page_node)
			{
				create_page_node($document);
			}
			
			$document->current_page_node->children[] = $id;
			break;
			
		case 'img':
			if (!isset($document->node_type_counter['figure']))
			{
				$document->node_type_counter['figure'] = 0;
			}
			$document->node_type_counter['figure']++;
			
			$id = 'figure_' . $document->node_type_counter['figure'];
			$document->nodes->{$id} = new stdclass;
			$document->nodes->{$id}->type = 'figure';
			$document->nodes->{$id}->id = $id;
			
			// HTML attributes
			if ($node->hasAttributes()) 
			{ 
				$attributes = $node->attributes; 
				
				foreach ($attributes as $attribute)
				{
					switch ($attribute->name)
					{
						case 'src':
							$document->nodes->{$id}->url = $attribute->value;
							break;
							
						default:
							break;
					}
				}
			}
			break;			
		
		case 'i':
			new_annotation($document, 'emphasis');
			break;
			
		case 'b':
			new_annotation($document, 'strong');
			break;

		case 'br':
			new_annotation($document, 'linebreak');
			break;

		case 'sup':
			new_annotation($document, 'superscript');
			break;
			
		case 'wbr':
			new_annotation($document, 'softhyphen');
			break;	
						
		case '#text':
			// Grab text and clean it up
			if (!isset($document->node_type_counter['text']))
			{
				$document->node_type_counter['text'] = 0;
			}
			$document->node_type_counter['text']++;
			
			$id = 'text_' . $document->node_type_counter['text'];
			$document->nodes->{$id} = new stdclass;
			$document->nodes->{$id}->type = 'text';
			$document->nodes->{$id}->id = $id;		
		
			$content = $node->nodeValue;
			
			// clean text 
			$content = clean_text($content);
			
			// very important!
			$content_length =  mb_strlen($content, mb_detect_encoding($content));
		
			$document->current_paragraph_node->content .= $content;
			$document->counter += $content_length;
			
			// text node
			$document->nodes->{$id}->content = $content;
			$document->current_node[] = $document->nodes->{$id};
			
			$document->current_paragraph_node->children[] = $id;
			break;	
			
		case 'script':
			break;
			
		case '#cdata-section':
			$content = $node->nodeValue;
			$document->current_paragraph_node->data = $content;
			break;
			
		case 'body':
			break;
						
		default:
			// a tag we don't handle, just record for now
			if (!isset($document->node_type_counter['unknown']))
			{
				$document->node_type_counter['unknown'] = 0;
			}
			$document->node_type_counter['unknown']++;
			$id = 'unknown' . $document->node_type_counter['unknown'];
			$document->nodes->{$id} = new stdclass;
			$document->nodes->{$id}->type = 'unknown';
			$document->nodes->{$id}->id = $id;
			$document->nodes->{$id}->name = $node->nodeName;
			
			$document->current_node[] = $document->nodes->{$id};
		
			break;
	}
	
	// Visit any children of this node
	if ($node->hasChildNodes())
	{
		foreach ($node->childNodes as $children) {
			dive($children, $document, $callback_func);
		}
	}
	
	// Leaving this node, any annotations that cover a span of text get closed here
	// This is also the point at which we have all the text for a paragraph node, so
	// do any entity recognistion here
	$n = array_pop($document->current_node);
	
	//print_r($n);
	
	if ($n)
	{
	
		switch ($n->type)
		{
			// handle formatting annotations that span a range of text
			case 'emphasis':
			case 'strong':
			case 'superscript':
				$n->range[1] = max(0, $document->counter - 1);
				$n->path[0] = $document->current_paragraph_node->id;
			
				// These annotations are spans that have open and closing tags
				add_annotation($document, $n);			
				break;
			
			// formatting that is a closed tag with no text content , e.g. <wbr/>
			case 'linebreak':
			case 'softhyphen':
				$n->range[1] = $document->counter;
				$n->path[0] = $document->current_paragraph_node->id;
				add_annotation($document, $n);			
				break;

			case 'paragraph':
				// leaving paragraph node, do any entity recognition here
				if ($callback_func != '')
				{
					$callback_func($document);
				}	
				break;
			
			default:
				break;
		}
	
	}
}

//--------------------------------------------------------------------------------------------------

function to_html($document, $extra = false, $show_italics=true)
{
	$html = '';

	// dump as HTML (ideally should be able to completely reproduce input...)
	foreach ($document->nodes as $doc_node)
	{
		if ($doc_node->type == 'page')
		{
			// $html .= '<div style="border:1px solid red;padding:20px;">';
			$html .= '<section style="border:1px solid black;padding:40px;margin-below:20px;">';
		
			foreach ($doc_node->children as $child_id)
			{
				if ($document->nodes->{$child_id}->type == 'paragraph')
				{
					$node = $document->nodes->{$child_id};
				
					$html .= '<p';
								
					if (isset($node->style))
					{
						$html .= ' style="' . $node->style . '"';
						//echo $node->style;
					}
					//$html .= '"';				
				
					$html .= '>';
				
				
					// walk along text and output annotations and text
					$content_length =  mb_strlen($node->content, mb_detect_encoding($node->content));
				
					for ($i = 0; $i < $content_length; $i++)
					{
						$char = mb_substr($node->content, $i, 1);
					
						if (isset($node->open_annotation[$i]))
						{
							foreach ($node->open_annotation[$i] as $k => $v)
							{
								foreach ($v as $annotation_id)
								{
									switch ($document->nodes->{$annotation_id}->type)
									{
									
										case 'emphasis':
											if ($show_italics)
											{
												$html .= '<i>';
											}
											break;
			
										case 'strong':
											$html .= '<b>';
											break;
			
										case 'superscript':
											$html .= '<sup>';
											break;
											
										case 'linebreak':
											$html .= '<br/>';
											break;
																						
										// extra annotations
										
										case 'highlight':
											if ($extra)
											{
												$html .= '<mark>';
											}
											break;
											
										/*
		
										case 'occurrence':
											if ($extra)
											{
												$html .= '<span style="background-color:#99FFFF">';
											}
											break;
		
										case 'point':
											if ($extra)
											{
												$html .= '<span style="background-color:orange">';
											}
											break;
													
										case 'genbank':
											if ($extra)
											{
												$html .= '<span style="background-color:yellow;">';
											}
											break;
										*/
																					
										
										
										default:
											break;
									}
								}
							}
						}
					
						//echo $char;
						$html .= htmlspecialchars($char, ENT_NOQUOTES | ENT_HTML5);
					
						//if (isset($node->close_annotation[$i]))
						if (isset($node->close_annotation[$i]))
						{
							foreach ($node->close_annotation[$i] as $k => $v)
							{
								foreach ($v as $annotation_id)
								{
									switch ($document->nodes->{$annotation_id}->type)
									{
								
										case 'softhyphen':
											$html .= '&shy;';
											break;
									
										case 'emphasis':
											if ($show_italics)
											{
												$html .= '</i>';
											}
											break;
										
										case 'strong':
											$html .= '</b>';
											break;
										
										case 'superscript':
											$html .= '</sup>';
											break;
																			
										case 'highlight':
											if ($extra)
											{
												$html .= '</mark>';
											}
											break;

										/*
										case 'occurrence':
											if ($extra)
											{
												$html .= '</span>';
											}
											break;
		
										case 'point':
											if ($extra)
											{
												$html .= '</span>';
										
												if (1)
												{
													//echo '<a href="http://maps.google.com/?q=' . $document->nodes->{$annotation_id}->feature->geometry->coordinates[1] . ',' . $document->nodes->{$annotation_id}->feature->geometry->coordinates[0] . '&z=8" target="_new">(Google)</a>';
													$html .= '<a href="http://www.openstreetmap.org/?mlat=' . $document->nodes->{$annotation_id}->feature->geometry->coordinates[1]. '&mlon=' . $document->nodes->{$annotation_id}->feature->geometry->coordinates[0] . '&zoom=8" target="_new">(OSM)</a>';
												}
											}										
											break;										

										case 'genbank':
											if ($extra)
											{
												$html .= '</span>';
											}
											break;
										*/
										
										default:
											break;
									}
								}
							}
						}
					}
				
					$html .= '</p>';
				}
			}
			
			$html .= '</section>';
		}
	}
	
	return $html;
}




?>