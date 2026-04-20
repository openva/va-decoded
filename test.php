<?php

/**
 * Tests for the Virginia Code scraper.
 *
 * Usage: php test.php
 *
 * Runs unit tests against the scraper's parsing and XML generation functions,
 * then validates a sample of live CSV data produces well-formed XML.
 */

require __DIR__ . '/scraper.php';

$passed = 0;
$failed = 0;

function assert_equal($expected, $actual, string $test_name): void
{
    global $passed, $failed;
    if ($expected === $actual)
    {
        $passed++;
    }
    else
    {
        $failed++;
        echo "FAIL: $test_name\n";
        echo "  Expected: " . var_export($expected, true) . "\n";
        echo "  Actual:   " . var_export($actual, true) . "\n";
    }
}

function assert_true(bool $condition, string $test_name): void
{
    global $passed, $failed;
    if ($condition)
    {
        $passed++;
    }
    else
    {
        $failed++;
        echo "FAIL: $test_name\n";
    }
}


/*
 * =========================================================================
 * extract_paragraphs()
 * =========================================================================
 */

echo "Testing extract_paragraphs...\n";

assert_equal(
    [],
    extract_paragraphs(''),
    'empty string returns empty array'
);

assert_equal(
    ['Hello world.'],
    extract_paragraphs('<p>Hello world.</p>'),
    'single paragraph'
);

assert_equal(
    ['First.', 'Second.'],
    extract_paragraphs('<p>First.</p><p>Second.</p>'),
    'two paragraphs'
);

assert_equal(
    ['Link to 18.2-31 here.'],
    extract_paragraphs('<p>Link to <a href="/vacode/18.2-31/">18.2-31</a> here.</p>'),
    'HTML tags are stripped'
);

assert_equal(
    ['"Quoted" text & more.'],
    extract_paragraphs('<p>&#x22;Quoted&#x22; text &amp; more.</p>'),
    'HTML entities are decoded'
);

assert_equal(
    ['Content here.'],
    extract_paragraphs('<p class=\'sidenote\'>Content here.</p>'),
    'paragraphs with attributes are matched'
);


/*
 * =========================================================================
 * extract_history()
 * =========================================================================
 */

echo "Testing extract_history...\n";

assert_equal(
    '',
    extract_history([]),
    'empty array returns empty string'
);

assert_equal(
    '1975, cc. 14, 15.',
    extract_history(['Some law text.', '1975, cc. 14, 15.']),
    'year-starting history is extracted'
);

assert_equal(
    'Code 1919, § 2, § 1-10; 2005, c. 839.',
    extract_history(['Some text.', 'Code 1919, § 2, § 1-10; 2005, c. 839.']),
    'Code-starting history is extracted'
);

assert_equal(
    'R. P. 1948, § 1-2.1.',
    extract_history(['Some text.', 'R. P. 1948, § 1-2.1.']),
    'R. P.-starting history is extracted'
);

assert_equal(
    'Acts 1966, c. 702.',
    extract_history(['Repealed by Acts 1966, c. 702.', 'Acts 1966, c. 702.']),
    'Acts-starting history is extracted'
);

assert_equal(
    '',
    extract_history(['This is not history text.']),
    'non-history paragraph returns empty string'
);

assert_equal(
    '',
    extract_history(['A. Some subsection text that starts with a letter.']),
    'subsection text is not mistaken for history'
);


/*
 * =========================================================================
 * detect_prefix()
 * =========================================================================
 */

echo "Testing detect_prefix...\n";

assert_equal(
    'A. ',
    detect_prefix('A. Some text here.'),
    'uppercase letter prefix'
);

assert_equal(
    'Z. ',
    detect_prefix('Z. Last letter.'),
    'letter Z prefix'
);

assert_equal(
    'A1. ',
    detect_prefix('A1. Subsection A1 text.'),
    'letter+digit prefix'
);

