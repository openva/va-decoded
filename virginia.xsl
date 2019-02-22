<?xml version="1.0" encoding="UTF-8" ?>
			
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:orig="http://StatRev.xsd"
	xmlns:fn="http://localhost/"  xmlns:xs="http://www.w3.org/2001/XMLSchema" >

	<!-- Strip whitespace from everything except the text of laws. -->
	<xsl:strip-space elements="*" />
	<xsl:preserve-space elements="Body" />

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
	<xsl:template match="ClassObjects.VaCodeObjectsTitleListForWebService">

		<law>

			<structure>
				<xsl:apply-templates select="structure"/>
			</structure>
			
			<section_number>
				<xsl:value-of select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/SectionNumber"/>
			</section_number>

			<catch_line><xsl:value-of select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/SectionTitle" /></catch_line>
			
			<text>
				<xsl:for-each select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/Body" />
			</text>

		</law>
		
	</xsl:template>

	<xsl:template match="structure">

		<unit label="title" identifier="$TitleIdentifier" level="1">
			<xsl:value-of select="TitleName"/>
		</unit>
		<unit label="subtitle" identifier="$SubtitleIdentifier" level="2">
			<xsl:value-of select="SubtitleName"/>
		</unit>
		<unit label="part" identifier="$PartIdentifier" level="3">
			<xsl:value-of select="PartName"/>
		</unit>
		<unit label="chapter" identifier="$ChapterIdentifier" level="4">
			<xsl:value-of select="ChapterName"/>
		</unit>
		<unit label="subpart" identifier="$SubpartIdentifier" level="5">
			<xsl:value-of select="SubPartName"/>
		</unit>
		<unit label="article" identifier="$ArticleIdentifier" level="6">
			<xsl:value-of select="ArticleName"/>
		</unit>
	
	</xsl:template>


	<xsl:attribute name="TitleIdentifier">
		<xsl:value-of select="TitleNumber"/>
	</xsl:attribute>

	<xsl:attribute name="SubtitleIdentifier">
		<xsl:value-of select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/SubtitleNum"/>
	</xsl:attribute>

	<xsl:attribute name="PartIdentifier">
		<xsl:value-of select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/PartNum"/>
	</xsl:attribute>

	<xsl:attribute name="ChapterIdentifier">
		<xsl:value-of select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/ChapterNum"/>
	</xsl:attribute>

	<xsl:attribute name="SubpartIdentifier">
		<xsl:value-of select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/SubpartNum"/>
	</xsl:attribute>

	<xsl:attribute name="ArticleIdentifier">
		<xsl:value-of select="ChapterList/ClassObjects.VaCodeObjectsChapterListForWebService/ArticleNum"/>
	</xsl:attribute>

</xsl:stylesheet>
