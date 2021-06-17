# Markup and annotation of PDF files

Exploring markup and annotation of PDFs, both born digital and OCR.


## Scripts

- ./pdftoxml/pdftoxml -blocks $<	 (PDF to XML)
- php pdfXmlToJson.php $<	 (XML to simple JSON)
- php jsonToHtml.php $<	 (JSON to HTML with coordinates embedded in JSON) 
- php jsonToHtmlLayout.php $<	(JSON to HTML that matches PDF layout as closely as possible, figures embedded as separate images)
- php pdfToImages.php $<	 (extract page images from PDF, overlay text as transparent layer)

There is a `Makefile` that will run these scripts for all PDFs in this folder.

![](https://github.com/rdmpage/pdf-markup/raw/main/makefile.png)

`pdfXmlToJson.php` takes the XML output from `pdftoxml` and converts it a JSON format that describes the tokens and their location on the page. This JSON can then be used to generate other outputs, mostly useful for checking that the PDF extraction has worked as expected. `jsonToHtmlLayout.php` makes a weak attempt to output HTML that is close to the original PDF, `pdfToImages.php` tends to look better by outputting each page as an image and overlaying text on that.


## pdf.js with hypothesis

To view a local PDF: `http://localhost/~rpage/pdf-markup/pdf.js-hypothes.is/viewer/web/viewer.html?file=..%2F..%2F..%2Fnew_species_of_eriocaulon_eriocaulaceae_from_the_southern_western_ghats_of_kerala_india.pdf`