assert_equal(
    'C2. ',
    detect_prefix('C2. Another subsection.'),
    'C2 prefix'
);

assert_equal(
    '1. ',
    detect_prefix('1. Numbered item.'),
    'numbered prefix'
);

assert_equal(
    '12. ',
    detect_prefix('12. Multi-digit number.'),
    'multi-digit numbered prefix'
);

assert_equal(
    '(a) ',
    detect_prefix('(a) Parenthesized lowercase.'),
    'parenthesized lowercase letter'
);

assert_equal(
    '(1) ',
    detect_prefix('(1) Parenthesized number.'),
    'parenthesized number'
);

assert_equal(
    '(ii) ',
    detect_prefix('(ii) Roman numeral.'),
    'parenthesized roman numeral'
);

assert_equal(
    '(A) ',
    detect_prefix('(A) Parenthesized uppercase.'),
    'parenthesized uppercase letter'
);

assert_equal(
    null,
    detect_prefix('The common law of England shall continue.'),
    'ordinary sentence returns null'
);

assert_equal(
    null,
    detect_prefix('"Person" includes any individual.'),
    'quoted text returns null'
);


/*
 * =========================================================================
 * build_structure_xml()
 * =========================================================================
 */

echo "Testing build_structure_xml...\n";

$row_simple = [
    'TitleNum' => '1', 'TitleName' => 'General Provisions',
    'SubTitleNum' => '', 'SubTitleName' => '',
    'PartNum' => '', 'PartName' => '',
    'ChapterNum' => '2.1', 'ChapterName' => 'Common Law',
    'SubPartNum' => '', 'SubPartName' => '',
    'ArticleNum' => '1', 'ArticleName' => 'Common Law and Acts',
];

$structure = build_structure_xml($row_simple);
assert_true(
    strpos($structure, 'label="title" identifier="1" level="1"') !== false,
    'title unit is present at level 1'
);
assert_true(
    strpos($structure, 'label="chapter" identifier="2.1" level="2"') !== false,
    'chapter unit is at level 2 (skipping empty subtitle/part)'
);
assert_true(
    strpos($structure, 'label="article" identifier="1" level="3"') !== false,
    'article unit is at level 3'
);
assert_true(
    strpos($structure, 'label="subtitle"') === false,
    'empty subtitle is omitted'
);

$row_full = [
    'TitleNum' => '2.2', 'TitleName' => 'Administration of Government',
    'SubTitleNum' => 'I', 'SubTitleName' => 'Organization',
    'PartNum' => 'A', 'PartName' => 'Office of Governor',
    'ChapterNum' => '1', 'ChapterName' => 'Governor',
    'SubPartNum' => '', 'SubPartName' => '',
    'ArticleNum' => '1', 'ArticleName' => 'General Provisions',
];

$structure_full = build_structure_xml($row_full);
assert_true(
    strpos($structure_full, 'label="subtitle" identifier="I" level="2"') !== false,
    'subtitle is at level 2 when present'
);
assert_true(
    strpos($structure_full, 'label="part" identifier="A" level="3"') !== false,
    'part is at level 3 when present'
);
assert_true(
    strpos($structure_full, 'label="chapter" identifier="1" level="4"') !== false,
    'chapter is at level 4 with subtitle+part before it'
);
assert_true(
    strpos($structure_full, 'label="article" identifier="1" level="5"') !== false,
    'article is at level 5'
);


/*
 * =========================================================================
 * build_text_xml()
 * =========================================================================
 */

echo "Testing build_text_xml...\n";

assert_equal('', build_text_xml([]), 'empty paragraphs returns empty string');

$text = build_text_xml(['The common law shall continue.']);
assert_true(
    strpos($text, 'The common law shall continue.') !== false,
    'plain paragraph is included as text'
);
assert_true(
    strpos($text, '<section') === false,
    'plain paragraph has no section wrapper'
);

