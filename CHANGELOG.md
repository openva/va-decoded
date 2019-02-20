# Changelog

## Version 1.0 (April 24, 2017)
* Created plugin system.
* Refactored file exports, added downloads for EPUB, PDF, Word, and others.
* Replaced PHP's SimpleXML with DomDocument for better handling of mixed content XML on imports.
* Finish editions support.
* Cleaned up import process for better visual display and error handling.
* Added full environment test and import abilities to the command line tool.
* Lots of visual tweaks and minor fixes.

## Version 0.9 (May 12, 2015)
* Added support for multiple editions of a code in one database, to store revisions of the code over time.
* Created a generic search wrapper, fixed several search-related bugs.
* Moved to using Solarium to interface with Solr, eliminating the need for XSLT to transfer State Decoded XML to Solr XML.
* Made Solr optional, because getting it installed and running was too great an obstacle for many people. Users can now choose to use a more basic search system, built in MySQL.
* Versioned the database with migrations, which are stored in `includes/migrations`.
* Included a new command line tool, `statedecoded`. It manages database migrations, and allows importing data from the command line. It is extensible—new tasks can be added in `includes/task/`.
* Made various bug fixes and design tweaks.
* **This will certainly break most installations.** Review carefully the changes to `config-sample.inc.php`, `class.State-sample.inc.php`, and the usage of the `statedecoded` tool.

## Version 0.8 (November 11, 2013)
* Conducted a complete user interface overhaul. Everything is different: SASS-based layout, a responsive design, a pluggable template system, and a great deal more.
* Conducted a complete overhaul of the code structure, adding a routing system and a permalinks table.
* Baked in support for Apache Solr, the best text indexing software available, to ingest and analyze all laws, and create a comprehensive search system.
* Added an API method for search.
* Created a heavily automated setup process, making it easy to install.
* Added a rigorous set of environmental tests to make sure that the server has all necessary software.
* Created support for non-unique section identifiers (e.g., when a § 100 exists in 10 different Titles within a single code), a major overhaul.
* Packaged a default home page, that looks good for any site right out of the box.
* Included a sample XSLT, to serve as a starting point for those who have XML that they need to get into The State Decoded's XML format.
* Tested The State Decoded in a wide variety of server configurations and made many modifications to ensure that it runs well under nearly all common environments.
* Added an entire workflow to handle new editions of legal codes, important for those codes that are updated frequently.
* Bulk downloads are automatically created, as plain text, JSON, and XML, along with a page for visitors to download them.
* Overhauled the administrative interface, adding a great deal of functionality.
* Builds a sitemap.xml automatically.
* Included Dublin Core tags in the HTML for each law, to support Zotero and similar software.

## Version 0.7 (June 19, 2013)
* Made extensive optimizations throughout the entire codebase and within MySQL, eliminating all sources of PHP errors of level E_NOTICE and above.
* Added support for import, storage, and display of arbitrary metadata fields.
* Added APC support, and implemented caching of API keys, constants, and templates.
* Added Varnish support, to clear the Varnish cache upon running the importer.
* Replaced MDB2 with PDO, eliminating an installation requirement and modernizing database connectivity.
* Replaced HTML Purifier with Tidy, which more servers are likely to have installed already.
* Added support for port numbers in site URLs, to facilitate development on Vagrant.
* Provided comments for all non-obvious database columns, to improve extensibility.
* Normalized all code along PEAR standardards, or at least our own variant of PEAR.
* Added Dublin Core metadata tags to each law, in case that's useful to somebody for something.
* Created the infrastructure for an extensible inline help text.
* Implemented keyboard navigation within laws and structures.
* Baked in support for Disqus-based commenting on each law page.
* Implemented a logger and debug system within the parser, to improve reporting and error handling.
* Added production of bulk plain text and JSON versions of laws.
* Reduced substantially the parser's memory usage, eliminating out-of-memory errors affecting larger legal codes.

## Version 0.6 (February 7, 2013)
* Established a public API with methods for retrieving data about laws, structural units, and definitions.
* Created an XML standard for legal codes to simplify enormously the process of parsing them. Many legal codes are already provided as some type of XML, so XSLT is all that's necessary to convert them into the format expected by Richmond Sunlight.
* Overhauled the private API for importing legal codes. Previously it was written in a manner intended to serve as a guide to writing one’s own import functionality, with the expectation that a developer would modify it to parse their particular legal code. Now it both serves as that guide and actually works, reading files stored in the State Decoded XML file format.
* Built an API key registration system, so that third party developers can register to use the API. This key registration system is not a centralized one, shared between State Decoded sites -- each site has its own registration system.

