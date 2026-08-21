<?php
/**
 * Word (.docx) Parser
 *
 * A .docx file is a ZIP archive, and the document text inside it lives in an XML
 * file called `word/document.xml`. Pull that entry out of the archive and
 * QueryPath can traverse it like any other XML document.
 *
 * The markup is namespaced (`w:p`, `w:r`, `w:t`, ...). In a CSS selector the
 * namespace separator is a pipe rather than a colon, so `w:p` is selected with
 * `w|p`.
 *
 * The structure this example walks is:
 *
 *   <w:p>   a paragraph
 *     <w:r>   a run - a span of text sharing one set of formatting
 *       <w:rPr>  the run's formatting (<w:b/> for bold, <w:u/> for underline)
 *       <w:t>    the text itself
 *
 * A copy of the extracted XML is included as `example.xml` if you want to read
 * through it.
 *
 * @author  Emily Brand
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     https://www.php.net/manual/en/class.ziparchive.php
 */

use QueryPath\CSS\ParseException;
use QueryPath\DOMQuery;
use QueryPath\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

echo '<h1>Create a Basic Docx Parser</h1>';

echo '<p>This example parses <code>example.docx</code>, walks its nodes, and displays the text with basic formatting. <code>example.xml</code> in this directory is the XML extracted from that file - the document QueryPath actually processes.</p>';

echo '<h2>Content of example.docx file...</h2>';

try {
	// Load the example.docx file, parse for text nodes and output with basic formatting
	foreach (qp(docx2text(__DIR__ . '/example.docx'), 'w|p') as $qp) {
		/** @var $qp DOMQuery */
		/** @var $qr DOMQuery */
		foreach ($qp->find('w|r') as $qr) {
			echo format($qr);
		}

		echo '<br />';
	}
} catch (Exception $e) {
	echo $e->getMessage();
	exit(1);
}

/**
 * Get the node text and apply basic formatting, if necessary
 *
 * @param DOMQuery $qp
 *
 * @return string
 * @throws ParseException
 * @throws Exception
 */
function format(DOMQuery $qp): string
{
	$text = $qp->find('w|t')->text() . ' ';

	$text = checkUnderline($qp) ? sprintf('<u>%s</u>', $text) : $text;
	$text = checkBold($qp) ? sprintf('<b>%s</b>', $text) : $text;

	return $text;
}

/**
 * Look for the <w:rPr><w:b></w:rPr> node to determine if the text is bolded
 *
 * @param DOMQuery $qp
 *
 * @return bool
 * @throws ParseException
 * @throws Exception
 */
function checkBold(DOMQuery $qp): bool
{
	return (bool) $qp->children('w|rPr')
		->children('w|b')
		->count();
}

/**
 * Look for the <w:rPr><w:u></w:rPr> node to determine if the text is underlined
 *
 * @param DOMQuery $qp
 *
 * @return bool
 * @throws ParseException
 * @throws Exception
 */
function checkUnderline(DOMQuery $qp): bool
{
	return (bool) $qp->children('w|rPr')
		->children('w|u')
		->count();
}

/**
 * Extract the text from a docx file
 *
 * @param string $archiveFile The path to the .docx file to extract information from
 * @return string
 */
function docx2text(string $archiveFile): string
{
	$dataFile = 'word/document.xml';

	if (!class_exists('ZipArchive', false)) {
		throw new RuntimeException('ZipArchive extension must be enabled to parse .docx files');
	}

	$zip = new ZipArchive();
	// Open received archive file
	if (true !== $zip->open($archiveFile)) {
		throw new RuntimeException('Could not open the file using ZipArchive: ' . $zip->getStatusString());
	}

	$data = '';
	// Search for the docx data file
	if (($index = $zip->locateName($dataFile)) !== false) {
		$data = $zip->getFromIndex($index);
	}

	// Close zip to prevent memory leak
	$zip->close();

	return $data;
}
