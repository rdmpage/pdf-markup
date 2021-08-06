<?xml version="1.0"?>
<xsl:stylesheet xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:abbyy="http://www.abbyy.com/FineReader_xml/FineReader10-schema-v1.xml" version="1.0">
	<xsl:output encoding="utf-8" indent="yes" method="html" version="1.0"/>
	
	<xsl:variable name="scale" select="0.2" />
	
	<xsl:template match="/">

		<xsl:apply-templates select="//abbyy:page" />
	
	</xsl:template>
		
	<xsl:template match="abbyy:page">		
	
		<div>

	<xsl:attribute name="style">
		<xsl:text>position:relative;</xsl:text>
		<xsl:text>border:1px solid rgb(192,192,192);</xsl:text>
		<xsl:variable name="height" select="@height" />
		<xsl:variable name="width" select="@width" />
		<xsl:text>width:</xsl:text><xsl:value-of select="$width * $scale" /><xsl:text>px;</xsl:text>
		<xsl:text>height:</xsl:text><xsl:value-of select="$height * $scale" /><xsl:text>px;</xsl:text>
	</xsl:attribute>
	
	<!--
		<img src="26245998.png">
			<xsl:attribute name="width">
				<xsl:value-of select="@width * $scale" />
			</xsl:attribute>
			<xsl:attribute name="height">
				<xsl:value-of select="@height * $scale" />
			</xsl:attribute>
		</img>
	-->

	
		<xsl:apply-templates select="abbyy:block" />
	
	</div>
	</xsl:template>
	
	<xsl:template match="abbyy:block">	
		<xsl:choose>
			<xsl:when test="@blockType='Text'">
				<!--
				<div>
					<xsl:attribute name="style">
						<xsl:text>position:absolute;</xsl:text>
						<xsl:text>opacity:0.4;</xsl:text>
						<xsl:text>background-color:green;</xsl:text>
						<xsl:text>left:</xsl:text><xsl:value-of select="@l * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>top:</xsl:text><xsl:value-of select="@t * $scale" /><xsl:text>px;</xsl:text>
						<xsl:variable name="height" select="@b - @t" />
						<xsl:variable name="width" select="@r - @l" />
						<xsl:text>width:</xsl:text><xsl:value-of select="$width * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>height:</xsl:text><xsl:value-of select="$height * $scale" /><xsl:text>px;</xsl:text>
					</xsl:attribute>
					
					<xsl:apply-templates select="abbyy:text/abbyy:par" />
					
					
				</div> -->
				
				<xsl:apply-templates select="abbyy:text/abbyy:par" /> 
				
			</xsl:when>
			
			<xsl:when test="@blockType='Picture'">
				<div>
					<xsl:attribute name="style">
						<xsl:text>position:absolute;</xsl:text>
						<xsl:text>opacity:0.4;</xsl:text>
						<xsl:text>background-color:pink;</xsl:text>
						<xsl:text>left:</xsl:text><xsl:value-of select="@l * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>top:</xsl:text><xsl:value-of select="@t * $scale" /><xsl:text>px;</xsl:text>
						<xsl:variable name="height" select="@b - @t" />
						<xsl:variable name="width" select="@r - @l" />
						<xsl:text>width:</xsl:text><xsl:value-of select="$width * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>height:</xsl:text><xsl:value-of select="$height * $scale" /><xsl:text>px;</xsl:text>
					</xsl:attribute>
				</div>
			</xsl:when>
			
			<xsl:when test="@blockType='Table'">
				<div>
					<xsl:attribute name="style">
						<xsl:text>position:absolute;</xsl:text>
						<xsl:text>opacity:0.4;</xsl:text>
						<xsl:text>background-color:yellow;</xsl:text>
						<xsl:text>left:</xsl:text><xsl:value-of select="@l * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>top:</xsl:text><xsl:value-of select="@t * $scale" /><xsl:text>px;</xsl:text>
						<xsl:variable name="height" select="@b - @t" />
						<xsl:variable name="width" select="@r - @l" />
						<xsl:text>width:</xsl:text><xsl:value-of select="$width * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>height:</xsl:text><xsl:value-of select="$height * $scale" /><xsl:text>px;</xsl:text>
					</xsl:attribute>
				</div>
			</xsl:when>
			
			
			
			<xsl:otherwise>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	

	<xsl:template match="abbyy:par">	
		<xsl:apply-templates select="abbyy:line" />
	</xsl:template>
	
	<xsl:template match="abbyy:line">	
				<div>
					<xsl:attribute name="style">
						<xsl:text>position:absolute;</xsl:text>
						<xsl:text>opacity:0.4;</xsl:text>
						<xsl:text>border:1px solid rgb(192,192,192);</xsl:text>
						<xsl:text>background-color:green;</xsl:text>
						<xsl:text>left:</xsl:text><xsl:value-of select="@l * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>top:</xsl:text><xsl:value-of select="@t * $scale" /><xsl:text>px;</xsl:text>
						<xsl:variable name="height" select="@b - @t" />
						<xsl:variable name="width" select="@r - @l" />
						<xsl:text>width:</xsl:text><xsl:value-of select="$width * $scale" /><xsl:text>px;</xsl:text>
						<xsl:text>height:</xsl:text><xsl:value-of select="$height * $scale" /><xsl:text>px;</xsl:text>
					</xsl:attribute>
					
				</div>

	</xsl:template>
	
	
	
</xsl:stylesheet>