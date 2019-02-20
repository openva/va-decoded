<?php

/**
 * XML Downloader
 * 
 * Virginia runs an API for legal data, documented at
 * <https://law.lis.virginia.gov/LawPortalWebService/xml/help>. This script iterates through every
 * section of the code and saves it as XML.
 **/

/*
 * Get a list of all titles.
 */
$title_xml = file_get_contents('https://law.lis.virginia.gov/LawPortalWebService/xml/CodeofVAGetTitleList');
$titles = simplexml_load_string($title_xml);
$titles = reset($titles);

/*
 * Create an array to store our sections.
 */
$sections = array();

/*
 * Iterate through each title to get a list of sections.
 */
foreach ($titles as $title)
{

    $title_sections_xml = file_get_contents('https://law.lis.virginia.gov/LawPortalWebService/xml/CodeofVAGetChapterList/' . $title->TitleNumber);
    $title_sections = simplexml_load_string($title_sections_xml);
    $title_sections = reset($title_sections->ChapterList);
    
    foreach ($title_sections as $section)
    {
        $sections[] = (string) $section->SectionNumber;
    }

}

/*
 * Now we have a list of all sections, at $sections. We can now
 * retrieve the XML for all sections.
 */
$output_dir = 'output';
if (!is_dir($output_dir))
{
    mkdir($output_dir);
}

foreach ($sections as $section)
{

    $section_xml = file_get_contents('https://law.lis.virginia.gov/LawPortalWebService/xml/CodeofVAGetSectionDetails/' . $section);
    file_put_contents($output_dir . '/' . $section . '.xml', $section_xml);
    echo '.';
    
}
