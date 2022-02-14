<?php

/*
 * Get a list of all titles.
 */
$title_xml = file_get_contents('https://law.lis.virginia.gov/api/CoVTitlesGetListOfXml/');
if ($title_xml === false)
{
    die('Error: Could not get title list.');
}

$titles = simplexml_load_string($title_xml);
if ($titles === false)
{
    die('Error: Could not parse title list.');
}
$titles = reset($titles);

/*
 * Create an array to store our sections.
 */
$sections = array();

/*
 * Iterate through titles and chapters to build up a list of sections.
 */
foreach ($titles as $title)
{

    $title_chapters_xml = file_get_contents('https://law.lis.virginia.gov/api/CoVChaptersGetListOfXml/' . $title->TitleNumber);
    if ($title_chapters_xml === false)
    {
        die('Error: Could not get chapter list for title ' . $title->TitleNumber);
    }
    
    $title_chapters = simplexml_load_string($title_chapters_xml);
    if ($title_chapters === false)
    {
        die('Error: Could not parse chapter list for title ' . $title->TitleNumber);
    }
    $title_chapters = $title_chapters->ChapterList;
    
    /*
     * Iterate through chapters to build up a list of sections.
     */
    foreach ($title_chapters as $chapter)
    {

        $chapter_xml = file_get_contents('https://law.lis.virginia.gov/api/CoVSectionsGetListOfXml/'
            . $title->ChapterNum . '/' . $chapter->ChapterNum);
        if ($chapter_xml === false)
        {
            die('Error: Could not get section list for chapter ' . $chapter->ChapterNum);
        }
        
        $chapter_sections = simplexml_load_string($chapter_xml);
        if ($chapter_sections === false)
        {
            die('Error: Could not parse section list for chapter ' . $chapter->ChapterNum);
        }
        $chapter_sections = $chapter_sections->ArticleList;
        
        /////
        ///// You have to iterate through the egregiously named "VaCodeObjectsArticleListForWS" elements,
        ///// and then through the "SubPartList" elements, and probably others too.
        foreach ($chapter_sections as $chapter_section)
        {
            $sections[] = (string) $chapter_section->SectionNumber;
        }
    }

}

/*
 * Now we have a list of all sections, at $sections. We can now retrieve the XML for all sections.
 */
$output_dir = 'output';
if (!file_exists($output_dir))
{
    mkdir($output_dir);
}

foreach ($sections as $section)
{

    $section_xml = file_get_contents('https://law.lis.virginia.gov/api/CoVSectionsGetSectionDetailsXml/' . $section . '/');
    if ($section_xml === false)
    {
        echo 'Error: Could not get section ' . $section;
    }
    file_put_contents($output_dir . '/' . str_replace(':', '_', $section) . '.xml', $section_xml);
    echo '.';
    
}
