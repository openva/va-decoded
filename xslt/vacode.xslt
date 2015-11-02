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

			<!--Weirdly, this isn't recursing. Weirder, it's getting the most deeply-
				nested element rather than just the first one. -->
			<structure>
				<xsl:for-each select="metadata/hierarchy">
					<xsl:apply-templates select="hierarchyLevel"/>
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
				
				<!-- To do: This needs to recurse, nesting levels! Right now, we're just
				picking two levels deep as the place to work, but of course that's not
				how it needs to work.-->
				<xsl:for-each select="legislativeDocBody/statute/level/level">
					
					<section>
						<xsl:attribute name="prefix">
							<!--Strip off the trailing period of each heading.
								(Strictly speaking, this is removing ALL periods.)-->
							<xsl:value-of select="translate(heading/desig, '.', '')" />
						</xsl:attribute>
						
						<!--To do: Despite stripping off leading and trailing text,
						we're still getting a single leading space prior to each
						section. Figure out why.-->

						<!--To do: Multi-paragraph laws without subsections, or
						muti-paragraph subsections, are being combined into one, long
						string, with no spaces at all separating where they've been
						joined. This is because of <xsl:strip-space> in the head, which
						we need to keep subsections from being lead and trailed with vast
						seas of space. Figure out how to reconcile these two problems.-->
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
				<xsl:value-of select="@levelType" />
			</xsl:attribute>

			<xsl:attribute name="identifier">
				<xsl:value-of select="heading/desig" />
			</xsl:attribute>

      		<!-- Counter -->
			<xsl:attribute name="level">
			  <xsl:value-of select="count(ancestor::hierarchyLevel) + 1"/>
			</xsl:attribute>

			<xsl:value-of select="heading/title"/>
		
		</unit>

		<xsl:if test="hierarchyLevel">
  		<xsl:apply-templates select="hierarchyLevel"/>
		</xsl:if>
		
	</xsl:template>
	
</xsl:stylesheet>
