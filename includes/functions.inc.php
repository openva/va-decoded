<?php

/**
 * The core function library for The State Decoded.
 *
 * PHP version 5
 *
 * @license		http://www.gnu.org/licenses/gpl.html GPL 3
 * @version		0.9
 * @link		http://www.statedecoded.com/
 * @since		0.1
*/

/**
 * Autoload any class file when it is called.
 */
function __autoload_libraries($class_name)
{

	$filename = 'class.' . $class_name . '.inc.php';

	$paths = explode(PATH_SEPARATOR, get_include_path());

	foreach($paths as $path)
	{
		if(substr($path, -1, 1) !== DIRECTORY_SEPARATOR)
		{
			$path .= DIRECTORY_SEPARATOR;
		}

		if (file_exists($path . $filename) === TRUE)
		{
			$result = include_once $path . $filename;
			return;
		}
	}

}

spl_autoload_register('__autoload_libraries');


/**
 * Get the contents of a given URL. A wrapper for cURL.
 */
function fetch_url($url)
{

	if (!isset($url))
	{
		return FALSE;
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1200);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	/* Set CURLOPT_PROTOCOLS to protect against exploitation of CVE-2013-0249 that affects cURL
	 * v7.26.0 through v7.28.1, inclusive.
	 *
	 * http://curl.haxx.se/docs/adv_20130206.html
	 * http://www.h-online.com/open/news/item/cURL-goes-wrong-1800880.html
	 */
	$allowed_protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
	curl_setopt($ch, CURLOPT_PROTOCOLS, $allowed_protocols);
	curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $allowed_protocols & ~(CURLPROTO_FILE | CURLPROTO_SCP));

	$html = curl_exec($ch);
	curl_close($ch);
	return $html;

}


/**
 * Ensure that a JSONP callback doesn't contain any reserved terms.
 * By Brett Wejrowski <http://stackoverflow.com/questions/2777021/do-i-need-to-sanitize-the-callback-parameter-from-a-jsonp-call/10900911#10900911>
 */
function valid_jsonp_callback($callback)
{
    return !preg_match( '/[^0-9a-zA-Z\$_]|^(abstract|boolean|break|byte|case|catch|char|class|const|continue|debugger|default|delete|do|double|else|enum|export|extends|false|final|finally|float|for|function|goto|if|implements|import|in|instanceof|int|interface|long|native|new|null|package|private|protected|public|return|short|static|super|switch|synchronized|this|throw|throws|transient|true|try|typeof|var|volatile|void|while|with|NaN|Infinity|undefined)$/', $callback);
}


/**
 * Send an error message formatted as JSON. This requires the text of an error message.
 */
function json_error($text)
{

	if (!isset($text))
	{
		return FALSE;
	}

	$error = array('error',
		array(
			'message' => 'An Error Occurred',
			'details' => $text
		)
	);
	$error = json_encode($error);

	/*
	 * Return a 400 "Bad Request" error. This indicates that the request was invalid. Whether this
	 * is the best HTTP header is subject to debate.
	 */
	header("HTTP/1.0 400 OK");

	/*
	 * Send an HTTP header defining the content as JSON.
	 */
	header('Content-type: application/json');
	echo $error;

}


/**
 * Throw a 404.
 */
function send_404()
{
	include ($_SERVER['DOCUMENT_ROOT'] . '/404.php');
	exit();
}


/**
 * This is relied on by usort() in law.php and by extract_definitions().
 */
function sort_by_length($a, $b)
{
	return strlen($b) - strlen($a);
}


/**
 * The following two functions were pulled out of WordPress 3.7.1. They've been modified somewhat, in
 * order to remove the use of a pair of internal WordPress functions (_x and apply_filters(), and
 * also to replace WordPress’ use of entities with the use of actual Unicode characters.
 */

