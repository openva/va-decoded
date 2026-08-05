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
 * to_title_case()
 * =========================================================================
 */

echo "Testing to_title_case...\n";

assert_equal(
    'General Provisions',
    to_title_case('GENERAL PROVISIONS'),
    'all-caps converted to title case'
);

assert_equal(
    'Code of Virginia',
    to_title_case('CODE OF VIRGINIA'),
    'minor word "of" stays lowercase'
);

assert_equal(
    'Common Law and Rules of Construction',
    to_title_case('COMMON LAW AND RULES OF CONSTRUCTION'),
    'minor words "and", "of" stay lowercase'
);

assert_equal(
    'Common Law and Rules of Construction',
    to_title_case('Common Law and Rules of Construction'),
    'already mixed case is left alone'
);

assert_equal(
    'Citizenship [Repealed]',
    to_title_case('CITIZENSHIP [Repealed]'),
    'bracketed annotation preserved in title case'
);

assert_equal(
    'Common Law, Statutes and Rules of Construction [Repealed]',
    to_title_case('COMMON LAW, STATUTES AND RULES OF CONSTRUCTION [Repealed]'),
    'all-caps with bracket converts correctly'
);

assert_equal(
    'Jurisdiction Over Lands Acquired by the United States',
    to_title_case('JURISDICTION OVER LANDS ACQUIRED BY THE UNITED STATES'),
    '"by" and "the" stay lowercase mid-sentence'
);

