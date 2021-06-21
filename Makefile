
# https://stackoverflow.com/a/51081949
# https://stackoverflow.com/a/36143344/9684

PDFFILES    := $(wildcard *.pdf)
XMLFILES    := $(PDFFILES:.pdf=.xml)
JSONFILES   := $(XMLFILES:.xml=.json)
HTMFILES    := $(JSONFILES:.json=.htm)
HTMLFILES   := $(JSONFILES:.json=.html)
#IMAGEFILES  := $(PDFFILES:.pdf=.images.html)
OBJFILES	:= $(XMLFILES) $(JSONFILES) $(HTMFILES) $(HTMLFILES) $(IMAGEFILES)

.PHONY: all
all: $(OBJFILES)

$(XMLFILES): %.xml: %.pdf
	./pdftoxml/pdftoxml -blocks $<	

$(JSONFILES): %.json: %.xml
	php pdfXmlToJson.php $<	

$(HTMFILES): %.htm: %.json
	php jsonToHtml.php $<	
	
$(HTMLFILES): %.html: %.json
	php jsonToHtmlLayout.php $<		

#$(IMAGEFILES): %.images.html: %.pdf
#	php pdfToImages.php $<		

clean:
	rm -f *.htm
	#rm -f *.html
	rm -f *.shtml
	rm -f *.images.html		
	rm -f *.json
	rm -f *.txt
	rm -f *.xml
	rm -rf *.xml_data
	
	
        