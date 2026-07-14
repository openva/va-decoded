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

$library_url  = 'https://law.lis.virginia.gov/law-library/';
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
 * If a title number was provided as an argument, use only that title.
 * Otherwise fetch the current list from the law library index page.
 */
if (isset($argv[1]))
{
    $title_numbers = [$argv[1]];
}
else
{
    echo "Fetching title list from $library_url\n";
    $title_numbers = fetch_title_numbers($library_url);
    if (empty($title_numbers))
    {
        echo "ERROR: Could not fetch title numbers from $library_url\n";
        exit(1);
    }
    echo 'Found ' . count($title_numbers) . " titles\n";
}

/*
 * Process each title. Track any titles we fail to process, so the run can exit
 * non-zero: a partial scrape must not be treated as success, since the importer
 * does a full replace and would drop the missing sections from the live site.
 */
$failed_titles = [];

/*
 * Accumulates the source-order of structural units across the whole run, so
 * each unit can be tagged with an order_by reflecting the order it first
 * appears in the source data. See assign_order().
 */
$order_state = [];

foreach ($title_numbers as $title_number)
{

    $csv_url = $csv_base_url . $title_number . '.csv';
    echo "Downloading $csv_url\n";

    $csv_data = file_get_contents($csv_url);
    if ($csv_data === false)
    {
        echo "  ERROR: Could not download CSV for Title $title_number\n";
        $failed_titles[] = $title_number;
        continue;
    }

    /*
     * Parse the CSV. The file uses standard comma-delimited format with quoted fields.
     */
    $rows = parse_csv($csv_data);
    if (empty($rows))
    {
        echo "  WARNING: No rows found for Title $title_number\n";
        $failed_titles[] = $title_number;
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

        $xml = generate_xml($row, $order_state);

        /*
         * Write the XML file. Replace colons with underscores in filenames.
         */
        $filename = str_replace([':', '/'], '_', $section_number) . '.xml';
        file_put_contents($output_dir . '/' . $filename, $xml);

    }

}

/*
 * If any title failed to download or parse, report it and exit non-zero so the
 * updater aborts before importing an incomplete scrape.
 */
if (!empty($failed_titles))
{
    echo 'ERROR: ' . count($failed_titles) . ' title(s) failed: '
        . implode(', ', $failed_titles) . "\n";
    exit(1);
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
 * Fetch Code of Virginia title numbers from the law library index page.
 *
 * Each title appears in a <td class='child'> cell as "Title X.X: Name".
 * Returns an array of title number strings, e.g. ['1', '2.2', '8.01', ...].
 */
function fetch_title_numbers(string $url): array
{

    $html = file_get_contents($url);
    if ($html === false)
    {
        return [];
    }

    preg_match_all('/Title ([0-9][0-9A-Za-z.]*)/', $html, $matches);

    return $matches[1] ?? [];

}


/**
 * Generate State Decoded XML for a single section.
 */
function generate_xml(array $row, array &$order_state = []): string
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
    $structure_xml = build_structure_xml($row, $order_state);

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
function build_structure_xml(array $row, array &$order_state = []): string
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

    /*
     * Running path of the ancestor units above the one being emitted. Used to
     * scope the source-order counter so that, e.g., "Article 1" under one
     * chapter is ordered independently of "Article 1" under another.
     */
    $parent_path = '';

    foreach ($hierarchy as $label => $fields)
    {

        $identifier = trim($row[$fields['num']] ?? '');
        $name = trim($row[$fields['name']] ?? '');

        if ($identifier === '' && $name === '')
        {
            continue;
        }

        $identifier_attr = htmlspecialchars($identifier, ENT_XML1, 'UTF-8');
        $name_escaped = htmlspecialchars(to_title_case($name), ENT_XML1, 'UTF-8');

        /*
         * A unit is identified among its siblings by its label, identifier, and
         * name; include the name so admin divisions (which share a blank
         * identifier) remain distinct in the path.
         */
        $unit_key = $label . ':' . $identifier . '|' . $name;

        /*
         * Record the order this unit first appears in the source. Admin
         * divisions (blank identifier) are forced to order_by=0 by the importer
         * regardless, so we skip the attribute for them — but still extend the
         * path so their children stay correctly scoped.
         */
        $order_attr = '';
        if ($identifier !== '')
        {
            $order = assign_order($parent_path, $unit_key, $order_state);
            $order_attr = " order_by=\"$order\"";
        }

        $xml .= "\t\t<unit label=\"$label\" identifier=\"$identifier_attr\" level=\"$level\"$order_attr>";
        $xml .= $name_escaped;
        $xml .= "</unit>\n";

        $parent_path .= "\x1f" . $unit_key;
        $level++;

    }

    $xml .= "\t</structure>\n";

    return $xml;

}


/**
 * Assign a stable, 1-based order to a structural unit among its siblings,
 * reflecting the order units are first encountered across a scrape run.
 *
 * The source CSVs are in document order, so the first time a unit is seen under
 * a given parent determines its position. $order_state accumulates across every
 * row and title in a single run and must be passed by reference throughout.
 */
