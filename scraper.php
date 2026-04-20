<?php

/**
 * Virginia Code of Law Scraper
 *
 * Downloads CSV files from the Virginia Law Library and converts each section
 * into State Decoded XML format.
 *
 * Usage: php scraper.php [title_number]
 *   If a title number is provided, only that title is processed.
 *   Otherwise, all titles are processed.
 */

/*
 * If this file is being included by another script (e.g., tests), don't run
 * the main logic — just make the functions available.
 */
if (realpath($argv[0] ?? '') !== realpath(__FILE__))
{
    return;
}

/*
 * All title numbers in the Code of Virginia.
 */
$title_numbers = [
    '1', '2.2', '3.2', '4.1', '5.1', '6.2',
    '8.01', '8.1A', '8.2', '8.2A', '8.3A', '8.4', '8.4A', '8.5A',
    '8.7', '8.8A', '8.9A', '8.10', '8.11', '8.12', '8.13',
    '9.1', '10.1', '11', '12.1', '13.1', '15.2', '16.1', '17.1',
    '18.2', '19.2', '20', '21', '22.1', '23.1', '24.2', '25.1',
    '27', '28.2', '29.1', '30', '32.1', '33.2', '34', '35.1',
    '36', '37.2', '38.2', '40.1', '41.1', '42.1', '43', '44',
    '45.2', '46.2', '47.1', '48', '49', '50', '51.1', '51.5',
    '52', '53.1', '54.1', '55.1', '56', '57', '58.1', '59.1',
    '60.2', '61.1', '62.1', '63.2', '64.2', '65.2', '66',
];

$csv_base_url = 'https://law.lis.virginia.gov/CSV/CoVTitle_';

/*
 * Create the output directory.
 */
$output_dir = 'output';
if (!is_dir($output_dir))
{
    mkdir($output_dir);
}

/*
 * If a title number was provided as an argument, process only that title.
 */
if (isset($argv[1]))
{
    $title_numbers = [$argv[1]];
}

/*
 * Process each title.
 */
foreach ($title_numbers as $title_number)
{

    $csv_url = $csv_base_url . $title_number . '.csv';
    echo "Downloading $csv_url\n";

    $csv_data = file_get_contents($csv_url);
    if ($csv_data === false)
    {
        echo "  ERROR: Could not download CSV for Title $title_number\n";
        continue;
    }

    /*
     * Parse the CSV. The file uses standard comma-delimited format with quoted fields.
     */
    $rows = parse_csv($csv_data);
    if (empty($rows))
    {
        echo "  WARNING: No rows found for Title $title_number\n";
        continue;
    }

    echo '  Processing ' . count($rows) . " sections\n";

    foreach ($rows as $row)
    {

        $section_number = $row['Section'];
        if (empty($section_number))
        {
            continue;
        }

        $xml = generate_xml($row);

        /*
         * Write the XML file. Replace colons with underscores in filenames.
         */
        $filename = str_replace([':', '/'], '_', $section_number) . '.xml';
        file_put_contents($output_dir . '/' . $filename, $xml);
        echo '.';

    }

    echo "\n";

}

echo "Done.\n";


/**
 * Parse a CSV string into an array of associative arrays.
 */
function parse_csv(string $csv_data): array
{

    $rows = [];
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $csv_data);
    rewind($handle);

    $headers = fgetcsv($handle);
    if ($headers === false)
    {
        fclose($handle);
        return [];
    }

    while (($data = fgetcsv($handle)) !== false)
    {
        if (count($data) === count($headers))
        {
            $rows[] = array_combine($headers, $data);
        }
    }

    fclose($handle);
    return $rows;

}


/**
 * Generate State Decoded XML for a single section.
 */
function generate_xml(array $row): string
{

    $body_html = $row['Body'] ?? '';

    /*
     * Separate the body into text paragraphs and history.
     */
    $paragraphs = extract_paragraphs($body_html);
    $history = extract_history($paragraphs);
    $text_paragraphs = array_slice($paragraphs, 0, count($paragraphs) - ($history !== '' ? 1 : 0));

    /*
     * Parse subsections from the text paragraphs.
     */
    $text_xml = build_text_xml($text_paragraphs);

    /*
     * Build the structure hierarchy.
     */
    $structure_xml = build_structure_xml($row);

    /*
     * Assemble the final XML.
     */
    $section_number = htmlspecialchars($row['Section'], ENT_XML1, 'UTF-8');
    $catch_line = htmlspecialchars($row['Title'] ?? '', ENT_XML1, 'UTF-8');
    $history = htmlspecialchars($history, ENT_XML1, 'UTF-8');

    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
    $xml .= "<law>\n";
    $xml .= $structure_xml;
    $xml .= "\t<section_number>$section_number</section_number>\n";
    $xml .= "\t<catch_line>$catch_line</catch_line>\n";
    $xml .= "\t<text>\n";
    $xml .= $text_xml;
    $xml .= "\t</text>\n";
    $xml .= "\t<history>$history</history>\n";
    $xml .= "</law>\n";

    return $xml;

}


/**
 * Build the <structure> element from the CSV row's hierarchy fields.
 */