/**
 * Replaces common plain text characters into formatted entities
 *
 * As an example,
 * <code>
 * 'cause today's effort makes it worth tomorrow's "holiday"...
 * </code>
 * Becomes:
 * <code>
 * ’cause today’s effort makes it worth tomorrow’s “holiday”&#8230;
 * </code>
 * Code within certain html blocks are skipped.
 *
 * @since 0.71
 * @uses $wp_cockneyreplace Array of formatted entities for certain common phrases
 *
 * @param string $text The text to be formatted
 * @return string The string replaced with html entities
 */
function wptexturize($text)
{

	global $wp_cockneyreplace;
	static $static_characters, $static_replacements, $dynamic_characters, $dynamic_replacements,
		$default_no_texturize_tags, $default_no_texturize_shortcodes;

	// No need to set up these static variables more than once
	if ( ! isset( $static_characters ) )
	{
		/* translators: opening curly double quote */
		$opening_quote = '&#8220;'; // 'opening curly double quote' );
		/* translators: closing curly double quote */
		$closing_quote = '&#8221;'; // closing curly double quote

		/* translators: apostrophe, for example in 'cause or can't */
		$apos = '&#8217;'; // apostrophe

		/* translators: prime, for example in 9' (nine feet) */
		$prime = '&#8242;'; // prime
		/* translators: double prime, for example in 9" (nine inches) */
		$double_prime = '&#8243;'; // double prime

		/* translators: opening curly single quote */
		$opening_single_quote = '&#8216;'; // opening curly single quote
		/* translators: closing curly single quote */
		$closing_single_quote = '&#8217;'; // closing curly single quote

		/* translators: en dash */
		$en_dash = '&#8211;'; // en dash
		/* translators: em dash */
		$em_dash = '&#8212;'; // em dash

		$default_no_texturize_tags = array('pre', 'code', 'kbd', 'style', 'script', 'tt');
		$default_no_texturize_shortcodes = array('code');

		// if a plugin has provided an autocorrect array, use it
		if ( isset($wp_cockneyreplace) )
		{
			$cockney = array_keys($wp_cockneyreplace);
			$cockneyreplace = array_values($wp_cockneyreplace);
		}

		elseif ( "'" != $apos ) // Only bother if we're doing a replacement.
		{
			$cockney = array( "'tain't", "'twere", "'twas", "'tis", "'twill", "'til", "'bout", "'nuff", "'round", "'cause" );
			$cockneyreplace = array( $apos . "tain" . $apos . "t", $apos . "twere", $apos . "twas", $apos . "tis", $apos . "twill", $apos . "til", $apos . "bout", $apos . "nuff", $apos . "round", $apos . "cause" );
		}

		else
		{
			$cockney = $cockneyreplace = array();
		}

		$static_characters = array_merge( array( '---', ' -- ', '--', ' - ', 'xn&#8211;', '...', '``', '\'\'', ' (tm)' ), $cockney );
		$static_replacements = array_merge( array( $em_dash, ' ' . $em_dash . ' ', $en_dash, ' ' . $en_dash . ' ', 'xn--', '&#8230;', $opening_quote, $closing_quote, ' &#8482;' ), $cockneyreplace );

		$dynamic = array();
		if ( "'" != $apos )
		{
			$dynamic[ '/\'(\d\d(?:&#8217;|\')?s)/' ] = $apos . '$1'; // '99's
			$dynamic[ '/\'(\d)/'                   ] = $apos . '$1'; // '99
		}
		if ( "'" != $opening_single_quote )
			$dynamic[ '/(\s|\A|[([{<]|")\'/'       ] = '$1' . $opening_single_quote; // opening single quote, even after (, {, <, [
		if ( '"' != $double_prime )
			$dynamic[ '/(\d)"/'                    ] = '$1' . $double_prime; // 9" (double prime)
		if ( "'" != $prime )
			$dynamic[ '/(\d)\'/'                   ] = '$1' . $prime; // 9' (prime)
		if ( "'" != $apos )
			$dynamic[ '/(\S)\'([^\'\s])/'          ] = '$1' . $apos . '$2'; // apostrophe in a word
		if ( '"' != $opening_quote )
			$dynamic[ '/(\s|\A|[([{<])"(?!\s)/'    ] = '$1' . $opening_quote . '$2'; // opening double quote, even after (, {, <, [
		if ( '"' != $closing_quote )
			$dynamic[ '/"(\s|\S|\Z)/'              ] = $closing_quote . '$1'; // closing double quote
		if ( "'" != $closing_single_quote )
			$dynamic[ '/\'([\s.]|\Z)/'             ] = $closing_single_quote . '$1'; // closing single quote

		$dynamic[ '/\b(\d+)x(\d+)\b/'              ] = '$1&#215;$2'; // 9x9 (times)

		$dynamic_characters = array_keys( $dynamic );
		$dynamic_replacements = array_values( $dynamic );
	}

	// Transform into regexp sub-expression used in _wptexturize_pushpop_element
	// Must do this every time in case plugins use these filters in a context sensitive manner
	$no_texturize_tags = '(' . implode('|', $default_no_texturize_tags ) . ')';
	$no_texturize_shortcodes = '(' . implode('|', $default_no_texturize_shortcodes ) . ')';

	$no_texturize_tags_stack = array();
	$no_texturize_shortcodes_stack = array();

	$textarr = preg_split('/(<.*>|\[.*\])/Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

	foreach ( $textarr as &$curl )
	{
		if ( empty( $curl ) )
			continue;

		// Only call _wptexturize_pushpop_element if first char is correct tag opening
		$first = $curl[0];
		if ( '<' === $first )
		{
			_wptexturize_pushpop_element($curl, $no_texturize_tags_stack, $no_texturize_tags, '<', '>');
		}

		elseif ( '[' === $first )
		{
			_wptexturize_pushpop_element($curl, $no_texturize_shortcodes_stack, $no_texturize_shortcodes, '[', ']');
		}

		elseif ( empty($no_texturize_shortcodes_stack) && empty($no_texturize_tags_stack) )
		{
			// This is not a tag, nor is the texturization disabled static strings
			$curl = str_replace($static_characters, $static_replacements, $curl);
			// regular expressions
			$curl = preg_replace($dynamic_characters, $dynamic_replacements, $curl);
		}
		$curl = preg_replace('/&([^#])(?![a-zA-Z1-4]{1,8};)/', '&#038;$1', $curl);
	}
	return implode( '', $textarr );
}

/**
 * Search for disabled element tags. Push element to stack on tag open and pop
 * on tag close. Assumes first character of $text is tag opening.
 *
 * @since Wordpress 2.9.0
 *
 * @param string $text Text to check. First character is assumed to be $opening
 * @param array $stack Array used as stack of opened tag elements
 * @param string $disabled_elements Tags to match against formatted as regexp sub-expression
 * @param string $opening Tag opening character, assumed to be 1 character long
 * @param string $closing Tag closing character
 */
function _wptexturize_pushpop_element($text, &$stack, $disabled_elements, $opening = '<', $closing = '>')
{

	// Check if it is a closing tag -- otherwise assume opening tag
	if (strncmp($opening . '/', $text, 2))
	{

		// Opening? Check $text+1 against disabled elements
		if (preg_match('/^' . $disabled_elements . '\b/', substr($text, 1), $matches))
		{
			/*
			 * This disables texturize until we find a closing tag of our type
			 * (e.g. <pre>) even if there was invalid nesting before that
			 *
			 * Example: in the case <pre>sadsadasd</code>"baba"</pre>
			 *          "baba" won't be texturize
			 */

			array_push($stack, $matches[1]);
		}
	}

	else
	{
		// Closing? Check $text+2 against disabled elements
		$c = preg_quote($closing, '/');
		if (preg_match('/^' . $disabled_elements . $c . '/', substr($text, 2), $matches))
		{
			$last = array_pop($stack);

			// Make sure it matches the opening tag
			if ($last != $matches[1])
			{
				array_push($stack, $last);
			}
		}
	}

}

/**
 * Check that a file is available and safe to use. Throws catchable exceptions if not. Optionally
 * checks if the file is writable.
 */
function check_file_available($filename, $writable=false)
{

	if (!file_exists($filename))
	{
		throw new Exception('File does not exist. "' .
			$filename . '"');
		return false;
	}
	elseif (!is_file($filename))
	{
		throw new Exception('File does not exist. "' .
			$filename . '"');
		return false;
	}
	elseif (!is_readable($filename))
	{
		throw new Exception('File is not readable: "' .
			$filename . '"');
		return false;
	}
	elseif ($writable && !is_writable($filename))
	{
		throw new Exception('File is not writable: "' .
			$filename . '"');
		return false;
	}
	else {
		return true;
	}

}

/**
 * Check that a directory is available and safe to use
 * Throws catchable exceptions if not.
 * Optionally check if the directory is writable.
 */
function check_dir_available($dirname, $writable=false)
{

	if (!file_exists($dirname))
	{
		throw new Exception('Directory does not exist. "' .
			$dirname . '"');
		return false;
	}
	elseif (!is_dir($dirname))
	{
		throw new Exception('Directory does not exist. "' .
			$dirname . '"');
		return false;
	}
	elseif (!is_readable($dirname))
	{
		throw new Exception('Directory is not readable: "' .
			$dirname . '"');
		return false;
	}
	elseif ($writable && !is_writable($dirname))
	{
		throw new Exception('Directory is not writable: "' .
			$dirname . '"');
		return false;
	}
	else {
		return true;
	}

}

/**
 * Recusively travserses through an array to propagate SimpleXML objects.
 * @param array $array the array to parse
 * @param object $xml the Simple XML object (must be at least a single empty node)
 * @return object the Simple XML object (with array objects added)
 * @author Ben Balter
 */
function object_to_xml( $array, $xml )
{

	/*
	 * Array of keys that will be treated as attributes, not children.
	 */
	$attributes = array( 'id', 'number', 'label', 'prefix' );

	/*
	 * Recursively loop through each item.
	 */
	foreach ( $array as $key => $value )
	{

		/*
		 * If this is a numbered array, grab the parent node to determine the node name.
		 */
		if ( is_numeric( $key ) )
		{
			$key = 'unit';
		}

		/*
		 * If this is an attribute, treat as an attribute.
		 */
		if ( in_array( $key, $attributes ) )
		{
			$xml->addAttribute( $key, $value );
		}

		/*
		 * If this value is an object or array, add a child node and treat recursively.
		 */
		else
		{

			if ( is_object( $value ) || is_array( $value ) )
			{
				$child = $xml->addChild(  $key );
				$child = object_to_xml( $value, $child );
			}
			else
			{
				$xml->addChild( $key, $value );
			}

		}

	}

	return $xml;

}

/**
 * Translation of object_to_xml for PHP's DOMDocument class.
 */
function object_to_dom( $array, $doc, $node )
{

	/*
	 * Array of keys that will be treated as attributes, not children.
	 */
	$attributes = array( 'id', 'number', 'label', 'prefix' );

	/*
	 * Recursively loop through each item.
	 */
	foreach ( $array as $key => $value )
	{

		/*
		 * If this is a numbered array, grab the parent node to determine the node name.
		 */
		if ( is_numeric( $key ) )
		{
			$key = 'unit';
		}

		/*
		 * If this is an attribute, treat as an attribute.
		 */
		if ( in_array( $key, $attributes ) )
		{
			$attr = $doc->createAttribute($key);
			$attr->value = $value;

			$node->appendChild($attr);
		}

		/*
		 * If this value is an object or array, add a child node and treat recursively.
		 */
		else
		{

			if ( is_object( $value ) || is_array( $value ) )
			{
				$child = $doc->createElement($key);
				$child = object_to_dom( $value, $doc, $child );
			}
			else
			{
				$child = $doc->createElement($key, $value);
				$node->appendChild($child);
			}

		}

	}

	return $xml;

}


/**
 * Change the name of DOMXPath element $element to $newName
 * By Felix E. Klee <felix.klee at inka.de>
 * http://www.php.net/manual/en/class.domelement.php#111494
 */
function renameElement($element, $newName)
{

	$newElement = $element->ownerDocument->createElement($newName);
	$parentElement = $element->parentNode;
	$parentElement->insertBefore($newElement, $element);

	$childNodes = $element->childNodes;
	while ($childNodes->length > 0)
	{
		$newElement->appendChild($childNodes->item(0));
	}

	$attributes = $element->attributes;
	while ($attributes->length > 0)
	{
		$attribute = $attributes->item(0);
			if (!is_null($attribute->namespaceURI))
			{
				$newElement->setAttributeNS('http://www.w3.org/2000/xmlns/',
				  'xmlns:'.$attribute->prefix,
				  $attribute->namespaceURI);
    		}
			$newElement->setAttributeNode($attribute);
	}

	$parentElement->removeChild($element);

}

/*
 * Handle paths, trims extra trailing slashes.
 * By default, adds a trailing slash.
 */

function join_paths()
{
	$args = func_get_args();
	$paths = array();

	foreach($args as $arg) {
		if(is_array($arg)) {
			$paths = array_merge($paths, $arg);
		}
		else {
			$paths[] = $arg;
		}
	}

	foreach($paths as $key => $value)
	{
		$paths[$key] = rtrim($value, DIRECTORY_SEPARATOR);
	}

	$return_path = join(DIRECTORY_SEPARATOR, array_filter($paths));

	return $return_path;
}

/*
 * Recursively get all files
 */
function get_files($path, $files = array())
{
	if(substr($path, -1, 1) != '/')
	{
		$path .= '/';
	}

	$directory = dir($path);

	while (FALSE !== ($filename = $directory->read()))
	{

		$file_path = $path . $filename;
		if (substr($filename, 0, 1) !== '.')
		{
			if(is_file($file_path))
			{
				$files[] = $file_path;
			}
			elseif(is_dir($file_path))
			{
				$files = get_files($file_path, $files);
			}
		}
	}

	return $files;
}

/*
 * Recursively remove directories. Checks for Windows or not-Windows.
 */
function remove_dir($dir)
{
	if(defined('PHP_WINDOWS_VERSION_MAJOR'))
	{
		return system('rd /Q /S "' . $dir . '"');
	}
	else
	{
		return system('/bin/rm -rf ' . escapeshellarg($dir));
	}
}

/*
 * Recursively create directories
 */
function mkdir_safe($dir)
{
	if(!is_dir($dir))
	{
		return mkdir($dir, 0755, true);
	}
}

/*
 * Recursively strip html entities from an entire object
 */
function html_entity_decode_object($obj)
{
	foreach($obj as $field=>$value)
	{
		if(is_object($value))
		{
			$obj->$field = html_entity_decode_object($value);
		}
		elseif(is_string($value))
		{
			$obj->$field = decode_entities($value);
		}
	}

	return $obj;
}

/*
 * A rather more powerful version of html_entity_decode()
 * since the standard version isn't doing the job very well
 * to produce valid XML.
 *
 * From php.net: http://us2.php.net/manual/en/function.html-entity-decode.php#47371
 */
function decode_entities($text) {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, "ISO-8859-1"); #NOTE: UTF-8 does not work!
    // $text = preg_replace('/&#(\d+);/me',"chr(\\1)",$text); #decimal notation
    // $text = preg_replace('/&#x([a-f0-9]+);/mei',"chr(0x\\1)",$text);  #hex notation
    // $text = preg_replace('/&(?!#)/', '&amp;', $text);
    return $text;
}

/*
 * Works like array_splice, but on strings.
 */
function str_splice($input, $start, $length, $replace)
{
	$input = str_split($input);
	$replace = str_split($replace);

	// array_splice, unlike most functions, edits the element in-place.
	array_splice($input, $start, $length, $replace);
	return join( $input );
}
