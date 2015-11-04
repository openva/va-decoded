<?xml version="1.0" encoding="UTF-8" ?>
			
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:orig="http://StatRev.xsd"
	xmlns:fn="http://localhost/"  xmlns:xs="http://www.w3.org/2001/XMLSchema" >

	<xsl:strip-space elements="*" />
	
	<xsl:output
			method="xml"
			version="1.0"
			encoding="utf-8"
			omit-xml-declaration="no"
			indent="yes"
			media-type="text/xml"/>
	
	<xsl:template match="text()">
		<xsl:value-of select="normalize-space()" />
	</xsl:template>

	<!--Start processing at the top-level element.-->
	<xsl:template match="legislativeDoc">
		<law>

			<!--Weirdly, this isn't recursing. Weirder, it's getting the most deeply-
				nested element rather than just the first one. -->
			<structure>
				<xsl:for-each select="metadata/hierarchy">
					<xsl:apply-templates select="hierarchyLevel"/>
				</xsl:for-each>
			</structure>
			
			<!--Strip out the leading "§ " and the trailing period.-->
			<xsl:variable name="section-number" select="translate(legislativeDocBody/statute/level/heading/desig, '§ ', '')"/>
			<xsl:variable name="section-number-length" select="string-length($section-number)"/>
			<section_number><xsl:value-of select="substring($section-number, 1, ($section-number-length - 1))" /></section_number>

			<!--Include the catch line.-->
			<catch_line><xsl:value-of select="legislativeDocBody/statute/level/heading/title" /></catch_line>
			
			<history><xsl:value-of select="legislativeDocBody/statute/level/history/historyGroup/historyItem/bodyText" /></history>
			
			<text>
				
				<xsl:for-each select="legislativeDocBody/statute/level/level">
					
					<section>
						<xsl:attribute name="prefix">
							<xsl:value-of select="translate(heading/desig, '.', '')" />
						</xsl:attribute>
						<xsl:value-of select="bodyText" />
						
					</section>
					
				</xsl:for-each>
			</text>

		</law>
		
	</xsl:template>

	<!-- A template to recurse through structural hieirarchies. -->	
	<xsl:template match="hierarchyLevel">
		<unit>
		
			<xsl:attribute name="label">
				<xsl:value-of select="@levelType"/>
			</xsl:attribute>

			<xsl:attribute name="identifier"><xsl:value-of select="replace(replace(normalize-space(heading/desig), '^(TITLE|SUBTITLE|ARTICLE|CHAPTER|PART) ', '' ), '.$', '')"/>

		</xsl:attribute>

			<!-- Counter -->
			<xsl:attribute name="level">
			  <xsl:value-of select="count(ancestor::hierarchyLevel) + 1"/>
			</xsl:attribute>

			<xsl:value-of select="fn:capitalize_phrase(heading/title)"/>
		
		</unit>

		<xsl:if test="hierarchyLevel">
  		<xsl:apply-templates select="hierarchyLevel"/>
		</xsl:if>
		
	</xsl:template>

	<xsl:function name="fn:capitalize_word">
		<xsl:param name="word"  as="xs:string" />
		<xsl:value-of select="concat( upper-case(substring( $word, 1, 1 )), lower-case(substring($word,2)) )" />
	</xsl:function>

	<xsl:function name="fn:capitalize_phrase">
		<xsl:param name="phrase" as="xs:string" />
		<xsl:variable name="tokens">
		<xsl:for-each select="tokenize( normalize-space($phrase), ' ' )">
			<xsl:value-of select="concat(fn:capitalize_word(.), ' ')"/>
		</xsl:for-each>
		</xsl:variable>
		<xsl:value-of select="substring(string($tokens),1,string-length(string($tokens))-1)"/>
	</xsl:function>

</xsl:stylesheet>