function build_structure_xml(array $row): string
{

    $xml = "\t<structure>\n";

    $level = 1;
    $hierarchy = [
        'title'     => ['num' => 'TitleNum',    'name' => 'TitleName'],
        'subtitle'  => ['num' => 'SubTitleNum',  'name' => 'SubTitleName'],
        'part'      => ['num' => 'PartNum',      'name' => 'PartName'],
        'chapter'   => ['num' => 'ChapterNum',   'name' => 'ChapterName'],
        'subpart'   => ['num' => 'SubPartNum',   'name' => 'SubPartName'],
        'article'   => ['num' => 'ArticleNum',   'name' => 'ArticleName'],
    ];

    foreach ($hierarchy as $label => $fields)
    {

        $identifier = trim($row[$fields['num']] ?? '');
        $name = trim($row[$fields['name']] ?? '');

        if ($identifier === '' && $name === '')
        {
            continue;
        }

        $identifier_attr = htmlspecialchars($identifier, ENT_XML1, 'UTF-8');
        $name_escaped = htmlspecialchars($name, ENT_XML1, 'UTF-8');

        $xml .= "\t\t<unit label=\"$label\" identifier=\"$identifier_attr\" level=\"$level\">";
        $xml .= $name_escaped;
        $xml .= "</unit>\n";

        $level++;

    }

    $xml .= "\t</structure>\n";

    return $xml;

}


/**
 * Extract individual paragraphs from the Body HTML.
 * Returns an array of paragraph text content (with HTML tags stripped).
 */
function extract_paragraphs(string $html): array
{

    if (empty($html))
    {
        return [];
    }

    $paragraphs = [];

    /*
     * Split on <p> or <p ...> tags. The body is a series of <p>...</p> elements.
     */
    preg_match_all('/<p[^>]*>(.*?)<\/p>/s', $html, $matches);

    if (!empty($matches[1]))
    {
        foreach ($matches[1] as $content)
        {
            $text = strip_tags($content);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim($text);
            if ($text !== '')
            {
                $paragraphs[] = $text;
            }
        }
    }

    return $paragraphs;

}


/**
 * Determine if the last paragraph is legislative history and return it.
 *
 * History paragraphs typically start with a year or "Code" reference, e.g.:
 *   "1975, cc. 14, 15."
 *   "Code 1919, § 2, § 1-10; 2005, c. 839."
 *   "R. P. 1948, § 1-2.1."
 *   "Acts 1966, c. 702."
 */
function extract_history(array $paragraphs): string
{

    if (empty($paragraphs))
    {
        return '';
    }

    $last = end($paragraphs);

    /*
     * Match common history patterns:
     * - Starts with a year (4 digits)
     * - Starts with "Code"
     * - Starts with "R. P."
     * - Starts with "Acts"
     * - Contains "c." or "cc." (chapter references) near the start
     */
    if (preg_match('/^(Code |R\. P\. |\d{4},? |Acts )/', $last))
    {
        return $last;
    }

    return '';

}


/**
 * Build the <text> element contents from an array of paragraph strings.
 *
 * Detects subsection prefixes and wraps them in <section prefix=""> elements.
 * Supported prefixes:
 *   A. B. C. ... (uppercase letter followed by period)
 *   A1. B2. C1. ... (uppercase letter + digit followed by period)
 *   1. 2. 3. ... (number followed by period)
 *   (a) (b) (1) (2) (i) (ii) etc. (parenthesized)
 */
function build_text_xml(array $paragraphs): string
{

    if (empty($paragraphs))
    {
        return '';
    }

    $xml = '';

    foreach ($paragraphs as $paragraph)
    {

        $prefix = detect_prefix($paragraph);

        if ($prefix !== null)
        {
            /*
             * Remove the prefix from the start of the paragraph text.
             */
            $text = trim(substr($paragraph, strlen($prefix)));
            $prefix_clean = rtrim(trim($prefix), '.');
            $prefix_clean = trim($prefix_clean, '()');

            $xml .= "\t\t<section prefix=\""
                . htmlspecialchars($prefix_clean, ENT_XML1, 'UTF-8')
                . '">'
                . htmlspecialchars($text, ENT_XML1, 'UTF-8')
                . "</section>\n";
        }
        else
        {
            $xml .= "\t\t" . htmlspecialchars($paragraph, ENT_XML1, 'UTF-8') . "\n";
        }

    }

    return $xml;

}


/**
 * Detect if a paragraph starts with a subsection prefix.
 *
 * Returns the prefix string (including trailing period/paren) if found, or null.
 */
function detect_prefix(string $text): ?string
{

    /*
     * Order matters: check more specific patterns first.
     *
     * Parenthesized prefixes: (a), (1), (i), (ii), (A), etc.
     */
    if (preg_match('/^\([a-zA-Z0-9]+\)\s/', $text, $match))
    {
        return $match[0];
    }

    /*
     * Letter+digit prefix: A1. B2. C1. etc.
     */
    if (preg_match('/^[A-Z]\d+\.\s/', $text, $match))
    {
        return $match[0];
    }

    /*
     * Single uppercase letter prefix: A. B. C. through Z.
     * Must be followed by a space to avoid matching sentences.
     */
    if (preg_match('/^[A-Z]\.\s/', $text, $match))
    {
        return $match[0];
    }

    /*
     * Numbered prefix: 1. 2. 3. etc.
     */
    if (preg_match('/^\d+\.\s/', $text, $match))
    {
        return $match[0];
    }

    return null;

}