## Version 0.5 (December 21, 2012)

* Put into a state-specific file all functionality likely to require customization with each implementation, rather than mixed that in with core functionality.
* Put into place the beginnings of a templating system, allowing images, CSS, and HTML to be packaged together, in the general direction of how WordPress works.
* Added a new method to the Law class that simply verifies that a given law exists. This has led to a 350% improvement in page rendering times (with the benchmark law, 2,142 milliseconds reduced to 610 milliseconds), a result of the need to verify that every law mentioned in a section actually exists.
* Renamed several files, in order to prevent customizations from being overwritten with upgrades. This is an important step towards providing an upgrade path between versions.
* Two bulk download files are automatically generated each time the parser is run—a JSON version of the custom dictionary, and a JSON version of the entire legal code.
* Much has been done towards standardization generally, so that the project adheres to best practices in PHP and MySQL. While this is of little benefit to the end user, for anybody actually getting their hands dirty with code, it should make things much simpler. There’s a lot more to be done to comply with PEAR coding standards, but that’s underway.
* Added a print stylesheet, to format laws nicely when printed out. (Courtesy of Virginia attorney James Steele.)

## Version 0.4 (September 14, 2012)

* Packaged a built-in dictionary of general legal terms. Using several different non-copyrighted, government-created legal dictionaries, a collection of nearly 500 terms have been put together, which will help people to understand common legal terms that are rarely defined within legal codes, such as “mutatis mutandis,” “tort,” “pro tem,” and “cause of action.”
* Dictionary terms are now identified more aggressively, which means that for many states, the size and scope of the custom dictionary is going to expand substantially. In the case of Virginia there was a 49% increase (a leap from 7,681 to 11,504 definitions), a striking difference that could be observed immediately when browsing the site.
* Solved the problem of nested/overlapping definitions. When one definition was nested within another (e.g., if we have definitions for both “robbery” and “armed robbery”), then mousing over “robbery” would yield a pair of pop-up definitions, one obscuring the other. Now only the definition for the longest term is defined under those circumstances.
* Standardized internal terminology. In various places the dictionary and its components were all called different things (glossary, definitions, dictionary, terms, etc.) in different places. Now the collection of words is called a “dictionary,” each defined word is a “term,” and the description of that that term means is a “definition.”)
* Retrieval and display of definitions is substantially faster—they take about half the time that they used to. This is a result of optimizing and simplifying the structure of the database table in which definitions are stored.

## Version 0.3 (August 3, 2012)

* Rewrote the parser to be non-Virginia-specific and also non-functional, but made it clear and simple to facilitate its adaptation to be used for different legal codes.
* Improved support for and optimization of custom, state-specific functions.
* Removed unnecessary chunks of the function library, with remaining useful portions of them integrated into other functions.
* Started to support APC for storing frequently accessed variables, beginning with moving all constants into APC.
* Established a hook for and sample functionality to turn each law’s history section into a plain-English description of that history, along with links to see the acts of the legislature that made those changes.
* Created 404 functionality for proper error handling of requests for non-existent section numbers and structural units.
* Added arrow-key based navigation to move to prior and next sections within a single structural container.
* Provided a sample `.htaccess` file for supporting a decent URL structure.
* Moved JavaScript assets out of the general template and into the specific template to eliminate unnecessary code.

## Version 0.2 (July 26, 2012)

* Moved away from Virginia-specific nomenclature (“title,” “chapter,” etc.) and a three-tier structural system to support hierarchical structures of arbitrary depths and labels.
* Significant optimizations, including the dynamic creation of a SQL view at time of setup to access structural data, and moving to using section IDs instead of section numbers.
* Created a parser for law histories.
* Established a general metadata table to allow the storage and display of arbitrary types of information on a per-law basis (like WordPress's `wp_meta` table).
* Took initial steps towards integrating Solar into the project.
* Established a base of support for storing multiple versions of the same law (e.g., both the 2011 and 2012 revisions) simultaneously.

## Version 0.1 (June 4, 2012)

* Initial release.