function assign_order(string $parent_path, string $unit_key, array &$order_state): int
{

    $full = $parent_path . "\x1f" . $unit_key;

    if (!isset($order_state['order'][$full]))
    {
        $order_state['count'][$parent_path] = ($order_state['count'][$parent_path] ?? 0) + 1;
        $order_state['order'][$full] = $order_state['count'][$parent_path];
    }

    return $order_state['order'][$full];

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
 * Nesting level is determined dynamically per-law: the first prefix pattern
 * encountered becomes level 1, the second distinct pattern type becomes
 * level 2, and so on. Pattern type is determined by classify_prefix().
 */
function build_text_xml(array $paragraphs): string
{

    if (empty($paragraphs))
    {
        return '';
    }

    $xml = '';
    $stack = [];           // Each entry: ['level' => int, 'indent' => string]
    $pattern_levels = [];  // Maps pattern type string → assigned level int
    $next_level = 1;

    foreach ($paragraphs as $paragraph)
    {

        $raw_prefix = detect_prefix($paragraph);

        if ($raw_prefix !== null)
        {
            $text = trim(substr($paragraph, strlen($raw_prefix)));
            $prefix_clean = rtrim(trim($raw_prefix), '.');
            $prefix_clean = trim($prefix_clean, '()');

            $pattern = classify_prefix($raw_prefix);
            if (!isset($pattern_levels[$pattern]))
            {
                $pattern_levels[$pattern] = $next_level++;
            }
            $level = $pattern_levels[$pattern];

            /*
             * Close any open sections at the same or deeper nesting level.
             */
            while (!empty($stack) && end($stack)['level'] >= $level)
            {
                $top = array_pop($stack);
                $xml .= $top['indent'] . "</section>\n";
            }

            $indent = str_repeat("\t", count($stack) + 2);

            $xml .= $indent . '<section prefix="'
                . htmlspecialchars($prefix_clean, ENT_XML1, 'UTF-8')
                . '">'
                . htmlspecialchars($text, ENT_XML1, 'UTF-8')
                . "\n";

            $stack[] = ['level' => $level, 'indent' => $indent];
        }
        else
        {
            $indent = str_repeat("\t", count($stack) + 2);
            $xml .= $indent . htmlspecialchars($paragraph, ENT_XML1, 'UTF-8') . "\n\n";
        }

    }

    /*
     * Close any sections still open at end of content.
     */
    while (!empty($stack))
    {
        $top = array_pop($stack);
        $xml .= $top['indent'] . "</section>\n";
    }

    return $xml;

}


/**
 * Classify a raw prefix string into a pattern type for hierarchy assignment.
 *
 * Five pattern types are recognised:
 *   upper_letter  — A., B., A1.   (uppercase letter, optional trailing digit)
 *   number        — 1., 2., 3.    (bare integer)
 *   paren_letter  — (a), (A)      (parenthesised single letter)
 *   paren_digit   — (1), (10)     (parenthesised integer, any width)
 *   paren_multi   — (ii), (iii)   (parenthesised multi-char non-digit)
 */
function classify_prefix(string $raw_prefix): string
{

    if (preg_match('/^\(\d+\)/', $raw_prefix))
    {
        return 'paren_digit';
    }

    if (preg_match('/^\([a-zA-Z]\)/', $raw_prefix))
    {
        return 'paren_letter';
    }

    if (preg_match('/^\([^)]{2,}\)/', $raw_prefix))
    {
        return 'paren_multi';
    }

    if (preg_match('/^[A-Z]/', $raw_prefix))
    {
        return 'upper_letter';
    }

    if (preg_match('/^\d/', $raw_prefix))
    {
        return 'number';
    }

    return 'unknown';

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


/**
 * Convert a string to title case, handling legal text conventions.
 *
 * Words that are typically lowercase in titles (articles, conjunctions,
 * short prepositions) are kept lowercase unless they are the first word.
 * Roman numerals and bracketed annotations like "[Repealed]" are preserved.
 */
function to_title_case(string $text): string
{

    /*
     * If the text is already mixed case, leave it alone.
     * Strip bracketed annotations like [Repealed] before checking, since
     * those are always mixed case even when the rest is all-caps.
     */
    $text_without_brackets = preg_replace('/\s*\[[^\]]*\]/', '', $text);
    if ($text_without_brackets !== mb_strtoupper($text_without_brackets, 'UTF-8'))
    {
        return $text;
    }

    /*
     * Words that should stay lowercase in title case (unless first word).
     */
    $minor_words = [
        'a', 'an', 'the',
        'and', 'but', 'or', 'nor', 'for', 'yet', 'so',
        'at', 'by', 'in', 'of', 'on', 'to', 'up',
        'as', 'if',
    ];

    $words = explode(' ', $text);
    $result = [];

    foreach ($words as $i => $word)
    {

        /*
         * Preserve bracketed annotations like [Repealed] or [Reserved].
         */
        if (preg_match('/^\[.*\]$/', $word))
        {
            $result[] = '[' . mb_convert_case(trim($word, '[]'), MB_CASE_TITLE, 'UTF-8') . ']';
            continue;
        }

        $lower = mb_strtolower($word, 'UTF-8');

        /*
         * Keep minor words lowercase unless they are the first word.
         */
        if ($i > 0 && in_array($lower, $minor_words, true))
        {
            $result[] = $lower;
            continue;
        }

        $result[] = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');

    }

    return implode(' ', $result);

}
