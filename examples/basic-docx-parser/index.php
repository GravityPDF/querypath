<?php
/**
 * DocX Parser
 *
 * For namespaces use | instead of :
 *
 *
 * @author  Emily Brand
 * @license LGPL The GNU Lesser GPL (LGPL) or an MIT-like license.
 * @see     http://www.urbandictionary.com/
 */

use QueryPath\CSS\ParseException;
use QueryPath\DOMQuery;
use QueryPath\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

try {
	// Try load the test.docx file, parse for text nodes and output with basic formatting
	foreach (qp(docx2text('test.docx'), 'w|p') as $qp) {
		/** @var $qp DOMQuery */
		/** @var $qr DOMQuery */
		foreach ($qp->find('w|r') as $qr) {
			echo format($qr);
		}

		echo '<br />';
	}
} catch (Exception $e) {
	die($e->getMessage());
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