$text_sub = build_text_xml(['A. First subsection.', 'B. Second subsection.']);
assert_true(
    strpos($text_sub, '<section prefix="A">First subsection.</section>') !== false,
    'A. prefix is parsed into section element'
);
assert_true(
    strpos($text_sub, '<section prefix="B">Second subsection.</section>') !== false,
    'B. prefix is parsed into section element'
);

$text_paren = build_text_xml(['(a) Lowercase paren.', '(1) Numbered paren.']);
assert_true(
    strpos($text_paren, '<section prefix="a">Lowercase paren.</section>') !== false,
    '(a) prefix is parsed, parens stripped'
);
assert_true(
    strpos($text_paren, '<section prefix="1">Numbered paren.</section>') !== false,
    '(1) prefix is parsed, parens stripped'
);


/*
 * =========================================================================
 * generate_xml() — full integration
 * =========================================================================
 */

echo "Testing generate_xml...\n";

$row = [
    'TitleNum' => '18.2', 'TitleName' => 'Crimes and Offenses',
    'SubTitleNum' => '', 'SubTitleName' => '',
    'PartNum' => '', 'PartName' => '',
    'ChapterNum' => '1', 'ChapterName' => 'In General',
    'SubPartNum' => '', 'SubPartName' => '',
    'ArticleNum' => '1', 'ArticleName' => 'Transition',
    'Section' => '18.2-1',
    'Title' => 'Repealing clause',
    'Body' => '<p>A. First provision.</p><p>B. Second provision.</p><p>1975, cc. 14, 15.</p>',
];

$xml = generate_xml($row);

assert_true(
    strpos($xml, '<?xml version="1.0" encoding="utf-8"?>') === 0,
    'XML declaration is present'
);
assert_true(
    strpos($xml, '<law>') !== false,
    'root <law> element is present'
);
assert_true(
    strpos($xml, '<section_number>18.2-1</section_number>') !== false,
    'section_number is correct'
);
assert_true(
    strpos($xml, '<catch_line>Repealing clause</catch_line>') !== false,
    'catch_line is correct'
);
assert_true(
    strpos($xml, '<section prefix="A">First provision.</section>') !== false,
    'subsection A is in text'
);
assert_true(
    strpos($xml, '<section prefix="B">Second provision.</section>') !== false,
    'subsection B is in text'
);
assert_true(
    strpos($xml, '<history>1975, cc. 14, 15.</history>') !== false,
    'history is extracted and separated from text'
);
assert_true(
    strpos($xml, '>1975, cc. 14, 15.<') === false
        || strpos($xml, '<history>1975') !== false,
    'history does not appear inside <text>'
);

/*
 * Validate that the generated XML is well-formed.
 */
$doc = new DOMDocument();
$well_formed = @$doc->loadXML($xml);
assert_true($well_formed !== false, 'generated XML is well-formed');


/*
 * =========================================================================
 * XML special characters
 * =========================================================================
 */

echo "Testing XML special character escaping...\n";

$row_special = [
    'TitleNum' => '1', 'TitleName' => 'Provisions & Rules',
    'SubTitleNum' => '', 'SubTitleName' => '',
    'PartNum' => '', 'PartName' => '',
    'ChapterNum' => '1', 'ChapterName' => 'The "Code"',
    'SubPartNum' => '', 'SubPartName' => '',
    'ArticleNum' => '', 'ArticleName' => '',
    'Section' => '1-1',
    'Title' => 'Contents & designation of "Code"',
    'Body' => '<p>Laws in "this Code" &amp; following titles.</p><p>Code 1919, § 1.</p>',
];

$xml_special = generate_xml($row_special);
$doc_special = new DOMDocument();
$well_formed_special = @$doc_special->loadXML($xml_special);
assert_true($well_formed_special !== false, 'XML with special characters is well-formed');
assert_true(
    strpos($xml_special, 'Provisions &amp; Rules') !== false,
    'ampersand in title name is escaped'
);
assert_true(
    strpos($xml_special, 'Contents &amp; designation') !== false,
    'ampersand in catch_line is escaped'
);


