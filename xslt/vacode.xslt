<?xml version="1.0" encoding="UTF-8" ?>
			
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:orig="http://StatRev.xsd">

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

	<!--Start processing at the top-level element. This will match on the root tag in
		the input document. (The first tag that isn't "<xml>" is the root tag.) For
		example, if the match is set for the fourth tag in the document, nothing in the
		second or third tags would make it into the output document. You should almost
		always match on the root element.
	-->
	<xsl:template match="legislativeDoc">

		<law>

			<structure>
				<xsl:for-each select="metadata/hierarchy/hierarchyLevel">
					<xsl:call-template name="hierarchyLevel" />
				</xsl:for-each>
			</structure>
			
			<!-- Strip out the leading "§ " and the trailing period. -->
			<xsl:variable name="section-number" select="translate(legislativeDocBody/statute/level/heading/desig, '§ ', '')"/>
			<xsl:variable name="section-number-length" select="string-length($section-number)"/>
			<section_number><xsl:value-of select="substring($section-number, 0, ($section-number-length - 1))" /></section_number>

			<!--Include the catch line.-->
			<catch_line><xsl:value-of select="legislativeDocBody/statute/level/heading/title" /></catch_line>
			
			<history><xsl:value-of select="legislativeDocBody/statute/level/history/historyGroup/historyItem/bodyText" /></history>
			
			<text>

				<xsl:for-each select="legislativeDocBody/statute/level">
					<xsl:call-template name="bodyTextRecursion" />
				</xsl:for-each>

			</text>

		</law>
		
	</xsl:template>

	<!-- A template to recurse through body text. -->	
	<xsl:template name="bodyTextRecursion">

		<section>

			<!-- Only include the subsection prefix if it's not the section number. -->
			<xsl:if test="not(contains(heading/desig,'§'))">
				<xsl:attribute name="prefix">
					<!--Strip off the trailing period of each heading.
						(Strictly speaking, this is removing ALL periods.)-->
					<xsl:value-of select="translate(heading/desig, '.', '')" />
				</xsl:attribute>
			</xsl:if>

			<!-- Only include body text if we have it. (Some subsections are purely
			structural.) -->
			<xsl:if test="bodyText">
				<xsl:value-of select="bodyText" />
			</xsl:if>
			
		</section>

		<xsl:for-each select="level">
			<xsl:call-template name="bodyTextRecursion" />
		</xsl:for-each>

	</xsl:template>

	<!-- A template to recurse through structural hieirarchies. -->	
	<xsl:template name="hierarchyLevel">
			
		<!-- Start a counter. -->
		<xsl:variable name="counter" select="position()" />
			
		<unit>
		
			<xsl:attribute name="label">
				<xsl:value-of select="@levelType" />
			</xsl:attribute>
	
			<xsl:attribute name="identifier">
				<xsl:value-of select="heading/desig"/>
			</xsl:attribute>
	
			<xsl:attribute name="level">
				<xsl:value-of select="$counter" />
			</xsl:attribute>

			<xsl:value-of select="heading/title"/>
		
		</unit>
		
		<!--<xsl:param name="counter" select="$counter + 1"/>-->
		
		<xsl:for-each select="hierarchyLevel">
			<xsl:call-template name="hierarchyLevel" />
		</xsl:for-each>
		
	</xsl:template>
	
</xsl:stylesheet>
