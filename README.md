# Virginia Decoded

[![Build Status](https://travis-ci.org/openva/va-decoded.svg?branch=master)](https://travis-ci.org/openva/va-decoded)

The Virginia implementation of [The State Decoded](https://github.com/statedecoded/statedecoded/), found at [vacode.org](https://vacode.org/). 

## XSLT

The included XML is in Lexis Nexis’ format, and must be transformed to [the State Decoded format]((http://docs.statedecoded.com/xml-format.html).). XML transformations can be applied with Saxon-B (e.g., `java net.sf.saxon.Transform -o:output.xml -s:lexis-nexis.xml -xsl:decoded.xsl`, or `java net.sf.saxon.Transform -o:htdocs/admin/import-data/ -s:2016-xml/ -xsl:lexis-nexis.xsl` to transform all files). Make sure you run `export CLASSPATH=$CLASSPATH:/usr/share/java/saxonb.jar` first.
