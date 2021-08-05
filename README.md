# Markup and annotation of PDF files

Exploring markup and annotation of PDFs, both born digital and OCR.


## Scripts

- `./pdftoxml/pdftoxml -blocks $<`	 (PDF to XML)
- `php pdfXmlToJson.php $<`	 (XML to simple JSON)
- `php jsonToHtml.php $<	` (JSON to HTML with coordinates embedded in JSON) 
- `php jsonToHtmlLayout.php $<`	(JSON to HTML that matches PDF layout as closely as possible, figures embedded as separate images)
- `php pdfToImages.php $<` (extract page images from PDF, overlay text as transparent layer)

There is a `Makefile` that will run these scripts for all PDFs in this folder.

![](https://github.com/rdmpage/pdf-markup/raw/main/images/makefile.png)

`pdfXmlToJson.php` takes the XML output from `pdftoxml` and converts it a JSON format that describes the tokens and their location on the page. This JSON can then be used to generate other outputs, mostly useful for checking that the PDF extraction has worked as expected. `jsonToHtmlLayout.php` makes a weak attempt to output HTML that is close to the original PDF, `pdfToImages.php` tends to look better by outputting each page as an image and overlaying text on that.

## Markup

My approach to markup is to do the following:

1. Convert PDF to JSON
2. Generate a simple HTML document from that JSON where each block of text in the PDF is a paragraph (`<p>`) element.
3. Parse that HTML and convert it into a data structure closely modelled on that developed by [Substance](https://substance.io). This treats all markup as annotation, including formatting such as **bold** and *italics*. These annotations are attached to the corresponding node (e.g., a paragraph) and record the start and end of the span of text that they apply too. Once the data structure is assembled, we can traverse it and regenerate the input HTML.
4. In assembling the data structure, once we have processed a paragraph we have removed any markup and are left with just the text, in which we can now look for entities. Each entity found can itself be added as an annotation, which could be stored separately, or embedded in the output HTML.

The goal here is not to faithfully represent the layout of the original document, nor necessarily reflect its logical structure (stray things like headings, page numbers, etc. may be included in the body of the text), but to provide a way to add annotations while still preserving a original markup (e.g., italics which can be useful to flag entities such as taxa). Because we can regenerate the input HTML together with markup for the annotations (such as highlighting them) this provides a simple way to debug tools used to extract entities.


## Making annotations



## Outputting annotations

We need to be able to output annotations in various formats, and also attach them to various representations of the source document.

### PDF

We can add annotations to a PDF using “pdfmarks” and GhostScript (see [Applying pdfmark To PDF Documents Using GhostScript](https://thechriskent.com/2017/02/13/applying-pdfmark-to-pdf-documents-using-ghostscript/) for a gentle introduction). More details are given in Adobe’s documentation, [Cooking up Enhanced PDF with pdfmark Recipes](http://www.meadowmead.com/wp-content/uploads/2011/04/PDFMarkRecipes.pdf), and [Post-Processing PDFs with Ghostscript](https://www.lexjansen.com/phuse/2018/ad/AD07.pdf) (copies are in the `reading` folder). 

To add a highlight we can use a pdfmark like:

```
[
/SrcPg 7
/Rect[186.746 466.16591 269.6104 475.056]
/Type /Annot
/Subtype /Highlight
/Color [1 1 0]
/F 4
/QuadPoints [186.746 475.056 231.4064 475.056 186.746 466.16591 231.4064 466.16591 233.69 475.056 269.6104 475.056 233.69 466.16591 269.6104 466.16591]
/ANN pdfmark

```

The coordinates are w.r.t. the PDF origin which places (0,0) at the bottom left of the document. Documentation on QuadPoints was inconsistent, and Adobe’s reference is incorrect. QuadPoints comprises one or more set of eight points, representing a rectangle. The order of the points is TopLeft, TopRight, BottomLeft, BottomRight. 

Given a file with one or more pdfmarks we can add them to the source PDF using this command:


```
gs -sDEVICE=pdfwrite -sOutputFile=output.pdf -dPDFSETTINGS=/prepress -dNOPAUSE -dBATCH source.pdf pdfmarks.txt
```

The `-dNOPAUSE -dBATCH` flags mean the command runs entirely in batch mode, otherwise the user is prompted to process each page.


## PDF.JS

The version in this repository has a tweak to the `viewer.html` file:

```
<script type="text/javascript">
  document.addEventListener("pagechange", function(e) {
  	window.parent.postMessage(e.pageNumber, "*");
  });
</script>
```

This means we send `pagechange` messages to the document element. We can add a listener to the main document to respond to page changes. Hence we can have a simple PDF viewer with a sidebar where we could display annotations.

## Viewing a single page with pdf.js

Using version 1.4.20 build b15f335 of `pdf.js` I’m experimenting with code to render a single PDF page. This could be used, for example, to display a BHL page that has a taxonomic name. If a highlight annotation is available in SVG we can render that on top of the PDF page, so we have an annotation without modifying the PDF. Although this looses context (the BHL page is isolated from its original item) it makes it simple to display a BHL page with an annotation.



## Hypothes.is

### pdf.js with hypothesis

To view a local PDF: `http://localhost/~rpage/pdf-markup/pdf.js-hypothes.is/viewer/web/viewer.html?file=..%2F..%2F..%2Fnew_species_of_eriocaulon_eriocaulaceae_from_the_southern_western_ghats_of_kerala_india.pdf`


## Annotation formats

See [Format.html](https://www.ncbi.nlm.nih.gov/CBBresearch/Lu/Demo/tmTools/Format.html) for a list, including [PubAnnotation](http://pubannotation.org) and BioC.

### PubAnnotation

[PubAnnotation](http://www.pubannotation.org/docs/annotation-format/) looks like a simple format aimed at biomedical entities in PMC, and comes with nice ways to visualise terms and their connections [TextAE](http://textae.pubannotation.org).

### PubTator

[PubTator](https://www.ncbi.nlm.nih.gov/research/pubtator/) is an NLM tool with an attractive interface. It uses the BioC format which treats the document as a series of text blocks.

