<?php

/*
 * The subdirectory where all of the raw XML will be stored. Omit the trailing
 * slash.
 */
$output_dir = 'xml';

/*
 * Initialize cURL.
 */
$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

/*
 * Request a list of all tiles.
 */
$url = 'http://law.lis.virginia.gov/LawPortalWebService/CodeofVAGetTitleList';
curl_setopt($curl, CURLOPT_URL, $url);
$xml = curl_exec($curl);

if ($xml === FALSE)
{
	die('Fatal error: Could not retrieve a list of titles.');
}

/*
 * Turn our XML into an object.
 */
$parser = xml_parser_create();
xml_parse_into_struct($parser, $xml, $titles, $index);
xml_parser_free($parser);

/*
 * Iterate through the titles. Note that this isn't *really* a list of titles
 * -- it's a list of XML nodes, some of which are titles, but most of which are
 * noise, for our purposes.
 */
foreach ($titles as $title)
{

	/*
	 * If this is a title number.
	 */
	if ($title['tag'] == 'TITLENUMBER')
	{

		$title_number = $title['value'];

		$url = 'http://law.lis.virginia.gov/LawPortalWebService/CodeofVAGetChapterList/'
			. $title_number;
		curl_setopt($curl, CURLOPT_URL, $url);
		$xml = curl_exec($curl);

		if ($xml === FALSE)
		{
			echo 'Error: Title ' . $title_number . ' does not exist (' . $url . ')';
		}

		/*
		 * Turn our XML into an object.
		 */
		$parser = xml_parser_create();
		xml_parse_into_struct($parser, $xml, $sections, $index);
		xml_parser_free($parser);

		/*
		 * Iterate through the chapters. Note that this isn't *really* a list
		 * of chapters -- it's a list of XML nodes, some of which are chapters,
		 * but most of which are noise, for our purposes.
		 */
		foreach ($sections as $section)
		{

			/*
			 * If this is a section number.
			 */
			if ($section['tag'] == 'SECTIONNUMBER')
			{

				$section_number = $section['value'];

				$url = 'http://law.lis.virginia.gov/LawPortalWebService/CodeofVAGetSectionDetails/'
					. $section_number;
				curl_setopt($curl, CURLOPT_URL, $url);
				$xml = curl_exec($curl);

				if ($xml === FALSE)
				{
					echo 'Error: Section ' . $section_number . ' does not exist ('
						. $url . ')';
				}

				$filename = $output_dir . '/' . str_replace(':', '_', $section_number) . '.xml';
				file_put_contents($filename, $xml);

			}

		}

	}

}