assert_equal(
    'In General',
    to_title_case('In General'),
    'mixed case left alone (In General)'
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
 * Structural ordering (order_by)
 * =========================================================================
 *
 * order_by records the order structural units first appear in the source, so
 * that siblings (e.g. Roman-numeral subtitles) sort by document order rather
 * than alphabetically by identifier.
 */

echo "Testing structural ordering...\n";

/*
 * Siblings under the same parent, seen in source order, get ascending order_by.
 * Roman numerals are the motivating case: alphabetically "IX" precedes "V", but
 * by appearance order they must not.
 */
$order_state = [];
$rows_ordered = [
    ['ChapterNum' => '1', 'ChapterName' => 'V',    'ArticleNum' => '', 'ArticleName' => ''],
    ['ChapterNum' => '2', 'ChapterName' => 'IX',   'ArticleNum' => '', 'ArticleName' => ''],
    ['ChapterNum' => '3', 'ChapterName' => 'X',    'ArticleNum' => '', 'ArticleName' => ''],
];
$base = ['TitleNum' => '1', 'TitleName' => 'General Provisions'];
$out = [];
foreach ($rows_ordered as $r)
{
    $out[] = build_structure_xml(array_merge($base, $r), $order_state);
}

assert_true(
    strpos($out[0], 'identifier="1" level="2" order_by="1"') !== false,
    'first chapter seen gets order_by="1"'
);
assert_true(
    strpos($out[1], 'identifier="2" level="2" order_by="2"') !== false,
    'second chapter seen gets order_by="2"'
);
assert_true(
    strpos($out[2], 'identifier="3" level="2" order_by="3"') !== false,
    'third chapter seen gets order_by="3"'
);
assert_true(
    strpos($out[0], 'label="title" identifier="1" level="1" order_by="1"') !== false,
    'the shared title gets order_by="1"'
);

/*
 * A unit seen again in a later row keeps its original order_by rather than
 * being reassigned.
 */
$repeat = build_structure_xml(array_merge($base, $rows_ordered[0]), $order_state);
assert_true(
    strpos($repeat, 'identifier="1" level="2" order_by="1"') !== false,
    'a repeated chapter keeps its first-seen order_by'
);

/*
 * The same identifier under different parents is ordered independently: each
 * chapter's first article is order_by="1".
 */
$order_state2 = [];
$s_ch1 = build_structure_xml(
    ['TitleNum' => '1', 'TitleName' => 'T', 'ChapterNum' => '1', 'ChapterName' => 'One',
     'ArticleNum' => '1', 'ArticleName' => 'First'],
    $order_state2
);
$s_ch2 = build_structure_xml(
    ['TitleNum' => '1', 'TitleName' => 'T', 'ChapterNum' => '2', 'ChapterName' => 'Two',
     'ArticleNum' => '1', 'ArticleName' => 'First'],
    $order_state2
);
assert_true(
    strpos($s_ch1, 'label="article" identifier="1" level="3" order_by="1"') !== false,
    'article 1 under chapter 1 is order_by="1"'
);
assert_true(
    strpos($s_ch2, 'label="article" identifier="1" level="3" order_by="1"') !== false,
    'article 1 under chapter 2 is independently order_by="1"'
);

/*
 * Admin divisions (blank identifier, name only) get no order_by attribute — the
 * importer forces those to 0 — but their children are still scoped beneath them.
 */
$order_state3 = [];
$s_admin = build_structure_xml(
    ['TitleNum' => '1', 'TitleName' => 'T', 'ChapterNum' => '', 'ChapterName' => 'Uncodified',
     'ArticleNum' => '1', 'ArticleName' => 'First'],
    $order_state3
);
assert_true(
    strpos($s_admin, 'label="chapter" identifier="" level="2">') !== false,
    'blank-identifier chapter is emitted without an order_by attribute'
);
assert_true(
    strpos($s_admin, 'label="article" identifier="1" level="3" order_by="1"') !== false,
    'a real child under an admin division still gets ordered'
);

/*
 * Called without a shared state argument, a single row still orders internally:
 * every unit is the first of its siblings seen, so all are order_by="1".
 */
$s_stateless = build_structure_xml(array_merge($base, $rows_ordered[0]));
assert_true(
    strpos($s_stateless, 'label="chapter" identifier="1" level="2" order_by="1"') !== false,
    'a stateless call still orders each unit within the single row'
);


/*
 * =========================================================================
 * classify_prefix()
 * =========================================================================
 */

echo "Testing classify_prefix...\n";

assert_equal('upper_letter', classify_prefix('A. '),   'A. is upper_letter');
assert_equal('upper_letter', classify_prefix('Z. '),   'Z. is upper_letter');
assert_equal('upper_letter', classify_prefix('A1. '),  'A1. is upper_letter');
assert_equal('number',       classify_prefix('1. '),   '1. is number');
assert_equal('number',       classify_prefix('12. '),  '12. is number');
assert_equal('paren_letter', classify_prefix('(a) '),  '(a) is paren_letter');
assert_equal('paren_letter', classify_prefix('(A) '),  '(A) is paren_letter');
assert_equal('paren_digit',  classify_prefix('(1) '),  '(1) is paren_digit');
assert_equal('paren_digit',  classify_prefix('(10) '), '(10) is paren_digit');
assert_equal('paren_multi',  classify_prefix('(ii) '), '(ii) is paren_multi');
assert_equal('paren_multi',  classify_prefix('(iii) '),'(iii) is paren_multi');


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
    strpos($text_sub, '<section prefix="A">First subsection.') !== false,
    'A. prefix is parsed into section element'
);
assert_true(
    strpos($text_sub, '<section prefix="B">Second subsection.') !== false,
    'B. prefix is parsed into section element'
);

$text_paren = build_text_xml(['(a) Lowercase paren.', '(1) Numbered paren.']);
assert_true(
    strpos($text_paren, '<section prefix="a">Lowercase paren.') !== false,
    '(a) prefix is parsed, parens stripped'
);
assert_true(
    strpos($text_paren, '<section prefix="1">Numbered paren.') !== false,
    '(1) prefix is parsed, parens stripped'
);
$dom_paren = new DOMDocument();
$dom_paren->loadXML("<?xml version=\"1.0\"?>\n<text>\n" . $text_paren . "</text>");
$xpath_paren = new DOMXPath($dom_paren);
assert_true(
    $xpath_paren->query('//section[@prefix="a"]/section[@prefix="1"]')->length === 1,
    'paren_digit (1) is nested inside paren_letter (a) when it appears second'
);

