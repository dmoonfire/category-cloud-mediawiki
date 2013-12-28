<?php
/**
 * Parser hook extension adds a <category-cloud> tag to wiki markup. The
 * following attributes are used:
 *    category = The category, minus the "Category:"
 *    minsize = The minimum size, as percentage. Defaults to 80.
 *    maxsize = The maximum size, as a percentage. Defaults to 125.
 *    class = The CSS class to assign to the outer div, defaults
 *            to "category-cloud"
 *
 * There is also a parser function that uses {{#category-cloud:CategoryName}}
 * with optional parameters being includes as "|param=value".
 *
 * @addtogroup Extensions
 * @author Dylan R. E. Moonfire <contact@mfgames.com>
 * @copyright © 2007 Dylan R. E. Moonfire
 * @licence GNU General Public Licence 2.0
 */

// Make sure we are being properly
if( !defined( 'MEDIAWIKI' ) ) {
    echo( "This file is an extension to the MediaWiki software "
		. "and cannot be used standalone.\n" );
    die( -1 );
}

// Hook up into MediaWiki
$wgExtensionFunctions[] = 'categoryCloud';
$wgHooks['LanguageGetMagic'][]	= 'categoryCloudMagic';
$wgExtensionCredits['parserhook'][] = array(
    'name' => 'Category Cloud',
    'author' => 'Dylan R. E. Moonfire',
    'description' => 'Create a tag cloud using categories.',
    'url' => 'http://www.mediawiki.org/wiki/Extension:CategoryCloud',
    'version' => '0.4.0'
);

function categoryCloud()
{
        global $wgParser, $wgMessageCache;

	// Set the hooks
        $wgParser->setHook('category-cloud', 'categoryCloudRender');
	$wgParser->setFunctionHook('category-cloud', 'categoryCloudFunction');

	// Set our messages
	$wgMessageCache->addMessages( array(
                    'categorycloud_missingcategory'
			=> 'CategoryCloud: Cannot find category attribute',
                    'categorycloud_emptycategory'
			=> 'CategoryCloud: Category is empty: ',
                    'categorycloud_cannotparse'
			=> 'CategoryCloud: Cannot parse parameter: ',
		));
}

// This manipulates the results of the CategoryCloud extension
// into the same function as the <category-cloud> tag.
function categoryCloudFunction($parser)
{
	// Get the arguments
        $fargs = func_get_args();
        $input = array_shift($fargs);

	// The first category is required
	$category = array_shift($fargs);
	$params = array();
	$params["category"] = $category;
	$params["donotparse"] = 1;

	// Split the rest of the arguments
	foreach ($fargs as $parm)
	{
		// Split it into its components
		$split = split("=", $parm);

		if (!$split[1])
		{
			return htmlspecialchars(wfMsg(
				'categorycloud_cannotparse')
				. $parm);
		}

		// Save it
		$params[$split[0]] = $split[1];
	}

	// Return the cloud
	return categoryCloudRender($input, $params, $parser);
}

// Sets up the magic for the parser functions
function categoryCloudMagic(&$magicWords, $langCode)
{
	$magicWords['category-cloud'] = array(0, 'category-cloud');
	return true;
}

// The actual processing
function categoryCloudRender($input, $args, &$parser)
{
	// Imports
	global $wgOut;

	// Profiling
        wfProfileIn('CategoryCloud::Render');

	// Disable the cache, otherwise the cloud will only update
	// itself when a user edits and saves the page.
	$parser->disableCache();

	// Get the database handler and specific controls
        $dbr =& wfGetDB( DB_SLAVE );
        $pageTable = $dbr->tableName('page');
    	$categoryLinksTable = $dbr->tableName('categorylinks');

	// Normalize the order
	$order = "name";
	if (array_key_exists("order", $args)) $order = $args["order"];

	if ($order != "count")
		$order = "name";
	else // we want reverse
		$order = "count desc";

	// Get the list of the subcategories and the number of children
	if (!array_key_exists("category", $args))
	{
		return htmlspecialchars(wfMsg(
			'categorycloud_missingcategory'));
	}

	$categoryName = $args["category"];

	// Build up an SQL of everything
	$categoryNamespace = 14;
	$sql = "SELECT p1.page_title as name, count(*) as count "
		. "FROM $categoryLinksTable cl, $categoryLinksTable cl2, "
		. "  $pageTable p1, $pageTable p2 "
		. "WHERE cl.cl_to = " . $dbr->addQuotes($categoryName)
		. " AND cl.cl_from  = p1.page_id "
		. " AND cl2.cl_to   = p1.page_title "
		. " AND cl2.cl_from = p2.page_id "
		. " AND p1.page_namespace = $categoryNamespace "
		. " AND p1.page_id != p2.page_id "
		. "GROUP BY p1.page_title "
		. "ORDER BY $order";
	$res = $dbr->query($sql);

    	if ($dbr->numRows( $res ) == 0)
	{
		// Can't find category
		return htmlspecialchars(wfMsg(
			'categorycloud_emptycategory')
			. $categoryName);
	}

	// Build up an array and keep track of mins and maxes
	$minCount = -1;
	$maxCount = -1;
        $countAll = 0;
        $total = 0;
	$categories = array();
	$names = array();

	while ($row = $dbr->fetchObject($res))
	{
		// Pull out the fields
		$name = $row->name;
		$count = $row->count;

		// Add it to the array and keep track of min/max
		$categories[$name] = $count;
		$names[] = $name;
		$countAll++;
		$total += $count;
		
		if ($minCount < 0 || $minCount > $count)
			$minCount = $count;

		if ($maxCount < 0 || $maxCount < $count)
			$maxCount = $count;
	}

	// Figure out the averages and font sizes
	$minSize = 80;
	$maxSize = 125;

	if (array_key_exists("minsize", $args)) $minSize = $args["minsize"];
	if (array_key_exists("maxsize", $args)) $maxSize = $args["maxsize"];

	$countDelta = $maxCount - $minCount;
	$sizeDelta = $maxSize - $minSize;
	$average = $total / $countAll;

	// Create the tag cloud div
	$class = "category-cloud";

	if ($args["class"]) $class = $args["class"];

	$text  = "<div class='$class'";

	if ($args["style"])
		$text .= " style='" . $args["style"]. "'";

	$text .= ">";

	// Go through the categories by name
	foreach ($names as $cat)
	{
		// Wrap the link in a size
		if ($countDelta == 0)
			$size = 100;
		else
			$size = (($categories[$cat] - $minCount)
				* $sizeDelta / $countDelta) + $minSize;

		// Get the link
		$cat = str_replace("_", " ", $cat);
		$text .= " <span style='font-size: $size%;'>"
			. "[[:Category:$cat|$cat]]</span>";
	}

	// Finish up
	$text .= "</div>";

	// If donotparse is set to a value, then we don't want
	// to parse it into wiki text.
	if (array_key_exists("donotparse", $args))
	{
	        wfProfileOut('CategoryCloud::RenderNoParse');
		return $text;
	}

	// Parse the results into wiki text
	$output = $parser->parse($text,
			$parser->mTitle, $parser->mOptions,
			true, false);

	// Finish up and return the results
        wfProfileOut('CategoryCloud::Render');
        return $output->getText();
}
?>
