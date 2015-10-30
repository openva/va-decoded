# Virginia Decoded

The Virginia implementation of [The State Decoded](https://github.com/statedecoded/statedecoded/), found at [vacode.org](https://vacode.org/).

## Testing XSLT

XML transformations can be applied via [this web-based transformer](http://www.freeformatter.com/xsl-transformer.html). (xsltproc won’t work because it doesn’t support XSLT 2.0.) The goal here is to get the outputted XML to match [the State Decoded standard](http://docs.statedecoded.com/xml-format.html).

Note that this XSLT is particularly important because it is LexisNexis’ XML format for legal codes. Getting this created means that any LexisNexis legal XML can be used to populate a State Decoded website.