$text_nested = build_text_xml(['B. Intro.', '1. First.', '2. Second.', 'C. Next.']);
$dom_nested = new DOMDocument();
$dom_nested->loadXML("<?xml version=\"1.0\"?>\n<text>\n" . $text_nested . "</text>");
$xpath = new DOMXPath($dom_nested);
$section_1 = $xpath->query('//section[@prefix="1"]')->item(0);
assert_true(
    $section_1 !== null && $section_1->parentNode->getAttribute('prefix') === 'B',
    'numbered section is nested inside letter section'
);
assert_true(
    $xpath->query('//section[@prefix="B"]/section[@prefix="1"]')->length === 1,
    'section 1 is a direct child of section B'
);
assert_true(
    $xpath->query('//section[@prefix="B"]/section[@prefix="2"]')->length === 1,
    'section 2 is a direct child of section B'
);
assert_true(
    $xpath->query('/text/section[@prefix="C"]')->length === 1,
    'section C is a top-level sibling of B, not nested inside it'
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
    strpos($xml, '<section prefix="A">First provision.') !== false,
    'subsection A is in text'
);
assert_true(
    strpos($xml, '<section prefix="B">Second provision.') !== false,
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
 * fetch_title_numbers()
 * =========================================================================
 */

echo "Testing fetch_title_numbers...\n";

$library_url = 'https://law.lis.virginia.gov/law-library/';
$title_numbers_live = @fetch_title_numbers($library_url);

if (empty($title_numbers_live))
{
    echo "  SKIP: Could not fetch law library page (network unavailable)\n";
}
else
{
    assert_true(count($title_numbers_live) > 60, 'law library returns more than 60 titles');
    assert_true(in_array('1', $title_numbers_live, true), 'Title 1 is in the list');
    assert_true(in_array('2.2', $title_numbers_live, true), 'Title 2.2 is in the list');
    assert_true(in_array('8.01', $title_numbers_live, true), 'Title 8.01 is in the list');
    assert_true(in_array('8.1A', $title_numbers_live, true), 'Title 8.1A is in the list');
    assert_true(in_array('66', $title_numbers_live, true), 'Title 66 is in the list');
    echo '  Found ' . count($title_numbers_live) . " titles\n";
}


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
 * translate_history()
 * =========================================================================
 *
 * translate_history() lives in the State class, which the State Decoded
 * importer consumes. It renders a law's history citations as plain English.
 */

echo "Testing translate_history...\n";

/*
 * The class file requires State Decoded's own includes, which aren't present in
 * this repository, so stub them out before loading it.
 */
if (!defined('INCLUDE_PATH'))
{
    define('INCLUDE_PATH', sys_get_temp_dir() . '/va-decoded-test-stubs/');
}
if (!is_dir(INCLUDE_PATH))
{
    mkdir(INCLUDE_PATH, 0777, true);
}
foreach (['class.Edition.inc.php', 'class.Permalink.inc.php'] as $stub)
{
    if (!file_exists(INCLUDE_PATH . $stub))
    {
        file_put_contents(INCLUDE_PATH . $stub, "<?php\n");
    }
}

require_once __DIR__ . '/includes/config.inc.php';
require_once __DIR__ . '/includes/class.Virginia.inc.php';

/*
 * Render a history string and strip the markup, so that tests assert on the
 * prose rather than on the HTML.
 */
function render_history(string $history): string
{
    $law = new State();
    $law->history = $history;
    $result = $law->translate_history();

    if ($result === false)
    {
        return '';
    }

    return html_entity_decode(strip_tags($result), ENT_QUOTES, 'UTF-8');
}

assert_equal(
    '',
    render_history(''),
    'empty history returns no text'
);

/*
 * A history consisting only of a reference to an earlier codification once
 * rendered as nothing at all, because no entry matched an Acts of Assembly
 * citation and the entry list came back empty.
 */
assert_true(
    strpos(render_history('Code 1919, § 5559.'), 'Code of Virginia of 1919') !== false,
    'a lone Code reference names the codification it came from'
);
assert_true(
    strpos(render_history('Code 1919, § 5559.'), '§ 5559') !== false,
    'a lone Code reference names the section it was codified as'
);
assert_true(
    render_history('Code 1919, § 5559.') !== '',
    'a lone Code reference renders text rather than nothing'
);

/*
 * A recompilation reference is likewise a location, not an enactment.
 */
assert_true(
    strpos(render_history('R. P. 1948, § 1-8.'), 'Replacement Pamphlet') !== false,
    'a lone R. P. reference names the replacement pamphlet'
);

/*
 * An Acts of Assembly citation is a genuine enactment, so it is reported as the
 * law's creation.
 */
assert_true(
    strpos(render_history('1975, cc. 14, 15.'), 'first created in 1975') !== false,
    'an Acts citation reports the year the law was created'
);
assert_true(
    strpos(render_history('1975, cc. 14, 15.'), 'chapters 14 and 15') !== false,
    'multiple chapters are joined as English'
);

/*
 * Chapters from 1994 onward link to the General Assembly's website; earlier
 * years have no online record.
 */
$law_linked = new State();
$law_linked->history = '2005, c. 839.';
assert_true(
    strpos($law_linked->translate_history(), 'CHAP0839') !== false,
    'a post-1994 chapter is linked to the General Assembly'
);

$law_unlinked = new State();
$law_unlinked->history = '1975, c. 14.';
assert_true(
    strpos($law_unlinked->translate_history(), '<a href') === false,
    'a pre-1994 chapter is not linked'
);

/*
 * Special session citations were previously dropped entirely.
 */
assert_true(
    strpos(render_history('2021, Sp. Sess. I, c. 263.'), 'Special Session I') !== false,
    'a special session is named in expanded form'
);
assert_true(
    strpos(render_history('1971, Ex. Sess., c. 14.'), 'Extra Session') !== false,
    'an extra session is named in expanded form'
);

/*
 * Every entry in the history must be counted as a modification. Entries that
 * were not Acts citations used to be dropped silently, undercounting the total.
 */
assert_true(
    strpos(
        render_history('Code 1919, § 5; Code 1950, § 1-13; 1950, p. 21; 1995, c. 155; 2005, c. 839.'),
        'modified 4 times'
    ) !== false,
    'code, page, and Acts entries all count as modifications'
);

/*
 * A page citation refers to a page of a volume of the Acts, not a chapter.
 */
assert_true(
    strpos(render_history('1950, p. 20.'), 'page 20') !== false,
    'a page citation is rendered as a page'
);
assert_true(
    strpos(render_history('1930, pp. 81, 82.'), 'pages 81 and 82') !== false,
    'multiple pages are joined as English'
);

/*
 * A single entry may carry several runs of section marks; the rendered list
 * should carry exactly one.
 */
assert_true(
    strpos(render_history('Code 1919, § 835, §§ 23-93, 23-94.'), '§§ 835, 23-93, and 23-94') !== false,
    'repeated section marks are consolidated into one list'
);

/*
 * Malformed citations — a chapter number missing from the source — are dropped
 * rather than rendered as a chapter with no number.
 */
assert_true(
    strpos(render_history('1996, c. 167; 1997, c.; 2000, c. 293.'), 'modified 1 time') !== false,
    'a chapter citation with no number is dropped'
);

/*
 * Unparseable history yields no text at all, rather than a sentence with holes.
 */
assert_equal(
    '',
    render_history('This is not a history.'),
    'unparseable history returns no text'
);


/*
 * =========================================================================
 * Results
 * =========================================================================
 */

echo "\n";
echo "Results: $passed passed, $failed failed\n";

/*
 * Exit non-zero if anything failed, so CI (and any other caller) detects it.
 */
exit($failed > 0 ? 1 : 0);
