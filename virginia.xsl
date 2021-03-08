<?xml version="1.0" encoding="UTF-8" ?>
			
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:orig="http://StatRev.xsd"
	xmlns:fn="http://localhost/"  xmlns:xs="http://www.w3.org/2001/XMLSchema" >

	<!-- Strip whitespace from everything. -->
	<xsl:strip-space elements="*" />

	<!-- Don't include any whitespace-only text nodes. -->
	<xsl:strip-space elements="*" />
	
	<xsl:output
			method="xml"
			version="1.0"
			encoding="utf-8"
			omit-xml-declaration="no"
			indent="yes"
			media-type="text/xml"/>

	<!--Start processing at the section element.-->
	<xsl:template match="section">

		<law>

			<structure>
				<xsl:apply-templates select="structure"/>
			</structure>
			
			<section_number>
				<xsl:value-of select="desig"/>
			</section_number>

			<catch_line><xsl:value-of select="head" /></catch_line>
			
			<text>
				<xsl:for-each select="para">
					<xsl:value-of select="."/>
				</xsl:for-each>
			</text>

			<history>
				<xsl:value-of select="history"/>
			</history>

		</law>
		
	</xsl:template>

	<xsl:template match="structure">

		<xsl:apply-templates select="hierarchy[@label='title']" />
		<xsl:apply-templates select="hierarchy[@label='subtitle']" />
		<xsl:apply-templates select="hierarchy[@label='part']" />
		<xsl:apply-templates select="hierarchy[@label='chapter']" />
		<xsl:apply-templates select="hierarchy[@label='subpart']" />
		<xsl:apply-templates select="hierarchy[@label='article']" />
	
	</xsl:template>

	<xsl:template match="hierarchy[@label='title']">
		<unit label="title" identifier="$TitleIdentifier" level="1">
			<xsl:value-of select="@head"/>
		</unit>

		<xsl:attribute name="TitleIdentifier">
			<xsl:value-of select="hierarchy/desig"/>
		</xsl:attribute>
	</xsl:template>

	<xsl:template match="hierarchy[@label='subtitle']">
		<unit label="subtitle" identifier="$SubtitleIdentifier" level="2">
			<xsl:value-of select="@head"/>
		</unit>

		<xsl:attribute name="SubtitleIdentifier">
			<xsl:value-of select="hierarchy/desig"/>
		</xsl:attribute>
	</xsl:template>

	<xsl:template match="hierarchy[@label='part']">
		<unit label="part" identifier="$PartIdentifier" level="3">
			<xsl:value-of select="@head"/>
		</unit>

		<xsl:attribute name="PartIdentifier">
			<xsl:value-of select="hierarchy/desig"/>
		</xsl:attribute>
	</xsl:template>

	<xsl:template match="hierarchy[@label='chapter']">
		<unit label="chapter" identifier="$ChapterIdentifier" level="4">
			<xsl:value-of select="@head"/>
		</unit>

		<xsl:attribute name="ChapterIdentifier">
			<xsl:value-of select="hierarchy/desig"/>
		</xsl:attribute>
	</xsl:template>

	<xsl:template match="hierarchy[@label='article']">
		<unit label="article" identifier="$ArticleIdentifier" level="5">
			<xsl:value-of select="@head"/>
		</unit>

		<xsl:attribute name="ArticleIdentifier">
			<xsl:value-of select="hierarchy/desig"/>
		</xsl:attribute>
	</xsl:template>

</xsl:stylesheet>
