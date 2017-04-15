<?xml version="1.0" encoding="UTF-8" ?>
			
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:orig="http://StatRev.xsd"
	xmlns:fn="http://localhost/"  xmlns:xs="http://www.w3.org/2001/XMLSchema" >

	<!-- Strip whitespace from everything except the text of laws. -->
	<xsl:strip-space elements="*" />
	<xsl:preserve-space elements="bodyText" />

	<!-- Don't include any whitespace-only text nodes. -->
	<xsl:strip-space elements="*" />
	
	<xsl:output
			method="xml"
			version="1.0"
			encoding="utf-8"
			omit-xml-declaration="no"
			indent="yes"
			media-type="text/xml"/>

	<!--Start processing at the top-level element.-->
	<xsl:template match="legislativeDoc">
		<law>

			<structure>
				<xsl:for-each select="metadata/hierarchy">
					<xsl:apply-templates select="hierarchyLevel"/>
				</xsl:for-each>
			</structure>
			
			<!--Strip out the leading "_ " and replace any others with a colon.-->
			<xsl:choose>
				<!--If it's included in the anchor.-->
				<xsl:when test="legislativeDocBody/statute/level/anchor">
					<xsl:variable name="section-number" select="translate(legislativeDocBody/statute/level/anchor/@id, '_', ':')" />
					<section_number><xsl:value-of select="substring($section-number, 2)"/></section_number>
				</xsl:when>
				<!--Otherwise we must parse it out of the citation info.-->
				<xsl:otherwise>
					<xsl:analyze-string select="legislativeDocHead/citations/citeForThisResource" regex="§ ?(.*)$">
						 <xsl:matching-substring>
							<section_number><xsl:value-of select="regex-group(1)"/></section_number>
						</xsl:matching-substring>
					</xsl:analyze-string>
				</xsl:otherwise>
			</xsl:choose>

			<!--Include the catch line.-->
			<catch_line><xsl:value-of select="legislativeDocBody/statute/level/heading/title" /></catch_line>
			
			<history><xsl:value-of select="normalize-space(legislativeDocBody/statute/level/history/historyGroup/historyItem/bodyText)" /></history>
			
			<text>
				<xsl:for-each select="legislativeDocBody/statute">
					<xsl:apply-templates select="level"/>
				</xsl:for-each>
			</text>

		</law>
		
	</xsl:template>

	<!-- Recurse through structural hierarchies. -->	
	<xsl:template match="hierarchyLevel">
		<unit>
		
			<xsl:attribute name="label">
				<xsl:value-of select="@levelType"/>
			</xsl:attribute>

			<!-- Counter -->
			<xsl:attribute name="level">
			  <xsl:value-of select="count(ancestor::hierarchyLevel) + 1"/>
			</xsl:attribute>

			<!-- If we have a title, desig is the identifier. Otherwise, the desig is the title. -->
			<xsl:choose>
				<xsl:when test="heading/title">
					<xsl:attribute name="identifier">
						<xsl:value-of select="replace(replace(normalize-space(heading/desig), '^(TITLE|SUBTITLE|ARTICLE|CHAPTER|SUBCHAPTER|PART) ', '' ), '.$', '')"/>
					</xsl:attribute>
					<xsl:value-of select="fn:capitalize_phrase(heading/title)"/>
				</xsl:when>

				<xsl:otherwise>
					<xsl:value-of select="fn:capitalize_phrase(heading/desig)"/>
				</xsl:otherwise>
			</xsl:choose>

		</unit>

		<xsl:if test="hierarchyLevel">
  			<xsl:apply-templates select="hierarchyLevel"/>
		</xsl:if>
		
	</xsl:template>

	<!--Recurse through textual hierarchies (e.g., § 1(a)(iv)).-->
	<xsl:template match="level">

		<!-- Counter -->
		<xsl:variable name="depth" select="count(ancestor::level)"/>

		<!-- Handle  -->
		<xsl:choose>

			<!-- Only include a prefix if we're at least 1 level deep. -->
			<xsl:when test="$depth > 0">
				<section>
					<xsl:attribute name="prefix">
						<xsl:variable name="prefix_length" select="string-length(heading/desig)"/>
						<xsl:value-of select="substring(heading/desig, 0, $prefix_length)"/>
					</xsl:attribute>

					<xsl:apply-templates select="bodyText"/>

					<xsl:if test="level">
						<xsl:apply-templates select="level"/>
					</xsl:if>

				</section>
			</xsl:when>

			<xsl:otherwise>
				<xsl:apply-templates />
			</xsl:otherwise>

		</xsl:choose>

	</xsl:template>

	<!--Handle markup in our bodyText-->

	<xsl:template match="bodyText">
		<xsl:apply-templates />
	</xsl:template>

	<xsl:template match="p">
		<p><xsl:apply-templates /></p>
	</xsl:template>

	<xsl:template match="pre|br|em">
		<xsl:copy copy-namespaces="no"><xsl:apply-templates /></xsl:copy>
	</xsl:template>

	<!--Delete locator and heading-->
	<xsl:template match="locator|heading" />

	<!--We already have the history-->
	<xsl:template match="history" />

	<!--Get the content of citation.-->
	<xsl:template match="citation">
		<xsl:value-of select="content/span" />
	</xsl:template>

	<xsl:function name="fn:capitalize_word">
		<xsl:param name="word" as="xs:string" />
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
