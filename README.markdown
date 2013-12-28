# Category Cloud for MediaWiki

A tag cloud based on MediaWiki categories. This was originally written in 2007 and has not seen significant maintenance since, but it should work with most versions of MediaWiki.

## Usage

This provides a <category-cloud /> tag that can be used with the following attributes:

* category: The name of the category, minus the "Category:" link.
* minsize: The minimum size, as a percentage. Defaults to 80.
* maxsize: The maximum size, as a percentage. Defaults to 125.
* class: The CSS class to assign to the outer div, defaults to "category-cloud".

There is also a parser function that uses {{#category-cloud:CategoryName}} with the optional parameters being included as "|param=value".

## Results

![Example Screenshot](category-cloud-screen1.png "Category Cloud")