/*
 * =========================================================================
 * parse_csv()
 * =========================================================================
 */

echo "Testing parse_csv...\n";

$csv_input = "TitleNum,Section,Title,Body\n"
    . "1,1-1,Contents,\"<p>Some text.</p>\"\n"
    . "1,1-2,Effective date,\"<p>More text.</p>\"\n";

$parsed = parse_csv($csv_input);
assert_equal(2, count($parsed), 'parse_csv returns correct row count');
assert_equal('1-1', $parsed[0]['Section'], 'first row Section is correct');
assert_equal('1-2', $parsed[1]['Section'], 'second row Section is correct');
assert_equal('<p>Some text.</p>', $parsed[0]['Body'], 'quoted HTML body is preserved');


/*
 * =========================================================================
 * Edge case: repealed section with no history
 * =========================================================================
 */

echo "Testing edge cases...\n";

$row_repealed = [
    'TitleNum' => '1', 'TitleName' => 'General Provisions',
    'SubTitleNum' => '', 'SubTitleName' => '',
    'PartNum' => '', 'PartName' => '',
    'ChapterNum' => '2', 'ChapterName' => 'Repealed Chapter',
    'SubPartNum' => '', 'SubPartName' => '',
    'ArticleNum' => '', 'ArticleName' => '',
    'Section' => '1-10',
    'Title' => 'Repealed',
    'Body' => '<p>Repealed by Acts 2005, c. 839, cl. 10.</p>',
];

$xml_repealed = generate_xml($row_repealed);
$doc_repealed = new DOMDocument();
assert_true(
    @$doc_repealed->loadXML($xml_repealed) !== false,
    'repealed section XML is well-formed'
);
assert_true(
    strpos($xml_repealed, '<history></history>') !== false,
    'repealed section has empty history (text is not a history pattern)'
);

/*
 * Edge case: section with empty body.
 */
$row_empty = [
    'TitleNum' => '1', 'TitleName' => 'General Provisions',
    'SubTitleNum' => '', 'SubTitleName' => '',
    'PartNum' => '', 'PartName' => '',
    'ChapterNum' => '1', 'ChapterName' => 'Code',
    'SubPartNum' => '', 'SubPartName' => '',
    'ArticleNum' => '', 'ArticleName' => '',
    'Section' => '1-0',
    'Title' => 'Empty section',
    'Body' => '',
];

$xml_empty = generate_xml($row_empty);
$doc_empty = new DOMDocument();
assert_true(
    @$doc_empty->loadXML($xml_empty) !== false,
    'empty body section XML is well-formed'
);


/*
 * =========================================================================
 * Live validation: download a title and validate all generated XML
 * =========================================================================
 */

echo "Testing live CSV download and XML validation...\n";

$csv_url = 'https://law.lis.virginia.gov/CSV/CoVTitle_1.csv';
$csv_data = @file_get_contents($csv_url);

if ($csv_data === false)
{
    echo "  SKIP: Could not download live CSV (network unavailable)\n";
}
else
{

    $rows = parse_csv($csv_data);
    assert_true(count($rows) > 0, 'live CSV has rows');

    $xml_errors = 0;
    $xml_count = 0;

    foreach ($rows as $row)
    {
        if (empty($row['Section']))
        {
            continue;
        }

        $xml = generate_xml($row);
        $xml_count++;

        $doc = new DOMDocument();
        if (@$doc->loadXML($xml) === false)
        {
            $xml_errors++;
            if ($xml_errors <= 3)
            {
                echo "  INVALID XML for section " . $row['Section'] . "\n";
            }
        }
    }

    assert_equal(0, $xml_errors, "all $xml_count live sections produce well-formed XML");
    echo "  Validated $xml_count sections from Title 1\n";

}


/*
 * =========================================================================
 * Results
 * =========================================================================
 */

echo "\n";
echo "Results: $passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
